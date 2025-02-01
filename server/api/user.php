<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../../app/models/User.php';
require_once '../../app/models/Partner.php';
require_once '../../app/utils/Logger.php';

$database = new Database();
$db = $database->connect();
$user = new User($db);
$partner = new Partner($db);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(400);
    echo json_encode(["error" => "Invalid API request"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id_number'], $data['phone_number'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields."]);
    exit;
}

$id_number = trim($data['id_number']);
$phone_number = trim($data['phone_number']);
$partner_code = isset($data['partner_code']) ? trim($data['partner_code']) : null;

// ✅ **Step 1: Validate ID Number (Must be 13 digits)**
if (!preg_match("/^[0-9]{13}$/", $id_number)) {
    echo json_encode(["error" => "Invalid ID Number. Must be exactly 13 digits."]);
    exit;
}

// ✅ **Step 2: Validate Phone Number (Must start with 06, 07, or 08 and be 10 digits)**
if (!preg_match("/^(06|07|08)[0-9]{8}$/", $phone_number)) {
    echo json_encode(["error" => "Invalid Phone Number. Must start with 06, 07, or 08 and be 10 digits long."]);
    exit;
}

// ✅ **Step 3: Lookup `partner_id` if `partner_code` is provided**
$partner_id = null;
if ($partner_code) {
    $stmt = $db->prepare("SELECT partner_id FROM partners WHERE partner_code = ?");
    $stmt->execute([$partner_code]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partnerData) {
        $partner_id = $partnerData['partner_id'];
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid partner code"]);
        exit;
    }
}

// ✅ **Step 4: Check if ID Exists**
$existingUser = $user->getUserByIdNumber($id_number);

if ($existingUser) {
    $user_id = $existingUser['user_id'];
    $new_token = bin2hex(random_bytes(32));

    try {
        // ✅ Insert new token for this user
        $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                              VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 180 DAY))");
        $stmt->execute([$user_id, $phone_number, $new_token, $_SERVER['HTTP_USER_AGENT'] ?? '']);

        echo json_encode(["exists" => true, "user_token" => $new_token]);
        exit;
    } catch (PDOException $e) {
        Logger::logError('users', "Token generation failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Token generation failed."]);
        exit;
    }
}

// ✅ **Step 5: Register New User (Including `partner_id` if found)**
try {
    $stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state) VALUES (?, ?, 'Active')");
    $stmt->execute([$id_number, $partner_id]);

    // Get newly inserted user ID
    $user_id = $db->lastInsertId();
    if (!$user_id) {
        throw new Exception("User ID not found after registration.");
    }

    // ✅ Insert user token
    $user_token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                          VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 180 DAY))");
    $stmt->execute([$user_id, $phone_number, $user_token, $_SERVER['HTTP_USER_AGENT'] ?? '']);

    echo json_encode(["message" => "User registered successfully", "user_token" => $user_token]);
    exit;
} catch (Exception $e) {
    Logger::logError('users', "Registration failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Registration failed."]);
    exit;
}
?>
