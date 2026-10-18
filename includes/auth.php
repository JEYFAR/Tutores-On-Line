<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo de Autenticación, Roles y Cookies Persistentes
 * - Control de acceso por rol (requiere_login, requiere_rol)
 * - Creación y destrucción de sesión atada al User-Agent
 * - Cookie persistente ("Recordarme") con rotación de tokens (selector:validador)
 * - Detección de robo de token y revocación masiva
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

// Asegurar que la sesión esté iniciada
iniciar_sesion_segura();

/**
 * Retorna true si hay un usuario autenticado en la sesión actual
 *
 * @return bool
 */
function usuario_autenticado()
{
    return !empty($_SESSION['usuario_id']);
}

/**
 * Obtiene los datos del usuario actualmente autenticado desde la base de datos
 *
 * @return array|null
 */
function usuario_actual()
{
    if (!usuario_autenticado()) {
        return null;
    }

    static $cache_usuario = null;
    if ($cache_usuario !== null) {
        return $cache_usuario;
    }

    try {
        $pdo = obtener_conexion();
        $stmt = $pdo->prepare("SELECT `id`, `nombre`, `correo`, `rol`, `ciudad`, `foto`, `correo_verificado`, `creado_en` FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        $cache_usuario = $stmt->fetch();
        return $cache_usuario ?: null;
    } catch (Exception $e) {
        error_log("[Auth usuario_actual Error]: " . $e->getMessage());
        return null;
    }
}

/**
 * Bloquea la página si el usuario no ha iniciado sesión
 */
function requiere_login()
{
    if (!usuario_autenticado()) {
        set_flash('warning', 'Debes iniciar sesión para acceder a esta sección.');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Bloquea la página si el usuario no tiene el rol especificado
 *
 * @param string|array $roles_permitidos 'estudiante', 'tutor' o array
 */
function requiere_rol($roles_permitidos)
{
    requiere_login();

    $roles = is_array($roles_permitidos) ? $roles_permitidos : [$roles_permitidos];
    $rol_actual = $_SESSION['rol'] ?? '';

    if (!in_array($rol_actual, $roles, true)) {
        set_flash('danger', 'No tienes permisos para acceder a este recurso.');
        if ($rol_actual === 'tutor') {
            header('Location: ' . APP_URL . '/panel_tutor.php');
        } else {
            header('Location: ' . APP_URL . '/panel_estudiante.php');
        }
        exit;
    }
}

/**
 * Registra formalmente la sesión de un usuario tras autenticarse
 *
 * @param array $usuario
 */
function crear_sesion_usuario($usuario)
{
    // Regenerar el ID de sesión para prevenir Session Fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']        = (int)$usuario['id'];
    $_SESSION['nombre']            = $usuario['nombre'];
    $_SESSION['correo']            = $usuario['correo'];
    $_SESSION['rol']               = $usuario['rol'];
    $_SESSION['correo_verificado'] = (int)$usuario['correo_verificado'];
    $_SESSION['ua_hash']           = obtener_ua_hash();
    $_SESSION['ip']                = obtener_ip();
    $_SESSION['ultimo_acceso']     = time();
    $_SESSION['creado_en_sesion']  = time();
}

/**
 * Cierra sesión completamente: destruye sesión PHP y revoca la cookie persistente
 */
function cerrar_sesion_completa()
{
    // Revocar token persistente en la BD si existe cookie recordarme
    if (!empty($_COOKIE['recordarme'])) {
        $partes = explode(':', $_COOKIE['recordarme']);
        if (count($partes) === 2) {
            $selector = $partes[0];
            try {
                $pdo = obtener_conexion();
                $stmt = $pdo->prepare("DELETE FROM `tokens_sesion` WHERE `selector` = ?");
                $stmt->execute([$selector]);
            } catch (Exception $e) {
                error_log("[Auth Logout Token Error]: " . $e->getMessage());
            }
        }

        // Borrar cookie en el navegador
        $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('recordarme', '', time() - 3600, '/', '', $es_https, true);
    }

    destruir_sesion();
}

/**
 * Genera una cookie persistente ("Recordarme") de 30 días usando selector y validador
 *
 * @param int $usuario_id
 */
function generar_token_recordarme($usuario_id)
{
    try {
        $selector = bin2hex(random_bytes(16));  // 32 caracteres
        $validador = bin2hex(random_bytes(32)); // 64 caracteres
        $validador_hash = hash('sha256', $validador);
        $ua_hash = obtener_ua_hash();
        $ip = obtener_ip();
        $dias_validez = 30;
        $expira_en = date('Y-m-d H:i:s', time() + ($dias_validez * 86400));

        $pdo = obtener_conexion();
        $stmt = $pdo->prepare(
            "INSERT INTO `tokens_sesion` (`usuario_id`, `selector`, `validador_hash`, `user_agent_hash`, `ip`, `expira_en`, `creado_en`, `ultimo_uso`)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$usuario_id, $selector, $validador_hash, $ua_hash, $ip, $expira_en]);

        // Enviar la cookie segura al navegador: formato selector:validador
        $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(
            'recordarme',
            "{$selector}:{$validador}",
            time() + ($dias_validez * 86400),
            '/',
            '',
            $es_https,
            true // HttpOnly para que JavaScript no pueda leerla
        );
    } catch (Exception $e) {
        error_log("[Auth generar_token_recordarme Error]: " . $e->getMessage());
    }
}

/**
 * Valida la cookie persistente si no hay sesión PHP activa.
 * Aplica rotación de token en cada uso y revoca todos si detecta intento de reutilización.
 */
function verificar_cookie_recordarme()
{
    if (usuario_autenticado() || empty($_COOKIE['recordarme'])) {
        return;
    }

    $partes = explode(':', $_COOKIE['recordarme']);
    if (count($partes) !== 2) {
        return;
    }

    list($selector, $validador) = $partes;

    try {
        $pdo = obtener_conexion();
        $stmt = $pdo->prepare("SELECT * FROM `tokens_sesion` WHERE `selector` = ? LIMIT 1");
        $stmt->execute([$selector]);
        $token_bd = $stmt->fetch();

        $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        if (!$token_bd) {
            // El selector no existe: limpiar cookie huérfana
            setcookie('recordarme', '', time() - 3600, '/', '', $es_https, true);
            return;
        }

        // Verificar expiración temporal
        if (strtotime($token_bd['expira_en']) < time()) {
            $stmt_del = $pdo->prepare("DELETE FROM `tokens_sesion` WHERE `id` = ?");
            $stmt_del->execute([$token_bd['id']]);
            setcookie('recordarme', '', time() - 3600, '/', '', $es_https, true);
            return;
        }

        // Verificar atadura al User-Agent
        if (!hash_equals($token_bd['user_agent_hash'], obtener_ua_hash())) {
            // Rechazar sesión si el agente de usuario cambió drásticamente
            error_log("[Alerta Seguridad]: Intento de uso de cookie 'recordarme' con User-Agent distinto. Selector: {$selector}");
            return;
        }

        // Validar el hash del validador recibido
        $hash_calculado = hash('sha256', $validador);
        if (!hash_equals($token_bd['validador_hash'], $hash_calculado)) {
            // ROBO DE TOKEN DETECTADO: El selector era válido pero el validador no coincide.
            // Se asume que el token fue interceptado y usado previamente.
            // Revocar TODOS los tokens persistentes de ese usuario por seguridad.
            $stmt_revocar = $pdo->prepare("DELETE FROM `tokens_sesion` WHERE `usuario_id` = ?");
            $stmt_revocar->execute([$token_bd['usuario_id']]);
            setcookie('recordarme', '', time() - 3600, '/', '', $es_https, true);
            error_log("[Alerta Seguridad]: Robo de token sospechoso. Todos los tokens del usuario {$token_bd['usuario_id']} han sido revocados.");
            return;
        }

        // TOKEN VÁLIDO: Cargar usuario
        $stmt_user = $pdo->prepare("SELECT * FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt_user->execute([$token_bd['usuario_id']]);
        $usuario = $stmt_user->fetch();

        if ($usuario) {
            // 1. Iniciar sesión formal
            crear_sesion_usuario($usuario);

            // 2. ROTACIÓN DE TOKEN: Emitir un nuevo validador y actualizar la BD
            $nuevo_validador = bin2hex(random_bytes(32));
            $nuevo_validador_hash = hash('sha256', $nuevo_validador);
            $nueva_expiracion = date('Y-m-d H:i:s', time() + (30 * 86400));

            $stmt_update = $pdo->prepare(
                "UPDATE `tokens_sesion` 
                 SET `validador_hash` = ?, `expira_en` = ?, `ultimo_uso` = NOW(), `ip` = ? 
                 WHERE `id` = ?"
            );
            $stmt_update->execute([$nuevo_validador_hash, $nueva_expiracion, obtener_ip(), $token_bd['id']]);

            // Reemitir cookie actualizada
            setcookie(
                'recordarme',
                "{$selector}:{$nuevo_validador}",
                time() + (30 * 86400),
                '/',
                '',
                $es_https,
                true
            );
        }
    } catch (Exception $e) {
        error_log("[Auth verificar_cookie_recordarme Error]: " . $e->getMessage());
    }
}

/**
 * Genera un token criptográfico de un solo uso para verificar correo o recuperar clave
 *
 * @param int $usuario_id
 * @param string $tipo 'correo' o 'recuperacion'
 * @param int $horas_validez Horas de vida del token
 * @return string Retorna el token en plano para enviarlo por enlace
 */
function generar_token_verificacion($usuario_id, $tipo = 'correo', $horas_validez = 24)
{
    $token_plano = bin2hex(random_bytes(32)); // 64 caracteres
    $token_hash  = hash('sha256', $token_plano);
    $expira_en   = date('Y-m-d H:i:s', time() + ($horas_validez * 3600));

    try {
        $pdo = obtener_conexion();
        // Invalidar tokens previos no usados del mismo tipo para este usuario
        $stmt_prev = $pdo->prepare("UPDATE `tokens_verificacion` SET `usado` = 1 WHERE `usuario_id` = ? AND `tipo` = ?");
        $stmt_prev->execute([$usuario_id, $tipo]);

        // Insertar nuevo token
        $stmt = $pdo->prepare(
            "INSERT INTO `tokens_verificacion` (`usuario_id`, `tipo`, `token_hash`, `expira_en`, `usado`, `creado_en`)
             VALUES (?, ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$usuario_id, $tipo, $token_hash, $expira_en]);

        return $token_plano;
    } catch (Exception $e) {
        error_log("[Auth generar_token_verificacion Error]: " . $e->getMessage());
        return '';
    }
}
