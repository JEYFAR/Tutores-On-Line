<?php
/**
 * =============================================================================
 * TutoresOnLine - Sala Privada de Videollamada (Con Expiración y Cierre de Sala)
 * Valida sesión activa, pertenencia estricta a la tutoría (estudiante o tutor),
 * controla la vigencia de la sesión, destruye el token al finalizar la clase
 * e integra la videollamada pasando las credenciales del usuario autenticado.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Exigir sesión iniciada en TutoresOnLine
requiere_login();

$usuario    = usuario_actual();
$usuario_id = (int)$_SESSION['usuario_id'];
$rol        = $usuario['rol'];
$sesion_id  = isset($_GET['id']) ? (int)($_GET['id']) : (int)($_POST['sesion_id'] ?? 0);

if ($sesion_id <= 0) {
    set_flash('danger', 'No se especificó una sesión de tutoría válida.');
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

try {
    $pdo = obtener_conexion();

    // 2. Consultar datos de la sesión y sus participantes autorizados
    $sql = "SELECT s.*, m.nombre AS materia_nombre,
                   e.nombre AS estudiante_nombre, e.correo AS estudiante_correo,
                   t.nombre AS tutor_nombre, t.correo AS tutor_correo
            FROM `sesiones` s
            INNER JOIN `materias` m ON m.id = s.materia_id
            INNER JOIN `usuarios` e ON e.id = s.estudiante_id
            INNER JOIN `usuarios` t ON t.id = s.tutor_id
            WHERE s.id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sesion_id]);
    $sesion = $stmt->fetch();

    if (!$sesion) {
        set_flash('danger', 'La tutoría solicitada no existe.');
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    // 3. Control estricto de autorización: SOLO el estudiante o el tutor de esta clase
    $es_estudiante_autorizado = ((int)$sesion['estudiante_id'] === $usuario_id);
    $es_tutor_autorizado      = ((int)$sesion['tutor_id'] === $usuario_id);

    if (!$es_estudiante_autorizado && !$es_tutor_autorizado) {
        http_response_code(403);
        set_flash('danger', 'Acceso denegado: no tienes permiso para ingresar a esta sala privada.');
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    // Acción POST: Cerrar y finalizar definitivamente la sala (destruye el token de videollamada)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cerrar_sala') {
        if (!verificar_csrf()) {
            set_flash('danger', 'Token de seguridad inválido.');
            header('Location: ' . APP_URL . '/videollamada.php?id=' . $sesion_id);
            exit;
        }

        $stmt_fin = $pdo->prepare("UPDATE `sesiones` SET `estado` = 'finalizada', `enlace_videollamada` = NULL WHERE `id` = ?");
        $stmt_fin->execute([$sesion_id]);

        set_flash('success', 'La clase ha finalizado y la sala de videollamada fue cerrada definitivamente.');
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    // 4. Si la tutoría ya fue finalizada o rechazada, la sala ya no existe
    if ($sesion['estado'] !== 'aceptada') {
        set_flash('info', 'Esta sala de videollamada ya fue cerrada porque la tutoría está ' . $sesion['estado'] . '.');
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    // 5. Asegurar código de sala criptográfico único mientras la sesión esté activa
    $codigo_sala = trim((string)($sesion['enlace_videollamada'] ?? ''));
    if ($codigo_sala === '' || strpos($codigo_sala, 'http://') === 0 || strpos($codigo_sala, 'https://') === 0 || preg_match('/^tutoria-\d+$/', $codigo_sala)) {
        $codigo_sala = 'TutoresOnLine_Sala_' . $sesion_id . '_' . bin2hex(random_bytes(12));
        $stmt_upd = $pdo->prepare("UPDATE `sesiones` SET `enlace_videollamada` = ? WHERE `id` = ?");
        $stmt_upd->execute([$codigo_sala, $sesion_id]);
    }
} catch (Exception $e) {
    error_log('[Videollamada Error]: ' . $e->getMessage());
    set_flash('danger', 'Ocurrió un error al cargar la sala de videollamada.');
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

// Calcular duración programada en minutos (para el temporizador de cierre automático)
$ts_inicio = strtotime($sesion['fecha'] . ' ' . $sesion['hora_inicio']);
$ts_fin    = strtotime($sesion['fecha'] . ' ' . $sesion['hora_fin']);
$duracion_segundos = max(1800, $ts_fin - $ts_inicio); // mínimo 30 min

// Si estamos dentro del horario real de la clase, contar hasta hora_fin; si es prueba fuera de fecha, usar la duración de la clase
$ahora = time();
if ($ahora >= $ts_inicio && $ahora < $ts_fin) {
    $segundos_restantes = $ts_fin - $ahora;
} else {
    $segundos_restantes = $duracion_segundos;
}

$otro_participante_id     = $es_tutor_autorizado ? (int)$sesion['estudiante_id'] : (int)$sesion['tutor_id'];
$otro_participante_nombre = $es_tutor_autorizado ? $sesion['estudiante_nombre'] : $sesion['tutor_nombre'];
$otro_participante_rol    = $es_tutor_autorizado ? 'Estudiante' : 'Profesor';
$etiqueta_mi_rol          = $es_tutor_autorizado ? 'Profesor' : 'Estudiante';
$nombre_mostrar_sala      = $usuario['nombre'] . ' (' . $etiqueta_mi_rol . ')';
$asunto_sala              = 'Tutoría de ' . $sesion['materia_nombre'];

$titulo_pagina = 'Sala de Tutoría: ' . $sesion['materia_nombre'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Barra de cabecera de la Sala Privada -->
<div class="card card-custom border-0 shadow-sm mb-3 p-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="bi bi-camera-video-fill fs-4"></i>
            </div>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark"><?= e($sesion['materia_nombre']) ?></h5>
                    <span class="badge bg-success-subtle text-success border">
                        <i class="bi bi-shield-lock-fill me-1"></i> Sala Privada Temporal
                    </span>
                    <span class="badge bg-dark text-white" id="badgeTemporizador" title="Tiempo restante antes del cierre automático de la sala">
                        <i class="bi bi-hourglass-split me-1"></i> <span id="tiempoRestanteTexto">--:--</span>
                    </span>
                </div>
                <div class="text-muted small mt-1">
                    <span><i class="bi bi-person-check me-1"></i><?= e($otro_participante_rol) ?>: <strong><?= e($otro_participante_nombre) ?></strong></span>
                    <span class="mx-2">&bull;</span>
                    <span><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($sesion['fecha'])) ?> (<?= substr($sesion['hora_inicio'], 0, 5) ?> - <?= substr($sesion['hora_fin'], 0, 5) ?>)</span>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= APP_URL ?>/chat.php?con=<?= $otro_participante_id ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-chat-dots me-1"></i> Chat con <?= e($otro_participante_rol) ?>
            </a>

            <form action="<?= APP_URL ?>/videollamada.php?id=<?= (int)$sesion['id'] ?>" method="POST" id="formCerrarSala" class="d-inline" onsubmit="return confirm('¿Deseas finalizar la clase y cerrar definitivamente esta sala de videollamada?');">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="cerrar_sala">
                <input type="hidden" name="sesion_id" value="<?= (int)$sesion['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bi bi-telephone-x-fill me-1"></i> Finalizar y Cerrar Sala
                </button>
            </form>

            <a href="<?= APP_URL ?>/panel.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Volver al Panel
            </a>
        </div>
    </div>
</div>

<!-- Contenedor Embebido de la Videoconferencia -->
<div class="card card-custom border-0 shadow-sm overflow-hidden mb-3 position-relative" style="background-color: #0f172a; height: 640px;">
    <div id="loaderVideollamada"
         class="text-white text-center d-flex flex-column align-items-center justify-content-center"
         style="position: absolute; inset: 0; background: #0f172a; z-index: 2;">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div class="fw-semibold">Preparando tu sala privada como <?= e($nombre_mostrar_sala) ?>...</div>
        <small class="text-white-50 mt-1">La sala se cerrará automáticamente al finalizar la sesión.</small>
    </div>
    <div id="contenedorVideollamada" style="width: 100%; height: 100%;"></div>
</div>

<script src="https://meet.jit.si/external_api.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const loader = document.getElementById('loaderVideollamada');
    const container = document.getElementById('contenedorVideollamada');
    const timerTxt = document.getElementById('tiempoRestanteTexto');
    const badgeTimer = document.getElementById('badgeTemporizador');
    const formCerrar = document.getElementById('formCerrarSala');

    // 1. Temporizador de vigencia de la sala (cierra automáticamente al terminar el tiempo de la tutoría)
    let restantes = <?= (int)$segundos_restantes ?>;

    function formatearTiempo(seg) {
        const h = Math.floor(seg / 3600);
        const m = Math.floor((seg % 3600) / 60);
        const s = seg % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? (h + ':' + mm + ':' + ss) : (mm + ':' + ss);
    }

    if (timerTxt) {
        timerTxt.textContent = formatearTiempo(restantes);
    }

    const intervalo = setInterval(function () {
        restantes--;
        if (timerTxt) {
            timerTxt.textContent = formatearTiempo(Math.max(0, restantes));
        }
        if (restantes === 300 && badgeTimer) {
            badgeTimer.classList.remove('bg-dark');
            badgeTimer.classList.add('bg-warning', 'text-dark');
        }
        if (restantes <= 0) {
            clearInterval(intervalo);
            if (formCerrar) {
                formCerrar.onsubmit = null;
                formCerrar.submit();
            }
        }
    }, 1000);

    // 2. Ocultar el overlay de carga apenas cargue el iframe
    function ocultarLoader() {
        if (loader && !loader.classList.contains('d-none')) {
            loader.classList.add('d-none');
        }
    }
    setTimeout(ocultarLoader, 1500);

    if (typeof JitsiMeetExternalAPI === 'undefined') {
        ocultarLoader();
        container.innerHTML = '<div class="p-4 text-center text-white"><p class="mb-2">No se pudo inicializar el reproductor de videollamada.</p><a href="https://meet.jit.si/' + encodeURIComponent(<?= json_encode($codigo_sala) ?>) + '" target="_blank" class="btn btn-primary btn-sm">Abrir videollamada directa</a></div>';
        return;
    }

    const domain = 'meet.jit.si';
    const options = {
        roomName: <?= json_encode($codigo_sala) ?>,
        parentNode: container,
        width: '100%',
        height: '100%',
        lang: 'es',
        userInfo: {
            displayName: <?= json_encode($nombre_mostrar_sala) ?>,
            email: <?= json_encode($usuario['correo']) ?>
        },
        configOverwrite: {
            defaultLanguage: 'es',
            subject: <?= json_encode($asunto_sala) ?>,
            startWithAudioMuted: false,
            startWithVideoMuted: false,
            disableDeepLinking: true
        },
        interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            DEFAULT_REMOTE_DISPLAY_NAME: <?= json_encode($otro_participante_nombre) ?>
        }
    };

    const api = new JitsiMeetExternalAPI(domain, options);

    const iframe = api.getIFrame();
    if (iframe) {
        iframe.style.border = '0';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.addEventListener('load', ocultarLoader);
    }

    api.addEventListener('videoConferenceJoined', function () {
        ocultarLoader();
        api.executeCommand('subject', <?= json_encode($asunto_sala) ?>);
    });

    // Al colgar la llamada desde dentro del control de video, preguntar si desea cerrar la sala definitivamente
    api.addEventListener('readyToClose', function () {
        if (confirm('Has salido de la videollamada. ¿Deseas dar por finalizada la tutoría y destruir esta sala?')) {
            if (formCerrar) {
                formCerrar.onsubmit = null;
                formCerrar.submit();
                return;
            }
        }
        window.location.href = '<?= APP_URL ?>/panel.php';
    });
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
