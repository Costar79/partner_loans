<?php
require_once 'config.php';
require_once __DIR__ . "/../../app/utils/Logger.php";

class Database {
    private $conn;

    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conn;
        } catch (PDOException $e) {
            Logger::logError('errors', "Database Connection Failed: " . $e->getMessage());
            die("A database error occurred. Please try again later.");
        }
    }
}
?>

