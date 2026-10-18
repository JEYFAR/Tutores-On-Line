<?php
/**
 * =============================================================================
 * TutoresOnLine - Agendar / Reservar Tutoría
 * Formulario para que un estudiante reserve una sesión con un tutor.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Solo estudiantes pueden agendar tutorías
requiere_rol('estudiante');

$estudiante_id = (int)$_SESSION['usuario_id'];
$tutor_id = (int)($_GET['tutor_id'] ?? $_POST['tutor_id'] ?? 0);

if ($tutor_id <= 0) {
    set_flash('danger', 'Debes seleccionar un tutor para agendar una clase.');
    header('Location: ' . APP_URL . '/buscar.php');
    exit;
}

try {
    $pdo = obtener_conexion();

    // 1. Datos del tutor
    $stmt_tutor = $pdo->prepare("SELECT `id`, `nombre`, `ciudad`, `foto` FROM `usuarios` WHERE `id` = ? AND `rol` = 'tutor' AND `correo_verificado` = 1 LIMIT 1");
    $stmt_tutor->execute([$tutor_id]);
    $tutor = $stmt_tutor->fetch();

    if (!$tutor) {
        set_flash('danger', 'Tutor no encontrado o no disponible.');
        header('Location: ' . APP_URL . '/buscar.php');
        exit;
    }

    // 2. Materias que enseña este tutor
    $stmt_mat = $pdo->prepare(
        "SELECT tm.*, m.nombre AS materia_nombre, n.nombre AS nivel_nombre
         FROM `tutor_materia` tm
         INNER JOIN `materias` m ON m.id = tm.materia_id
         INNER JOIN `niveles` n ON n.id = tm.nivel_id
         WHERE tm.tutor_id = ?"
    );
    $stmt_mat->execute([$tutor_id]);
    $materias = $stmt_mat->fetchAll();

    // 3. Disponibilidad semanal del tutor para referencia
    $stmt_disp = $pdo->prepare("SELECT * FROM `disponibilidad` WHERE `tutor_id` = ? ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')");
    $stmt_disp->execute([$tutor_id]);
    $horarios = $stmt_disp->fetchAll();

} catch (Exception $e) {
    error_log("[Reservar Error]: " . $e->getMessage());
    set_flash('danger', 'Error al cargar información del tutor.');
    header('Location: ' . APP_URL . '/buscar.php');
    exit;
}

$errores = [];
$materia_id = (int)($_POST['materia_id'] ?? 0);
$modalidad = $_POST['modalidad'] ?? 'virtual';
$fecha = $_POST['fecha'] ?? date('Y-m-d', strtotime('+1 day'));
$hora_inicio = $_POST['hora_inicio'] ?? '09:00';
$hora_fin = $_POST['hora_fin'] ?? '11:00';

// Procesar Reserva (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $errores[] = 'Error de seguridad (CSRF). Por favor recarga e intenta de nuevo.';
    }

    if ($materia_id <= 0) {
        $errores[] = 'Selecciona la materia que deseas ver en la tutoría.';
    }

    if (empty($fecha) || strtotime($fecha) < strtotime(date('Y-m-d'))) {
        $errores[] = 'La fecha de la tutoría no puede ser en el pasado.';
    }

    if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
        $errores[] = 'La hora de fin debe ser posterior a la hora de inicio.';
    }

    if (empty($errores)) {
        try {
            $stmt_ins = $pdo->prepare(
                "INSERT INTO `sesiones` (`estudiante_id`, `tutor_id`, `materia_id`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `estado`, `creado_en`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
            );
            $stmt_ins->execute([$estudiante_id, $tutor_id, $materia_id, $fecha, $hora_inicio, $hora_fin, $modalidad]);

            set_flash('success', '¡Tutoría agendada exitosamente! El tutor ha recibido tu solicitud en su panel.');
            header('Location: ' . APP_URL . '/panel_estudiante.php');
            exit;
        } catch (Exception $e) {
            error_log("[Reservar Insert Error]: " . $e->getMessage());
            $errores[] = 'Ocurrió un error al registrar la solicitud de tutoría.';
        }
    }
}

$titulo_pagina = 'Reservar Tutoría con ' . $tutor['nombre'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-lg-8">
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center gap-3 pb-3 border-bottom mb-4">
                <?php if (!empty($tutor['foto'])): ?>
                    <img src="<?= APP_URL ?>/uploads/<?= e($tutor['foto']) ?>" alt="<?= e($tutor['nombre']) ?>" class="rounded-circle shadow-sm object-fit-cover" style="width: 65px; height: 65px;">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 65px; height: 65px;">
                        <?= strtoupper(substr($tutor['nombre'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h3 class="fw-bold mb-0">Agendar Tutoría</h3>
                    <p class="text-muted mb-0">Con el profesor <strong><?= e($tutor['nombre']) ?></strong> &bull; <?= e($tutor['ciudad'] ?: 'Colombia') ?></p>
                </div>
            </div>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger shadow-sm">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errores as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/reservar.php" method="POST">
                <?= csrf_campo() ?>
                <input type="hidden" name="tutor_id" value="<?= (int)$tutor['id'] ?>">

                <!-- Materia -->
                <div class="mb-3">
                    <label for="materia_id" class="form-label fw-medium">Materia a tratar</label>
                    <select class="form-select" id="materia_id" name="materia_id" required>
                        <option value="">Selecciona una asignatura...</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int)$m['materia_id'] ?>" <?= $materia_id === (int)$m['materia_id'] ? 'selected' : '' ?>>
                                <?= e($m['materia_nombre']) ?> (<?= e($m['nivel_nombre']) ?>) &mdash; $<?= number_format($m['tarifa_hora'], 0, ',', '.') ?>/h
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Modalidad -->
                <div class="mb-3">
                    <label class="form-label fw-medium d-block">Modalidad de la clase</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalidad" id="mod_virtual" value="virtual" <?= $modalidad === 'virtual' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mod_virtual"><i class="bi bi-laptop me-1"></i> Virtual</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalidad" id="mod_presencial" value="presencial" <?= $modalidad === 'presencial' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mod_presencial"><i class="bi bi-person me-1"></i> Presencial</label>
                    </div>
                </div>

                <!-- Fecha y Horas -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="fecha" class="form-label fw-medium">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($fecha) ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hora_inicio" class="form-label fw-medium">Hora Inicio</label>
                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" value="<?= e($hora_inicio) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hora_fin" class="form-label fw-medium">Hora Fin</label>
                        <input type="time" class="form-control" id="hora_fin" name="hora_fin" value="<?= e($hora_fin) ?>" required>
                    </div>
                </div>

                <!-- Referencia de horarios habituales del tutor -->
                <?php if (!empty($horarios)): ?>
                    <div class="p-3 bg-light rounded-3 mb-4 small">
                        <div class="fw-semibold text-muted mb-2"><i class="bi bi-info-circle me-1"></i> Horarios habituales del profesor:</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($horarios as $h): ?>
                                <span class="badge bg-white text-dark border">
                                    <?= ucfirst(e($h['dia_semana'])) ?>: <?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?> (<?= e($h['modalidad']) ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= APP_URL ?>/perfil_tutor.php?id=<?= (int)$tutor['id'] ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                    <button type="submit" class="btn btn-primary-custom px-4 py-2">
                        <i class="bi bi-check2-circle me-1"></i> Confirmar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
