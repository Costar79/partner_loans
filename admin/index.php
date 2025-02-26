<?php
require_once __DIR__ . '/core/middleware.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/views/dashboard.php';
?>

