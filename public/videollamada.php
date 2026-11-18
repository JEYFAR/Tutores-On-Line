<?php
/**
 * =============================================================================
 * TutoresOnLine - Sala Privada de Videollamada (Sincronización en Tiempo Real)
 * - Valida sesión activa y pertenencia estricta (solo estudiante o tutor).
 * - Sincroniza los Peer IDs dinámicos en cada recarga mediante latido en BD
 *   (evita bloqueo de ID ocupado al actualizar la página).
 * - Incluye fallback automático de cámara en Canvas si se prueba Estudiante y
 *   Profesor en el mismo computador con una sola cámara web.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Exigir sesión iniciada en TutoresOnLine
requiere_login();

$usuario    = usuario_actual();
$usuario_id = (int)$_SESSION['usuario_id'];
$rol        = $usuario['rol'];
$sesion_id  = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['sesion_id'] ?? 0);

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

    // =========================================================================
    // ENDPOINT INTERNO DE SINCRONIZACIÓN DE SALA (LATIDO EN TIEMPO REAL)
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion_sala'] ?? '') === 'latido') {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');

        if ($sesion['estado'] !== 'aceptada') {
            echo json_encode(['exito' => false, 'cerrada' => true]);
            exit;
        }

        $mi_peer = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['peer_id'] ?? ''));
        $raw_estado = (string)($sesion['enlace_videollamada'] ?? '');
        $estado_sala = json_decode($raw_estado, true);
        if (!is_array($estado_sala)) {
            $estado_sala = ['t' => '', 'e' => '', 'tt' => 0, 'et' => 0];
        }

        $ahora_ts = time();
        if ($es_tutor_autorizado) {
            if ($mi_peer !== '') {
                $estado_sala['t'] = $mi_peer;
            }
            $estado_sala['tt'] = $ahora_ts;
        } else {
            if ($mi_peer !== '') {
                $estado_sala['e'] = $mi_peer;
            }
            $estado_sala['et'] = $ahora_ts;
        }

        $nuevo_json = json_encode($estado_sala);
        $stmt_lat = $pdo->prepare("UPDATE `sesiones` SET `enlace_videollamada` = ? WHERE `id` = ?");
        $stmt_lat->execute([$nuevo_json, $sesion_id]);

        $peer_remoto = $es_tutor_autorizado ? ($estado_sala['e'] ?? '') : ($estado_sala['t'] ?? '');
        $ts_remoto   = $es_tutor_autorizado ? (int)($estado_sala['et'] ?? 0) : (int)($estado_sala['tt'] ?? 0);
        $remoto_en_linea = ($peer_remoto !== '' && ($ahora_ts - $ts_remoto) <= 15);

        echo json_encode([
            'exito'           => true,
            'cerrada'         => false,
            'remoto_peer_id'  => $remoto_en_linea ? $peer_remoto : '',
            'remoto_en_linea' => $remoto_en_linea,
        ]);
        exit;
    }

    // Acción POST: Cerrar y finalizar definitivamente la sala
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
} catch (Exception $e) {
    error_log('[Videollamada Error]: ' . $e->getMessage());
    set_flash('danger', 'Ocurrió un error al cargar la sala de videollamada.');
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

// Calcular duración programada para el temporizador de cierre automático
$ts_inicio = strtotime($sesion['fecha'] . ' ' . $sesion['hora_inicio']);
$ts_fin    = strtotime($sesion['fecha'] . ' ' . $sesion['hora_fin']);
$duracion_segundos = max(1800, $ts_fin - $ts_inicio);

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

// Generar un Peer ID único en cada carga de página para que actualizar (F5) nunca bloquee la sesión
$mi_peer_id_unico = 'tol_' . $sesion_id . '_' . ($es_tutor_autorizado ? 'tut' : 'est') . '_' . bin2hex(random_bytes(5));

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
                    <span class="badge bg-success-subtle text-success border" id="estadoConexionBadge">
                        <i class="bi bi-shield-lock-fill me-1"></i> Sala Privada Verificada
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
            <a href="<?= APP_URL ?>/mensajes.php?con=<?= $otro_participante_id ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-chat-dots me-1"></i> Mensajes con <?= e($otro_participante_rol) ?>
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

<!-- Escenario Principal de Videollamada Nativa -->
<div class="card card-custom border-0 shadow-lg overflow-hidden mb-3 position-relative" id="escenarioVideo" style="background: #0f172a; height: 620px; border-radius: 16px;">

    <!-- Video Remoto (El otro participante) -->
    <video id="videoRemoto" autoplay playsinline class="w-100 h-100 d-none" style="object-fit: cover; background: #0f172a;"></video>

    <!-- Estado mientras se conecta el otro participante -->
    <div id="placeholderRemoto" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white text-center p-4">
        <div class="rounded-circle bg-primary bg-opacity-25 border border-primary border-opacity-50 d-flex align-items-center justify-content-center fw-bold mb-3 shadow" style="width: 96px; height: 96px; font-size: 2.2rem;">
            <?= strtoupper(substr($otro_participante_nombre, 0, 1)) ?>
        </div>
        <h5 class="fw-bold mb-1"><?= e($otro_participante_nombre) ?> (<?= e($otro_participante_rol) ?>)</h5>
        <p class="text-white-50 small mb-3" id="textoEstadoRemoto">
            <span class="spinner-grow spinner-grow-sm text-success me-1" role="status"></span>
            Sincronizando sala con <?= e($otro_participante_nombre) ?>...
        </p>
        <div class="badge bg-white bg-opacity-10 text-white fw-normal px-3 py-2 border border-light border-opacity-10">
            <i class="bi bi-check-circle-fill text-success me-1"></i> Conectado como <strong><?= e($nombre_mostrar_sala) ?></strong>
        </div>
    </div>

    <!-- Etiqueta flotante del participante remoto -->
    <div id="etiquetaRemoto" class="position-absolute top-0 start-0 m-3 px-3 py-1 rounded-pill bg-dark bg-opacity-75 text-white small d-none" style="z-index: 5;">
        <i class="bi bi-person-video2 text-success me-1"></i> <?= e($otro_participante_nombre) ?> (<?= e($otro_participante_rol) ?>) &bull; En línea
    </div>

    <!-- Mi Video Local (Picture-in-Picture esquina inferior derecha) -->
    <div class="position-absolute shadow-lg overflow-hidden border border-2 border-light border-opacity-25"
         style="bottom: 84px; right: 20px; width: 230px; height: 150px; border-radius: 12px; background: #1e293b; z-index: 5;">
        <video id="videoLocal" autoplay muted playsinline class="w-100 h-100" style="object-fit: cover; transform: scaleX(-1);"></video>
        <div class="position-absolute bottom-0 start-0 end-0 px-2 py-1 bg-dark bg-opacity-75 text-white d-flex justify-content-between align-items-center" style="font-size: 0.72rem;">
            <span class="text-truncate"><?= e($usuario['nombre']) ?> (Tú)</span>
            <span id="iconosEstadoLocal"><i class="bi bi-mic-fill text-success"></i></span>
        </div>
    </div>

    <!-- Barra de Controles de Videollamada (abajo al centro) -->
    <div class="position-absolute bottom-0 start-0 end-0 p-3 d-flex justify-content-center align-items-center gap-3"
         style="background: linear-gradient(to top, rgba(15,23,42,0.92), rgba(15,23,42,0)); z-index: 6;">
        <button type="button" id="btnToggleMic" onclick="alternarMicrofono()" class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;" title="Silenciar / Activar micrófono">
            <i class="bi bi-mic-fill fs-5 text-dark" id="iconoMic"></i>
        </button>

        <button type="button" id="btnToggleCam" onclick="alternarCamara()" class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;" title="Apagar / Encender cámara">
            <i class="bi bi-camera-video-fill fs-5 text-dark" id="iconoCam"></i>
        </button>

        <button type="button" id="btnCompartirPantalla" onclick="compartirPantalla()" class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;" title="Compartir pantalla">
            <i class="bi bi-display fs-5 text-dark" id="iconoPantalla"></i>
        </button>

        <button type="button" onclick="pantallaCompleta()" class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;" title="Pantalla completa">
            <i class="bi bi-arrows-fullscreen fs-5 text-dark"></i>
        </button>

        <button type="button" onclick="colgarLlamada()" class="btn btn-danger rounded-circle shadow d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;" title="Salir o finalizar clase">
            <i class="bi bi-telephone-x-fill fs-5 text-white"></i>
        </button>
    </div>
</div>

<!-- PeerJS WebRTC CDN -->
<script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
<script>
let streamLocal = null;
let peer = null;
let llamadaActual = null;
let ultimoPeerRemotoConectado = '';
let micActivo = true;
let camActiva = true;

const miPeerId = <?= json_encode($mi_peer_id_unico) ?>;
const miNombre = <?= json_encode($usuario['nombre']) ?>;
const miRolTexto = <?= json_encode($etiqueta_mi_rol) ?>;
const sesionId = <?= (int)$sesion_id ?>;

/**
 * Crea un stream de video en Canvas + pista de audio cuando la cámara física
 * está ocupada por otra ventana en el mismo computador.
 */
