<?php
/**
 * =============================================================================
 * TutoresOnLine - Conexión Segura a Base de Datos (PDO)
 * Utiliza PDO con emulación de preparados desactivada y manejo seguro de errores.
 * Compatible con PHP 7.4 y MySQL 5.7+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Obtiene la instancia única de conexión PDO a la base de datos
 *
 * @return PDO
 * @throws RuntimeException Si la conexión falla
 */
function obtener_conexion()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $dbname = env('DB_NAME', 'tutoresonline');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', 'usbw');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $opciones = [
        // Lanzar excepciones en cualquier error para que se capturen con try-catch
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Usar arrays asociativos por defecto
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // DESACTIVAR emulación de sentencias preparadas para máxima protección contra inyección SQL
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Mantener nombres de columnas originales
        PDO::ATTR_CASE               => PDO::CASE_NATURAL,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $opciones);
        return $pdo;
    } catch (PDOException $e) {
        // Registrar el error real en logs sin exponer credenciales al usuario final
        error_log("[TutoresOnLine DB Error]: " . $e->getMessage());

        if (env('APP_DEBUG', false)) {
            die("Error de conexión a la Base de Datos: " . htmlspecialchars($e->getMessage()));
        } else {
            die("No fue posible conectar con el servicio de base de datos. Por favor intenta más tarde.");
        }
    }
}
