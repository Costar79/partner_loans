<?php
//error_log("🔍 Logger.php is being executed from API request.");


class Logger {
    private static $settings;

    // ✅ Load settings once
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
        if (!empty(self::$settings['logging']['errors'])) {
            self::writeLog($category, "ERROR", $message);
        }
    }

    private static function writeLog($category, $level, $message) {
        $logDir = __DIR__ . "/../../logs/";

        // ✅ Ensure logs directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . "{$category}.log";
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        // ✅ Write to log file
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        // ✅ Debug: Log whether console logging is enabled
        $logFilePath = $logDir . "general_logger_errors.log";

        /*
        if (!file_put_contents($logFilePath, "🔍 Debug: Logger Console Setting = " . json_encode(self::$settings['logging']['console']) . PHP_EOL, FILE_APPEND)) {
            error_log("❌ Failed to write to logs/errors.log. Check file permissions.");
        }
        */
        
        // ✅ Only log to the browser console if `console` is explicitly `true`
        if (!empty(self::$settings['logging']['console']) && self::$settings['logging']['console'] === true) {
            //error_log("[LOGGER] [{$level}] {$message}"); // ✅ Only logs to error_log, no `<script>` output
        } else {
            file_put_contents($logFilePath, "[LOGGER ERROR] Logger attempted console output when disabled" . PHP_EOL, FILE_APPEND);
            error_log("[LOGGER ERROR] [{$level}] {$message}");
            //error_log("❌ Console logging is disabled. Skipping console output.");
        }
        
    }
}
?>
