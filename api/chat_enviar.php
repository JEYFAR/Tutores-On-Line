<?php
/**
 * =============================================================================
 * TutoresOnLine - API para Enviar Mensajes de Chat
 * Valida sesión, CSRF, pertenencia a la conversación y almacena el mensaje.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 1. Validar Token CSRF
$token_csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $token_csrf)) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$conversacion_id = (int)($input['conversacion_id'] ?? 0);
$texto = trim(strip_tags($input['texto'] ?? ''));

if ($conversacion_id <= 0) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Conversación no especificada']);
    exit;
}

if (empty($texto)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'El mensaje no puede estar vacío']);
    exit;
}

if (mb_strlen($texto) > 2000) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'El mensaje supera el límite de 2000 caracteres']);
    exit;
}

try {
    $pdo = obtener_conexion();

    // 2. Validar que el usuario pertenezca a la conversación
    $stmt_perm = $pdo->prepare("SELECT `id` FROM `conversaciones` WHERE `id` = ? AND (`estudiante_id` = ? OR `tutor_id` = ?) LIMIT 1");
    $stmt_perm->execute([$conversacion_id, $usuario_id, $usuario_id]);
    if (!$stmt_perm->fetch()) {
        http_response_code(403);
        echo json_encode(['exito' => false, 'mensaje' => 'No tienes permiso para enviar mensajes en este chat']);
        exit;
    }

    // 3. Insertar mensaje en la base de datos
    $stmt_ins = $pdo->prepare("INSERT INTO `mensajes` (`conversacion_id`, `emisor_id`, `texto`, `leido`, `creado_en`) VALUES (?, ?, ?, 0, NOW())");
    $stmt_ins->execute([$conversacion_id, $usuario_id, $texto]);
    $nuevo_id = (int)$pdo->lastInsertId();

    // 4. Actualizar timestamp de la conversación para que suba en la lista
    $pdo->prepare("UPDATE `conversaciones` SET `actualizado_en` = NOW() WHERE `id` = ?")->execute([$conversacion_id]);

    $ahora = date('Y-m-d H:i:s');
    echo json_encode([
        'exito' => true,
        'mensaje' => [
            'id'          => $nuevo_id,
            'es_mio'      => true,
            'texto'       => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'),
            'leido'       => false,
            'hora'        => date('H:i', strtotime($ahora)),
            'fecha_corta' => date('d/m', strtotime($ahora)),
            'timestamp'   => $ahora
        ]
    ]);
    exit;

} catch (Exception $e) {
    error_log("[Chat Enviar Error]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error al guardar el mensaje']);
    exit;
}
