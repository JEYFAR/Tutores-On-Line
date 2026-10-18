<?php
/**
 * =============================================================================
 * TutoresOnLine - Registro de Nuevos Usuarios (Estudiante / Tutor)
 * Validación estricta, protección CSRF, hash con bcrypt+pepper y token de correo.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/correo.php';

// Si ya está autenticado, redirigir a su panel
if (usuario_autenticado()) {
    $destino = ($_SESSION['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
    header('Location: ' . APP_URL . $destino);
    exit;
}

$errores = [];
$nombre = '';
$correo = '';
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

    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $rol      = trim($_POST['rol'] ?? 'estudiante');
    $ciudad   = trim($_POST['ciudad'] ?? '');

    // Validaciones
    if (empty($nombre) || strlen($nombre) < 3) {
        $errores[] = 'El nombre completo debe tener al menos 3 caracteres.';
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    if (strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if ($password !== $password_confirm) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if ($rol !== 'estudiante' && $rol !== 'tutor') {
        $errores[] = 'El tipo de cuenta seleccionado no es válido.';
    }

    // Si no hay errores iniciales, verificar duplicados e insertar
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

                // Insertar usuario
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

$titulo_pagina = 'Crear Cuenta';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-2">
                    <i class="bi bi-person-plus fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1">Crea tu Cuenta</h3>
                <p class="text-muted small">Únete como estudiante o como profesor tutor</p>
            </div>

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
                    <label class="form-label fw-medium d-block">Quiero registrarme como:</label>
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

                <div class="mb-3">
                    <label for="nombre" class="form-label fw-medium">Nombre Completo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($nombre) ?>" required placeholder="Ej. Juan Pérez">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="correo" class="form-label fw-medium">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" value="<?= e($correo) ?>" required placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="ciudad" class="form-label fw-medium">Ciudad</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= e($ciudad) ?>" placeholder="Ej. Bogotá, Medellín, Cali">
                    </div>
                </div>

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

                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Crear Cuenta
                </button>
            </form>

            <div class="position-relative my-4 text-center">
                <hr class="text-muted">
                <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">o regístrate con</span>
            </div>

            <a href="<?= APP_URL ?>/login_google.php?rol=<?= e($rol) ?>" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-google text-danger"></i> Registrarse con Google
            </a>

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
