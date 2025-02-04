<?php
header("Content-Type: application/json");
require_once 'user_functions.php';
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../../app/models/User.php';
require_once '../../app/models/Partner.php';
require_once '../../app/utils/Logger.php';

Logger::logInfo('user_api', "User API accessed. Received Data: " . json_encode($_POST));

$settings = require_once '../config/settings.php';

$database = new Database();
$db = $database->connect();
$user = new User($db);
$partner = new Partner($db);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(400);
    Logger::logError('user_api', "Invalid API request method: $method");
    echo json_encode(["error" => "Invalid API request"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
Logger::logInfo('user_api', "Received Data: " . json_encode($data));

if (!isset($data['id_number']) || !isset($data['phone_number'])) {
    http_response_code(400);
    Logger::logError('user_api', "Missing required fields. Received: " . json_encode($data));
    echo json_encode(["error" => "Missing ID Number or Phone Number."]);
    exit;
}

$id_number = strip_tags(trim($data['id_number']));
$phone_number = strip_tags(trim($data['phone_number']));
$partner_code = isset($data['partner_code']) ? filter_var($data['partner_code'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
$partner_id = null;
$delayed_payback = 0;



if (!isValidSouthAfricanID($id_number)) {
    Logger::logError('user_api', "Invalid ID Number: $id_number");
    echo json_encode(["error" => "Invalid South African ID Number."]);
    exit;
}

// Ensure it's exactly 13 digits and meets additional criteria (custom validation rules can be added)
if (!preg_match("/^[0-9]{13}$/", $id_number) || $id_number === "1234567890123") {
    Logger::logError('user_api', "Invalid or blocked ID Number: $id_number");
    echo json_encode(["error" => "Invalid ID Number. Must be exactly 13 digits and not restricted."]);
    exit;
}
if (!preg_match("/^[0-9]{13}$/", $id_number)) {
    Logger::logError('user_api', "Invalid ID Number: $id_number");
    echo json_encode(["error" => "Invalid ID Number. Must be exactly 13 digits."]);
    exit;
}

// ✅**lidate Phone Number (Must start with 06, 07, or 08 and be 10 digits)**
if (!preg_match("/^(06|07|08)[0-9]{8}$/", $phone_number)) {
    Logger::logError('user_api', "Invalid Phone Number: $phone_number");
    echo json_encode(["error" => "Invalid Phone Number."]);
    exit;
}

// ✅ **Step 1: Lookup Partner ID and Set `delayed_payback` Based on `max_delayed_payback`**
if (!empty($partner_code)) {
    $stmt = $db->prepare("SELECT partner_id, max_delayed_payback FROM partners WHERE partner_code = ?");
    $stmt->execute([$partner_code]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partnerData) {
        $partner_id = $partnerData['partner_id'];
        $delayed_payback = $partnerData['max_delayed_payback']; // ✅ Set from `max_delayed_payback`
    } else {
        Logger::logError('user_api', "Invalid Partner Code: $partner_code");
        http_response_code(400);
        echo json_encode(["error" => "Invalid partner code"]);
        exit;
    }
}

Logger::logInfo('user_api', "✅ Retrieved Partner ID: " . ($partner_id ?? 'None') . ", Delayed Payback: $delayed_payback");


// ✅ **Step 2: Check If User Already Exists**
$existingUser = $user->getUserByIdNumber($id_number);
if (!$existingUser) {
    try {
        // ✅ **Insert new user with `partner_id` and `delayed_payback`**
        $stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state, delayed_payback) VALUES (?, ?, 'Active', ?)");
        $stmt->execute([$id_number, $partner_id, $delayed_payback]);
        
        $user_id = $db->lastInsertId();
        if (!$user_id) {
            Logger::logError('user_api', "❌ User registration failed: User ID not found after insertion.");
            echo json_encode(["error" => "User registration failed."]);
            exit;
        }
        Logger::logInfo('user_api', "✅ New user registered: $user_id | Partner ID: " . ($partner_id ?? 'None') . " | Delayed Payback: $delayed_payback");

    } catch (Exception $e) {
        Logger::logError('user_api', "❌ User registration failed: " . $e->getMessage());
        echo json_encode(["error" => "User registration failed."]);
        exit;
    }
} else {
    $user_id = $existingUser['user_id'];
}

// ✅ **Step 3: Generate Token & Log In User**
$new_token = bin2hex(random_bytes(32));
$expiry_days = $settings['security']['token_expiry_days'] ?? 180;
$expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    // ✅ Remove old tokens for the same device before issuing a new one
    $stmt = $db->prepare("DELETE FROM user_tokens WHERE user_id = ? AND device_fingerprint = ?");
    $stmt->execute([$user_id, $device_fingerprint]);

    $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $phone_number, $new_token, $device_fingerprint, $expiry]);
    Logger::logInfo('user_api', "✅ Token issued for user_id: $user_id");

    echo json_encode([
        "message" => "Login successful",
        "user_token" => $new_token,
        "delayed_payback" => $delayed_payback,
        "partner_id" => $partner_id
    ]);
    exit;
} catch (PDOException $e) {
    Logger::logError('user_api', "❌ Token generation failed: " . $e->getMessage());
    echo json_encode(["error" => "Token generation failed."]);
    exit;
}
?>
