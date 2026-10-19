<?php
/**
 * =============================================================================
 * TutoresOnLine - Endpoint API: Buscar Tutores
 * Devuelve lista de tutores en formato JSON según filtros de materia, nivel, ciudad y modalidad.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';

try {
    $pdo = obtener_conexion();

    $materia   = trim($_GET['materia'] ?? '');
    $nivel_id  = (int)($_GET['nivel_id'] ?? 0);
    $ciudad    = trim($_GET['ciudad'] ?? '');
    $modalidad = trim($_GET['modalidad'] ?? '');

    // Construcción dinámica de consulta segura con parámetros preparados
    $condiciones = ["u.rol = 'tutor'"];
    $parametros = [];

    // Si se especifica materia, se filtra por la especialidad
    if (!empty($materia)) {
        $condiciones[] = "m.nombre LIKE ?";
        $parametros[] = "%{$materia}%";
    }

    if ($nivel_id > 0) {
        $condiciones[] = "tm.nivel_id = ?";
        $parametros[] = $nivel_id;
    }

    if (!empty($ciudad)) {
        $condiciones[] = "u.ciudad LIKE ?";
        $parametros[] = "%{$ciudad}%";
    }

    if (!empty($modalidad) && in_array($modalidad, ['virtual', 'presencial'], true)) {
        $condiciones[] = "EXISTS (SELECT 1 FROM disponibilidad d WHERE d.tutor_id = u.id AND d.modalidad = ?)";
        $parametros[] = $modalidad;
    }

    $where = implode(' AND ', $condiciones);

    $sql = "SELECT u.id, u.nombre, u.ciudad, u.foto,
                   COALESCE(GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', '), 'Especialidades por definir') AS materias_texto,
                   COALESCE(MIN(tm.tarifa_hora), 0) AS tarifa_min,
                   COALESCE(MAX(tm.tarifa_hora), 0) AS tarifa_max,
                   COALESCE(ROUND(AVG(c.puntaje), 1), 5.0) AS calificacion,
                   COUNT(DISTINCT c.id) AS total_calificaciones
            FROM usuarios u
            LEFT JOIN tutor_materia tm ON tm.tutor_id = u.id
            LEFT JOIN materias m ON m.id = tm.materia_id
            LEFT JOIN calificaciones c ON c.tutor_id = u.id
            WHERE {$where}
            GROUP BY u.id, u.nombre, u.ciudad, u.foto
            ORDER BY calificacion DESC, total_calificaciones DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $tutores = $stmt->fetchAll();

    // Formatear salida para el frontend
    $resultado = [];
    foreach ($tutores as $t) {
        $resultado[] = [
            'id' => (int)$t['id'],
            'nombre' => $t['nombre'],
            'ciudad' => $t['ciudad'] ?: 'Colombia',
            'foto' => $t['foto'] ? APP_URL . '/uploads/' . $t['foto'] : null,
            'materias' => $t['materias_texto'],
            'tarifa_min' => (float)$t['tarifa_min'],
            'tarifa_max' => (float)$t['tarifa_max'],
            'calificacion' => (float)$t['calificacion'],
            'total_resenas' => (int)$t['total_calificaciones'],
            'perfil_url' => APP_URL . '/perfil.php?id=' . $t['id']
        ];
    }

    echo json_encode([
        'exito' => true,
        'total' => count($resultado),
        'tutores' => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("[API Buscar Tutores Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error al procesar la búsqueda de tutores.'
    ], JSON_UNESCAPED_UNICODE);
}
