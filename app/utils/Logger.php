<?php

class Logger {
    private static $settings; // Store settings globally in this class

    // ✅ Load settings once (Singleton-style caching)
    private static function loadSettings() {
        if (self::$settings === null) {
            self::$settings = require __DIR__ . '/../../server/config/settings.php';
        }
    }

    public static function logInfo($category, $message) {
        self::loadSettings();

        if (!empty(self::$settings['logging'][$category])) {
            self::writeLog($category, "INFO", $message);
        }
    }

    public static function logWarning($category, $message) {
        self::loadSettings();

        if (!empty(self::$settings['logging'][$category])) {
            self::writeLog($category, "WARNING", $message);
        }
    }

    public static function logError($category, $message) {
        self::loadSettings();

        if (!empty(self::$settings['logging']['errors'])) { // ✅ Always log general errors
            self::writeLog($category, "ERROR", $message);
        }
    }

    private static function writeLog($category, $level, $message) {
        $logDir = __DIR__ . "/../../logs/";

        // ✅ Ensure logs directory exists
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                die("❌ Logger failed to create logs directory: $logDir");
            }
        }

        $logFile = $logDir . "{$category}.log";  // Log file path
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        // ✅ Attempt to write log
        if (!file_put_contents($logFile, $logMessage, FILE_APPEND)) {
            die("❌ Logger failed to write to $logFile. Check file permissions.");
        }
    }
}
?>
