<?php

class Logger {
    private static $settings;

    // ✅ Load settings only once (Singleton-style caching)
    private static function loadSettings() {
        if (self::$settings === null) {
            self::$settings = require_once __DIR__ . '/../../config/settings.php';
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

        if (!empty(self::$settings['logging']['general_errors'])) {
            self::writeLog($category, "ERROR", $message);
        }
    }

    private static function writeLog($category, $level, $message) {
        $logFile = __DIR__ . "/../../logs/{$category}.log";
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
