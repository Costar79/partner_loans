<?php
require_once __DIR__ . '/../../server/config/settings.php'; // Load settings
require_once __DIR__ . '/../../app/utils/Logger.php';      // Load Logger

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Log unauthorized access attempt
    Logger::logError('admin_access', "Unauthorized access attempt to {$_SERVER['REQUEST_URI']} from {$_SERVER['REMOTE_ADDR']}");

    // Redirect to admin login page
    header("Location: /admin/login.php");
    exit;
}


// Log successful access
Logger::logInfo('admin_access', "Admin accessed {$_SERVER['REQUEST_URI']} from {$_SERVER['REMOTE_ADDR']}");


?>
