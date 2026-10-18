<?php
/**
 * =============================================================================
 * TutoresOnLine - Panel del Estudiante
 * Dashboard para visualizar tutorías, agendar nuevas y calificar clases finalizadas.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Control estricto de acceso por rol
requiere_rol('estudiante');

$usuario = usuario_actual();
$estudiante_id = (int)$_SESSION['usuario_id'];
$pdo = obtener_conexion();

// Procesar Calificación de una tutoría finalizada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'calificar') {
    if (!verificar_csrf()) {
        set_flash('danger', 'Error de seguridad (CSRF). Intenta de nuevo.');
        header('Location: ' . APP_URL . '/panel_estudiante.php');
        exit;
    }

    $sesion_id  = (int)($_POST['sesion_id'] ?? 0);
    $puntaje    = (int)($_POST['puntaje'] ?? 5);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($puntaje < 1 || $puntaje > 5) {
        $puntaje = 5;
    }

    try {
        // Validar que la sesión sea del estudiante y esté finalizada
        $stmt_chk = $pdo->prepare("SELECT tutor_id FROM `sesiones` WHERE `id` = ? AND `estudiante_id` = ? AND `estado` = 'finalizada' LIMIT 1");
        $stmt_chk->execute([$sesion_id, $estudiante_id]);
        $ses = $stmt_chk->fetch();

        if ($ses) {
            $stmt_cal = $pdo->prepare(
                "INSERT INTO `calificaciones` (`sesion_id`, `estudiante_id`, `tutor_id`, `puntaje`, `comentario`, `creado_en`)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `puntaje` = VALUES(`puntaje`), `comentario` = VALUES(`comentario`)"
            );
            $stmt_cal->execute([$sesion_id, $estudiante_id, $ses['tutor_id'], $puntaje, $comentario]);
            set_flash('success', '¡Gracias por compartir tu opinión y calificar la tutoría!');
        } else {
            set_flash('danger', 'La sesión no es válida o aún no ha finalizado.');
        }
    } catch (Exception $e) {
        error_log("[Calificar Sesion Error]: " . $e->getMessage());
        set_flash('danger', 'Error al registrar tu calificación.');
    }

    header('Location: ' . APP_URL . '/panel_estudiante.php');
    exit;
}

// Obtener sesiones del estudiante
$sesiones = [];
try {
    $sql = "SELECT s.*, u.nombre AS tutor_nombre, u.correo AS tutor_correo, m.nombre AS materia_nombre,
                   c.puntaje AS calificacion_puntaje, c.comentario AS calificacion_comentario
            FROM `sesiones` s
            INNER JOIN `usuarios` u ON u.id = s.tutor_id
            INNER JOIN `materias` m ON m.id = s.materia_id
            LEFT JOIN `calificaciones` c ON c.sesion_id = s.id
            WHERE s.estudiante_id = ?
            ORDER BY s.fecha DESC, s.hora_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$estudiante_id]);
    $sesiones = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("[Panel Estudiante Error]: " . $e->getMessage());
}

$titulo_pagina = 'Panel de Estudiante';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4 border-0 bg-primary text-white shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-primary mb-2">Panel de Estudiante</span>
                    <h2 class="fw-bold mb-1">¡Hola, <?= e($usuario['nombre']) ?>!</h2>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-envelope me-1"></i> <?= e($usuario['correo']) ?> &bull; 
                        <i class="bi bi-geo-alt me-1"></i> <?= e($usuario['ciudad'] ?: 'Sin ciudad registrada') ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?= APP_URL ?>/buscar.php" class="btn btn-light text-primary fw-semibold">
                        <i class="bi bi-search me-1"></i> Buscar y Agendar Tutoría
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Lista de Tutorías -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 border-0 shadow-sm h-100">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-primary"></i> Mis Tutorías
            </h4>

            <?php if (empty($sesiones)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                    Aún no tienes tutorías solicitadas.
                    <div class="mt-3">
                        <a href="<?= APP_URL ?>/buscar.php" class="btn btn-primary-custom btn-sm">
                            Buscar Profesores Disponibles
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Materia</th>
                                <th>Tutor</th>
                                <th>Fecha y Hora</th>
                                <th>Modalidad</th>
                                <th>Estado</th>
                                <th class="text-end">Calificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sesiones as $s): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($s['materia_nombre']) ?></td>
                                    <td><?= e($s['tutor_nombre']) ?></td>
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
                                                <!-- Botón para abrir modal de calificación -->
                                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCalif<?= $s['id'] ?>">
                                                    <i class="bi bi-star me-1"></i> Calificar
                                                </button>

                                                <!-- Modal de Calificación -->
                                                <div class="modal fade" id="modalCalif<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content text-start">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold">Calificar Tutoría con <?= e($s['tutor_nombre']) ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                            </div>
                                                            <form action="<?= APP_URL ?>/panel_estudiante.php" method="POST">
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
                                                                        <textarea name="comentario" rows="3" class="form-control" placeholder="¿Cómo fue la clase y la puntualidad del profesor?"></textarea>
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

    <!-- Menú Lateral de Accesos -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h5 class="fw-bold mb-3">Gestión de Cuenta</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Estado del Correo:</span>
                    <?php if ((int)$usuario['correo_verificado'] === 1): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Verificado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Pendiente</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Total Tutorías:</span>
                    <span class="fw-bold"><?= count($sesiones) ?></span>
                </li>
            </ul>

            <div class="d-grid gap-2 mt-4">
                <a href="<?= APP_URL ?>/subir_archivo.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Subir Foto de Perfil
                </a>
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
