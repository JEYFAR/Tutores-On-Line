<?php
/**
 * =============================================================================
 * TutoresOnLine - API de Conversaciones de Chat
 * Lista las conversaciones del usuario activo o inicia una nueva con otro usuario.
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
$mi_rol = $_SESSION['rol'];
$pdo = obtener_conexion();

// =============================================================================
// GET: Listar conversaciones activas del usuario con su último mensaje
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "
            SELECT 
                c.id AS conversacion_id,
                c.estudiante_id,
                c.tutor_id,
                c.sesion_id,
                c.actualizado_en,
                u.id AS contacto_id,
                u.nombre AS contacto_nombre,
                u.correo AS contacto_correo,
                u.rol AS contacto_rol,
                u.foto AS contacto_foto,
                u.ciudad AS contacto_ciudad,
                m.texto AS ultimo_mensaje,
                m.emisor_id AS ultimo_emisor_id,
                m.creado_en AS ultimo_mensaje_fecha,
                (
                    SELECT COUNT(*) 
                    FROM `mensajes` msg 
                    WHERE msg.conversacion_id = c.id 
                      AND msg.emisor_id != ? 
                      AND msg.leido = 0
                ) AS no_leidos
            FROM `conversaciones` c
            INNER JOIN `usuarios` u ON u.id = IF(c.estudiante_id = ?, c.tutor_id, c.estudiante_id)
            LEFT JOIN `mensajes` m ON m.id = (
                SELECT id FROM `mensajes` 
                WHERE conversacion_id = c.id 
                ORDER BY id DESC 
                LIMIT 1
            )
            WHERE c.estudiante_id = ? OR c.tutor_id = ?
            ORDER BY c.actualizado_en DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conversaciones = [];
        foreach ($filas as $f) {
            $avatar_url = null;
            if (!empty($f['contacto_foto'])) {
                if (strpos($f['contacto_foto'], 'http://') === 0 || strpos($f['contacto_foto'], 'https://') === 0) {
                    $avatar_url = $f['contacto_foto'];
                } elseif (file_exists(UPLOADS_PATH . DIRECTORY_SEPARATOR . $f['contacto_foto'])) {
                    $avatar_url = APP_URL . '/uploads/' . $f['contacto_foto'];
                }
            }

            $conversaciones[] = [
                'conversacion_id'       => (int)$f['conversacion_id'],
                'contacto_id'           => (int)$f['contacto_id'],
                'contacto_nombre'       => $f['contacto_nombre'],
                'contacto_rol'          => $f['contacto_rol'],
                'contacto_ciudad'       => $f['contacto_ciudad'] ?: '',
                'contacto_avatar'       => $avatar_url,
                'ultimo_mensaje'        => $f['ultimo_mensaje'] ?: 'Inicia la conversación...',
                'ultimo_emisor_es_mio'  => ((int)$f['ultimo_emisor_id'] === $usuario_id),
                'ultimo_mensaje_fecha'  => $f['ultimo_mensaje_fecha'] ? date('H:i', strtotime($f['ultimo_mensaje_fecha'])) : '',
                'fecha_completa'        => $f['actualizado_en'],
                'no_leidos'             => (int)$f['no_leidos']
            ];
        }

        echo json_encode(['exito' => true, 'conversaciones' => $conversaciones]);
        exit;

    } catch (Exception $e) {
        error_log("[Chat Listar Conversaciones Error]: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['exito' => false, 'mensaje' => 'Error al cargar conversaciones']);
        exit;
    }
}

// =============================================================================
// POST: Iniciar o recuperar conversación con un contacto
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $contacto_id = (int)($input['contacto_id'] ?? 0);
    $sesion_id   = !empty($input['sesion_id']) ? (int)$input['sesion_id'] : null;

    if ($contacto_id <= 0 || $contacto_id === $usuario_id) {
        http_response_code(400);
        echo json_encode(['exito' => false, 'mensaje' => 'Contacto no válido']);
        exit;
    }

    try {
        // Verificar que el contacto exista
        $stmt_c = $pdo->prepare("SELECT `id`, `nombre`, `rol` FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt_c->execute([$contacto_id]);
        $contacto = $stmt_c->fetch();

        if (!$contacto) {
            http_response_code(404);
            echo json_encode(['exito' => false, 'mensaje' => 'El usuario no existe']);
            exit;
        }

        // Definir estudiante y tutor
        if ($mi_rol === 'estudiante') {
            $estudiante_id = $usuario_id;
            $tutor_id = $contacto_id;
        } else {
            $estudiante_id = $contacto_id;
            $tutor_id = $usuario_id;
        }

        // Buscar si ya existe la conversación
        $stmt_chk = $pdo->prepare("SELECT `id` FROM `conversaciones` WHERE `estudiante_id` = ? AND `tutor_id` = ? LIMIT 1");
        $stmt_chk->execute([$estudiante_id, $tutor_id]);
        $existente = $stmt_chk->fetch();

        if ($existente) {
            $conversacion_id = (int)$existente['id'];
        } else {
            // Crear nueva conversación
            $stmt_ins = $pdo->prepare("INSERT INTO `conversaciones` (`estudiante_id`, `tutor_id`, `sesion_id`, `actualizado_en`, `creado_en`) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt_ins->execute([$estudiante_id, $tutor_id, $sesion_id]);
            $conversacion_id = (int)$pdo->lastInsertId();
        }

        echo json_encode([
            'exito' => true,
            'conversacion_id' => $conversacion_id,
            'contacto' => [
                'id' => (int)$contacto['id'],
                'nombre' => $contacto['nombre'],
                'rol' => $contacto['rol']
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("[Chat Iniciar Conversacion Error]: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['exito' => false, 'mensaje' => 'Error al iniciar conversación']);
        exit;
    }
}
