<?php
/**
 * =============================================================================
 * TutoresOnLine - Dashboard Universal (Estudiantes y Tutores)
 * Panel central unificado y adaptativo según el rol del usuario autenticado.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Control estricto: requiere inicio de sesión
requiere_login();

$usuario = usuario_actual();
$usuario_id = (int)$_SESSION['usuario_id'];
$rol = $usuario['rol'];
$pdo = obtener_conexion();

// =============================================================================
// PROCESAMIENTO DE ACCIONES SEGÚN ROL
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        set_flash('danger', 'Error de seguridad (CSRF). Intenta de nuevo.');
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // ACCIÓN ESTUDIANTE: Calificar tutoría finalizada
    if ($rol === 'estudiante' && $accion === 'calificar') {
        $sesion_id  = (int)($_POST['sesion_id'] ?? 0);
        $puntaje    = (int)($_POST['puntaje'] ?? 5);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($puntaje < 1 || $puntaje > 5) $puntaje = 5;

        try {
            $stmt_chk = $pdo->prepare("SELECT `tutor_id` FROM `sesiones` WHERE `id` = ? AND `estudiante_id` = ? AND `estado` = 'finalizada' LIMIT 1");
            $stmt_chk->execute([$sesion_id, $usuario_id]);
            $ses = $stmt_chk->fetch();

            if ($ses) {
                $stmt_cal = $pdo->prepare(
                    "INSERT INTO `calificaciones` (`sesion_id`, `estudiante_id`, `tutor_id`, `puntaje`, `comentario`, `creado_en`)
                     VALUES (?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE `puntaje` = VALUES(`puntaje`), `comentario` = VALUES(`comentario`)"
                );
                $stmt_cal->execute([$sesion_id, $usuario_id, $ses['tutor_id'], $puntaje, $comentario]);
                set_flash('success', '¡Gracias por compartir tu opinión sobre la clase!');
            } else {
                set_flash('danger', 'La sesión no es válida o aún no ha finalizado.');
            }
        } catch (Exception $e) {
            error_log("[Panel Calificar Error]: " . $e->getMessage());
            set_flash('danger', 'Error al registrar tu calificación.');
        }
        header('Location: ' . APP_URL . '/panel.php');
        exit;
    }

    // ACCIONES TUTOR: Gestionar solicitudes y disponibilidad
    if ($rol === 'tutor') {
        // Cambiar estado de sesión
        if ($accion === 'estado_sesion') {
            $sesion_id = (int)($_POST['sesion_id'] ?? 0);
            $estado = $_POST['estado'] ?? '';

            if ($sesion_id > 0 && in_array($estado, ['aceptada', 'rechazada', 'finalizada'], true)) {
                $stmt = $pdo->prepare("UPDATE `sesiones` SET `estado` = ? WHERE `id` = ? AND `tutor_id` = ?");
                $stmt->execute([$estado, $sesion_id, $usuario_id]);
                set_flash('success', "La sesión #{$sesion_id} ahora está '{$estado}'.");
            }
            header('Location: ' . APP_URL . '/panel.php');
            exit;
        }

        // Agregar franja horaria
        if ($accion === 'agregar_horario') {
            $dia   = strtolower(trim($_POST['dia_semana'] ?? ''));
            $h_ini = trim($_POST['hora_inicio'] ?? '');
            $h_fin = trim($_POST['hora_fin'] ?? '');
            $mod   = trim($_POST['modalidad'] ?? 'virtual');

            if (!empty($dia) && !empty($h_ini) && !empty($h_fin) && strtotime($h_fin) > strtotime($h_ini)) {
                $stmt = $pdo->prepare("INSERT INTO `disponibilidad` (`tutor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad`) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$usuario_id, $dia, $h_ini, $h_fin, $mod]);
                set_flash('success', 'Franja de disponibilidad agregada.');
            } else {
                set_flash('danger', 'Verifica el horario ingresado. La hora de fin debe ser posterior a la de inicio.');
            }
            header('Location: ' . APP_URL . '/panel.php');
            exit;
        }

        // Eliminar franja horaria
        if ($accion === 'eliminar_horario') {
            $disp_id = (int)($_POST['disp_id'] ?? 0);
            if ($disp_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM `disponibilidad` WHERE `id` = ? AND `tutor_id` = ?");
                $stmt->execute([$disp_id, $usuario_id]);
                set_flash('success', 'Horario eliminado de tu disponibilidad.');
            }
            header('Location: ' . APP_URL . '/panel.php');
            exit;
        }

        // Agregar o actualizar especialidad/materia ofertada
        if ($accion === 'agregar_materia') {
            $materia_id_input = trim($_POST['materia_id'] ?? '');
            $nueva_materia    = trim($_POST['nueva_materia'] ?? '');
            $nivel_id         = (int)($_POST['nivel_id'] ?? 0);
            $tarifa_hora      = (float)($_POST['tarifa_hora'] ?? 0);

            // Si seleccionó escribir una nueva materia
            if ($materia_id_input === 'nueva' || empty($materia_id_input)) {
                if (empty($nueva_materia) || strlen($nueva_materia) < 2) {
                    set_flash('danger', 'Por favor escribe el nombre de la nueva materia o especialidad.');
                    header('Location: ' . APP_URL . '/panel.php');
                    exit;
                }
                // Verificar si ya existe en catálogo
                $stmt_mat_chk = $pdo->prepare("SELECT `id` FROM `materias` WHERE LOWER(TRIM(`nombre`)) = LOWER(?) LIMIT 1");
                $stmt_mat_chk->execute([$nueva_materia]);
                $mat_row = $stmt_mat_chk->fetch();

                if ($mat_row) {
                    $materia_id = (int)$mat_row['id'];
                } else {
                    $stmt_mat_ins = $pdo->prepare("INSERT INTO `materias` (`nombre`) VALUES (?)");
                    $stmt_mat_ins->execute([$nueva_materia]);
                    $materia_id = (int)$pdo->lastInsertId();
                }
            } else {
                $materia_id = (int)$materia_id_input;
            }

            if ($materia_id <= 0 || $nivel_id <= 0 || $tarifa_hora <= 0) {
                set_flash('danger', 'Por favor selecciona la materia, el nivel educativo y una tarifa por hora válida (mayor a 0).');
            } else {
                try {
                    $stmt_tm = $pdo->prepare("
                        INSERT INTO `tutor_materia` (`tutor_id`, `materia_id`, `nivel_id`, `tarifa_hora`)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE `tarifa_hora` = VALUES(`tarifa_hora`)
                    ");
                    $stmt_tm->execute([$usuario_id, $materia_id, $nivel_id, $tarifa_hora]);
                    set_flash('success', '¡Especialidad y tarifa guardadas exitosamente!');
                } catch (Exception $e) {
                    error_log("[Tutor Materia Error]: " . $e->getMessage());
                    set_flash('danger', 'Ocurrió un error al guardar la materia.');
                }
            }
            header('Location: ' . APP_URL . '/panel.php');
            exit;
        }

        // Eliminar especialidad/materia
        if ($accion === 'eliminar_materia') {
            $materia_id = (int)($_POST['materia_id'] ?? 0);
            $nivel_id   = (int)($_POST['nivel_id'] ?? 0);

            if ($materia_id > 0 && $nivel_id > 0) {
                $stmt_del = $pdo->prepare("DELETE FROM `tutor_materia` WHERE `tutor_id` = ? AND `materia_id` = ? AND `nivel_id` = ?");
                $stmt_del->execute([$usuario_id, $materia_id, $nivel_id]);
                set_flash('success', 'Materia eliminada de tus especialidades.');
            }
            header('Location: ' . APP_URL . '/panel.php');
            exit;
        }
    }
}

// =============================================================================
// CONSULTA DE DATOS SEGÚN ROL
// =============================================================================
$sesiones_estudiante = [];
$solicitudes_tutor   = [];
$materias_tutor      = [];
$horarios_tutor      = [];
$materias_catalogo   = [];
$niveles_catalogo    = [];

try {
    if ($rol === 'estudiante') {
        $sql = "SELECT s.*, u.nombre AS tutor_nombre, u.correo AS tutor_correo, m.nombre AS materia_nombre,
                       c.puntaje AS calificacion_puntaje, c.comentario AS calificacion_comentario
                FROM `sesiones` s
                INNER JOIN `usuarios` u ON u.id = s.tutor_id
                INNER JOIN `materias` m ON m.id = s.materia_id
                LEFT JOIN `calificaciones` c ON c.sesion_id = s.id
                WHERE s.estudiante_id = ?
                ORDER BY s.fecha DESC, s.hora_inicio DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $sesiones_estudiante = $stmt->fetchAll();
    } elseif ($rol === 'tutor') {
        // Solicitudes recibidas
        $sql_ses = "SELECT s.*, u.nombre AS estudiante_nombre, u.correo AS estudiante_correo, m.nombre AS materia_nombre
                    FROM `sesiones` s
                    INNER JOIN `usuarios` u ON u.id = s.estudiante_id
                    INNER JOIN `materias` m ON m.id = s.materia_id
                    WHERE s.tutor_id = ?
                    ORDER BY s.fecha DESC, s.hora_inicio DESC";
        $stmt_ses = $pdo->prepare($sql_ses);
        $stmt_ses->execute([$usuario_id]);
        $solicitudes_tutor = $stmt_ses->fetchAll();

        // Materias impartidas
        $sql_mat = "SELECT tm.*, m.nombre AS materia_nombre, n.nombre AS nivel_nombre
                    FROM `tutor_materia` tm
                    INNER JOIN `materias` m ON m.id = tm.materia_id
                    INNER JOIN `niveles` n ON n.id = tm.nivel_id
                    WHERE tm.tutor_id = ?
                    ORDER BY m.nombre ASC";
        $stmt_mat = $pdo->prepare($sql_mat);
        $stmt_mat->execute([$usuario_id]);
        $materias_tutor = $stmt_mat->fetchAll();

        // Catálogos para el formulario
        $materias_catalogo = $pdo->query("SELECT * FROM `materias` ORDER BY `nombre` ASC")->fetchAll();
        $niveles_catalogo  = $pdo->query("SELECT * FROM `niveles` ORDER BY `id` ASC")->fetchAll();

        // Disponibilidad horaria
        $stmt_disp = $pdo->prepare("SELECT * FROM `disponibilidad` WHERE `tutor_id` = ? ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio ASC");
        $stmt_disp->execute([$usuario_id]);
        $horarios_tutor = $stmt_disp->fetchAll();
    }
} catch (Exception $e) {
    error_log("[Dashboard Universal Error]: " . $e->getMessage());
}

$titulo_pagina = ($rol === 'tutor') ? 'Panel de Tutor' : 'Panel de Estudiante';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- BANNER HERO PRINCIPAL -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4 border-0 shadow-sm <?= $rol === 'tutor' ? 'bg-success text-white' : 'bg-primary text-white' ?>">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge bg-white text-dark mb-2 fw-semibold">
                        <?= $rol === 'tutor' ? 'Panel de Tutor' : 'Panel de Estudiante' ?>
                    </span>
                    <h2 class="fw-bold mb-1">¡Hola, <?= e($usuario['nombre']) ?>!</h2>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-envelope me-1"></i> <?= e($usuario['correo']) ?>
                        <?php if (!empty($usuario['ciudad'])): ?>
                            &bull; <i class="bi bi-geo-alt me-1"></i> <?= e($usuario['ciudad']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="<?= APP_URL ?>/chat.php" class="btn btn-light text-primary fw-semibold shadow-sm">
                        <i class="bi bi-chat-dots me-1"></i> Mensajes
                    </a>
                    <a href="<?= APP_URL ?>/perfil.php" class="btn btn-light text-dark fw-semibold shadow-sm">
                        <i class="bi bi-person-gear me-1"></i> Mi Perfil
                    </a>
                    <?php if ($rol === 'tutor'): ?>
                        <a href="<?= APP_URL ?>/perfil.php?id=<?= $usuario_id ?>" class="btn btn-light text-success fw-semibold shadow-sm">
                            <i class="bi bi-eye me-1"></i> Perfil Público
                        </a>
                    <?php else: ?>
                        <a href="<?= APP_URL ?>/buscar.php" class="btn btn-light text-primary fw-semibold shadow-sm">
                            <i class="bi bi-search me-1"></i> Buscar Tutores
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VISTA PARA ESTUDIANTE                                                     -->
<!-- ========================================================================= -->
<?php if ($rol === 'estudiante'): ?>
    <div class="row">
        <div class="col-12">
            <div class="card card-custom p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-event text-primary"></i> Mis Tutorías Solicitadas
                    </h4>
                    <span class="badge bg-light text-secondary border">
                        Total: <?= count($sesiones_estudiante) ?>
                    </span>
                </div>

                <?php if (empty($sesiones_estudiante)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-3 text-secondary"></i>
                        <h5 class="fw-bold text-dark mb-1">Aún no tienes tutorías agendadas</h5>
                        <p class="small text-muted mb-4">Encuentra a un profesor según tu materia y solicita tu primera clase.</p>
                        <a href="<?= APP_URL ?>/buscar.php" class="btn btn-primary-custom btn-sm px-3">
                            <i class="bi bi-search me-1"></i> Explorar Profesores
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Materia</th>
                                    <th>Tutor</th>
                                    <th>Fecha y Horario</th>
                                    <th>Modalidad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Calificación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sesiones_estudiante as $s): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= e($s['materia_nombre']) ?></td>
                                        <td>
                                            <div class="fw-medium d-flex align-items-center gap-2">
                                                <?= e($s['tutor_nombre']) ?>
                                                <a href="<?= APP_URL ?>/chat.php?con=<?= (int)$s['tutor_id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Chatear con el tutor">
                                                    <i class="bi bi-chat-dots"></i>
                                                </a>
                                            </div>
                                            <small class="text-muted"><?= e($s['tutor_correo']) ?></small>
                                        </td>
                                        <td>
                                            <div><i class="bi bi-calendar3 me-1 text-muted"></i><?= date('d/m/Y', strtotime($s['fecha'])) ?></div>
                                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= substr($s['hora_inicio'], 0, 5) ?> - <?= substr($s['hora_fin'], 0, 5) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst(e($s['modalidad'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            if ($s['estado'] === 'aceptada') $badge_class = 'bg-success';
                                            elseif ($s['estado'] === 'pendiente') $badge_class = 'bg-warning text-dark';
                                            elseif ($s['estado'] === 'finalizada') $badge_class = 'bg-primary';
                                            elseif ($s['estado'] === 'rechazada') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class ?> text-capitalize">
                                                <?= e($s['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($s['estado'] === 'finalizada'): ?>
                                                <?php if ($s['calificacion_puntaje']): ?>
                                                    <span class="estrellas-calificacion small">
                                                        <?php for ($st = 1; $st <= (int)$s['calificacion_puntaje']; $st++): ?>
                                                            <i class="bi bi-star-fill text-warning"></i>
                                                        <?php endfor; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCalif<?= $s['id'] ?>">
                                                        <i class="bi bi-star me-1"></i> Calificar
                                                    </button>

                                                    <!-- Modal de Calificación -->
                                                    <div class="modal fade" id="modalCalif<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content text-start">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title fw-bold">Calificar Tutoría</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                                </div>
                                                                <form action="<?= APP_URL ?>/panel.php" method="POST">
                                                                    <div class="modal-body">
                                                                        <?= csrf_campo() ?>
                                                                        <input type="hidden" name="accion" value="calificar">
                                                                        <input type="hidden" name="sesion_id" value="<?= (int)$s['id'] ?>">

                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-medium">Puntuación (1 a 5 estrellas)</label>
                                                                            <select name="puntaje" class="form-select" required>
                                                                                <option value="5" selected>⭐⭐⭐⭐⭐ - Excelente (5)</option>
                                                                                <option value="4">⭐⭐⭐⭐ - Muy buena (4)</option>
                                                                                <option value="3">⭐⭐⭐ - Aceptable (3)</option>
                                                                                <option value="2">⭐⭐ - Regular (2)</option>
                                                                                <option value="1">⭐ - Mala (1)</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-medium">Comentario u Opinión</label>
                                                                            <textarea name="comentario" rows="3" class="form-control" placeholder="¿Cómo fue la puntualidad y la claridad del profesor?"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button type="submit" class="btn btn-primary-custom btn-sm">Guardar Calificación</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- ========================================================================= -->
<!-- VISTA PARA TUTOR                                                          -->
<!-- ========================================================================= -->
<?php elseif ($rol === 'tutor'): ?>
    <!-- 1. Solicitudes de Clases -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-custom p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-inbox text-success"></i> Solicitudes de Tutoría
                    </h4>
                    <span class="badge bg-light text-secondary border">Total: <?= count($solicitudes_tutor) ?></span>
                </div>

                <?php if (empty($solicitudes_tutor)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2 text-secondary"></i>
                        No tienes solicitudes de tutorías por ahora.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Materia</th>
                                    <th>Fecha y Horario</th>
                                    <th>Modalidad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitudes_tutor as $sol): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark d-flex align-items-center gap-2">
                                                <?= e($sol['estudiante_nombre']) ?>
                                                <a href="<?= APP_URL ?>/chat.php?con=<?= (int)$sol['estudiante_id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Chatear con el estudiante">
                                                    <i class="bi bi-chat-dots"></i>
                                                </a>
                                            </div>
                                            <small class="text-muted"><?= e($sol['estudiante_correo']) ?></small>
                                        </td>
                                        <td class="fw-medium"><?= e($sol['materia_nombre']) ?></td>
                                        <td>
                                            <div><i class="bi bi-calendar3 me-1 text-muted"></i><?= date('d/m/Y', strtotime($sol['fecha'])) ?></div>
                                            <small class="text-muted"><?= substr($sol['hora_inicio'], 0, 5) ?> - <?= substr($sol['hora_fin'], 0, 5) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst(e($sol['modalidad'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            if ($sol['estado'] === 'aceptada') $badge_class = 'bg-success';
                                            elseif ($sol['estado'] === 'pendiente') $badge_class = 'bg-warning text-dark';
                                            elseif ($sol['estado'] === 'finalizada') $badge_class = 'bg-primary';
                                            elseif ($sol['estado'] === 'rechazada') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class ?> text-capitalize">
                                                <?= e($sol['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($sol['estado'] === 'pendiente'): ?>
                                                <div class="d-inline-flex gap-1">
                                                    <form action="<?= APP_URL ?>/panel.php" method="POST" class="d-inline">
                                                        <?= csrf_campo() ?>
                                                        <input type="hidden" name="accion" value="estado_sesion">
                                                        <input type="hidden" name="sesion_id" value="<?= (int)$sol['id'] ?>">
                                                        <input type="hidden" name="estado" value="aceptada">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aceptar"><i class="bi bi-check-lg"></i></button>
                                                    </form>
                                                    <form action="<?= APP_URL ?>/panel.php" method="POST" class="d-inline">
                                                        <?= csrf_campo() ?>
                                                        <input type="hidden" name="accion" value="estado_sesion">
                                                        <input type="hidden" name="sesion_id" value="<?= (int)$sol['id'] ?>">
                                                        <input type="hidden" name="estado" value="rechazada">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                </div>
                                            <?php elseif ($sol['estado'] === 'aceptada'): ?>
                                                <form action="<?= APP_URL ?>/panel.php" method="POST" class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="accion" value="estado_sesion">
                                                    <input type="hidden" name="sesion_id" value="<?= (int)$sol['id'] ?>">
                                                    <input type="hidden" name="estado" value="finalizada">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Marcar Finalizada</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Materias y Disponibilidad -->
    <div class="row g-4">
        <!-- Materias Ofertadas -->
        <div class="col-lg-6">
            <div class="card card-custom p-4 border-0 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-book text-success"></i> Materias y Tarifas
                    </h5>
                    <span class="badge bg-success-subtle text-success"><?= count($materias_tutor) ?> asignada(s)</span>
                </div>

                <!-- Formulario Agregar Especialidad / Materia -->
                <form action="<?= APP_URL ?>/panel.php" method="POST" class="mb-3 p-3 bg-light rounded-3" id="form-agregar-materia">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="agregar_materia">

                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5">
                            <label class="form-label small fw-medium mb-1">Materia</label>
                            <select name="materia_id" id="panel-select-materia" class="form-select form-select-sm" required onchange="toggleNuevaMateriaPanel(this.value)">
                                <option value="" disabled selected>Seleccionar...</option>
                                <?php foreach ($materias_catalogo as $mc): ?>
                                    <option value="<?= (int)$mc['id'] ?>"><?= e($mc['nombre']) ?></option>
                                <?php endforeach; ?>
                                <option value="nueva">+ Otra especialidad...</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small fw-medium mb-1">Nivel</label>
                            <select name="nivel_id" class="form-select form-select-sm" required>
                                <?php foreach ($niveles_catalogo as $nc): ?>
                                    <option value="<?= (int)$nc['id'] ?>"><?= e($nc['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-medium mb-1">Tarifa/h</label>
                            <input type="number" name="tarifa_hora" class="form-control form-control-sm" placeholder="$ COP" min="5000" step="1000" required>
                        </div>

                        <!-- Campo para escribir nueva materia si selecciona "+ Otra especialidad..." -->
                        <div class="col-12 d-none" id="panel-input-nueva-materia">
                            <label class="form-label small fw-medium mb-1 text-success">
                                <i class="bi bi-pencil-square"></i> Nombre de la nueva materia o especialidad:
                            </label>
                            <input type="text" name="nueva_materia" class="form-control form-control-sm" placeholder="Ej. Robótica, Álgebra, Bioquímica, Python...">
                        </div>

                        <div class="col-12 text-end pt-1">
                            <button type="submit" class="btn btn-sm btn-success px-3">
                                <i class="bi bi-plus-circle me-1"></i> Guardar Especialidad
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (empty($materias_tutor)): ?>
                    <div class="p-3 bg-light rounded text-center text-muted small">
                        <i class="bi bi-info-circle me-1 text-primary"></i> Aún no tienes materias asignadas. Registra las materias que impartes para que los estudiantes te encuentren en el buscador.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2" style="max-height: 280px; overflow-y: auto;">
                        <?php foreach ($materias_tutor as $m): ?>
                            <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark"><?= e($m['materia_nombre']) ?></strong>
                                    <span class="badge bg-secondary-subtle text-secondary border ms-1"><?= e($m['nivel_nombre']) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success-subtle text-success fw-bold">
                                        $<?= number_format($m['tarifa_hora'], 0, ',', '.') ?>/h
                                    </span>
                                    <form action="<?= APP_URL ?>/panel.php" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar esta especialidad?');">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="eliminar_materia">
                                        <input type="hidden" name="materia_id" value="<?= (int)$m['materia_id'] ?>">
                                        <input type="hidden" name="nivel_id" value="<?= (int)$m['nivel_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Disponibilidad Semanal -->
        <div class="col-lg-6">
            <div class="card card-custom p-4 border-0 shadow-sm h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-week text-success"></i> Mi Disponibilidad Semanal
                </h5>

                <!-- Formulario Agregar Horario -->
                <form action="<?= APP_URL ?>/panel.php" method="POST" class="row g-2 align-items-end mb-3 p-3 bg-light rounded-3">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="agregar_horario">

                    <div class="col-sm-4">
                        <label class="form-label small fw-medium mb-1">Día</label>
                        <select name="dia_semana" class="form-select form-select-sm" required>
                            <option value="lunes">Lunes</option>
                            <option value="martes">Martes</option>
                            <option value="miercoles">Miércoles</option>
                            <option value="jueves">Jueves</option>
                            <option value="viernes">Viernes</option>
                            <option value="sabado">Sábado</option>
                            <option value="domingo">Domingo</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small fw-medium mb-1">Inicio</label>
                        <input type="time" name="hora_inicio" class="form-control form-control-sm" value="08:00" required>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small fw-medium mb-1">Fin</label>
                        <input type="time" name="hora_fin" class="form-control form-control-sm" value="12:00" required>
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-sm btn-success w-100" title="Agregar Franja">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </form>

                <?php if (empty($horarios_tutor)): ?>
                    <p class="text-muted small mb-0">No has registrado horarios de atención aún.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($horarios_tutor as $h): ?>
                            <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-capitalize text-dark"><?= e($h['dia_semana']) ?>:</strong>
                                    <?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?>
                                    <span class="badge bg-light text-secondary border ms-1"><?= ucfirst(e($h['modalidad'])) ?></span>
                                </div>
                                <form action="<?= APP_URL ?>/panel.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este horario?');">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="eliminar_horario">
                                    <input type="hidden" name="disp_id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-2" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function toggleNuevaMateriaPanel(val) {
        var box = document.getElementById('panel-input-nueva-materia');
        if (!box) return;
        if (val === 'nueva') {
            box.classList.remove('d-none');
            var inp = box.querySelector('input');
            if (inp) {
                inp.required = true;
                inp.focus();
            }
        } else {
            box.classList.add('d-none');
            var inp = box.querySelector('input');
            if (inp) inp.required = false;
        }
    }
    </script>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
