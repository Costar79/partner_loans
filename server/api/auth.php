<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../config/settings.php';  
require_once '../../app/models/User.php';
require_once '../../app/utils/Logger.php';

Logger::logInfo('api_call', "API script executed: " . __FILE__);

$settings = require_once '../config/settings.php';

$database = new Database();
$db = $database->connect();
$user = new User($db);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(400);
    Logger::logError('auth_api', "Invalid API request method: $method");
    echo json_encode(["error" => "Invalid API request"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// ✅ Log received data
Logger::logError('auth_api', "Received Data: " . json_encode($data));

$user_token = $data['user_token'] ?? null;
$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

if (!$user_token) {
    http_response_code(400);
    Logger::logError('auth_api', "Missing user token in request.");
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}

// ✅ Retrieve user based on token (No need for id_number & phone_number)
$stmt = $db->prepare("
    SELECT u.user_id, u.state, t.token_id 
    FROM users u
    JOIN user_tokens t ON u.user_id = t.user_id
    WHERE t.token = ?
    LIMIT 1
");
$stmt->execute([$user_token]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    http_response_code(401);
    Logger::logError('auth_api', "User not found for token: $user_token");
    echo json_encode(["error" => "Invalid token. Please log in again."]);
    exit;
}

$user_id = $userData['user_id'];
$user_state = $userData['state'];

// ✅ **Step 2: Block `Inactive` Users**
if ($user_state === 'Inactive') {
    http_response_code(403);
    Logger::logError('auth_api', "Inactive user attempted login: $user_id");
    echo json_encode(["error" => "Your account is inactive. Access denied."]);
    exit;
}

// ✅ **Step 3: Return Success Response**
echo json_encode([
    "message" => "Authentication successful",
    "user_id" => $user_id,
    "state" => $user_state
]);
exit;
?>
