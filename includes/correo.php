<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo de Envío de Correos Electrónicos (SMTP + Log)
 * Soporta envío SMTP autenticado (Gmail con contraseña de aplicación o Mailtrap)
 * mediante sockets nativos PHP (sin dependencias externas) y registra siempre una
 * copia en logs/mail.log para auditoría y pruebas locales.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Registra el correo en logs/mail.log para auditoría y demos locales
 *
 * @param string $destinatario_correo
 * @param string $destinatario_nombre
 * @param string $asunto
 * @param string $cuerpo_html
 * @param string $enlace_accion
 * @param string $estado_entrega
 * @return bool
 */
function registrar_correo_log($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion = '', $estado_entrega = 'LOG')
{
    if (!is_dir(LOGS_PATH)) {
        @mkdir(LOGS_PATH, 0755, true);
    }

    $archivo_log = LOGS_PATH . DIRECTORY_SEPARATOR . 'mail.log';
    $fecha = date('Y-m-d H:i:s');

    $contenido = "=============================================================================\n";
    $contenido .= "FECHA: {$fecha} | MODO: {$estado_entrega}\n";
    $contenido .= "PARA: {$destinatario_nombre} <{$destinatario_correo}>\n";
    $contenido .= "ASUNTO: {$asunto}\n";
    if (!empty($enlace_accion)) {
        $contenido .= "ENLACE DIRECTO DE ACCIÓN: {$enlace_accion}\n";
    }
    $contenido .= "MENSAJE:\n" . trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ["\n", "\n", "\n", "\n", "\n"], $cuerpo_html))) . "\n";
    $contenido .= "=============================================================================\n\n";

    return (bool)@file_put_contents($archivo_log, $contenido, FILE_APPEND | LOCK_EX);
}

/**
 * Lee una respuesta completa (incluyendo líneas continuadas) desde un socket SMTP
 *
 * @param resource $socket
 * @return array ['codigo' => int, 'mensaje' => string]
 */
function smtp_leer_respuesta($socket)
{
    $respuesta = '';
    $codigo = 0;

    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;
        if (preg_match('/^(\d{3})([\s-])/', $linea, $m)) {
            $codigo = (int)$m[1];
            if ($m[2] === ' ') {
                break;
            }
        } else {
            break;
        }
    }

    return ['codigo' => $codigo, 'mensaje' => trim($respuesta)];
}

/**
 * Envía un comando SMTP y verifica el código de respuesta esperado
 *
 * @param resource $socket
 * @param string $comando
 * @param array $codigos_esperados
 * @return array ['ok' => bool, 'codigo' => int, 'mensaje' => string]
 */
function smtp_ejecutar_comando($socket, $comando, $codigos_esperados)
{
    fwrite($socket, $comando . "\r\n");
    $resp = smtp_leer_respuesta($socket);
    $ok = in_array($resp['codigo'], $codigos_esperados, true);

    return [
        'ok'      => $ok,
        'codigo'  => $resp['codigo'],
        'mensaje' => $resp['mensaje'],
    ];
}

/**
 * Envía un correo HTML mediante conexión SMTP nativa (STARTTLS / SSL + AUTH LOGIN)
 *
 * @param string $destinatario_correo
 * @param string $destinatario_nombre
 * @param string $asunto
 * @param string $cuerpo_html
 * @param string $from_email
 * @param string $from_name
 * @return array ['exito' => bool, 'error' => string|null]
 */
