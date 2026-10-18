<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo de Integración OAuth2 con Google
 * Flujo Authorization Code nativo con cURL (sin Composer / librerías externas).
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

// Constantes de Google OAuth 2.0
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Verifica si las credenciales de Google OAuth están configuradas en .env
 *
 * @return bool
 */
function google_oauth_configurado()
{
    $client_id = env('GOOGLE_CLIENT_ID');
    $client_secret = env('GOOGLE_CLIENT_SECRET');

    return !empty($client_id) && !empty($client_secret)
        && strpos($client_id, 'tu_google_client_id') === false;
}

/**
 * Genera la URL de autorización para redirigir al usuario al consentimiento de Google
 *
 * @param string $rol_deseado 'estudiante' o 'tutor'
 * @return string
 */
function obtener_url_autorizacion_google($rol_deseado = 'estudiante')
{
    iniciar_sesion_segura();

    // Generar un state criptográfico único para prevenir CSRF en OAuth
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_rol']   = in_array($rol_deseado, ['estudiante', 'tutor'], true) ? $rol_deseado : 'estudiante';

    $params = [
        'client_id'             => env('GOOGLE_CLIENT_ID'),
        'redirect_uri'          => env('GOOGLE_REDIRECT_URI', APP_URL . '/oauth_callback.php'),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'access_type'           => 'online',
        'prompt'                => 'select_account'
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Ejecuta una petición HTTP a las APIs de Google.
 * Utiliza cURL si está disponible; si no, utiliza stream_context_create nativo de PHP.
 *
 * @param string $url
 * @param string $metodo 'GET' o 'POST'
 * @param array $datos
 * @param array $cabeceras
 * @return array ['exito' => bool, 'datos' => array|null, 'error' => string|null]
 */
function ejecutar_peticion_oauth($url, $metodo = 'GET', $datos = [], $cabeceras = [])
{
    // MÉTODO 1: Usar cURL si la extensión está activa en Apache
    if (function_exists('curl_init')) {
        $ch = curl_init();

        $opciones = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if (env('APP_ENV', 'local') === 'local') {
            $opciones[CURLOPT_SSL_VERIFYPEER] = false;
            $opciones[CURLOPT_SSL_VERIFYHOST] = false;
        } else {
            $opciones[CURLOPT_SSL_VERIFYPEER] = true;
            $opciones[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        if ($metodo === 'POST') {
            $opciones[CURLOPT_POST] = true;
            $opciones[CURLOPT_POSTFIELDS] = http_build_query($datos);
        }

        if (!empty($cabeceras)) {
            $opciones[CURLOPT_HTTPHEADER] = $cabeceras;
        }

        curl_setopt_array($ch, $opciones);

        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        $codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false || !empty($error)) {
            return ['exito' => false, 'error' => "Error cURL: {$error}"];
        }

        $json = json_decode($respuesta, true);

        if ($codigo_http < 200 || $codigo_http >= 300) {
            $msg = $json['error_description'] ?? $json['error'] ?? "HTTP {$codigo_http}";
            return ['exito' => false, 'error' => $msg];
        }

        return ['exito' => true, 'datos' => $json];
    }

    // MÉTODO 2 (FALLBACK NATIVO): Usar streams de PHP nativos (sin extensiones externas)
    $headers_arr = [];
    if (!empty($cabeceras)) {
        foreach ($cabeceras as $h) {
            $headers_arr[] = $h;
        }
    }

    $http_options = [
        'method'        => $metodo,
        'ignore_errors' => true,
        'timeout'       => 15,
    ];

    if ($metodo === 'POST') {
        $body = http_build_query($datos);
        $headers_arr[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers_arr[] = 'Content-Length: ' . strlen($body);
        $http_options['content'] = $body;
    }

    $http_options['header'] = implode("\r\n", $headers_arr);

    $ssl_options = [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ];

    $ctx = stream_context_create([
        'http' => $http_options,
        'ssl'  => $ssl_options,
    ]);

    $respuesta = @file_get_contents($url, false, $ctx);
    if ($respuesta === false) {
        $err = error_get_last()['message'] ?? 'Error de conexión HTTP';
        return ['exito' => false, 'error' => $err];
    }

    $codigo_http = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $matches)) {
        $codigo_http = (int)$matches[1];
    }

    $json = json_decode($respuesta, true);

    if ($codigo_http < 200 || $codigo_http >= 300) {
        $msg = $json['error_description'] ?? $json['error'] ?? "HTTP {$codigo_http}";
        return ['exito' => false, 'error' => $msg];
    }

    return ['exito' => true, 'datos' => $json];
}

/**
 * Cambia el código de autorización devuelto por Google por tokens de acceso
 *
 * @param string $code
 * @return array ['exito' => bool, 'datos' => array|null, 'error' => string|null]
 */
function intercambiar_codigo_por_token($code)
{
    $datos_post = [
        'code'          => $code,
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI', APP_URL . '/oauth_callback.php'),
        'grant_type'    => 'authorization_code'
    ];

    return ejecutar_peticion_oauth(GOOGLE_TOKEN_URL, 'POST', $datos_post);
}

/**
 * Consulta la información del perfil del usuario usando el access_token
 *
 * @param string $access_token
 * @return array ['exito' => bool, 'perfil' => array|null, 'error' => string|null]
 */
function obtener_perfil_google($access_token)
{
    $cabeceras = [
        "Authorization: Bearer {$access_token}",
        "Accept: application/json"
    ];

    $resp = ejecutar_peticion_oauth(GOOGLE_USERINFO_URL, 'GET', [], $cabeceras);
    if (!$resp['exito']) {
        return ['exito' => false, 'error' => $resp['error']];
    }

    return ['exito' => true, 'perfil' => $resp['datos']];
}

/**
 * Busca o registra al usuario en la base de datos a partir de su ID de Google
 *
 * @param array $perfil Datos de Google (sub, email, name, picture, email_verified)
 * @param string $rol_deseado
 * @return array|false Datos del usuario en la tabla usuarios o false si falla
 */
function autenticar_o_crear_usuario_google($perfil, $rol_deseado = 'estudiante')
{
    $google_id = $perfil['sub'] ?? '';
    $email     = trim($perfil['email'] ?? '');
    $nombre    = trim($perfil['name'] ?? 'Usuario Google');
    $foto      = $perfil['picture'] ?? null;

    if (empty($google_id) || empty($email)) {
        return false;
    }

    try {
        $pdo = obtener_conexion();

        // 1. Verificar si ya existe una vinculación en cuentas_oauth
        $stmt_oauth = $pdo->prepare("SELECT `usuario_id` FROM `cuentas_oauth` WHERE `proveedor` = 'google' AND `proveedor_user_id` = ? LIMIT 1");
        $stmt_oauth->execute([$google_id]);
        $vinculo = $stmt_oauth->fetch();

        if ($vinculo) {
            // Usuario ya vinculado: obtener datos completos
            $stmt_u = $pdo->prepare("SELECT * FROM `usuarios` WHERE `id` = ? LIMIT 1");
            $stmt_u->execute([$vinculo['usuario_id']]);
            return $stmt_u->fetch() ?: false;
        }

        // 2. Si no hay vínculo por Google ID, buscar si ya existe una cuenta con ese correo
        $stmt_email = $pdo->prepare("SELECT * FROM `usuarios` WHERE `correo` = ? LIMIT 1");
        $stmt_email->execute([$email]);
        $usuario_existente = $stmt_email->fetch();

        if ($usuario_existente) {
            $usuario_id = (int)$usuario_existente['id'];

            // Vincular la cuenta existente con Google
            $stmt_link = $pdo->prepare("INSERT INTO `cuentas_oauth` (`usuario_id`, `proveedor`, `proveedor_user_id`, `creado_en`) VALUES (?, 'google', ?, NOW())");
            $stmt_link->execute([$usuario_id, $google_id]);

            // Asegurar que quede marcado como verificado
            if ((int)$usuario_existente['correo_verificado'] === 0) {
                $pdo->prepare("UPDATE `usuarios` SET `correo_verificado` = 1 WHERE `id` = ?")->execute([$usuario_id]);
                $usuario_existente['correo_verificado'] = 1;
            }

            return $usuario_existente;
        }

        // 3. Crear nuevo usuario en el sistema
        $pdo->beginTransaction();

        $rol = in_array($rol_deseado, ['estudiante', 'tutor'], true) ? $rol_deseado : 'estudiante';

        $stmt_ins = $pdo->prepare(
            "INSERT INTO `usuarios` (`nombre`, `correo`, `password_hash`, `rol`, `ciudad`, `foto`, `correo_verificado`, `creado_en`)
             VALUES (?, ?, NULL, ?, NULL, ?, 1, NOW())"
        );
        $stmt_ins->execute([$nombre, $email, $rol, $foto]);
        $nuevo_id = (int)$pdo->lastInsertId();

        // Vincular en cuentas_oauth
        $stmt_ins_oauth = $pdo->prepare(
            "INSERT INTO `cuentas_oauth` (`usuario_id`, `proveedor`, `proveedor_user_id`, `creado_en`)
             VALUES (?, 'google', ?, NOW())"
        );
        $stmt_ins_oauth->execute([$nuevo_id, $google_id]);

        $pdo->commit();

        $stmt_final = $pdo->prepare("SELECT * FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt_final->execute([$nuevo_id]);
        return $stmt_final->fetch();

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[OAuth Google Sync Error]: " . $e->getMessage());
        return false;
    }
}
