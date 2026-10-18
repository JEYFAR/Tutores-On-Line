<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo de Envío de Correos Electrónicos
 * Soporta modo 'log' para desarrollo local sin internet/SMTP (guarda en logs/mail.log)
 * y modo 'smtp' para envío real.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Envía un correo electrónico o lo registra en logs según MAIL_DRIVER
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
    $driver = env('MAIL_DRIVER', 'log');

    // MODO LOG: Ideal para pruebas locales en USBWebserver
    if ($driver === 'log') {
        $archivo_log = LOGS_PATH . DIRECTORY_SEPARATOR . 'mail.log';
        $fecha = date('Y-m-d H:i:s');

        $contenido = "=============================================================================\n";
        $contenido .= "FECHA: {$fecha}\n";
        $contenido .= "PARA: {$destinatario_nombre} <{$destinatario_correo}>\n";
        $contenido .= "ASUNTO: {$asunto}\n";
        if (!empty($enlace_accion)) {
            $contenido .= "ENLACE DIRECTO DE ACCIÓN: {$enlace_accion}\n";
        }
        $contenido .= "MENSAJE:\n" . strip_tags($cuerpo_html) . "\n";
        $contenido .= "=============================================================================\n\n";

        return (bool)file_put_contents($archivo_log, $contenido, FILE_APPEND | LOCK_EX);
    }

    // MODO SMTP / NATIVO mail()
    $from_email = env('SMTP_FROM', 'no-reply@tutoresonline.local');
    $from_name  = env('SMTP_FROM_NAME', 'TutoresOnLine');

    $cabeceras  = "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cabeceras .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
    $cabeceras .= "Reply-To: {$from_email}\r\n";
    $cabeceras .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($destinatario_correo, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $cuerpo_html, $cabeceras);
}
