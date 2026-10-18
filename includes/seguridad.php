<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo Central de Seguridad
 * - Cabeceras HTTP de protección (X-Frame-Options, X-Content-Type-Options, etc.)
 * - Inicio y validación estricta de sesiones (User-Agent, expiración, inactividad)
 * - Protección contra ataques CSRF con tokens criptográficos
 * - Sanitización y prevención de XSS
 * - Mitigación de fuerza bruta con tabla intentos_login
 * - Hashing de contraseñas con bcrypt (cost 12) + pepper opcional en HMAC
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/conexion.php';

/**
 * Emite cabeceras HTTP de endurecimiento de seguridad
 */
function enviar_cabeceras_seguridad()
{
    if (headers_sent()) {
        return;
    }

    // Evitar que el navegador intente adivinar el tipo MIME (MIME-sniffing)
    header('X-Content-Type-Options: nosniff');

    // Evitar clickjacking bloqueando la inclusión del sitio en iframes
    header('X-Frame-Options: DENY');

    // Control de la información enviada en la cabecera Referer
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Protección adicional XSS para navegadores antiguos
    header('X-XSS-Protection: 1; mode=block');
}

/**
 * Obtiene el hash SHA-256 del User-Agent de la petición actual
 *
 * @return string
 */
function obtener_ua_hash()
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    return hash('sha256', $ua);
}

/**
 * Obtiene la dirección IP real del cliente
 *
 * @return string
 */
