<?php
/**
 * =============================================================================
 * TutoresOnLine - Endpoint API: Reservar Tutoría
 * Recibe datos de reserva vía JSON/POST, valida disponibilidad y registra la sesión.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';

iniciar_sesion_segura();

// Verificar autenticación y rol
if (empty($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'Debes iniciar sesión como estudiante para reservar.']);
    exit;
}

// Leer cuerpo JSON o POST estándar
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$csrf_recibido = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verificar_csrf($csrf_recibido)) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido.']);
    exit;
}

$estudiante_id = (int)$_SESSION['usuario_id'];
$tutor_id      = (int)($input['tutor_id'] ?? 0);
$materia_id    = (int)($input['materia_id'] ?? 0);
$fecha         = trim($input['fecha'] ?? '');
$hora_inicio   = trim($input['hora_inicio'] ?? '');
$hora_fin      = trim($input['hora_fin'] ?? '');
$modalidad     = trim($input['modalidad'] ?? 'virtual');

if ($tutor_id <= 0 || $materia_id <= 0 || empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Por favor completa todos los campos de la reserva.']);
    exit;
}

if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'La fecha no puede ser en el pasado.']);
    exit;
}

if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'La hora de fin debe ser posterior a la de inicio.']);
    exit;
}

if (!in_array($modalidad, ['virtual', 'presencial'], true)) {
    $modalidad = 'virtual';
}

try {
    $pdo = obtener_conexion();

    // Validar que el tutor ofrezca esa materia
    $stmt_check = $pdo->prepare("SELECT 1 FROM `tutor_materia` WHERE `tutor_id` = ? AND `materia_id` = ? LIMIT 1");
    $stmt_check->execute([$tutor_id, $materia_id]);
    if (!$stmt_check->fetch()) {
        http_response_code(400);
        echo json_encode(['exito' => false, 'mensaje' => 'El tutor no imparte la materia seleccionada.']);
        exit;
    }

    // Insertar la sesión
    $stmt = $pdo->prepare(
        "INSERT INTO `sesiones` (`estudiante_id`, `tutor_id`, `materia_id`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `estado`, `creado_en`)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
    );
    $stmt->execute([$estudiante_id, $tutor_id, $materia_id, $fecha, $hora_inicio, $hora_fin, $modalidad]);
    $sesion_id = (int)$pdo->lastInsertId();

    echo json_encode([
        'exito' => true,
        'mensaje' => '¡Tutoría solicitada exitosamente! Quedó registrada como pendiente de aprobación.',
        'sesion_id' => $sesion_id
    ]);
} catch (Exception $e) {
    error_log("[API Reservar Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error en el servidor al registrar la tutoría.']);
}
