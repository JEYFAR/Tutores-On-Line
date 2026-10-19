<?php
/**
 * =============================================================================
 * TutoresOnLine - Inicio
 * =============================================================================
 */

$titulo_pagina = 'Inicio';
require_once dirname(__DIR__) . '/includes/auth.php';

// Si ya inició sesión, redirigir directo al dashboard
if (usuario_autenticado()) {
    header('Location: ' . APP_URL . '/panel.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row align-items-center py-5">
    <div class="col-lg-8 mx-auto text-center">
        <h1 class="display-5 fw-bold text-dark mb-3">
            Plataforma de <span class="text-primary">Tutorías Universitarias</span>
        </h1>
        <p class="lead text-secondary mb-4">
            Conecta estudiantes con tutores para clases individuales, virtuales o presenciales.
        </p>

        <div class="d-flex justify-content-center mb-5">
            <a href="<?= APP_URL ?>/buscar.php" class="btn btn-primary-custom btn-lg px-4">
                <i class="bi bi-search me-2"></i> Buscar Tutores
            </a>
        </div>

        <div class="row g-4 text-start">
            <div class="col-md-4">
                <div class="card card-custom p-4 h-100">
                    <div class="text-primary fs-3 mb-2">
                        <i class="bi bi-search"></i>
                    </div>
                    <h5 class="fw-bold mb-2">1. Busca tu materia</h5>
                    <p class="text-secondary small mb-0">Filtra por asignatura, nivel académico y modalidad para ver los tutores disponibles.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-4 h-100">
                    <div class="text-primary fs-3 mb-2">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h5 class="fw-bold mb-2">2. Reserva tu horario</h5>
                    <p class="text-secondary small mb-0">Elige una fecha y hora según la disponibilidad del tutor y agenda tu sesión.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-4 h-100">
                    <div class="text-primary fs-3 mb-2">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <h5 class="fw-bold mb-2">3. Aprende</h5>
                    <p class="text-secondary small mb-0">Toma tu tutoría y, al terminar, deja tu calificación sobre el servicio recibido.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
