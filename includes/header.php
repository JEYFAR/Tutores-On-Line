<?php
/**
 * =============================================================================
 * TutoresOnLine - Cabecera Común (Header)
 * Incluye metadatos, CSS de Bootstrap 5, navegación y mensajes flash.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/conexion.php';

// Iniciar sesión segura automáticamente
iniciar_sesion_segura();

// Si existe auth.php y no hay sesión activa, intentar restaurar sesión persistente ("Recordarme")
if (file_exists(__DIR__ . '/auth.php')) {
    require_once __DIR__ . '/auth.php';
    if (empty($_SESSION['usuario_id']) && function_exists('verificar_cookie_recordarme')) {
        verificar_cookie_recordarme();
    }
}

$titulo_pagina = isset($titulo_pagina) ? $titulo_pagina . ' | TutoresOnLine' : 'TutoresOnLine - Plataforma de Tutorías';
$pagina_actual = basename($_SERVER['PHP_SELF']);
$usuario_autenticado = !empty($_SESSION['usuario_id']);
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_usuario = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($titulo_pagina) ?></title>

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?= APP_URL ?>/css/estilos.css">
</head>
<body>

<!-- Barra de Navegación Principal -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom d-flex align-items-center gap-2" href="<?= APP_URL ?>/index.php">
            <i class="bi bi-mortarboard-fill fs-4"></i>
            <span>TutoresOnLine</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContenido" aria-controls="navbarContenido" aria-expanded="false" aria-label="Alternar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContenido">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'index.php' ? 'active fw-semibold text-primary' : '' ?>" href="<?= APP_URL ?>/index.php">
                        <i class="bi bi-house-door me-1"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'buscar.php' ? 'active fw-semibold text-primary' : '' ?>" href="<?= APP_URL ?>/buscar.php">
                        <i class="bi bi-search me-1"></i> Buscar Tutores
                    </a>
                </li>
                <?php if ($usuario_autenticado): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $pagina_actual === 'panel.php' ? 'active fw-semibold text-primary' : '' ?>" href="<?= APP_URL ?>/panel.php">
                            <i class="bi bi-speedometer2 me-1"></i> Mi Panel
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                <?php if ($usuario_autenticado): ?>
                    <!-- Menú Usuario Autenticado -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="menuUsuario" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5 text-primary"></i>
                            <span class="fw-medium"><?= e($nombre_usuario) ?></span>
                            <?php if ($rol_usuario === 'tutor'): ?>
                                <span class="badge bg-success badge-rol">Tutor</span>
                            <?php else: ?>
                                <span class="badge bg-primary badge-rol">Estudiante</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="menuUsuario">
                            <li>
                                <h6 class="dropdown-header">Mi Cuenta (<?= ucfirst(e($rol_usuario)) ?>)</h6>
                            </li>
                            <li>
                                <a class="dropdown-item <?= $pagina_actual === 'perfil.php' ? 'active fw-semibold' : '' ?>" href="<?= APP_URL ?>/perfil.php">
                                    <i class="bi bi-person-circle me-2"></i> Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= $pagina_actual === 'panel.php' ? 'active fw-semibold' : '' ?>" href="<?= APP_URL ?>/panel.php">
                                    <i class="bi bi-speedometer2 me-2"></i> Mi Panel
                                </a>
                            </li>
                            <?php if ($rol_usuario === 'tutor'): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/perfil_tutor.php?id=<?= (int)$_SESSION['usuario_id'] ?>">
                                        <i class="bi bi-person-badge me-2"></i> Ver Mi Perfil Público
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Menú Invitado -->
                    <li class="nav-item">
                        <a class="nav-link <?= $pagina_actual === 'login.php' ? 'active fw-semibold' : '' ?>" href="<?= APP_URL ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary-custom" href="<?= APP_URL ?>/registro.php">
                            <i class="bi bi-person-plus me-1"></i> Registrarse
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenedor Principal -->
<main class="container my-4">

    <!-- Despliegue Automático de Mensajes Flash -->
    <?php foreach (['success' => 'check-circle-fill', 'danger' => 'exclamation-triangle-fill', 'warning' => 'exclamation-circle-fill', 'info' => 'info-circle-fill'] as $tipo => $icono): ?>
        <?php if (hay_flash($tipo)): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="bi bi-<?= $icono ?> me-2 fs-5"></i>
                <div class="flex-grow-1">
                    <?= e(get_flash($tipo)) ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
