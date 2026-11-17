<?php
/**
 * =============================================================================
 * TutoresOnLine - Pie de Página Común (Footer)
 * Cierra contenedor principal, pie de página, Bootstrap JS y scripts extra.
 * Compatible con PHP 7.4+
 * =============================================================================
 */
?>
</main>

<footer class="footer-custom mt-auto">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-md-6 text-md-start mb-2 mb-md-0">
                <span class="fw-bold text-dark">TutoresOnLine</span> &copy; <?= date('Y') ?> &mdash; La red de tutorías académicas personalizadas.
            </div>
            <div class="col-md-6 text-md-end">
                <ul class="list-inline mb-0 small">
                    <li class="list-inline-item"><a href="<?= APP_URL ?>/buscar.php" class="text-secondary text-decoration-none">Buscar tutores</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><a href="<?= APP_URL ?>/registro.php?rol=tutor" class="text-secondary text-decoration-none">Ser tutor</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><span class="text-secondary">Términos y Privacidad</span></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS CDN (con Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Scripts adicionales específicos de la vista -->
<?php if (!empty($scripts_extra)): ?>
    <?= $scripts_extra ?>
<?php endif; ?>

<?php if (!empty($_SESSION['usuario_id'])): ?>
    <?php
    $chat_ia_csrf = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? '');
    $chat_ia_uid  = (int)$_SESSION['usuario_id'];
    ?>
    <!-- Widget Flotante: Asistente Académico IA -->
    <div id="widgetChatIA" style="position: fixed; bottom: 20px; right: 20px; z-index: 1055;">
        <!-- Botón flotante -->
        <button type="button"
                id="btnBurbujaChatIA"
                onclick="toggleChatIA()"
                class="btn btn-primary-custom rounded-pill shadow-lg d-flex align-items-center gap-2 px-3 py-2"
                title="Abrir Asistente Académico">
            <i class="bi bi-stars fs-5"></i>
            <span class="fw-semibold d-none d-sm-inline">Asistente IA</span>
        </button>

        <!-- Ventana de chat -->
        <div id="panelChatIA"
             class="card shadow-lg border-0 d-none mt-2"
             style="width: 370px; max-width: calc(100vw - 24px); height: 500px; max-height: calc(100vh - 100px); border-radius: 14px; overflow: hidden;">
            <!-- Cabecera -->
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2 px-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <div class="fw-bold small lh-sm">Asistente Académico</div>
                        <div class="opacity-75" style="font-size: 0.72rem;">En línea para ayudarte</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm text-white p-1" onclick="limpiarChatIA()" title="Nueva conversación">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button type="button" class="btn btn-sm text-white p-1" onclick="toggleChatIA(false)" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Área de mensajes -->
            <div id="mensajesChatIA" class="card-body p-3 overflow-auto bg-light d-flex flex-column gap-2" style="flex: 1 1 auto;"></div>

            <!-- Sugerencias rápidas -->
            <div id="sugerenciasChatIA" class="px-3 py-2 bg-white border-top d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;" onclick="enviarSugerenciaIA('¿Cómo agendo una tutoría?')">
                    ¿Cómo agendo una clase?
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;" onclick="enviarSugerenciaIA('Explícame qué es una derivada de forma sencilla')">
                    ¿Qué es una derivada?
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;" onclick="enviarSugerenciaIA('Dame 3 consejos para prepararme antes de mi tutoría')">
                    Consejos para estudiar
                </button>
            </div>

            <!-- Formulario de entrada -->
            <form id="formChatIA" class="card-footer bg-white border-top p-2" onsubmit="enviarMensajeChatIA(event)">
                <div class="input-group input-group-sm">
                    <input type="text"
                           id="inputChatIA"
                           class="form-control border-0 bg-light"
                           placeholder="Escribe tu duda aquí..."
                           maxlength="1000"
                           autocomplete="off"
                           required>
                    <button type="submit" id="btnEnviarChatIA" class="btn btn-primary px-3" title="Enviar">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const storageKey = 'tutores_chat_ia_<?= $chat_ia_uid ?>';
        const bienvenida = '¡Hola! Soy el asistente de TutoresOnLine. Puedo ayudarte a resolver dudas de tus materias, prepararte para tus clases o guiarte dentro de la plataforma.';
        let historial = [];
        let enviando = false;

        try {
            const guardado = sessionStorage.getItem(storageKey);
            if (guardado) {
                const parsed = JSON.parse(guardado);
                if (Array.isArray(parsed)) historial = parsed;
            }
        } catch (e) {}

        function guardarHistorial() {
            try {
                sessionStorage.setItem(storageKey, JSON.stringify(historial.slice(-20)));
            } catch (e) {}
        }

        function crearBurbuja(rol, texto, esCargando) {
            const wrap = document.createElement('div');
            wrap.className = rol === 'user' ? 'd-flex justify-content-end' : 'd-flex justify-content-start';

            const bubble = document.createElement('div');
            bubble.className = rol === 'user'
                ? 'bg-primary text-white rounded-3 px-3 py-2 shadow-sm small'
                : 'bg-white text-dark border rounded-3 px-3 py-2 shadow-sm small';
            bubble.style.maxWidth = '85%';
            bubble.style.whiteSpace = 'pre-wrap';
            bubble.style.wordBreak = 'break-word';

            if (esCargando) {
                wrap.id = 'iaCargandoBurbuja';
                const sp = document.createElement('span');
                sp.className = 'text-muted fst-italic';
                sp.textContent = 'Escribiendo...';
                bubble.appendChild(sp);
            } else {
                bubble.textContent = texto;
            }

            wrap.appendChild(bubble);
            return wrap;
        }

        function renderizarMensajes() {
            const box = document.getElementById('mensajesChatIA');
            if (!box) return;
            box.innerHTML = '';
            box.appendChild(crearBurbuja('assistant', bienvenida, false));
            historial.forEach(function (item) {
                if (item && item.texto) {
                    box.appendChild(crearBurbuja(item.rol === 'user' ? 'user' : 'assistant', item.texto, false));
                }
            });
            box.scrollTop = box.scrollHeight;
        }

        window.toggleChatIA = function (forzarAbrir) {
            const panel = document.getElementById('panelChatIA');
            const burbuja = document.getElementById('btnBurbujaChatIA');
            const input = document.getElementById('inputChatIA');
            if (!panel || !burbuja) return;

            const abrir = (typeof forzarAbrir === 'boolean') ? forzarAbrir : panel.classList.contains('d-none');
            if (abrir) {
                panel.classList.remove('d-none');
                panel.classList.add('d-flex', 'flex-column');
                burbuja.classList.add('d-none');
                renderizarMensajes();
                setTimeout(function () { if (input) input.focus(); }, 60);
            } else {
                panel.classList.add('d-none');
                panel.classList.remove('d-flex', 'flex-column');
                burbuja.classList.remove('d-none');
            }
        };

        window.limpiarChatIA = function () {
            historial = [];
            guardarHistorial();
            renderizarMensajes();
        };

        window.enviarSugerenciaIA = function (texto) {
            const input = document.getElementById('inputChatIA');
            if (!input) return;
            input.value = texto;
            enviarTextoAlAsistente(texto);
        };

        window.enviarMensajeChatIA = function (ev) {
            ev.preventDefault();
            const input = document.getElementById('inputChatIA');
            if (!input) return;
            const texto = input.value.trim();
            if (!texto) return;
            enviarTextoAlAsistente(texto);
        };

        async function enviarTextoAlAsistente(texto) {
            if (enviando) return;
            const input = document.getElementById('inputChatIA');
            const btn = document.getElementById('btnEnviarChatIA');
            const box = document.getElementById('mensajesChatIA');
            if (!box || !input || !btn) return;

            const historialPrevio = historial.slice(-8);
            historial.push({ rol: 'user', texto: texto });
            guardarHistorial();

            input.value = '';
            enviando = true;
            btn.disabled = true;

            box.appendChild(crearBurbuja('user', texto, false));
            box.appendChild(crearBurbuja('assistant', '', true));
            box.scrollTop = box.scrollHeight;

            try {
                const resp = await fetch('<?= APP_URL ?>/api/chat_ia.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= htmlspecialchars($chat_ia_csrf, ENT_QUOTES, 'UTF-8') ?>'
                    },
                    body: JSON.stringify({
                        csrf_token: '<?= htmlspecialchars($chat_ia_csrf, ENT_QUOTES, 'UTF-8') ?>',
                        mensaje: texto,
                        historial: historialPrevio
                    })
                });
                const data = await resp.json();
                const cargandoEl = document.getElementById('iaCargandoBurbuja');
                if (cargandoEl) cargandoEl.remove();

                const reply = (resp.ok && data && data.exito && data.respuesta)
                    ? data.respuesta
                    : (data && data.mensaje ? data.mensaje : 'No pude responder en este momento. Intenta nuevamente.');

                historial.push({ rol: 'assistant', texto: reply });
                guardarHistorial();
                box.appendChild(crearBurbuja('assistant', reply, false));
            } catch (err) {
                const cargandoEl = document.getElementById('iaCargandoBurbuja');
                if (cargandoEl) cargandoEl.remove();
                const msgErr = 'Ocurrió un inconveniente al enviar tu mensaje. Por favor intenta de nuevo.';
                box.appendChild(crearBurbuja('assistant', msgErr, false));
            } finally {
                enviando = false;
                btn.disabled = false;
                box.scrollTop = box.scrollHeight;
                input.focus();
            }
        }
    })();
    </script>
<?php endif; ?>

</body>
</html>
