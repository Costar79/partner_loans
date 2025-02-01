<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Get user by ID Number
    public function getUserByIdNumber($id_number) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ✅ Register new user
    public function createUser($id_number) {
        $stmt = $this->conn->prepare("INSERT INTO users (id_number, state) VALUES (?, 'Active')");
        return $stmt->execute([$id_number]);
    }
}
?>
