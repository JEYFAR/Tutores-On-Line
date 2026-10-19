<?php
/**
 * =============================================================================
 * TutoresOnLine - Redirección de Archivos
 * La gestión de archivos y documentos ahora está integrada en Mi Perfil (perfil.php)
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

requiere_login();

header('Location: ' . APP_URL . '/perfil.php');
exit;
