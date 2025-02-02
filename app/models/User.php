<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Get user by ID Number
    /*
        // Returns false instead of null
    public function getUserByIdNumber($id_number) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }*/
    // Use for now
    public function getUserByIdNumber($id_number) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null; // ✅ Ensures `null` is returned instead of `false`
    }

    // ✅ Register new user
    public function createUser($id_number) {
        $stmt = $this->conn->prepare("INSERT INTO users (id_number, state) VALUES (?, 'Active')");
        return $stmt->execute([$id_number]);
    }
}
?>
