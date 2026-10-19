<?php
/**
 * =============================================================================
 * TutoresOnLine - API de Mensajes de Chat (Polling)
 * Obtiene los mensajes de una conversación, soporta polling incremental (desde_id)
 * y marca como leídos los mensajes recibidos.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/auth.php';

iniciar_sesion_segura();

if (!usuario_autenticado()) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'No autorizado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$conversacion_id = (int)($_GET['conversacion_id'] ?? 0);
$desde_id = (int)($_GET['desde_id'] ?? 0);

if ($conversacion_id <= 0) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'ID de conversación no proporcionado']);
    exit;
}

try {
    $pdo = obtener_conexion();

    // 1. Validar que el usuario autenticado sea participante de la conversación
    $stmt_perm = $pdo->prepare("SELECT `id`, `estudiante_id`, `tutor_id` FROM `conversaciones` WHERE `id` = ? AND (`estudiante_id` = ? OR `tutor_id` = ?) LIMIT 1");
    $stmt_perm->execute([$conversacion_id, $usuario_id, $usuario_id]);
    $conv = $stmt_perm->fetch();

    if (!$conv) {
        http_response_code(403);
        echo json_encode(['exito' => false, 'mensaje' => 'No tienes permiso para acceder a esta conversación']);
        exit;
    }

    // 2. Consultar mensajes (incremental o inicial)
    if ($desde_id > 0) {
        $stmt_msg = $pdo->prepare("SELECT `id`, `conversacion_id`, `emisor_id`, `texto`, `leido`, `creado_en` FROM `mensajes` WHERE `conversacion_id` = ? AND `id` > ? ORDER BY `id` ASC");
        $stmt_msg->execute([$conversacion_id, $desde_id]);
        $filas = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_msg = $pdo->prepare("SELECT * FROM (SELECT `id`, `conversacion_id`, `emisor_id`, `texto`, `leido`, `creado_en` FROM `mensajes` WHERE `conversacion_id` = ? ORDER BY `id` DESC LIMIT 60) sub ORDER BY `id` ASC");
        $stmt_msg->execute([$conversacion_id]);
        $filas = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Marcar como leídos los mensajes recibidos del otro usuario
    $stmt_read = $pdo->prepare("UPDATE `mensajes` SET `leido` = 1 WHERE `conversacion_id` = ? AND `emisor_id` != ? AND `leido` = 0");
    $stmt_read->execute([$conversacion_id, $usuario_id]);

    $mensajes = [];
    foreach ($filas as $m) {
        $mensajes[] = [
            'id'          => (int)$m['id'],
            'es_mio'      => ((int)$m['emisor_id'] === $usuario_id),
            'texto'       => htmlspecialchars($m['texto'], ENT_QUOTES, 'UTF-8'),
            'leido'       => (bool)$m['leido'],
            'hora'        => date('H:i', strtotime($m['creado_en'])),
            'fecha_corta' => date('d/m', strtotime($m['creado_en'])),
            'timestamp'   => $m['creado_en']
        ];
    }

    echo json_encode([
        'exito' => true,
        'mensajes' => $mensajes,
        'total' => count($mensajes)
    ]);
    exit;

} catch (Exception $e) {
    error_log("[Chat Mensajes Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error al obtener mensajes']);
    exit;
}
