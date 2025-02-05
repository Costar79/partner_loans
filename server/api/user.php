<?php
header("Content-Type: application/json");
require_once 'user_functions.php';
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../../app/models/User.php';
require_once '../../app/models/Partner.php';
require_once '../../app/utils/Logger.php';

Logger::logInfo('user_api', "Raw POST Data: " . file_get_contents("php://input"));

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

Logger::logInfo('user_api', "Received JSON Data: " . json_encode($data));

if (!is_array($data)) {
    Logger::logError('user_api', "Invalid JSON format. Raw Input: " . file_get_contents("php://input"));
    echo json_encode(["error" => "Invalid JSON format."]);
    exit;
}

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
$max_delayed_payback = 0;

if (!isValidSouthAfricanID($id_number)) {
    Logger::logError('user_api', "Invalid ID Number: $id_number");
    echo json_encode(["error" => "Invalid South African ID Number."]);
    exit;
}

if (!preg_match("/^[0-9]{13}$/", $id_number)) {
    Logger::logError('user_api', "Invalid ID Number: $id_number");
    echo json_encode(["error" => "Invalid ID Number. Must be exactly 13 digits."]);
    exit;
}

if (!preg_match("/^(06|07|08)[0-9]{8}$/", $phone_number)) {
    Logger::logError('user_api', "Invalid Phone Number: $phone_number");
    echo json_encode(["error" => "Invalid Phone Number."]);
    exit;
}

// **Step 1: Retrieve User Data**
$existingUser = $user->getUserByIdNumber($id_number);
if ($existingUser) {
    $user_id = $existingUser['user_id'];
    $partner_id = $existingUser['partner_id'];
    $delayed_payback = $existingUser['delayed_payback'];
} else {
    $user_id = null;
}

// **Step 2: Fetch Partner's max_delayed_payback if partner_id is available**
if ($partner_id) {
    $stmt = $db->prepare("SELECT max_delayed_payback FROM partners WHERE partner_id = ?");
    $stmt->execute([$partner_id]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($partnerData) {
        $max_delayed_payback = $partnerData['max_delayed_payback'];
    }
}

Logger::logInfo('user_api', "User Data - User ID: $user_id, Partner ID: $partner_id, Delayed Payback: $delayed_payback, Max Delayed Payback: $max_delayed_payback");

// **If the user does not exist, register them**
if (!$existingUser) {
    try {
        $stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state, delayed_payback) VALUES (?, ?, 'Active', ?)");
        $stmt->execute([$id_number, $partner_id, $delayed_payback]);

        $user_id = $db->lastInsertId();
        if (!$user_id) {
            Logger::logError('user_api', "User registration failed: User ID not found after insertion.");
            echo json_encode(["error" => "User registration failed."]);
            exit;
        }
        Logger::logInfo('user_api', "New user registered: $user_id | Partner ID: " . ($partner_id ?? 'None') . " | Delayed Payback: $delayed_payback");
    } catch (Exception $e) {
        Logger::logError('user_api', "User registration failed: " . $e->getMessage());
        echo json_encode(["error" => "User registration failed."]);
        exit;
    }
}

// **Step 3: Retrieve or Generate Token**
$stmt = $db->prepare("SELECT token FROM user_tokens WHERE user_id = ?");
$stmt->execute([$user_id]);
$existingToken = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingToken) {
    $new_token = $existingToken['token'];
    Logger::logInfo('user_api', "Using existing token for user_id: $user_id");
} else {
    $new_token = bin2hex(random_bytes(32));
    $expiry_days = $settings['security']['token_expiry_days'] ?? 180;
    $expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
    $device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    try {
        $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $phone_number, $new_token, $device_fingerprint, $expiry]);
        Logger::logInfo('user_api', "New token issued for user_id: $user_id");
    } catch (PDOException $e) {
        Logger::logError('user_api', "Token generation failed: " . $e->getMessage());
        echo json_encode(["error" => "Token generation failed."]);
        exit;
    }
}

// **Step 4: Return User Data**
echo json_encode([
    "message" => "Login successful",
    "user_token" => $new_token,
    "id_number" => $id_number,
    "phone_number" => $phone_number,
    "delayed_payback" => $delayed_payback,
    "max_delayed_payback" => $max_delayed_payback,
    "partner_id" => $partner_id
]);
exit;
?>
