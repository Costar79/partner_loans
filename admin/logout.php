<?php
require_once __DIR__ . '/core/middleware.php'; // Ensure session is started
require_once __DIR__ . '/../app/utils/Logger.php'; // Load Logger

//  Log admin logout event
Logger::logInfo('admin_access', "Admin logged out from {$_SERVER['REMOTE_ADDR']}");

//  Destroy the session completely
session_unset();  // Unset all session variables
session_destroy(); // Destroy the session
setcookie(session_name(), '', time() - 3600, '/'); // Remove session cookie

//  Redirect to login page
header("Location: login.php");
exit;
?>

