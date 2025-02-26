<?php
require_once __DIR__ . '/../../app/utils/Logger.php'; // Load Logger

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token and store it in the session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from the request
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        Logger::logError('security', "CSRF validation failed from {$_SERVER['REMOTE_ADDR']} on {$_SERVER['REQUEST_URI']}");
        return false;
    }
    return true;
}

/**
 * Include CSRF token as a hidden input in forms
 */
function csrfField() {
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
?>