function enviar_por_smtp_nativo($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $from_email, $from_name)
{
    $host = trim((string)env('SMTP_HOST', ''));
    $port = (int)env('SMTP_PORT', 587);
    $user = trim((string)env('SMTP_USER', ''));
    $pass = (string)env('SMTP_PASS', '');

    if ($host === '' || $user === '' || $pass === '' || strpos($user, 'tu_correo@') !== false || strpos($pass, 'tu_password') !== false) {
        return ['exito' => false, 'error' => 'Credenciales SMTP no configuradas en .env'];
    }

    $es_local = env('APP_ENV', 'local') === 'local';
    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer'       => !$es_local,
            'verify_peer_name'  => !$es_local,
            'allow_self_signed' => $es_local,
        ],
    ]);

    $prefijo = ($port === 465) ? 'ssl://' : '';
    $socket = @stream_socket_client("{$prefijo}{$host}:{$port}", $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $contexto);
    if (!$socket) {
        return ['exito' => false, 'error' => "No se pudo conectar a {$host}:{$port} ({$errno}: {$errstr})"];
    }

    stream_set_timeout($socket, 12);

    $saludo = smtp_leer_respuesta($socket);
    if ($saludo['codigo'] !== 220) {
        fclose($socket);
        return ['exito' => false, 'error' => 'Saludo SMTP inesperado: ' . $saludo['mensaje']];
    }

    $ehlo_host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $resp = smtp_ejecutar_comando($socket, "EHLO {$ehlo_host}", [250]);
    if (!$resp['ok']) {
        fclose($socket);
        return ['exito' => false, 'error' => 'Error en EHLO: ' . $resp['mensaje']];
    }

    // Iniciar STARTTLS cuando se usa puerto 587 o 2525 (Gmail / Mailtrap)
    if ($port !== 465 && stripos($resp['mensaje'], 'STARTTLS') !== false) {
        $tls = smtp_ejecutar_comando($socket, 'STARTTLS', [220]);
        if (!$tls['ok']) {
            fclose($socket);
            return ['exito' => false, 'error' => 'Error al iniciar STARTTLS: ' . $tls['mensaje']];
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $crypto_method)) {
            fclose($socket);
            return ['exito' => false, 'error' => 'Fallo en negociación TLS con el servidor SMTP'];
        }
        $resp = smtp_ejecutar_comando($socket, "EHLO {$ehlo_host}", [250]);
        if (!$resp['ok']) {
            fclose($socket);
            return ['exito' => false, 'error' => 'Error en EHLO tras STARTTLS: ' . $resp['mensaje']];
        }
    }

    // Autenticación AUTH LOGIN
    $pasos_auth = [
        ['cmd' => 'AUTH LOGIN', 'codes' => [334]],
        ['cmd' => base64_encode($user), 'codes' => [334]],
        ['cmd' => base64_encode($pass), 'codes' => [235]],
    ];
    foreach ($pasos_auth as $paso) {
        $r = smtp_ejecutar_comando($socket, $paso['cmd'], $paso['codes']);
        if (!$r['ok']) {
            fclose($socket);
            return ['exito' => false, 'error' => 'Autenticación SMTP fallida: ' . $r['mensaje']];
        }
    }

    $remitente_smtp = filter_var($from_email, FILTER_VALIDATE_EMAIL) ? $from_email : $user;
    $mail_from = smtp_ejecutar_comando($socket, "MAIL FROM:<{$remitente_smtp}>", [250]);
    if (!$mail_from['ok']) {
        fclose($socket);
        return ['exito' => false, 'error' => 'MAIL FROM rechazado: ' . $mail_from['mensaje']];
    }

    $rcpt_to = smtp_ejecutar_comando($socket, "RCPT TO:<{$destinatario_correo}>", [250, 251]);
    if (!$rcpt_to['ok']) {
        fclose($socket);
        return ['exito' => false, 'error' => 'RCPT TO rechazado: ' . $rcpt_to['mensaje']];
    }

    $data_cmd = smtp_ejecutar_comando($socket, 'DATA', [354]);
    if (!$data_cmd['ok']) {
        fclose($socket);
        return ['exito' => false, 'error' => 'DATA rechazado: ' . $data_cmd['mensaje']];
    }

    $from_header = $from_name !== ''
        ? '=?UTF-8?B?' . base64_encode($from_name) . "?= <{$remitente_smtp}>"
        : "<{$remitente_smtp}>";
    $to_header = $destinatario_nombre !== ''
        ? '=?UTF-8?B?' . base64_encode($destinatario_nombre) . "?= <{$destinatario_correo}>"
        : "<{$destinatario_correo}>";

    $cabeceras  = "Date: " . date('r') . "\r\n";
    $cabeceras .= "From: {$from_header}\r\n";
    $cabeceras .= "To: {$to_header}\r\n";
    $cabeceras .= "Reply-To: {$remitente_smtp}\r\n";
    $cabeceras .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cabeceras .= "Content-Transfer-Encoding: base64\r\n";
    $cabeceras .= "X-Mailer: TutoresOnLine-PHP/" . phpversion() . "\r\n";

    $cuerpo_codificado = chunk_split(base64_encode($cuerpo_html), 76, "\r\n");
    $mensaje_completo  = $cabeceras . "\r\n" . $cuerpo_codificado . "\r\n.";

    $enviado = smtp_ejecutar_comando($socket, $mensaje_completo, [250]);
    smtp_ejecutar_comando($socket, 'QUIT', [221, 250]);
    fclose($socket);

    if (!$enviado['ok']) {
        return ['exito' => false, 'error' => 'Error al confirmar mensaje SMTP: ' . $enviado['mensaje']];
    }

    return ['exito' => true, 'error' => null];
}

