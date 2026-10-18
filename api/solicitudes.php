<?php
/**
 * =============================================================================
 * TutoresOnLine - Endpoint API: Gestión de Solicitudes de Tutoría
 * Permite al tutor aceptar o rechazar solicitudes de clase de los estudiantes.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';

iniciar_sesion_segura();

// Verificar autenticación y rol de tutor
if (empty($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'tutor') {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'Acceso denegado. Solo para tutores.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$csrf_token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verificar_csrf($csrf_token)) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido.']);
    exit;
}

$tutor_id  = (int)$_SESSION['usuario_id'];
$sesion_id = (int)($input['sesion_id'] ?? 0);
$accion    = trim($input['accion'] ?? ''); // 'aceptar' o 'rechazar'

if ($sesion_id <= 0 || !in_array($accion, ['aceptar', 'rechazar', 'finalizar'], true)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Parámetros de solicitud inválidos.']);
    exit;
}

$nuevo_estado = 'pendiente';
if ($accion === 'aceptar') $nuevo_estado = 'aceptada';
elseif ($accion === 'rechazar') $nuevo_estado = 'rechazada';
elseif ($accion === 'finalizar') $nuevo_estado = 'finalizada';

try {
    $pdo = obtener_conexion();

    // Actualizar estado garantizando que la sesión pertenezca al tutor en sesión
    $stmt = $pdo->prepare("UPDATE `sesiones` SET `estado` = ? WHERE `id` = ? AND `tutor_id` = ?");
    $stmt->execute([$nuevo_estado, $sesion_id, $tutor_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'exito' => true,
            'mensaje' => "La sesión #{$sesion_id} ha sido marcada como '{$nuevo_estado}'.",
            'nuevo_estado' => $nuevo_estado
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['exito' => false, 'mensaje' => 'No se encontró la sesión o no tienes permisos sobre ella.']);
    }
} catch (Exception $e) {
    error_log("[API Solicitudes Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error al actualizar el estado de la solicitud.']);
}
