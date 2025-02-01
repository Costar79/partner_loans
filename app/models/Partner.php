<?php
class Partner {
    private $conn;
    private $table = "partners";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Get Partner by Partner Code
    public function getPartnerByCode($partner_code) {
        $stmt = $this->conn->prepare("SELECT partner_id FROM " . $this->table . " WHERE partner_code = ?");
        $stmt->execute([$partner_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
