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

        // Auto-migración ligera para bases de datos ya importadas
        static $migrado = false;
        if (!$migrado) {
            $migrado = true;
            try {
                $stmt_col = $pdo->query("SHOW COLUMNS FROM `sesiones` LIKE 'enlace_videollamada'");
                if ($stmt_col && !$stmt_col->fetch()) {
                    $pdo->exec("ALTER TABLE `sesiones` ADD COLUMN `enlace_videollamada` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Enlace Jitsi Meet generado al aceptar la solicitud' AFTER `estado`");
                    $pdo->exec("UPDATE `sesiones` SET `enlace_videollamada` = CONCAT('https://meet.jit.si/tutoria-', `id`) WHERE `estado` IN ('aceptada', 'finalizada') AND `enlace_videollamada` IS NULL");
                }

                $stmt_desc = $pdo->query("SHOW COLUMNS FROM `usuarios` LIKE 'descripcion'");
                if ($stmt_desc && !$stmt_desc->fetch()) {
                    $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `descripcion` TEXT NULL DEFAULT NULL COMMENT 'Presentación académica y metodología del tutor' AFTER `foto`");
                    $descripciones_demo = [
                        4 => 'Ingeniero Civil con más de 6 años de experiencia dictando tutorías universitarias en Matemáticas, Cálculo Diferencial y Álgebra Lineal. Metodología práctica enfocada en resolver ejercicios paso a paso y preparar parciales.',
                        5 => 'Doctora en Ciencias de la Computación y docente universitaria. Especialista en Programación Web, desarrollo de software, Matemáticas e Inglés técnico con clases personalizadas orientadas a proyectos reales.',
                        6 => 'Licenciado en Física y Química con amplia trayectoria en bachillerato y primeros semestres universitarios. Clases dinámicas con ejemplos de la vida cotidiana, refuerzo teórico y guías de estudio.',
                    ];
                    $stmt_upd_desc = $pdo->prepare("UPDATE `usuarios` SET `descripcion` = ? WHERE `id` = ? AND `rol` = 'tutor' AND (`descripcion` IS NULL OR `descripcion` = '')");
                    foreach ($descripciones_demo as $tid => $texto_desc) {
                        $stmt_upd_desc->execute([$texto_desc, $tid]);
                    }
                }
            } catch (Exception $e_mig) {
                error_log("[TutoresOnLine Auto-Migración]: " . $e_mig->getMessage());
            }
        }

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
