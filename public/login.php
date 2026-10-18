<?php
/**
 * =============================================================================
 * TutoresOnLine - Iniciar Sesión (Login Seguro)
 * Incluye protección contra fuerza bruta, CSRF, cookie persistente y sesiones blindadas.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Si ya tiene sesión activa, redirigir a su panel correspondiente
if (usuario_autenticado()) {
    $destino = ($_SESSION['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
    header('Location: ' . APP_URL . $destino);
    exit;
}

$errores = [];
$correo = '';

// Procesar formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar Token CSRF
    if (!verificar_csrf()) {
        $errores[] = 'Error de seguridad (CSRF). Por favor recarga el formulario e intenta de nuevo.';
    }

    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordarme = !empty($_POST['recordarme']);

    if (empty($correo) || empty($password)) {
        $errores[] = 'Por favor ingresa tu correo y contraseña.';
    } else {
        // 2. Control de Fuerza Bruta (Rate Limiting)
        if (esta_bloqueado_fuerza_bruta($correo)) {
            $errores[] = 'Has superado el límite de intentos fallidos. Por seguridad, el acceso está bloqueado durante 15 minutos.';
        } else {
            // 3. Consulta de usuario con sentencia preparada (PDO)
            try {
                $pdo = obtener_conexion();
                $stmt = $pdo->prepare("SELECT * FROM `usuarios` WHERE `correo` = ? LIMIT 1");
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch();

                // 4. Verificación de hash de contraseña (bcrypt + pepper)
                if ($usuario && verificar_password($password, $usuario['password_hash'])) {
                    // Registrar intento exitoso
                    registrar_intento_login($correo, true);

                    // Validar si el correo está pendiente de verificación
                    if ((int)$usuario['correo_verificado'] === 0) {
                        set_flash('warning', 'Tu correo aún no está verificado. Te enviamos un enlace de activación al registrarte.');
                    }

                    // Establecer sesión segura
                    crear_sesion_usuario($usuario);

                    // Si solicitó "Recordarme", generar token criptográfico rotativo
                    if ($recordarme) {
                        generar_token_recordarme($usuario['id']);
                    }

                    set_flash('success', '¡Hola de nuevo, ' . $usuario['nombre'] . '!');

                    $destino = ($usuario['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
                    header('Location: ' . APP_URL . $destino);
                    exit;
                } else {
                    // Registrar intento fallido
                    registrar_intento_login($correo, false);
                    // Mensaje genérico para no filtrar si el correo existe o no
                    $errores[] = 'Correo o contraseña incorrectos.';
                }
            } catch (Exception $e) {
                error_log("[Login Error]: " . $e->getMessage());
                $errores[] = 'Ocurrió un error al procesar tu solicitud. Intenta más tarde.';
            }
        }
    }
}

$titulo_pagina = 'Iniciar Sesión';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-2">
                    <i class="bi bi-box-arrow-in-right fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1">Iniciar Sesión</h3>
                <p class="text-muted small">Ingresa tus credenciales para acceder a tu cuenta</p>
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

            <form action="<?= APP_URL ?>/login.php" method="POST" autocomplete="off">
                <?= csrf_campo() ?>

                <div class="mb-3">
                    <label for="correo" class="form-label fw-medium">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" value="<?= e($correo) ?>" required autofocus placeholder="ejemplo@correo.com">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label fw-medium">Contraseña</label>
                        <a href="<?= APP_URL ?>/recuperar.php" class="small text-decoration-none">¿Olvidaste tu contraseña?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Tu contraseña">
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="recordarme" name="recordarme" value="1">
                    <label class="form-check-label small text-secondary" for="recordarme">
                        Recordarme en este equipo (30 días)
                    </label>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                </button>
            </form>

            <div class="position-relative my-4 text-center">
                <hr class="text-muted">
                <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">o continúa con</span>
            </div>

            <a href="<?= APP_URL ?>/login_google.php" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-google text-danger"></i> Entrar con Google
            </a>

            <hr class="my-4">

            <div class="text-center small">
                ¿Aún no tienes una cuenta? <a href="<?= APP_URL ?>/registro.php" class="fw-semibold text-decoration-none">Regístrate aquí</a>
            </div>

            <!-- Accesos rápidos para pruebas locales -->
            <div class="mt-4 p-3 bg-light rounded-3 border small">
                <div class="fw-semibold text-muted mb-1"><i class="bi bi-info-circle me-1"></i> Cuentas de prueba (Password: <code>password123</code>):</div>
                <div class="text-secondary">&bull; Estudiante: <code>carlos.estudiante@ejemplo.com</code></div>
                <div class="text-secondary">&bull; Tutor: <code>roberto.tutor@ejemplo.com</code></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
