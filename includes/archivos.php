<?php
/**
 * =============================================================================
 * TutoresOnLine - Módulo de Gestión Segura de Archivos
 * Validación de extensiones, tipos MIME reales (finfo), límites de tamaño,
 * nombres aleatorios y operaciones en la base de datos (tabla archivos).
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

/**
 * Detecta el tipo MIME real inspeccionando el contenido binario.
 * Utiliza finfo si está disponible en PHP, y aplica fallbacks múltiples
 * (mime_content_type, getimagesize y Magic Bytes de cabecera binaria)
 * para garantizar compatibilidad universal sin fallar si la extensión fileinfo está inactiva.
 *
 * @param string $ruta_temporal
 * @param string $extension
 * @return string|null
 */
function detectar_mime_seguro($ruta_temporal, $extension = '')
{
    // 1. Método finfo (si la extensión fileinfo está habilitada en PHP)
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $ruta_temporal);
            @finfo_close($finfo);
            if (!empty($mime) && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }
    }

    // 2. Método mime_content_type
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($ruta_temporal);
        if (!empty($mime) && $mime !== 'application/octet-stream') {
            return $mime;
        }
    }

    // 3. Método getimagesize para imágenes
    $ext = strtolower($extension);
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && function_exists('getimagesize')) {
        $info = @getimagesize($ruta_temporal);
        if ($info && !empty($info['mime'])) {
            return $info['mime'];
        }
    }

    // 4. Fallback 100% nativo por Magic Bytes (cabecera binaria directa)
    $fp = @fopen($ruta_temporal, 'rb');
    if ($fp) {
        $bytes = @fread($fp, 8);
        @fclose($fp);

        if ($bytes !== false && strlen($bytes) >= 4) {
            // PDF: comienza con %PDF
            if (substr($bytes, 0, 4) === '%PDF') {
                return 'application/pdf';
            }
            // PNG: comienza con \x89PNG
            if (substr($bytes, 0, 4) === "\x89PNG") {
                return 'image/png';
            }
            // JPEG: comienza con \xFF\xD8
            if (substr($bytes, 0, 2) === "\xFF\xD8") {
                return 'image/jpeg';
            }
        }
    }

    return null;
}

/**
 * Valida y guarda un archivo subido por un usuario
 *
 * @param array $archivo Array proveniente de $_FILES['nombre_campo']
 * @param int $usuario_id ID del usuario que sube el archivo
 * @param string $tipo 'foto' o 'hoja_vida'
 * @return array ['exito' => bool, 'mensaje' => string, 'archivo_id' => int|null]
 */
