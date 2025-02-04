<?php
header("Content-Type: application/json");
require_once '../config/config.php'; // Ensure config.php is loaded

$consoleLogging = isset($env['CONSOLE_LOGGING']) ? filter_var($env['CONSOLE_LOGGING'], FILTER_VALIDATE_BOOLEAN) : false;

echo json_encode(["consoleLogging" => $consoleLogging]);
exit;
?>
