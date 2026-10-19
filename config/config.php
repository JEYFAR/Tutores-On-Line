<?php
/**
 * =============================================================================
 * TutoresOnLine - Configuración General del Sistema
 * Carga de variables de entorno (.env), rutas, errores y constantes.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

// Evitar múltiples inclusiones
if (defined('TUTORES_CONFIG_LOADED')) {
    return;
}
define('TUTORES_CONFIG_LOADED', true);

// Definición de rutas absolutas del proyecto
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'includes');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('LOGS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'logs');
define('UPLOADS_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads');

/**
 * Función para cargar y parsear el archivo .env sin dependencias externas
 */
function cargar_env($ruta_env)
{
    if (!file_exists($ruta_env)) {
        return;
    }

    $lineas = file($ruta_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);

        // Omitir comentarios o líneas vacías
        if ($linea === '' || strpos($linea, '#') === 0) {
            continue;
        }

        // Buscar el primer signo igual '='
        $pos = strpos($linea, '=');
        if ($pos !== false) {
            $clave = trim(substr($linea, 0, $pos));
            $valor = trim(substr($linea, $pos + 1));

            // Quitar comillas dobles o simples circundantes
            if ((substr($valor, 0, 1) === '"' && substr($valor, -1) === '"') ||
                (substr($valor, 0, 1) === "'" && substr($valor, -1) === "'")) {
                $valor = substr($valor, 1, -1);
            }

            // Asignar en $_ENV y putenv
            $_ENV[$clave] = $valor;
            putenv("{$clave}={$valor}");
        }
    }
}

// Cargar el archivo .env local
cargar_env(CONFIG_PATH . DIRECTORY_SEPARATOR . '.env');

/**
 * Helper para obtener valores del entorno con valor por defecto
 *
 * @param string $clave
 * @param mixed $por_defecto
 * @return mixed
 */
function env($clave, $por_defecto = null)
{
    $valor = $_ENV[$clave] ?? getenv($clave);

    if ($valor === false || $valor === null) {
        return $por_defecto;
    }

    switch (strtolower((string)$valor)) {
        case 'true':
            return true;
        case 'false':
            return false;
        case 'null':
            return null;
        case 'empty':
            return '';
    }

    return $valor;
}

// Configurar zona horaria (Colombia / Bogotá por defecto)
date_default_timezone_set('America/Bogota');

// URL base de la aplicación (sin barra al final)
$app_url = env('APP_URL', 'http://localhost/tutoresonline/public');
define('APP_URL', rtrim($app_url, '/'));

// URL base del proyecto y de la API
$base_url = preg_replace('#/public$#', '', APP_URL);
define('BASE_URL', $base_url);
define('API_URL', BASE_URL . '/api');

// Configuración de manejo de errores
$es_debug = env('APP_DEBUG', true);

// Siempre registrar errores en el archivo de logs
ini_set('log_errors', '1');
if (!is_dir(LOGS_PATH)) {
    @mkdir(LOGS_PATH, 0755, true);
}
ini_set('error_log', LOGS_PATH . DIRECTORY_SEPARATOR . 'php_error.log');

if ($es_debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
