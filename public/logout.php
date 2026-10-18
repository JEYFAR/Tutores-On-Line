<?php
/**
 * =============================================================================
 * TutoresOnLine - Cerrar Sesión (Logout)
 * Destruye la sesión PHP, revoca el token 'recordarme' en BD y borra las cookies.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Limpiar sesión y cookies de forma segura
cerrar_sesion_completa();

// Iniciar sesión temporal para el mensaje flash
iniciar_sesion_segura();
set_flash('info', 'Has cerrado sesión correctamente.');

header('Location: ' . APP_URL . '/login.php');
exit;
