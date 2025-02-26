<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get user by ID Number
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
        return $user ?: null; 
    }

    public function getUserByPhoneNumber($phone_number) {
        $stmt = $this->conn->prepare("
            SELECT u.* 
            FROM users u
            JOIN user_tokens ut ON u.user_id = ut.user_id
            WHERE ut.phone_number = ?
            LIMIT 1
        ");
        $stmt->execute([$phone_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null; // Returns null if no user is found
    }
    
    // Register new user
    public function createUser($id_number) {
        $stmt = $this->conn->prepare("INSERT INTO users (id_number, state) VALUES (?, 'Active')");
        return $stmt->execute([$id_number]);
    }
    
    public function createUserWithPhone($id_number, $pay_day, $phone_number, $session_id, $expiry, $device_fingerprint = 'USSD Session', $partner_id = null) {
    
        try {

            $phoneNumber = preg_replace('/^27/', '0', $phone_number);
        
                    
            // Insert user
            $stmt = $this->conn->prepare("INSERT INTO users (id_number, state, payday, partner_id) VALUES (?, 'Active', ?, ?)");
            $stmt->execute([$id_number, $pay_day, $partner_id]);
            
            //$state = 'Active';
            //$stmt = $this->conn->prepare("INSERT INTO users (id_number, state, payday) VALUES (?,'Active','last')");
            //$stmt->execute([$id_number]);            
            
            $user_id = $this->conn->lastInsertId();

            // Insert into user_tokens using session_id as token
            $stmt = $this->conn->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                                          VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $phoneNumber, $session_id, $device_fingerprint, $expiry]);            
    
            return $user_id;
            
        } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // SQLSTATE[23000] - Duplicate entry
                    //return "Error: ID number or phone number already exists.";
                    $user = $this->getUserByIdNumber($id_number);
                    $user_id = $user['user_id'];
                    error_log('User ID is: ' . $user_id);
                    // Insert into user_tokens using session_id as token
                    $stmt = $this->conn->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                                                  VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $phoneNumber, $session_id, $device_fingerprint, $expiry]);                     
                    
                    return $user_id;
                    
                }
                return "Error: " . $e->getMessage();
        }
        
    }
        
    
    public function createUserWithPhone_OLD($id_number, $phone_number, $partner_id = null) {
        try {
            // Insert user
            $stmt = $this->conn->prepare("INSERT INTO users (id_number, state, payday, partner_id) VALUES (?, 'Inactive', 'last', ?)");
            $stmt->execute([$id_number, $partner_id]);
            $user_id = $this->conn->lastInsertId();
    
            // Insert into user_tokens
            $stmt = $this->conn->prepare("INSERT INTO user_tokens (user_id, phone_number) VALUES (?, ?)");
            $stmt->execute([$user_id, $phone_number]);
    
            return $user_id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // SQLSTATE[23000] - Duplicate entry
                return "Error: ID number or phone number already exists.";
            }
            return "Error: " . $e->getMessage();
        }
    }


    
}
?>
