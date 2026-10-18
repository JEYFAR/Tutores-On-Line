<?php
/**
 * =============================================================================
 * TutoresOnLine - Verificación de Correo Electrónico
 * Valida el token criptográfico enviado al usuario y activa su cuenta.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

$token_plano = trim($_GET['token'] ?? '');

if (empty($token_plano)) {
    set_flash('danger', 'Token de verificación no proporcionado.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$token_hash = hash('sha256', $token_plano);

try {
    $pdo = obtener_conexion();

    // Buscar el token en la base de datos
    $stmt = $pdo->prepare(
        "SELECT * FROM `tokens_verificacion`
         WHERE `token_hash` = ? AND `tipo` = 'correo' AND `usado` = 0 AND `expira_en` > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token_hash]);
    $token_registro = $stmt->fetch();

    if ($token_registro) {
        $usuario_id = (int)$token_registro['usuario_id'];

        // 1. Marcar el token como usado
        $stmt_token = $pdo->prepare("UPDATE `tokens_verificacion` SET `usado` = 1 WHERE `id` = ?");
        $stmt_token->execute([$token_registro['id']]);

        // 2. Activar la cuenta del usuario
        $stmt_user = $pdo->prepare("UPDATE `usuarios` SET `correo_verificado` = 1 WHERE `id` = ?");
        $stmt_user->execute([$usuario_id]);

        // Si el usuario tiene sesión activa, actualizar variable de sesión
        if (usuario_autenticado() && (int)$_SESSION['usuario_id'] === $usuario_id) {
            $_SESSION['correo_verificado'] = 1;
        }

        set_flash('success', '¡Excelente! Tu correo electrónico ha sido verificado con éxito. Ya tienes acceso completo a la plataforma.');
    } else {
        set_flash('danger', 'El enlace de activación es inválido o ha expirado. Por favor solicita uno nuevo.');
    }
} catch (Exception $e) {
    error_log("[Verificar Correo Error]: " . $e->getMessage());
    set_flash('danger', 'Ocurrió un error al verificar tu cuenta.');
}

if (usuario_autenticado()) {
    $destino = ($_SESSION['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
    header('Location: ' . APP_URL . $destino);
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
