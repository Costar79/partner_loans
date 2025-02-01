<?php
require_once 'config.php';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    require_once __DIR__ . "/../../app/utils/Logger.php";
    Logger::logError('errors', "Database Connection Failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}
?>
