<?php
/**
 * =============================================================================
 * TutoresOnLine - Callback de Retorno OAuth2 de Google
 * Valida el estado contra CSRF, intercambia el código de autorización por token,
 * obtiene los datos del perfil y autentica o crea la cuenta en base de datos.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/oauth_google.php';

iniciar_sesion_segura();

// 1. Manejar cancelación o error devuelto por Google
if (!empty($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
    set_flash('warning', "Inicio de sesión con Google cancelado: {$error_msg}");
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$code  = trim($_GET['code'] ?? '');
$state = trim($_GET['state'] ?? '');

// 2. Validar State contra CSRF
$state_esperado = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']); // Consumir inmediatamente el state

if (empty($state) || empty($state_esperado) || !hash_equals($state_esperado, $state)) {
    set_flash('danger', 'Error de validación de seguridad (State OAuth inválido). Intenta de nuevo.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

if (empty($code)) {
    set_flash('danger', 'Google no devolvió ningún código de autorización.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// 3. Intercambiar código por token vía POST con cURL
$resp_token = intercambiar_codigo_por_token($code);

if (!$resp_token['exito'] || empty($resp_token['datos']['access_token'])) {
    error_log("[Google OAuth Token Error]: " . ($resp_token['error'] ?? 'Sin access token'));
    set_flash('danger', 'Error al comunicarse con Google para canjear el token de acceso.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$access_token = $resp_token['datos']['access_token'];

// 4. Obtener información del usuario desde Google UserInfo API
$resp_perfil = obtener_perfil_google($access_token);

if (!$resp_perfil['exito'] || empty($resp_perfil['perfil'])) {
    error_log("[Google OAuth UserInfo Error]: " . ($resp_perfil['error'] ?? 'Perfil vacío'));
    set_flash('danger', 'No fue posible obtener los datos de tu perfil de Google.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$perfil = $resp_perfil['perfil'];
$rol_deseado = $_SESSION['oauth_rol'] ?? 'estudiante';
unset($_SESSION['oauth_rol']);

// 5. Autenticar o crear el usuario en la base de datos
$usuario = autenticar_o_crear_usuario_google($perfil, $rol_deseado);

if (!$usuario) {
    set_flash('danger', 'Ocurrió un error al vincular tu cuenta de Google en la base de datos.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// 6. Iniciar formalmente la sesión protegida en el sistema
crear_sesion_usuario($usuario);

set_flash('success', '¡Bienvenido, ' . $usuario['nombre'] . '! Has iniciado sesión con tu cuenta de Google.');

$destino = ($usuario['rol'] === 'tutor') ? '/panel_tutor.php' : '/panel_estudiante.php';
header('Location: ' . APP_URL . $destino);
exit;
