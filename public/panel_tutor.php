<?php
/**
 * =============================================================================
 * TutoresOnLine - Panel del Tutor (Acceso por rol)
 * Redirige al Dashboard Universal garantizando validación estricta de permisos.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Control estricto de acceso por rol: si no es tutor (ej. estudiante), acceso denegado
requiere_rol('tutor');

// Redirigir de manera transparente al dashboard universal
header('Location: ' . APP_URL . '/panel.php');
exit;
