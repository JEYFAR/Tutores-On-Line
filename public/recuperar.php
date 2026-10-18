<?php
/**
 * =============================================================================
 * TutoresOnLine - Recuperación de Contraseña
 * Solicitud de token temporal de 1 hora y restablecimiento seguro con bcrypt.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/correo.php';

$token_plano = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errores = [];
$modo_cambio = false;
$token_valido = false;
$usuario_id = null;

// Si hay un token, validar si es legítimo y vigente
if (!empty($token_plano)) {
    $modo_cambio = true;
    $token_hash = hash('sha256', $token_plano);

    try {
        $pdo = obtener_conexion();
        $stmt = $pdo->prepare(
            "SELECT * FROM `tokens_verificacion`
             WHERE `token_hash` = ? AND `tipo` = 'recuperacion' AND `usado` = 0 AND `expira_en` > NOW()
             LIMIT 1"
        );
        $stmt->execute([$token_hash]);
        $registro_token = $stmt->fetch();

        if ($registro_token) {
            $token_valido = true;
            $usuario_id = (int)$registro_token['usuario_id'];
        }
    } catch (Exception $e) {
        error_log("[Recuperar Token Check Error]: " . $e->getMessage());
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $errores[] = 'Error de seguridad (CSRF). Por favor intenta de nuevo.';
    } else {
        if ($modo_cambio) {
            // CASO 2: Restablecer nueva contraseña
            if (!$token_valido) {
                $errores[] = 'El enlace de recuperación es inválido o ya ha expirado.';
            } else {
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';

                if (strlen($password) < 6) {
                    $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
                } elseif ($password !== $password_confirm) {
                    $errores[] = 'Las contraseñas ingresadas no coinciden.';
                } else {
                    try {
                        $pdo = obtener_conexion();
                        $nuevo_hash = hashear_password($password);

                        // Actualizar contraseña del usuario
                        $stmt_up = $pdo->prepare("UPDATE `usuarios` SET `password_hash` = ? WHERE `id` = ?");
                        $stmt_up->execute([$nuevo_hash, $usuario_id]);

                        // Invalidar el token usado
                        $stmt_tok = $pdo->prepare("UPDATE `tokens_verificacion` SET `usado` = 1 WHERE `id` = ?");
                        $stmt_tok->execute([$registro_token['id']]);

                        set_flash('success', '¡Tu contraseña ha sido actualizada con éxito! Ya puedes iniciar sesión.');
                        header('Location: ' . APP_URL . '/login.php');
                        exit;
                    } catch (Exception $e) {
                        error_log("[Recuperar Update Password Error]: " . $e->getMessage());
                        $errores[] = 'Ocurrió un error al actualizar la contraseña.';
                    }
                }
            }
        } else {
            // CASO 1: Solicitar correo de recuperación
            $correo = trim($_POST['correo'] ?? '');

            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Por favor ingresa un correo electrónico válido.';
            } else {
                try {
                    $pdo = obtener_conexion();
                    $stmt = $pdo->prepare("SELECT `id`, `nombre`, `correo` FROM `usuarios` WHERE `correo` = ? LIMIT 1");
                    $stmt->execute([$correo]);
                    $usuario = $stmt->fetch();

                    if ($usuario) {
                        // Generar token con 1 hora de validez
                        $token = generar_token_verificacion($usuario['id'], 'recuperacion', 1);
                        $enlace = APP_URL . '/recuperar.php?token=' . $token;

                        $cuerpo = "<p>Hola <strong>" . e($usuario['nombre']) . "</strong>,</p>"
                                . "<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en TutoresOnLine.</p>"
                                . "<p>Haz clic en el siguiente enlace para crear una nueva clave (válido por 1 hora):</p>"
                                . "<p><a href='{$enlace}'>Restablecer mi contraseña</a></p>"
                                . "<p>Si tú no solicitaste este cambio, puedes ignorar este mensaje; tu clave seguirá siendo la misma.</p>";

                        enviar_correo($usuario['correo'], $usuario['nombre'], 'Restablece tu contraseña en TutoresOnLine', $cuerpo, $enlace);
                    }

                    // Mensaje neutro para evitar enumeración de usuarios
                    set_flash('info', 'Si el correo ingresado está registrado, recibirás un enlace con instrucciones para restablecer tu clave.');
                    header('Location: ' . APP_URL . '/login.php');
                    exit;
                } catch (Exception $e) {
                    error_log("[Recuperar Request Error]: " . $e->getMessage());
                    $errores[] = 'Ocurrió un error al procesar tu solicitud.';
                }
            }
        }
    }
}

$titulo_pagina = 'Recuperar Contraseña';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-2">
                    <i class="bi bi-key fs-3"></i>
                </div>
                <h3 class="fw-bold mb-1"><?= $modo_cambio ? 'Nueva Contraseña' : 'Recuperar Contraseña' ?></h3>
                <p class="text-muted small">
                    <?= $modo_cambio ? 'Ingresa y confirma tu nueva clave de acceso' : 'Ingresa tu correo registrado para recibir un enlace de recuperación' ?>
                </p>
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

            <?php if ($modo_cambio && !$token_valido): ?>
                <div class="alert alert-warning text-center">
                    <i class="bi bi-exclamation-triangle fs-4 d-block mb-2"></i>
                    El enlace de recuperación es inválido o ha caducado.
                    <div class="mt-3">
                        <a href="<?= APP_URL ?>/recuperar.php" class="btn btn-outline-primary btn-sm">Solicitar un nuevo enlace</a>
                    </div>
                </div>
            <?php elseif ($modo_cambio && $token_valido): ?>
                <!-- Formulario de cambio de contraseña -->
                <form action="<?= APP_URL ?>/recuperar.php?token=<?= e($token_plano) ?>" method="POST">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="token" value="<?= e($token_plano) ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label fw-medium">Nueva Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Mínimo 6 caracteres">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label fw-medium">Confirmar Nueva Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Repite la contraseña">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2">
                        Guardar Nueva Contraseña
                    </button>
                </form>
            <?php else: ?>
                <!-- Formulario para solicitar enlace -->
                <form action="<?= APP_URL ?>/recuperar.php" method="POST">
                    <?= csrf_campo() ?>

                    <div class="mb-4">
                        <label for="correo" class="form-label fw-medium">Correo Electrónico Registrado</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="correo" name="correo" required autofocus placeholder="correo@ejemplo.com">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2">
                        Enviar Enlace de Recuperación
                    </button>
                </form>
            <?php endif; ?>

            <hr class="my-4">

            <div class="text-center small">
                <a href="<?= APP_URL ?>/login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Iniciar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
