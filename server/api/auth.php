<?php
ob_start(); // Start output buffering to prevent accidental output
header("Content-Type: application/json");

require_once '../config/database.php';
require_once '../config/settings.php';  
require_once '../../app/models/User.php';
require_once '../../app/utils/Logger.php';

Logger::logInfo('api_call', "API script executed: " . __FILE__);

$database = new Database();
$db = $database->connect();
$user = new User($db);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(400);
    Logger::logError('auth_api', "Invalid API request method: " . $_SERVER["REQUEST_METHOD"]);
    ob_clean();
    echo json_encode(["error" => "Invalid API request"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$user_token = $data['user_token'] ?? null;
$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

if (!$user_token) {
    http_response_code(400);
    Logger::logError('auth_api', "Missing user token in request.");
    ob_clean();
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}

// Step 1: Check if Token is Valid & Not Expired**
$stmt = $db->prepare("
    SELECT u.user_id, u.state, u.id_number, t.phone_number, t.expires_at 
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
    ob_clean();
    echo json_encode(["error" => "Invalid token. Please log in again."]);
    exit;
}

// ** Step 2: Block Expired Tokens**
if (strtotime($userData["expires_at"]) < time()) {
    http_response_code(401);
    Logger::logError('auth_api', "Token expired for user: " . $userData["user_id"]);
    ob_clean();
    echo json_encode(["error" => "Session expired. Please log in again."]);
    exit;
}

// ** Step 3: Block `Inactive` Users**
if ($userData["state"] === "Inactive") {
    http_response_code(403);
    Logger::logError('auth_api', "Inactive user attempted login: " . $userData["user_id"]);
    ob_clean();
    echo json_encode(["error" => "Your account is inactive. Access denied."]);
    exit;
}

// ** Step 4: Authentication Success**
Logger::logInfo('auth_api', "User authenticated: " . $userData["user_id"]);
ob_clean();
echo json_encode([
    "message" => "Authentication successful",
    "user_id" => $userData["user_id"],
    "id_number" => $userData["id_number"],
    "phone_number" => $userData["phone_number"],
    "state" => $userData["state"]
]);

ob_end_flush(); // Ensure output buffer is properly flushed
exit;
?>
