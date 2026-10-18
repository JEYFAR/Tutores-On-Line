<?php
/**
 * =============================================================================
 * TutoresOnLine - Endpoint API: Disponibilidad Horaria del Tutor
 * Permite listar, agregar o eliminar franjas de disponibilidad semanal.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';

iniciar_sesion_segura();

// Verificar tutor
if (empty($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'tutor') {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

$tutor_id = (int)$_SESSION['usuario_id'];
$metodo   = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = obtener_conexion();

    // 1. GET: Listar horarios del tutor
    if ($metodo === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT * FROM `disponibilidad`
             WHERE `tutor_id` = ?
             ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio ASC"
        );
        $stmt->execute([$tutor_id]);
        echo json_encode(['exito' => true, 'disponibilidad' => $stmt->fetchAll()]);
        exit;
    }

    // 2. POST: Operaciones de mutación (agregar / eliminar)
    if ($metodo === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $csrf_token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verificar_csrf($csrf_token)) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido.']);
            exit;
        }

        $accion = $input['accion'] ?? 'crear';

        if ($accion === 'crear') {
            $dia_semana  = strtolower(trim($input['dia_semana'] ?? ''));
            $hora_inicio = trim($input['hora_inicio'] ?? '');
            $hora_fin    = trim($input['hora_fin'] ?? '');
            $modalidad   = trim($input['modalidad'] ?? 'virtual');

            $dias_validos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            if (!in_array($dia_semana, $dias_validos, true) || empty($hora_inicio) || empty($hora_fin)) {
                http_response_code(400);
                echo json_encode(['exito' => false, 'mensaje' => 'Datos de horario incompletos o día no válido.']);
                exit;
            }

            if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
                http_response_code(400);
                echo json_encode(['exito' => false, 'mensaje' => 'La hora de fin debe ser posterior a la de inicio.']);
                exit;
            }

            $stmt_ins = $pdo->prepare(
                "INSERT INTO `disponibilidad` (`tutor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad`)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt_ins->execute([$tutor_id, $dia_semana, $hora_inicio, $hora_fin, $modalidad]);

            echo json_encode([
                'exito' => true,
                'mensaje' => 'Horario agregado correctamente.',
                'id' => (int)$pdo->lastInsertId()
            ]);
            exit;
        }

        if ($accion === 'eliminar') {
            $disp_id = (int)($input['id'] ?? 0);
            $stmt_del = $pdo->prepare("DELETE FROM `disponibilidad` WHERE `id` = ? AND `tutor_id` = ?");
            $stmt_del->execute([$disp_id, $tutor_id]);

            echo json_encode(['exito' => true, 'mensaje' => 'Horario eliminado con éxito.']);
            exit;
        }
    }
} catch (Exception $e) {
    error_log("[API Disponibilidad Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error del servidor al procesar la disponibilidad.']);
}