function obtener_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $partes = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($partes[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Inicia la sesión de PHP aplicando todas las directivas de seguridad recomendadas
 */
function iniciar_sesion_segura()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Configurar directivas estrictas de sesión antes de session_start() si las cabeceras no se han enviado
    if (!headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        // Configurar parámetros de la cookie de sesión
        session_set_cookie_params([
            'lifetime' => 0,              // Dura hasta que el usuario cierre el navegador
            'path'     => '/',
            'domain'   => '',
            'secure'   => $es_https,      // Solo por HTTPS si está activo
            'httponly' => true,           // Inaccesible para JavaScript (mitiga robo por XSS)
            'samesite' => 'Lax'           // Protección contra solicitudes cross-site
        ]);

        session_start();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    // Enviar cabeceras de seguridad
    enviar_cabeceras_seguridad();

    // Validar sesión activa si ya está autenticado
    if (!empty($_SESSION['usuario_id'])) {
        // 1. Control de inactividad: expirar sesión tras 30 minutos sin peticiones
        $tiempo_max_inactivo = 30 * 60; // 30 minutos en segundos
        if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > $tiempo_max_inactivo) {
            destruir_sesion();
            header('Location: ' . APP_URL . '/login.php?motivo=inactividad');
            exit;
        }
        $_SESSION['ultimo_acceso'] = time();

        // 2. Validación de User-Agent: si cambia el agente de usuario, revocar sesión
        if (!empty($_SESSION['ua_hash']) && !hash_equals($_SESSION['ua_hash'], obtener_ua_hash())) {
            destruir_sesion();
            header('Location: ' . APP_URL . '/login.php?motivo=agente_invalido');
            exit;
        }

        // 3. Regeneración periódica del ID de sesión cada 15 minutos para evitar session fixation
        if (!isset($_SESSION['creado_en_sesion'])) {
            $_SESSION['creado_en_sesion'] = time();
        } elseif ((time() - $_SESSION['creado_en_sesion']) > (15 * 60)) {
            session_regenerate_id(true);
            $_SESSION['creado_en_sesion'] = time();
        }
    }
}

/**
 * Cierra y destruye completamente la sesión activa y borra su cookie
 */
function destruir_sesion()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    // Borrar la cookie de sesión en el cliente
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Genera u obtiene el token CSRF para la sesión actual
 *
 * @return string
 */
function csrf_token()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Genera el campo oculto HTML para formularios con el token CSRF
 *
 * @return string
 */
function csrf_campo()
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifica si el token CSRF recibido es válido (usando hash_equals contra timing attacks)
 *
 * @param string|null $token
 * @return bool
 */
function verificar_csrf($token = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }

    if ($token === null) {
        // Buscar en POST o en cabecera HTTP (X-CSRF-TOKEN para peticiones fetch/Vue)
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escapa cadenas para prevenir ataques de Cross-Site Scripting (XSS) en vistas HTML
 *
 * @param string|null $valor
 * @return string
 */
function e($valor)
{
    if ($valor === null) {
        return '';
    }
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Limpia y normaliza entradas de texto
 *
 * @param string|null $valor
 * @return string
 */
function limpiar($valor)
{
    if ($valor === null) {
        return '';
    }
    return trim(strip_tags((string)$valor));
}

/**
 * Genera el hash seguro de una contraseña usando bcrypt (cost 12) y pepper de .env
 *
 * @param string $password
 * @return string
 */
function hashear_password($password)
{
    $pepper = env('APP_PEPPER', '');
    $prehash = hash_hmac('sha256', $password, $pepper);
    return password_hash($prehash, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Verifica una contraseña contra su hash almacenado en base de datos.
 * Soporta contraseñas con pepper y contraseñas estándar de prueba.
 *
 * @param string $password
 * @param string|null $hash_almacenado
 * @return bool
 */
function verificar_password($password, $hash_almacenado)
{
    if (empty($hash_almacenado)) {
        return false;
    }

    $pepper = env('APP_PEPPER', '');
    $prehash = hash_hmac('sha256', $password, $pepper);

    // Intentar primero con pepper
    if (password_verify($prehash, $hash_almacenado)) {
        return true;
    }

    // Intentar directo (para datos_prueba.sql o cuentas iniciales)
    if (password_verify($password, $hash_almacenado)) {
        return true;
    }

    return false;
}

/**
 * Registra un intento de login en la base de datos para auditoría y rate limiting
 *
 * @param string $correo
 * @param bool $exitoso
 */
function registrar_intento_login($correo, $exitoso)
{
    try {
        $pdo = obtener_conexion();
        $ip = obtener_ip();
        $stmt = $pdo->prepare(
            "INSERT INTO `intentos_login` (`correo`, `ip`, `exitoso`, `creado_en`) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$correo, $ip, $exitoso ? 1 : 0]);
    } catch (Exception $e) {
        error_log("[TutoresOnLine RateLimit Error]: " . $e->getMessage());
    }
}

/**
 * Verifica si un correo o IP está temporalmente bloqueado por exceso de intentos fallidos
 *
 * @param string $correo
 * @param int $max_intentos Máximo de intentos fallidos permitidos (def: 5)
 * @param int $minutos Ventana de tiempo en minutos (def: 15)
 * @return bool
 */
function esta_bloqueado_fuerza_bruta($correo, $max_intentos = 5, $minutos = 15)
{
    try {
        $pdo = obtener_conexion();
        $ip = obtener_ip();

        $sql = "SELECT COUNT(*) FROM `intentos_login`
                WHERE (`correo` = ? OR `ip` = ?)
                  AND `exitoso` = 0
                  AND `creado_en` > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$correo, $ip, $minutos]);
        $intentos_fallidos = (int)$stmt->fetchColumn();

        return $intentos_fallidos >= $max_intentos;
    } catch (Exception $e) {
        error_log("[TutoresOnLine RateLimit Check Error]: " . $e->getMessage());
        return false;
    }
}

/**
 * Guarda un mensaje flash en sesión para mostrarlo en la siguiente petición
 *
 * @param string $tipo 'success', 'danger', 'warning', 'info'
 * @param string $mensaje
 */
function set_flash($tipo, $mensaje)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    $_SESSION['flash'][$tipo] = $mensaje;
}

/**
 * Obtiene y elimina un mensaje flash de la sesión
 *
 * @param string $tipo
 * @return string|null
 */
function get_flash($tipo)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    if (isset($_SESSION['flash'][$tipo])) {
        $msg = $_SESSION['flash'][$tipo];
        unset($_SESSION['flash'][$tipo]);
        return $msg;
    }
    return null;
}

/**
 * Verifica si existe un mensaje flash pendiente
 *
 * @param string $tipo
 * @return bool
 */
function hay_flash($tipo)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    return !empty($_SESSION['flash'][$tipo]);
}