function guardar_archivo_subido($archivo, $usuario_id, $tipo)
{
    // 1. Validar errores de subida nativos de PHP
    if (!isset($archivo['error']) || is_array($archivo['error'])) {
        return ['exito' => false, 'mensaje' => 'Parámetros de subida inválidos.'];
    }

    switch ($archivo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['exito' => false, 'mensaje' => 'El archivo supera el tamaño máximo permitido.'];
        case UPLOAD_ERR_NO_FILE:
            return ['exito' => false, 'mensaje' => 'No seleccionaste ningún archivo para subir.'];
        default:
            return ['exito' => false, 'mensaje' => 'Error al subir el archivo al servidor temporal.'];
    }

    $nombre_original = basename($archivo['name']);
    $tamano_bytes = (int)$archivo['size'];
    $ruta_temporal = $archivo['tmp_name'];

    // 2. Reglas por tipo de archivo
    if ($tipo === 'foto') {
        $tamano_maximo = 2 * 1024 * 1024; // 2 MB
        $extensiones_permitidas = ['jpg', 'jpeg', 'png'];
        $mimes_permitidos = ['image/jpeg', 'image/png'];
    } elseif ($tipo === 'hoja_vida') {
        $tamano_maximo = 5 * 1024 * 1024; // 5 MB
        $extensiones_permitidas = ['pdf'];
        $mimes_permitidos = ['application/pdf'];
    } else {
        return ['exito' => false, 'mensaje' => 'Tipo de archivo no reconocido.'];
    }

    // 3. Validar tamaño
    if ($tamano_bytes > $tamano_maximo) {
        $limite_mb = $tamano_maximo / (1024 * 1024);
        return ['exito' => false, 'mensaje' => "El archivo supera el límite máximo de {$limite_mb} MB."];
    }

    // 4. Validar extensión por lista blanca
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensiones_permitidas, true)) {
        return ['exito' => false, 'mensaje' => 'Extensión no permitida. Formatos válidos: ' . implode(', ', $extensiones_permitidas)];
    }

    // 5. Validar tipo MIME REAL inspeccionando el contenido binario
    $mime_real = detectar_mime_seguro($ruta_temporal, $extension);

    if (empty($mime_real) || !in_array($mime_real, $mimes_permitidos, true)) {
        return ['exito' => false, 'mensaje' => 'El contenido real del archivo no corresponde a un formato seguro válido.'];
    }

    // 6. Generar nombre de archivo aleatorio e impredecible
    $nombre_guardado = bin2hex(random_bytes(16)) . '.' . $extension;

    // Asegurar directorio destino
    if (!is_dir(UPLOADS_PATH)) {
        @mkdir(UPLOADS_PATH, 0755, true);
    }

    $ruta_destino = UPLOADS_PATH . DIRECTORY_SEPARATOR . $nombre_guardado;

    // 7. Mover archivo seguro
    if (!move_uploaded_file($ruta_temporal, $ruta_destino)) {
        return ['exito' => false, 'mensaje' => 'No se pudo mover el archivo al directorio de destino.'];
    }

    // 8. Registrar en la base de datos (tabla archivos)
    try {
        $pdo = obtener_conexion();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO `archivos` (`usuario_id`, `tipo`, `nombre_original`, `nombre_guardado`, `mime`, `tamano`, `subido_en`)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$usuario_id, $tipo, $nombre_original, $nombre_guardado, $mime_real, $tamano_bytes]);
        $archivo_id = (int)$pdo->lastInsertId();

        // Si es foto, actualizar campo foto en la tabla usuarios
        if ($tipo === 'foto') {
            $stmt_user = $pdo->prepare("UPDATE `usuarios` SET `foto` = ? WHERE `id` = ?");
            $stmt_user->execute([$nombre_guardado, $usuario_id]);
        }

        $pdo->commit();
        return ['exito' => true, 'mensaje' => 'Archivo subido y registrado correctamente.', 'archivo_id' => $archivo_id, 'nombre_guardado' => $nombre_guardado];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($ruta_destino); // Eliminar del disco si falla la BD
        error_log("[Archivos Guardar Error]: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => 'Ocurrió un error al registrar el archivo en la base de datos.'];
    }
}

/**
 * Obtiene la lista de archivos registrados para un usuario
 *
 * @param int $usuario_id
 * @return array
 */
function listar_archivos_usuario($usuario_id)
{
    try {
        $pdo = obtener_conexion();
        $stmt = $pdo->prepare("SELECT * FROM `archivos` WHERE `usuario_id` = ? ORDER BY `subido_en` DESC");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("[Archivos Listar Error]: " . $e->getMessage());
        return [];
    }
}

/**
 * Elimina un archivo físico del disco y su registro en la BD (solo permitido para el dueño)
 *
 * @param int $archivo_id
 * @param int $usuario_id
 * @return bool
 */
function eliminar_archivo_usuario($archivo_id, $usuario_id)
{
    try {
        $pdo = obtener_conexion();

        // Verificar pertenencia del archivo
        $stmt = $pdo->prepare("SELECT * FROM `archivos` WHERE `id` = ? AND `usuario_id` = ? LIMIT 1");
        $stmt->execute([$archivo_id, $usuario_id]);
        $archivo = $stmt->fetch();

        if (!$archivo) {
            return false;
        }

        $pdo->beginTransaction();

        // Borrar de la base de datos
        $stmt_del = $pdo->prepare("DELETE FROM `archivos` WHERE `id` = ?");
        $stmt_del->execute([$archivo_id]);

        // Si era la foto de perfil actual del usuario, limpiarla
        if ($archivo['tipo'] === 'foto') {
            $stmt_foto = $pdo->prepare("UPDATE `usuarios` SET `foto` = NULL WHERE `id` = ? AND `foto` = ?");
            $stmt_foto->execute([$usuario_id, $archivo['nombre_guardado']]);
        }

        $pdo->commit();

        // Borrar del disco
        $ruta_fisica = UPLOADS_PATH . DIRECTORY_SEPARATOR . $archivo['nombre_guardado'];
        if (file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }

        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[Archivos Eliminar Error]: " . $e->getMessage());
        return false;
    }
}
