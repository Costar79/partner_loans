<?php
class Partner {
    private $conn;
    private $table = "partners";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Get Partner by Partner Code
    public function getPartnerByID($partner_id) {
        if (empty($partner_id)) {
            return null; // Return null to indicate no valid partner_id
        }
        
        $stmt = $this->conn->prepare("SELECT partner_id FROM " . $this->table . " WHERE partner_id = ? LIMIT 1");
        $stmt->execute([$partner_id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['partner_id'] : null; // Return integer or null if not found
    }
    
    public function getPartnerByCode($partner_code) {
        if (empty($partner_code)) {
            return false; // Prevents unnecessary DB query
        }
    
        $stmt = $this->conn->prepare("SELECT partner_id FROM " . $this->table . " WHERE partner_code = ?");
        $stmt->execute([$partner_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPartnerByUSSD_ID($ussd_id) {
        if (empty($partner_code)) {
            return false; // Prevents unnecessary DB query
        }
    
        $stmt = $this->conn->prepare("SELECT partner_id FROM " . $this->table . " WHERE ussd_id = ?");
        $stmt->execute([$partner_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}
?>
