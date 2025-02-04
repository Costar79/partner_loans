<?php
header("Content-Type: application/json");
require_once '../config/config.php'; // Load environment variables

$consoleLogging = getenv('CONSOLE_LOGGING') === 'true' ? true : false;

echo json_encode(["consoleLogging" => $consoleLogging]);
exit;
?>
