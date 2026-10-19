<?php
/**
 * =============================================================================
 * TutoresOnLine - Mensajería Interna (Chat en Tiempo Real Simulado con Polling)
 * Interfaz interactiva con Vue 3 CDN, polling cada 2.5s y comunicación cifrada.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Requiere sesión activa
requiere_login();

$usuario_actual = usuario_actual();
$mi_id = (int)$_SESSION['usuario_id'];
$mi_rol = $_SESSION['rol'];
$csrf_token = $_SESSION['csrf_token'] ?? '';

// Si viene con parámetro ?con=ID (iniciar chat directo con alguien)
$contacto_inicial_id = isset($_GET['con']) ? (int)$_GET['con'] : 0;

$titulo_pagina = 'Mensajes y Chat';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div id="chatApp" class="container-fluid px-0" v-cloak>
    <div class="card card-custom border-0 shadow-sm overflow-hidden" style="height: calc(100vh - 170px); min-height: 560px;">
        <div class="row g-0 h-100">

            <!-- ========================================================= -->
            <!-- COLUMNA IZQUIERDA: LISTA DE CONVERSACIONES                -->
            <!-- ========================================================= -->
            <div class="col-md-4 col-lg-3 border-end h-100 d-flex flex-column bg-white">
                <!-- Cabecera de la lista -->
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-chat-dots-fill text-primary"></i> Mensajes
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary p-1 px-2" @click="cargarConversaciones" title="Actualizar lista">
                        <i class="bi bi-arrow-clockwise" :class="{'spin-anim': cargandoConversaciones}"></i>
                    </button>
                </div>

                <!-- Buscador de conversaciones -->
                <div class="p-2 border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar conversación...">
                    </div>
                </div>

                <!-- Lista de conversaciones scrolleable -->
                <div class="flex-grow-1 overflow-auto list-group list-group-flush">
                    <div v-if="cargandoConversaciones && conversaciones.length === 0" class="text-center py-5 text-muted small">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div>Cargando conversaciones...</div>
                    </div>

                    <div v-else-if="conversacionesFiltradas.length === 0" class="text-center py-5 text-muted small px-3">
                        <i class="bi bi-chat-square-text fs-2 d-block mb-2 text-secondary"></i>
                        No tienes conversaciones activas aún.
                        <div class="mt-2" v-if="miRol === 'estudiante'">
                            <a href="<?= APP_URL ?>/buscar.php" class="btn btn-primary-custom btn-sm">Explorar Tutores</a>
                        </div>
                    </div>

                    <!-- Item de conversación -->
                    <button 
                        v-for="c in conversacionesFiltradas" 
                        :key="c.conversacion_id"
                        type="button"
                        class="list-group-item list-group-item-action p-3 border-bottom d-flex align-items-center gap-3 transition-all"
                        :class="{'active-chat': conversacionActiva && conversacionActiva.conversacion_id === c.conversacion_id}"
                        @click="seleccionarConversacion(c)"
                    >
                        <!-- Avatar -->
                        <div class="position-relative flex-shrink-0">
                            <img v-if="c.contacto_avatar" :src="c.contacto_avatar" :alt="c.contacto_nombre" class="icono-circulo" style="width: 46px; height: 46px; object-fit: cover;">
                            <div v-else class="icono-circulo bg-primary-subtle text-primary fw-bold" style="width: 46px; height: 46px; font-size: 1.1rem;">
                                {{ c.contacto_nombre.charAt(0).toUpperCase() }}
                            </div>
                        </div>

                        <!-- Info del contacto y último mensaje -->
                        <div class="flex-grow-1 overflow-hidden text-start">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-truncate text-dark" style="max-width: 140px;">
                                    {{ c.contacto_nombre }}
                                </span>
                                <small class="text-muted" style="font-size: 0.72rem;">{{ c.ultimo_mensaje_fecha }}</small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted text-truncate d-block" style="max-width: 150px; font-size: 0.8rem;">
                                    <span v-if="c.ultimo_emisor_es_mio" class="text-primary me-1"><i class="bi bi-check2"></i></span>
                                    {{ c.ultimo_mensaje }}
                                </small>
                                <span v-if="c.no_leidos > 0" class="badge rounded-pill bg-primary ms-1" style="font-size: 0.7rem;">
                                    {{ c.no_leidos }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- COLUMNA DERECHA: VENTANA DE MENSAJES                      -->
            <!-- ========================================================= -->
            <div class="col-md-8 col-lg-9 h-100 d-flex flex-column bg-light">
                <!-- CASO A: CONVERSACIÓN SELECCIONADA -->
                <template v-if="conversacionActiva">
                    <!-- Cabecera del chat activo -->
                    <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <div class="position-relative">
                                <img v-if="conversacionActiva.contacto_avatar" :src="conversacionActiva.contacto_avatar" :alt="conversacionActiva.contacto_nombre" class="icono-circulo" style="width: 44px; height: 44px; object-fit: cover;">
                                <div v-else class="icono-circulo bg-primary-subtle text-primary fw-bold" style="width: 44px; height: 44px;">
                                    {{ conversacionActiva.contacto_nombre.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ conversacionActiva.contacto_nombre }}</h6>
                                <small class="text-muted">
                                    <span class="badge" :class="conversacionActiva.contacto_rol === 'tutor' ? 'bg-success' : 'bg-primary'" style="font-size: 0.7rem;">
                                        {{ conversacionActiva.contacto_rol === 'tutor' ? 'Tutor' : 'Estudiante' }}
                                    </span>
                                    <span v-if="conversacionActiva.contacto_ciudad" class="ms-1">&bull; {{ conversacionActiva.contacto_ciudad }}</span>
                                </small>
                            </div>
                        </div>

                        <div>
                            <a :href="'<?= APP_URL ?>/perfil.php?id=' + conversacionActiva.contacto_id" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver Perfil">
                                <i class="bi bi-person-badge me-1"></i> Perfil
                            </a>
                        </div>
                    </div>

                    <!-- Contenedor scrolleable de mensajes -->
                    <div class="flex-grow-1 overflow-auto p-3 d-flex flex-column gap-2" id="mensajesBox" ref="mensajesContainer" style="background-color: #f8fafc;">
                        <div v-if="cargandoMensajes && mensajes.length === 0" class="text-center py-5 text-muted small">
                            <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                            <div>Cargando mensajes...</div>
                        </div>

                        <div v-else-if="mensajes.length === 0" class="text-center py-5 text-muted small">
                            <i class="bi bi-chat-heart fs-1 d-block mb-2 text-primary opacity-50"></i>
                            <div>No hay mensajes aún en esta conversación.</div>
                            <div class="small">¡Escribe tu primer mensaje abajo para iniciar!</div>
                        </div>

                        <!-- Burbujas de chat -->
                        <div 
                            v-for="m in mensajes" 
                            :key="m.id" 
                            class="d-flex flex-column"
                            :class="m.es_mio ? 'align-items-end' : 'align-items-start'"
                        >
                            <div 
                                class="p-3 rounded-4 shadow-sm position-relative"
                                :class="m.es_mio ? 'bg-primary text-white rounded-bottom-end-0' : 'bg-white text-dark rounded-bottom-start-0 border'"
                                style="max-width: 75%; word-break: break-word;"
                            >
                                <div style="white-space: pre-line; font-size: 0.95rem;">{{ m.texto }}</div>
                                <div class="text-end mt-1 opacity-75" style="font-size: 0.7rem;">
                                    {{ m.hora }}
                                    <span v-if="m.es_mio" class="ms-1">
                                        <i class="bi" :class="m.leido ? 'bi-check2-all text-info' : 'bi-check2'"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de envío de mensaje -->
                    <div class="p-3 bg-white border-top">
                        <form @submit.prevent="enviarMensaje" class="d-flex gap-2">
                            <textarea 
                                class="form-control" 
                                rows="1" 
                                v-model="nuevoTexto" 
                                placeholder="Escribe un mensaje aquí... (Presiona Enter para enviar)"
                                @keydown.enter.exact.prevent="enviarMensaje"
                                :disabled="enviando"
                                style="resize: none; font-size: 0.95rem;"
                                autofocus
                            ></textarea>
                            <button 
                                type="submit" 
                                class="btn btn-primary-custom px-4 d-flex align-items-center justify-content-center"
                                :disabled="enviando || !nuevoTexto.trim()"
                            >
                                <span v-if="enviando" class="spinner-border spinner-border-sm"></span>
                                <i v-else class="bi bi-send-fill fs-5"></i>
                            </button>
                        </form>
                    </div>
                </template>

                <!-- CASO B: SIN CONVERSACIÓN SELECCIONADA -->
                <template v-else>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center p-4 text-muted">
                        <div class="bg-primary-subtle text-primary icono-circulo mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-chat-dots fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Tus Mensajes de Tutorías</h4>
                        <p class="small text-muted mb-4" style="max-width: 360px;">
                            Selecciona una conversación de la lista a la izquierda para leer y responder preguntas académicas y coordinar tus clases.
                        </p>
                        <div v-if="miRol === 'estudiante'">
                            <a href="<?= APP_URL ?>/buscar.php" class="btn btn-primary-custom btn-sm">
                                <i class="bi bi-search me-1"></i> Buscar Tutores para Chatear
                            </a>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>
</div>

<style>
.active-chat {
    background-color: #eff6ff !important;
    border-left: 4px solid var(--app-primary) !important;
}
.spin-anim {
    animation: rotar 0.8s linear infinite;
}
@keyframes rotar {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
[v-cloak] { display: none; }
</style>

<!-- Vue 3 CDN -->
<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>

<script>
const { createApp, ref, computed, onMounted, onUnmounted, nextTick } = Vue;

createApp({
    setup() {
        const miId = <?= (int)$mi_id ?>;
        const miRol = '<?= e($mi_rol) ?>';
        const csrfToken = '<?= e($csrf_token) ?>';
        const contactoInicialId = <?= (int)$contacto_inicial_id ?>;

        const conversaciones = ref([]);
        const conversacionActiva = ref(null);
        const mensajes = ref([]);
        const nuevoTexto = ref('');
        const busqueda = ref('');
        const cargandoConversaciones = ref(false);
        const cargandoMensajes = ref(false);
        const enviando = ref(false);
        const mensajesContainer = ref(null);

        let pollingInterval = null;

        // Filtrado en vivo de conversaciones
        const conversacionesFiltradas = computed(() => {
            if (!busqueda.value.trim()) return conversaciones.value;
            const q = busqueda.value.toLowerCase();
            return conversaciones.value.filter(c => 
                c.contacto_nombre.toLowerCase().includes(q) || 
                c.ultimo_mensaje.toLowerCase().includes(q)
            );
        });

        // Cargar lista de conversaciones
        const cargarConversaciones = async () => {
            cargandoConversaciones.value = true;
            try {
                const resp = await fetch('<?= APP_URL ?>/api/chat_conversaciones.php');
                const data = await resp.json();
                if (data.exito) {
                    conversaciones.value = data.conversaciones;

                    // Si hay un contacto inicial por URL y aún no se seleccionó
                    if (contactoInicialId > 0 && !conversacionActiva.value) {
                        const encontrada = conversaciones.value.find(c => c.contacto_id === contactoInicialId);
                        if (encontrada) {
                            seleccionarConversacion(encontrada);
                        } else {
                            // Crear la conversación con ese contacto
                            iniciarChatConContacto(contactoInicialId);
                        }
                    }
                }
            } catch (err) {
                console.error('Error al cargar conversaciones:', err);
            } finally {
                cargandoConversaciones.value = false;
            }
        };

        // Iniciar conversación cuando viene con ?con=ID
        const iniciarChatConContacto = async (contactoId) => {
            try {
                const resp = await fetch('<?= APP_URL ?>/api/chat_conversaciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contacto_id: contactoId })
                });
                const data = await resp.json();
                if (data.exito) {
                    await cargarConversaciones();
                    const nueva = conversaciones.value.find(c => c.conversacion_id === data.conversacion_id);
                    if (nueva) {
                        seleccionarConversacion(nueva);
                    }
                }
            } catch (err) {
                console.error('Error al iniciar conversación inicial:', err);
            }
        };

        // Seleccionar una conversación
        const seleccionarConversacion = async (conv) => {
            conversacionActiva.value = conv;
            conv.no_leidos = 0; // Marcar leído localmente
            mensajes.value = [];
            await cargarMensajesIniciales();
        };

        // Scroll al fondo del chat
        const scrollAlFondo = () => {
            nextTick(() => {
                const el = document.getElementById('mensajesBox');
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        };

        // Carga inicial de mensajes de la conversación activa
        const cargarMensajesIniciales = async () => {
            if (!conversacionActiva.value) return;
            cargandoMensajes.value = true;
            try {
                const resp = await fetch(`<?= APP_URL ?>/api/chat_mensajes.php?conversacion_id=${conversacionActiva.value.conversacion_id}&desde_id=0`);
                const data = await resp.json();
                if (data.exito) {
                    mensajes.value = data.mensajes;
                    scrollAlFondo();
                }
            } catch (err) {
                console.error('Error al cargar mensajes:', err);
            } finally {
                cargandoMensajes.value = false;
            }
        };

        // Polling para mensajes nuevos (cada 2.5s)
        const hacerPolling = async () => {
            if (!conversacionActiva.value) return;

            const ultimoId = mensajes.value.length > 0 ? mensajes.value[mensajes.value.length - 1].id : 0;
            try {
                const resp = await fetch(`<?= APP_URL ?>/api/chat_mensajes.php?conversacion_id=${conversacionActiva.value.conversacion_id}&desde_id=${ultimoId}`);
                const data = await resp.json();
                if (data.exito && data.mensajes.length > 0) {
                    mensajes.value.push(...data.mensajes);
                    scrollAlFondo();
                }
            } catch (err) {
                // Silenciar error en polling recurrente
            }
        };

        // Enviar mensaje
        const enviarMensaje = async () => {
            const txt = nuevoTexto.value.trim();
            if (!txt || !conversacionActiva.value || enviando.value) return;

            enviando.value = true;
            try {
                const resp = await fetch('<?= APP_URL ?>/api/chat_enviar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversacion_id: conversacionActiva.value.conversacion_id,
                        texto: txt,
                        csrf_token: csrfToken
                    })
                });

                const data = await resp.json();
                if (data.exito) {
                    mensajes.value.push(data.mensaje);
                    nuevoTexto.value = '';
                    conversacionActiva.value.ultimo_mensaje = txt;
                    conversacionActiva.value.ultimo_mensaje_fecha = data.mensaje.hora;
                    conversacionActiva.value.ultimo_emisor_es_mio = true;
                    scrollAlFondo();
                } else {
                    alert(data.mensaje || 'Error al enviar mensaje');
                }
            } catch (err) {
                console.error('Error al enviar mensaje:', err);
                alert('Ocurrió un error al enviar el mensaje.');
            } finally {
                enviando.value = false;
            }
        };

        onMounted(() => {
            cargarConversaciones();
            pollingInterval = setInterval(hacerPolling, 2500);
        });

        onUnmounted(() => {
            if (pollingInterval) clearInterval(pollingInterval);
        });

        return {
            miId,
            miRol,
            conversaciones,
            conversacionActiva,
            mensajes,
            nuevoTexto,
            busqueda,
            cargandoConversaciones,
            cargandoMensajes,
            enviando,
            mensajesContainer,
            conversacionesFiltradas,
            cargarConversaciones,
            seleccionarConversacion,
            enviarMensaje
        };
    }
}).mount('#chatApp');
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
