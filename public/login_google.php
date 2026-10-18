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

// Si ya tiene sesión activa, redirigir a su panel correspondiente
if (usuario_autenticado()) {
    $destino = ($_SESSION['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
    header('Location: ' . APP_URL . $destino);
    exit;
}

// Verificar si las credenciales están configuradas en .env
if (!google_oauth_configurado()) {
    $uri_callback = APP_URL . '/oauth_callback.php';
    set_flash(
        'warning',
        "Para iniciar sesión con Google en local, debes configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en config/.env con la URI de redirección: {$uri_callback}"
    );
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$rol_deseado = trim($_GET['rol'] ?? 'estudiante');
$url_google = obtener_url_autorizacion_google($rol_deseado);

header('Location: ' . $url_google);
exit;