/**
 * Envía un correo electrónico vía SMTP (si está configurado) y registra copia en logs/mail.log
 *
 * @param string $destinatario_correo
 * @param string $destinatario_nombre
 * @param string $asunto
 * @param string $cuerpo_html
 * @param string $enlace_accion Enlace principal para facilitar pruebas en desarrollo
 * @return bool
 */
function enviar_correo($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion = '')
{
    $driver = strtolower((string)env('MAIL_DRIVER', 'smtp'));
    $from_email = env('SMTP_FROM', 'no-reply@tutoresonline.local');
    $from_name  = env('SMTP_FROM_NAME', 'TutoresOnLine');

    if ($driver === 'log') {
        return registrar_correo_log($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion, 'LOG');
    }

    if ($driver === 'smtp') {
        $resultado = enviar_por_smtp_nativo($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $from_email, $from_name);
        if ($resultado['exito']) {
            registrar_correo_log($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion, 'SMTP OK');
            return true;
        }

        // Respaldo automático en mail.log si no hay credenciales SMTP o no hay conexión en entorno local
        error_log("[Mailer SMTP Fallback]: " . ($resultado['error'] ?? 'Error desconocido'));
        return registrar_correo_log($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion, 'SMTP FALLBACK -> LOG (' . ($resultado['error'] ?? 'sin credenciales') . ')');
    }

    // Fallback a mail() nativo si se configuró otro driver
    registrar_correo_log($destinatario_correo, $destinatario_nombre, $asunto, $cuerpo_html, $enlace_accion, 'PHP_MAIL');
    $cabeceras  = "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cabeceras .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
    $cabeceras .= "Reply-To: {$from_email}\r\n";
    $cabeceras .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($destinatario_correo, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $cuerpo_html, $cabeceras);
}

/**
 * Obtiene los datos completos de una sesión para construir notificaciones por correo
 *
 * @param PDO $pdo
 * @param int $sesion_id
 * @return array|false
 */
function obtener_detalle_sesion_correo(PDO $pdo, $sesion_id)
{
    $sql = "SELECT s.id, s.fecha, s.hora_inicio, s.hora_fin, s.modalidad, s.estado, s.enlace_videollamada,
                   m.nombre AS materia_nombre,
                   e.nombre AS estudiante_nombre, e.correo AS estudiante_correo,
                   t.nombre AS tutor_nombre, t.correo AS tutor_correo
            FROM `sesiones` s
            INNER JOIN `materias` m ON m.id = s.materia_id
            INNER JOIN `usuarios` e ON e.id = s.estudiante_id
            INNER JOIN `usuarios` t ON t.id = s.tutor_id
            WHERE s.id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$sesion_id]);
    return $stmt->fetch();
}

/**
 * Notifica por correo al tutor y al estudiante cuando se solicita una nueva tutoría
 *
 * @param PDO $pdo
 * @param int $sesion_id
 * @return void
 */
function notificar_reserva_creada(PDO $pdo, $sesion_id)
{
    try {
        $s = obtener_detalle_sesion_correo($pdo, $sesion_id);
        if (!$s) {
            return;
        }

        $esc = function ($txt) {
            return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
        };

        $fecha_fmt = date('d/m/Y', strtotime($s['fecha']));
        $horario   = substr($s['hora_inicio'], 0, 5) . ' - ' . substr($s['hora_fin'], 0, 5);
        $modalidad = ucfirst($s['modalidad']);
        $enlace_panel = APP_URL . '/panel.php';

        // 1. Correo al Tutor
        $asunto_tutor = "Nueva solicitud de tutoría: {$s['materia_nombre']} (#{$s['id']})";
        $cuerpo_tutor = "<p>Hola <strong>" . $esc($s['tutor_nombre']) . "</strong>,</p>"
            . "<p>El estudiante <strong>" . $esc($s['estudiante_nombre']) . "</strong> ha solicitado una nueva tutoría contigo:</p>"
            . "<ul>"
            . "<li><strong>Materia:</strong> " . $esc($s['materia_nombre']) . "</li>"
            . "<li><strong>Fecha:</strong> " . $esc($fecha_fmt) . "</li>"
            . "<li><strong>Horario:</strong> " . $esc($horario) . "</li>"
            . "<li><strong>Modalidad:</strong> " . $esc($modalidad) . "</li>"
            . "</ul>"
            . "<p>Ingresa a tu panel para aceptar o rechazar la solicitud:</p>"
            . "<p><a href='{$enlace_panel}'>Gestionar solicitud en TutoresOnLine</a></p>";

        enviar_correo($s['tutor_correo'], $s['tutor_nombre'], $asunto_tutor, $cuerpo_tutor, $enlace_panel);

        // 2. Correo de confirmación al Estudiante
        $asunto_est = "Solicitud de tutoría enviada: {$s['materia_nombre']} (#{$s['id']})";
        $cuerpo_est = "<p>Hola <strong>" . $esc($s['estudiante_nombre']) . "</strong>,</p>"
            . "<p>Tu solicitud de tutoría con <strong>" . $esc($s['tutor_nombre']) . "</strong> ha sido registrada exitosamente y está pendiente de aprobación.</p>"
            . "<ul>"
            . "<li><strong>Materia:</strong> " . $esc($s['materia_nombre']) . "</li>"
            . "<li><strong>Fecha:</strong> " . $esc($fecha_fmt) . "</li>"
            . "<li><strong>Horario:</strong> " . $esc($horario) . "</li>"
            . "<li><strong>Modalidad:</strong> " . $esc($modalidad) . "</li>"
            . "</ul>"
            . "<p>Te avisaremos por correo cuando el tutor confirme la clase.</p>"
            . "<p><a href='{$enlace_panel}'>Ver mis tutorías</a></p>";

        enviar_correo($s['estudiante_correo'], $s['estudiante_nombre'], $asunto_est, $cuerpo_est, $enlace_panel);
    } catch (Exception $e) {
        error_log("[Mailer Reserva Error]: " . $e->getMessage());
    }
}