function crearStreamAvatarFallback(pistaAudioExistente) {
    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 360;
    const ctx = canvas.getContext('2d');

    function dibujarFrame() {
        ctx.fillStyle = '#1e293b';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#2563eb';
        ctx.beginPath();
        ctx.arc(320, 145, 58, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText((miNombre || 'U').charAt(0).toUpperCase(), 320, 145);

        ctx.font = 'bold 22px sans-serif';
        ctx.fillText(miNombre + ' (' + miRolTexto + ')', 320, 245);

        ctx.fillStyle = '#94a3b8';
        ctx.font = '15px sans-serif';
        ctx.fillText('En línea en la sala', 320, 278);
    }

    dibujarFrame();
    setInterval(dibujarFrame, 1000);

    const canvasStream = canvas.captureStream(15);
    if (pistaAudioExistente) {
        canvasStream.addTrack(pistaAudioExistente);
    } else {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const dest = audioCtx.createMediaStreamDestination();
            const trackSilencio = dest.stream.getAudioTracks()[0];
            if (trackSilencio) canvasStream.addTrack(trackSilencio);
        } catch (e) {}
    }
    return canvasStream;
}

document.addEventListener('DOMContentLoaded', async function () {
    const timerTxt = document.getElementById('tiempoRestanteTexto');
    const badgeTimer = document.getElementById('badgeTemporizador');
    const formCerrar = document.getElementById('formCerrarSala');

    // 1. Temporizador regresivo de la sesión
    let restantes = <?= (int)$segundos_restantes ?>;
    function formatearTiempo(seg) {
        const h = Math.floor(seg / 3600);
        const m = Math.floor((seg % 3600) / 60);
        const s = seg % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? (h + ':' + mm + ':' + ss) : (mm + ':' + ss);
    }
    if (timerTxt) timerTxt.textContent = formatearTiempo(restantes);

    const intervalo = setInterval(function () {
        restantes--;
        if (timerTxt) timerTxt.textContent = formatearTiempo(Math.max(0, restantes));
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

    // 2. Obtener cámara y micrófono (con fallback automático a audio + avatar si la cámara está ocupada en el mismo PC)
    const videoLocal = document.getElementById('videoLocal');
    try {
        streamLocal = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    } catch (errCam) {
        let pistaAudio = null;
        try {
            const soloAudio = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
            pistaAudio = soloAudio.getAudioTracks()[0] || null;
        } catch (errAud) {}
        streamLocal = crearStreamAvatarFallback(pistaAudio);
        if (videoLocal) videoLocal.style.transform = 'none';
    }

    if (videoLocal && streamLocal) {
        videoLocal.srcObject = streamLocal;
    }

    // 3. Iniciar PeerJS y sincronización por latido con la base de datos
    iniciarPeerWebRTC();
});

function iniciarPeerWebRTC() {
    if (typeof Peer === 'undefined') return;

    peer = new Peer(miPeerId, {
        config: {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        }
    });

    peer.on('open', function () {
        sincronizarSalaLatido();
        setInterval(sincronizarSalaLatido, 2500);
    });

    peer.on('call', function (call) {
        call.answer(streamLocal);
        configurarEventosLlamada(call);
    });
}

async function sincronizarSalaLatido() {
    try {
        const form = new URLSearchParams();
        form.append('accion_sala', 'latido');
        form.append('sesion_id', String(sesionId));
        form.append('peer_id', miPeerId);

        const resp = await fetch('videollamada.php?id=' + sesionId, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        });
        const data = await resp.json();

        if (data && data.cerrada) {
            window.location.href = '<?= APP_URL ?>/panel.php';
            return;
        }

        if (data && data.exito && data.remoto_en_linea && data.remoto_peer_id) {
            const peerRemoto = data.remoto_peer_id;
            const videoRemoto = document.getElementById('videoRemoto');
            const sinVideoActivo = !videoRemoto || videoRemoto.classList.contains('d-none') || !videoRemoto.srcObject;

            // Si el otro participante entró o recargó su página (nuevo peer_id), conectar de inmediato
            if (peerRemoto !== ultimoPeerRemotoConectado || sinVideoActivo) {
                ultimoPeerRemotoConectado = peerRemoto;
                const call = peer.call(peerRemoto, streamLocal);
                if (call) {
                    configurarEventosLlamada(call);
                }
            }
        }
    } catch (e) {}
}

function configurarEventosLlamada(call) {
    llamadaActual = call;

    call.on('stream', function (remoteStream) {
        const videoRemoto = document.getElementById('videoRemoto');
        const placeholder = document.getElementById('placeholderRemoto');
        const etiqueta = document.getElementById('etiquetaRemoto');
        const badge = document.getElementById('estadoConexionBadge');

        if (videoRemoto) {
            videoRemoto.srcObject = remoteStream;
            videoRemoto.classList.remove('d-none');
            videoRemoto.play().catch(function () {});
        }
        if (placeholder) placeholder.classList.add('d-none');
        if (etiqueta) etiqueta.classList.remove('d-none');
        if (badge) {
            badge.innerHTML = '<i class="bi bi-broadcast me-1"></i> En Vivo con <?= e($otro_participante_rol) ?>';
        }
    });

    call.on('close', function () {
        mostrarPlaceholderEspera();
    });
}

function mostrarPlaceholderEspera() {
    const videoRemoto = document.getElementById('videoRemoto');
    const placeholder = document.getElementById('placeholderRemoto');
    const etiqueta = document.getElementById('etiquetaRemoto');
    if (videoRemoto) videoRemoto.classList.add('d-none');
    if (placeholder) placeholder.classList.remove('d-none');
    if (etiqueta) etiqueta.classList.add('d-none');
}

function alternarMicrofono() {
    if (!streamLocal) return;
    const audioTracks = streamLocal.getAudioTracks();
    if (audioTracks.length === 0) return;

    micActivo = !micActivo;
    audioTracks[0].enabled = micActivo;

    const btn = document.getElementById('btnToggleMic');
    const icono = document.getElementById('iconoMic');
    if (micActivo) {
        btn.className = 'btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center';
        icono.className = 'bi bi-mic-fill fs-5 text-dark';
    } else {
        btn.className = 'btn btn-danger rounded-circle shadow d-flex align-items-center justify-content-center';
        icono.className = 'bi bi-mic-mute-fill fs-5 text-white';
    }
}

function alternarCamara() {
    if (!streamLocal) return;
    const videoTracks = streamLocal.getVideoTracks();
    if (videoTracks.length === 0) return;

    camActiva = !camActiva;
    videoTracks[0].enabled = camActiva;

    const btn = document.getElementById('btnToggleCam');
    const icono = document.getElementById('iconoCam');
    if (camActiva) {
        btn.className = 'btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center';
        icono.className = 'bi bi-camera-video-fill fs-5 text-dark';
    } else {
        btn.className = 'btn btn-danger rounded-circle shadow d-flex align-items-center justify-content-center';
        icono.className = 'bi bi-camera-video-off-fill fs-5 text-white';
    }
}

async function compartirPantalla() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) return;
    try {
        const displayStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const screenTrack = displayStream.getVideoTracks()[0];
        const videoLocal = document.getElementById('videoLocal');
        if (videoLocal) {
            videoLocal.style.transform = 'none';
            videoLocal.srcObject = displayStream;
        }

        if (llamadaActual && llamadaActual.peerConnection) {
            const sender = llamadaActual.peerConnection.getSenders().find(function (s) {
                return s.track && s.track.kind === 'video';
            });
            if (sender) sender.replaceTrack(screenTrack);
        }

        screenTrack.onended = function () {
            if (streamLocal && videoLocal) {
                videoLocal.srcObject = streamLocal;
                const camTrack = streamLocal.getVideoTracks()[0];
                if (camTrack && llamadaActual && llamadaActual.peerConnection) {
                    const sender = llamadaActual.peerConnection.getSenders().find(function (s) {
                        return s.track && s.track.kind === 'video';
                    });
                    if (sender) sender.replaceTrack(camTrack);
                }
            }
        };
    } catch (e) {}
}

function pantallaCompleta() {
    const esc = document.getElementById('escenarioVideo');
    if (!esc) return;
    if (!document.fullscreenElement) {
        if (esc.requestFullscreen) esc.requestFullscreen();
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
    }
}

function colgarLlamada() {
    const formCerrar = document.getElementById('formCerrarSala');
    if (confirm('¿Deseas finalizar definitivamente la tutoría y cerrar la sala? (Pulsa Cancelar si solo quieres volver al panel sin cerrar la sala)')) {
        if (formCerrar) {
            formCerrar.onsubmit = null;
            formCerrar.submit();
            return;
        }
    }
    window.location.href = '<?= APP_URL ?>/panel.php';
}
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
