<?php
/**
 * =============================================================================
 * TutoresOnLine - Panel del Tutor
 * Dashboard para gestionar solicitudes de estudiantes, materias y disponibilidad horaria.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Control de acceso para rol tutor
requiere_rol('tutor');

$usuario = usuario_actual();
$tutor_id = (int)$_SESSION['usuario_id'];
$pdo = obtener_conexion();

// Procesar acciones de gestión de sesiones o disponibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        set_flash('danger', 'Error de seguridad (CSRF). Intenta de nuevo.');
        header('Location: ' . APP_URL . '/panel_tutor.php');
        exit;
    }

    $tipo_accion = $_POST['tipo_accion'] ?? '';

    // 1. Cambiar estado de solicitud (aceptar, rechazar, finalizar)
    if ($tipo_accion === 'estado_sesion') {
        $sesion_id = (int)($_POST['sesion_id'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        if ($sesion_id > 0 && in_array($estado, ['aceptada', 'rechazada', 'finalizada'], true)) {
            $stmt = $pdo->prepare("UPDATE `sesiones` SET `estado` = ? WHERE `id` = ? AND `tutor_id` = ?");
            $stmt->execute([$estado, $sesion_id, $tutor_id]);
            set_flash('success', "La sesión #{$sesion_id} ahora está '{$estado}'.");
        }
        header('Location: ' . APP_URL . '/panel_tutor.php');
        exit;
    }

    // 2. Agregar franja de disponibilidad
    if ($tipo_accion === 'agregar_horario') {
        $dia = strtolower(trim($_POST['dia_semana'] ?? ''));
        $h_ini = trim($_POST['hora_inicio'] ?? '');
        $h_fin = trim($_POST['hora_fin'] ?? '');
        $mod = trim($_POST['modalidad'] ?? 'virtual');

        if (!empty($dia) && !empty($h_ini) && !empty($h_fin) && strtotime($h_fin) > strtotime($h_ini)) {
            $stmt = $pdo->prepare("INSERT INTO `disponibilidad` (`tutor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tutor_id, $dia, $h_ini, $h_fin, $mod]);
            set_flash('success', 'Horario de disponibilidad agregado.');
        } else {
            set_flash('danger', 'Por favor verifica los datos del horario. La hora de fin debe ser posterior a la de inicio.');
        }
        header('Location: ' . APP_URL . '/panel_tutor.php');
        exit;
    }

    // 3. Eliminar franja de disponibilidad
    if ($tipo_accion === 'eliminar_horario') {
        $disp_id = (int)($_POST['disp_id'] ?? 0);
        if ($disp_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `disponibilidad` WHERE `id` = ? AND `tutor_id` = ?");
            $stmt->execute([$disp_id, $tutor_id]);
            set_flash('success', 'Horario eliminado de tu disponibilidad.');
        }
        header('Location: ' . APP_URL . '/panel_tutor.php');
        exit;
    }
}

// Consultas de datos
$solicitudes = [];
$materias = [];
$horarios = [];

try {
    // Sesiones
    $sql_ses = "SELECT s.*, u.nombre AS estudiante_nombre, u.correo AS estudiante_correo, m.nombre AS materia_nombre
                FROM `sesiones` s
                INNER JOIN `usuarios` u ON u.id = s.estudiante_id
                INNER JOIN `materias` m ON m.id = s.materia_id
                WHERE s.tutor_id = ?
                ORDER BY s.fecha DESC, s.hora_inicio DESC";
    $stmt_ses = $pdo->prepare($sql_ses);
    $stmt_ses->execute([$tutor_id]);
    $solicitudes = $stmt_ses->fetchAll();

    // Materias
    $sql_mat = "SELECT tm.*, m.nombre AS materia_nombre, n.nombre AS nivel_nombre
                FROM `tutor_materia` tm
                INNER JOIN `materias` m ON m.id = tm.materia_id
                INNER JOIN `niveles` n ON n.id = tm.nivel_id
                WHERE tm.tutor_id = ?
                ORDER BY m.nombre ASC";
    $stmt_mat = $pdo->prepare($sql_mat);
    $stmt_mat->execute([$tutor_id]);
    $materias = $stmt_mat->fetchAll();

    // Horarios
    $stmt_disp = $pdo->prepare("SELECT * FROM `disponibilidad` WHERE `tutor_id` = ? ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio ASC");
    $stmt_disp->execute([$tutor_id]);
    $horarios = $stmt_disp->fetchAll();

} catch (Exception $e) {
    error_log("[Panel Tutor Error]: " . $e->getMessage());
}

$titulo_pagina = 'Panel de Tutor';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4 border-0 bg-success text-white shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-success mb-2">Panel del Tutor</span>
                    <h2 class="fw-bold mb-1">¡Bienvenido, Profesor <?= e($usuario['nombre']) ?>!</h2>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-envelope me-1"></i> <?= e($usuario['correo']) ?> &bull; 
                        <i class="bi bi-geo-alt me-1"></i> <?= e($usuario['ciudad'] ?: 'Sin ciudad') ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?= APP_URL ?>/perfil_tutor.php?id=<?= $tutor_id ?>" class="btn btn-light text-success fw-semibold">
                        <i class="bi bi-eye me-1"></i> Ver Mi Perfil Público
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Solicitudes de Tutoría -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-inbox text-success"></i> Solicitudes y Clases Agendadas
            </h4>

            <?php if (empty($solicitudes)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                    No tienes solicitudes de tutorías por ahora.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Materia</th>
                                <th>Fecha y Hora</th>
                                <th>Modalidad</th>
                                <th>Estado</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($sol['estudiante_nombre']) ?></div>
                                        <small class="text-muted"><?= e($sol['estudiante_correo']) ?></small>
                                    </td>
                                    <td><?= e($sol['materia_nombre']) ?></td>
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
                                                <form action="<?= APP_URL ?>/panel_tutor.php" method="POST" class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="tipo_accion" value="estado_sesion">
                                                    <input type="hidden" name="sesion_id" value="<?= (int)$sol['id'] ?>">
                                                    <input type="hidden" name="estado" value="aceptada">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Aceptar"><i class="bi bi-check-lg"></i></button>
                                                </form>
                                                <form action="<?= APP_URL ?>/panel_tutor.php" method="POST" class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="tipo_accion" value="estado_sesion">
                                                    <input type="hidden" name="sesion_id" value="<?= (int)$sol['id'] ?>">
                                                    <input type="hidden" name="estado" value="rechazada">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            </div>
                                        <?php elseif ($sol['estado'] === 'aceptada'): ?>
                                            <form action="<?= APP_URL ?>/panel_tutor.php" method="POST" class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="tipo_accion" value="estado_sesion">
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

        <!-- Gestión de Disponibilidad Semanal -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-calendar-week text-success"></i> Mi Disponibilidad Semanal
            </h4>

            <!-- Formulario agregar horario -->
            <form action="<?= APP_URL ?>/panel_tutor.php" method="POST" class="row g-2 align-items-end mb-4 p-3 bg-light rounded-3">
                <?= csrf_campo() ?>
                <input type="hidden" name="tipo_accion" value="agregar_horario">

                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label small fw-medium mb-1">Hora Inicio</label>
                    <input type="time" name="hora_inicio" class="form-control form-select-sm" value="08:00" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-medium mb-1">Hora Fin</label>
                    <input type="time" name="hora_fin" class="form-control form-select-sm" value="12:00" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Modalidad</label>
                    <select name="modalidad" class="form-select form-select-sm">
                        <option value="virtual">Virtual</option>
                        <option value="presencial">Presencial</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-success w-100" title="Agregar Franja">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </form>

            <?php if (empty($horarios)): ?>
                <p class="text-muted small mb-0">No has registrado horarios aún.</p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($horarios as $h): ?>
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-capitalize"><?= e($h['dia_semana']) ?>:</strong>
                                    <?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?>
                                    <span class="badge bg-light text-secondary border ms-1"><?= ucfirst(e($h['modalidad'])) ?></span>
                                </div>
                                <form action="<?= APP_URL ?>/panel_tutor.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este horario?');">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="tipo_accion" value="eliminar_horario">
                                    <input type="hidden" name="disp_id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-2" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Menú Lateral del Tutor -->
    <div class="col-lg-4">
        <!-- Mis Materias -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-book text-success"></i> Materias Impartidas
            </h5>
            <?php if (empty($materias)): ?>
                <p class="text-muted small mb-0">Aún no tienes materias asignadas.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush mb-0">
                    <?php foreach ($materias as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <div class="fw-semibold text-dark"><?= e($m['materia_nombre']) ?></div>
                                <small class="text-muted"><?= e($m['nivel_nombre']) ?></small>
                            </div>
                            <span class="badge bg-success-subtle text-success fw-bold">
                                $<?= number_format($m['tarifa_hora'], 0, ',', '.') ?>/h
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Acciones Rápidas -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <h5 class="fw-bold mb-3">Herramientas</h5>
            <div class="d-grid gap-2">
                <a href="<?= APP_URL ?>/subir_archivo.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Subir Foto o Hoja de Vida
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