/**
 * Notifica por correo al estudiante cuando el tutor acepta o rechaza una tutoría
 *
 * @param PDO $pdo
 * @param int $sesion_id
 * @param string $nuevo_estado 'aceptada' o 'rechazada'
 * @return void
 */
function notificar_cambio_estado_sesion(PDO $pdo, $sesion_id, $nuevo_estado)
{
    if (!in_array($nuevo_estado, ['aceptada', 'rechazada'], true)) {
        return;
    }

    try {
        $s = obtener_detalle_sesion_correo($pdo, $sesion_id);
        if (!$s) {
            return;
        }

        $esc = function ($txt) {
            return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
        };

        $fecha_fmt = date('d/m/Y', strtotime($s['fecha']));
        $horario   = substr($s['hora_inicio'], 0, 5) . ' - ' . substr($s['hora_fin'], 0, 5);
        $enlace_panel = APP_URL . '/panel.php';

        if ($nuevo_estado === 'aceptada') {
            $enlace_sala = APP_URL . '/videollamada.php?id=' . (int)$s['id'];

            $asunto = "¡Tutoría aceptada! {$s['materia_nombre']} con {$s['tutor_nombre']}";
            $cuerpo = "<p>Hola <strong>" . $esc($s['estudiante_nombre']) . "</strong>,</p>"
                . "<p>¡Buenas noticias! El tutor <strong>" . $esc($s['tutor_nombre']) . "</strong> ha <strong>aceptado</strong> tu solicitud de tutoría.</p>"
                . "<ul>"
                . "<li><strong>Materia:</strong> " . $esc($s['materia_nombre']) . "</li>"
                . "<li><strong>Fecha:</strong> " . $esc($fecha_fmt) . "</li>"
                . "<li><strong>Horario:</strong> " . $esc($horario) . "</li>"
                . "<li><strong>Modalidad:</strong> " . $esc(ucfirst($s['modalidad'])) . "</li>"
                . "<li><strong>Enlace de tu clase virtual:</strong> <a href='" . $esc($enlace_sala) . "'>Ingresar a la sala privada en TutoresOnLine</a></li>"
                . "</ul>"
                . "<p><a href='" . $esc($enlace_sala) . "'>Entrar a la sala de videollamada</a> | <a href='{$enlace_panel}'>Ir a mi panel</a></p>";

            enviar_correo($s['estudiante_correo'], $s['estudiante_nombre'], $asunto, $cuerpo, $enlace_sala);
        } else {
            $asunto = "Actualización de tutoría: {$s['materia_nombre']} (#{$s['id']})";
            $cuerpo = "<p>Hola <strong>" . $esc($s['estudiante_nombre']) . "</strong>,</p>"
                . "<p>El tutor <strong>" . $esc($s['tutor_nombre']) . "</strong> no pudo aceptar tu solicitud de tutoría de <strong>" . $esc($s['materia_nombre']) . "</strong> para el {$fecha_fmt} ({$horario}).</p>"
                . "<p>Puedes buscar otros horarios disponibles u otro tutor en TutoresOnLine:</p>"
                . "<p><a href='" . APP_URL . "/buscar.php'>Buscar otros tutores</a></p>";

            enviar_correo($s['estudiante_correo'], $s['estudiante_nombre'], $asunto, $cuerpo, APP_URL . '/buscar.php');
        }
    } catch (Exception $e) {
        error_log("[Mailer Estado Sesion Error]: " . $e->getMessage());
    }
}
