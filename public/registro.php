<?php
/**
 * =============================================================================
 * TutoresOnLine - Registro de Nuevos Usuarios (Estudiante / Tutor)
 * Soporta registro tradicional (correo + contraseña con bcrypt)
 * y finalización de registro con Google (selección de rol y ciudad, sin contraseña).
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/correo.php';
require_once dirname(__DIR__) . '/includes/oauth_google.php';

// Si ya está autenticado, redirigir directo al dashboard universal
if (usuario_autenticado()) {
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

$es_google = !empty($_SESSION['google_registro']);
$datos_google = $_SESSION['google_registro'] ?? [];

$errores = [];
$nombre = $es_google ? ($datos_google['nombre'] ?? '') : '';
$correo = $es_google ? ($datos_google['correo'] ?? '') : '';
$rol = $_GET['rol'] ?? 'estudiante';
$ciudad = '';

if ($rol !== 'tutor' && $rol !== 'estudiante') {
    $rol = 'estudiante';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF
    if (!verificar_csrf()) {
        $errores[] = 'Error de validación de seguridad (CSRF). Intenta de nuevo.';
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $rol    = trim($_POST['rol'] ?? 'estudiante');
    $ciudad = trim($_POST['ciudad'] ?? '');

    if (empty($nombre) || strlen($nombre) < 3) {
        $errores[] = 'El nombre completo debe tener al menos 3 caracteres.';
    }

    if (empty($ciudad)) {
        $errores[] = 'Por favor ingresa tu ciudad.';
    }

    if ($rol !== 'estudiante' && $rol !== 'tutor') {
        $errores[] = 'El tipo de cuenta seleccionado no es válido.';
    }

    // FLUJO A: Completar Registro con Google (sin contraseña)
    if ($es_google) {
        if (empty($errores)) {
            $nuevo_usuario = registrar_usuario_oauth_google(
                $datos_google['id'],
                $nombre,
                $datos_google['correo'],
                $rol,
                $ciudad,
                $datos_google['foto'] ?? null
            );

            if ($nuevo_usuario) {
                unset($_SESSION['google_registro']);
                crear_sesion_usuario($nuevo_usuario);
                set_flash('success', '¡Perfil completado con éxito! Bienvenido a TutoresOnLine.');
                header('Location: ' . APP_URL . '/panel.php');
                exit;
            } else {
                $errores[] = 'Ocurrió un error al registrar tu cuenta de Google en el sistema.';
            }
        }
    } else {
        // FLUJO B: Registro Tradicional con Contraseña
        $correo   = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no tiene un formato válido.';
        }

        if (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        if ($password !== $password_confirm) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        if (empty($errores)) {
            try {
                $pdo = obtener_conexion();

                // Comprobar si el correo ya existe
                $stmt_check = $pdo->prepare("SELECT `id` FROM `usuarios` WHERE `correo` = ? LIMIT 1");
                $stmt_check->execute([$correo]);

                if ($stmt_check->fetch()) {
                    $errores[] = 'Este correo electrónico ya está registrado. Intenta iniciar sesión.';
                } else {
                    // Hashear contraseña con bcrypt (cost 12) + pepper
                    $password_hash = hashear_password($password);

                    $stmt_insert = $pdo->prepare(
                        "INSERT INTO `usuarios` (`nombre`, `correo`, `password_hash`, `rol`, `ciudad`, `correo_verificado`, `creado_en`)
                         VALUES (?, ?, ?, ?, ?, 0, NOW())"
                    );
                    $stmt_insert->execute([$nombre, $correo, $password_hash, $rol, $ciudad]);
                    $usuario_id = (int)$pdo->lastInsertId();

                    // Generar token de verificación de correo (24 horas)
                    $token = generar_token_verificacion($usuario_id, 'correo', 24);
                    $enlace_verificacion = APP_URL . '/verificar.php?token=' . $token;

                    // Enviar correo (o guardar en logs/mail.log)
                    $cuerpo = "<p>Hola <strong>" . e($nombre) . "</strong>,</p>"
                            . "<p>Gracias por unirte a TutoresOnLine. Para activar tu cuenta, haz clic en el siguiente enlace:</p>"
                            . "<p><a href='{$enlace_verificacion}'>Activar mi cuenta</a></p>"
                            . "<p>Si no te registraste en TutoresOnLine, puedes ignorar este mensaje.</p>";

                    enviar_correo($correo, $nombre, 'Activa tu cuenta en TutoresOnLine', $cuerpo, $enlace_verificacion);

                    set_flash('success', '¡Cuenta creada con éxito! Hemos enviado un enlace de confirmación a tu correo para activarla.');
                    header('Location: ' . APP_URL . '/login.php');
                    exit;
                }
            } catch (Exception $e) {
                error_log("[Registro Error]: " . $e->getMessage());
                $errores[] = 'Ocurrió un error al procesar el registro. Intenta nuevamente.';
            }
        }
    }
}

$titulo_pagina = $es_google ? 'Completar Perfil con Google' : 'Crear Cuenta';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-2">
                    <i class="bi <?= $es_google ? 'bi-person-check' : 'bi-person-plus' ?> fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1"><?= $es_google ? 'Completa tu Perfil' : 'Crea tu Cuenta' ?></h3>
                <p class="text-muted small">
                    <?= $es_google ? 'Elige tu rol y ciudad para finalizar tu registro con Google' : 'Únete como estudiante o como profesor tutor' ?>
                </p>
            </div>

            <?php if ($es_google): ?>
                <div class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-google text-danger fs-5"></i>
                    <div>
                        <strong>Autenticado con Google:</strong> Tu cuenta no requiere contraseña local. Completa los datos siguientes para entrar.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger shadow-sm">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errores as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/registro.php" method="POST" autocomplete="off">
                <?= csrf_campo() ?>

                <!-- Selector de Rol -->
                <div class="mb-3">
                    <label class="form-label fw-medium d-block">Quiero usar la plataforma como:</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="rol" id="rol_estudiante" value="estudiante" <?= $rol === 'estudiante' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2" for="rol_estudiante">
                                <i class="bi bi-mortarboard"></i> Estudiante
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="rol" id="rol_tutor" value="tutor" <?= $rol === 'tutor' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2" for="rol_tutor">
                                <i class="bi bi-briefcase"></i> Tutor
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Nombre Completo -->
                <div class="mb-3">
                    <label for="nombre" class="form-label fw-medium">Nombre Completo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($nombre) ?>" required placeholder="Ej. Juan Pérez">
                    </div>
                </div>

                <!-- Correo Electrónico -->
                <div class="mb-3">
                    <label for="correo" class="form-label fw-medium">
                        Correo Electrónico
                        <?php if ($es_google): ?>
                            <span class="badge bg-success-subtle text-success ms-1"><i class="bi bi-check-circle me-1"></i>Verificado por Google</span>
                        <?php endif; ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" value="<?= e($correo) ?>" required <?= $es_google ? 'readonly' : '' ?> placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <!-- Ciudad -->
                <div class="mb-3">
                    <label for="ciudad" class="form-label fw-medium">Ciudad de Residencia</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= e($ciudad) ?>" required placeholder="Ej. Bogotá, Medellín, Cali">
                    </div>
                </div>

                <!-- Contraseña (SOLO SI NO ES GOOGLE) -->
                <?php if (!$es_google): ?>
                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-medium">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Mín. 6 caracteres">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label fw-medium">Confirmar Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Repite la clave">
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-4"></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="bi bi-check2-circle me-1"></i> <?= $es_google ? 'Completar Registro' : 'Crear Cuenta' ?>
                </button>
            </form>

            <?php if (!$es_google): ?>
                <div class="position-relative my-4 text-center">
                    <hr class="text-muted">
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">o regístrate con</span>
                </div>

                <a href="<?= APP_URL ?>/login_google.php?rol=<?= e($rol) ?>" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-google text-danger"></i> Registrarse con Google
                </a>
            <?php endif; ?>

            <hr class="my-4">

            <div class="text-center small">
                ¿Ya tienes una cuenta? <a href="<?= APP_URL ?>/login.php" class="fw-semibold text-decoration-none">Inicia sesión</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
