<?php
class Logger {
    public static function logError($category, $message) {
        $logDir = __DIR__ . "/../../logs/";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . "$category.log";
        $timestamp = date("Y-m-d H:i:s");
        $logMessage = "[$timestamp] $message" . PHP_EOL;

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
