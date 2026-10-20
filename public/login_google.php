<?php
/**
 * =============================================================================
 * TutoresOnLine - Iniciar Flujo OAuth2 con Google
 * Redirige al usuario a la pantalla de consentimiento de Google.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/oauth_google.php';

// Si ya tiene sesión activa, redirigir a su panel
if (usuario_autenticado()) {
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

// Si no están configuradas las credenciales, registrar en log interno y mostrar mensaje limpio al usuario
if (!google_oauth_configurado()) {
    error_log("[Google OAuth]: Falta configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en config/.env. Callback esperado: " . APP_URL . "/oauth_callback.php");
    set_flash('info', 'El acceso con Google no está disponible temporalmente. Por favor ingresa con tu correo y contraseña.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$rol_deseado = trim($_GET['rol'] ?? 'estudiante');
$url_google = obtener_url_autorizacion_google($rol_deseado);

header('Location: ' . $url_google);
exit;
