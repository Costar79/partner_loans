<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../config/settings.php';  
require_once '../../app/models/User.php';
require_once '../../app/utils/Logger.php';

$settings = require_once '../config/settings.php';

$database = new Database();
$db = $database->connect();
$user = new User($db);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(400);
    Logger::logError('auth', "Invalid API request method: $method");
    echo json_encode(["error" => "Invalid API request"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id_number']) || !isset($data['phone_number'])) {
    http_response_code(400);
    Logger::logError('auth', "Missing ID Number or Phone Number in request.");
    echo json_encode(["error" => "Missing ID Number or Phone Number."]);
    exit;
}

$id_number = trim($data['id_number']);
$phone_number = trim($data['phone_number']);
$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// ✅ **Step 1: Check if the User Exists**
$existingUser = $user->getUserByIdNumber($id_number);

if (!$existingUser) {
    http_response_code(401);
    Logger::logError('auth', "User not found for ID Number: $id_number");
    echo json_encode(["error" => "User not found. Please register first."]);
    exit;
}

$user_id = $existingUser['user_id'];
$user_state = $existingUser['state'];

// ✅ **Step 2: Block `Inactive` Users**
if ($user_state === 'Inactive') {
    http_response_code(403);
    Logger::logError('auth', "Inactive user attempted login: $user_id");
    echo json_encode(["error" => "Your account is inactive. Access denied."]);
    exit;
}

// ✅ **Step 3: Check if a Token Exists for This User + Device + Phone Number**
$stmt = $db->prepare("SELECT token_id FROM user_tokens WHERE user_id = ? AND device_fingerprint = ? AND phone_number = ? LIMIT 1");
$stmt->execute([$user_id, $device_fingerprint, $phone_number]);
$existingToken = $stmt->fetch(PDO::FETCH_ASSOC);

$new_token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+' . $settings['security']['token_expiry_days'] . ' days'));

try {
    if ($existingToken) {
        // ✅ **Update existing token only if the same device + same phone number**
        $stmt = $db->prepare("UPDATE user_tokens SET token = ?, expires_at = ? WHERE token_id = ?");
        $stmt->execute([$new_token, $expiry, $existingToken['token_id']]);
        
        Logger::logInfo('token_generation', "Updated token for user_id: $user_id with device: $device_fingerprint and phone: $phone_number");
    } else {
        // ✅ **Insert a new token if a new device OR new phone number is detected**
        $stmt = $db->prepare("INSERT INTO user_tokens (user_id, token, device_fingerprint, phone_number, expires_at) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $new_token, $device_fingerprint, $phone_number, $expiry]);

        Logger::logInfo('new_phone_number', "Inserted new token for user_id: $user_id with phone_number: $phone_number (New device or phone number detected)");
    }

    echo json_encode([
        "message" => "Login successful",
        "user_token" => $new_token,
        "state" => $user_state
    ]);
    exit;
} catch (PDOException $e) {
    Logger::logError('general_errors', "Token generation failed for user_id: $user_id - Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Token generation failed."]);
    exit;
}
?>
