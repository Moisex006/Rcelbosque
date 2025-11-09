<?php
/**
 * Sistema de logging que también muestra logs en la consola del navegador
 */

// Buffer para almacenar logs que se mostrarán en la consola
if (!isset($_SESSION['browser_logs'])) {
    $_SESSION['browser_logs'] = [];
}

/**
 * Función para loggear y también almacenar para mostrar en consola del navegador
 */
function browser_log($message, $type = 'info') {
    // Loggear normalmente
    error_log($message);
    
    // Almacenar para mostrar en consola del navegador
    if (!isset($_SESSION['browser_logs'])) {
        $_SESSION['browser_logs'] = [];
    }
    
    $_SESSION['browser_logs'][] = [
        'message' => $message,
        'type' => $type, // info, success, error, warning
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Limitar a los últimos 50 logs para no sobrecargar
    if (count($_SESSION['browser_logs']) > 50) {
        $_SESSION['browser_logs'] = array_slice($_SESSION['browser_logs'], -50);
    }
}

/**
 * Obtener logs para mostrar en JavaScript
 */
function get_browser_logs() {
    $logs = $_SESSION['browser_logs'] ?? [];
    $_SESSION['browser_logs'] = []; // Limpiar después de leer
    return $logs;
}

