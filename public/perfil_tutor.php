<?php
/**
 * =============================================================================
 * TutoresOnLine - Perfil Público del Tutor
 * Detalle del profesor, materias y tarifas, disponibilidad semanal,
 * hoja de vida descargable y calificaciones con estrellas de estudiantes.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

$tutor_id = (int)($_GET['id'] ?? 0);

if ($tutor_id <= 0) {
    set_flash('danger', 'Tutor no especificado.');
    header('Location: ' . APP_URL . '/buscar.php');
    exit;
}

try {
    $pdo = obtener_conexion();

    // 1. Datos del tutor
    $stmt_tutor = $pdo->prepare("SELECT `id`, `nombre`, `correo`, `ciudad`, `foto`, `creado_en` FROM `usuarios` WHERE `id` = ? AND `rol` = 'tutor' AND `correo_verificado` = 1 LIMIT 1");
    $stmt_tutor->execute([$tutor_id]);
    $tutor = $stmt_tutor->fetch();

    if (!$tutor) {
        set_flash('danger', 'El tutor solicitado no existe o no está verificado.');
        header('Location: ' . APP_URL . '/buscar.php');
        exit;
    }

    // 2. Materias impartidas y tarifas
    $stmt_mat = $pdo->prepare(
        "SELECT tm.*, m.nombre AS materia_nombre, n.nombre AS nivel_nombre
         FROM `tutor_materia` tm
         INNER JOIN `materias` m ON m.id = tm.materia_id
         INNER JOIN `niveles` n ON n.id = tm.nivel_id
         WHERE tm.tutor_id = ?
         ORDER BY m.nombre ASC"
    );
    $stmt_mat->execute([$tutor_id]);
    $materias = $stmt_mat->fetchAll();

    // 3. Disponibilidad semanal
    $stmt_disp = $pdo->prepare(
        "SELECT * FROM `disponibilidad`
         WHERE `tutor_id` = ?
         ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio ASC"
    );
    $stmt_disp->execute([$tutor_id]);
    $disponibilidad = $stmt_disp->fetchAll();

    // 4. Calificaciones y reseñas recibidas
    $stmt_calif = $pdo->prepare(
        "SELECT c.*, u.nombre AS estudiante_nombre
         FROM `calificaciones` c
         INNER JOIN `usuarios` u ON u.id = c.estudiante_id
         WHERE c.tutor_id = ?
         ORDER BY c.creado_en DESC"
    );
    $stmt_calif->execute([$tutor_id]);
    $calificaciones = $stmt_calif->fetchAll();

    // Promedio de calificación
    $stmt_prom = $pdo->prepare("SELECT COALESCE(ROUND(AVG(puntaje), 1), 5.0) AS promedio, COUNT(*) AS total FROM `calificaciones` WHERE `tutor_id` = ?");
    $stmt_prom->execute([$tutor_id]);
    $rating_info = $stmt_prom->fetch();
    $promedio = (float)$rating_info['promedio'];
    $total_resenas = (int)$rating_info['total'];

    // 5. Hoja de vida del tutor si existe
    $stmt_cv = $pdo->prepare("SELECT `nombre_guardado`, `nombre_original` FROM `archivos` WHERE `usuario_id` = ? AND `tipo` = 'hoja_vida' ORDER BY `subido_en` DESC LIMIT 1");
    $stmt_cv->execute([$tutor_id]);
    $hoja_vida = $stmt_cv->fetch();

} catch (Exception $e) {
    error_log("[Perfil Tutor Error]: " . $e->getMessage());
    set_flash('danger', 'Error al cargar el perfil del tutor.');
    header('Location: ' . APP_URL . '/buscar.php');
    exit;
}

$titulo_pagina = 'Perfil de ' . $tutor['nombre'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
    <!-- Columna Izquierda: Tarjeta del Tutor -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 border-0 shadow-sm text-center mb-4 sticky-top" style="top: 85px;">
            <div class="mb-3">
                <?php if (!empty($tutor['foto'])): ?>
                    <img src="<?= APP_URL ?>/uploads/<?= e($tutor['foto']) ?>" alt="<?= e($tutor['nombre']) ?>" class="rounded-circle shadow-sm object-fit-cover" style="width: 110px; height: 110px;">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-1 mx-auto shadow-sm" style="width: 110px; height: 110px;">
                        <?= strtoupper(substr($tutor['nombre'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="fw-bold mb-1"><?= e($tutor['nombre']) ?></h3>
            <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($tutor['ciudad'] ?: 'Colombia') ?></p>

            <div class="mb-3">
                <span class="estrellas-calificacion">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= round($promedio) ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?>"></i>
                    <?php endfor; ?>
                    <span class="fw-bold text-dark ms-1"><?= number_format($promedio, 1) ?></span>
                </span>
                <div class="small text-muted">(<?= $total_resenas ?> reseñas)</div>
            </div>

            <?php if ($hoja_vida): ?>
                <div class="mb-3">
                    <a href="<?= APP_URL ?>/uploads/<?= e($hoja_vida['nombre_guardado']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Ver Hoja de Vida
                    </a>
                </div>
            <?php endif; ?>

            <hr class="my-3">

            <div class="d-grid gap-2">
                <a href="<?= APP_URL ?>/reservar.php?tutor_id=<?= (int)$tutor['id'] ?>" class="btn btn-primary-custom py-2">
                    <i class="bi bi-calendar-plus me-1"></i> Reservar Tutoría
                </a>
                <a href="<?= APP_URL ?>/buscar.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Seguir buscando
                </a>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Materias, Disponibilidad y Reseñas -->
    <div class="col-lg-8">
        <!-- 1. Materias y Tarifas -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-book text-primary"></i> Materias Ofertadas y Tarifas
            </h4>

            <?php if (empty($materias)): ?>
                <p class="text-muted">Este tutor no tiene materias asignadas actualmente.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Materia</th>
                                <th>Nivel Académico</th>
                                <th class="text-end">Tarifa por Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materias as $mat): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($mat['materia_nombre']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($mat['nivel_nombre']) ?></span></td>
                                    <td class="text-end fw-bold text-primary">$<?= number_format($mat['tarifa_hora'], 0, ',', '.') ?> COP</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Horarios de Disponibilidad Semanal -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-calendar-week text-primary"></i> Horarios de Disponibilidad
            </h4>

            <?php if (empty($disponibilidad)): ?>
                <p class="text-muted">El tutor no ha publicado horarios fijos de disponibilidad semanal.</p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($disponibilidad as $disp): ?>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-capitalize text-dark"><?= e($disp['dia_semana']) ?></div>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?= substr($disp['hora_inicio'], 0, 5) ?> - <?= substr($disp['hora_fin'], 0, 5) ?>
                                    </small>
                                </div>
                                <span class="badge <?= $disp['modalidad'] === 'virtual' ? 'bg-info-subtle text-info' : 'bg-success-subtle text-success' ?> border">
                                    <?= ucfirst(e($disp['modalidad'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Calificaciones y Opiniones -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-chat-quote text-primary"></i> Opiniones de Estudiantes
            </h4>

            <?php if (empty($calificaciones)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-chat-square-dots fs-2 d-block mb-2"></i>
                    Aún no hay opiniones registradas para este tutor.
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($calificaciones as $cal): ?>
                        <div class="p-3 border rounded-3 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-dark"><?= e($cal['estudiante_nombre']) ?></span>
                                <span class="estrellas-calificacion">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi <?= $s <= (int)$cal['puntaje'] ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?> small"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <p class="text-secondary small mb-1"><?= nl2br(e($cal['comentario'] ?: 'Sin comentario adicional.')) ?></p>
                            <small class="text-muted"><?= date('d/m/Y', strtotime($cal['creado_en'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
