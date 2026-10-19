<?php
/**
 * =============================================================================
 * TutoresOnLine - Redirección a Perfil Universal
 * El perfil ahora es único y universal en perfil.php
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
header('Location: ' . APP_URL . '/perfil.php' . ($id > 0 ? '?id=' . $id : ''));
exit;
