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

if (!is_array($data) || !isset($data['id_number']) || !isset($data['phone_number'])) {
    http_response_code(400);
    Logger::logError('user_api', "Missing ID Number or Phone Number.");
    echo json_encode(["error" => "Missing ID Number or Phone Number."]);
    exit;
}

$id_number = strip_tags(trim($data['id_number']));
$phone_number = strip_tags(trim($data['phone_number']));
$partner_code = isset($data['partner_code']) ? filter_var($data['partner_code'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
$payDate = isset($data['payday']) ? trim($data['payday']) : "last";
//$payday = (!empty($data['payday']) && is_string($data['payday'])) ? trim($data['payday']) : "last";
$partner_id = null;
$delayed_payback = 0;
$max_delayed_payback = 0;

Logger::logInfo('user_api', "Partner Code: " . $partner_code);

$payday = isset($data['payday']) ? trim($data['payday']) : "last"; 

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

Logger::logInfo("user_api", '$payDate: ' . $payDate);

$payday = setValidPayday($payDate);

Logger::logInfo("user_api", "Storing payday: $payday for user ID: $id_number");


// **Step 1: Retrieve User Data**
$existingUser = $user->getUserByIdNumber($id_number);
if ($existingUser) {
    $user_id = $existingUser['user_id'];
    $user_partner_id = $existingUser['partner_id'];
    $delayed_payback = $existingUser['delayed_payback'];
} else {
    $user_id = null;
}
/*
$result = $partner->getPartnerByCode($partner_code);
$partner_id = $result ? $result['partner_id'] : null;

$partnerData = !empty($partner_code) ? $partner->getPartnerByCode($partner_code) : false;
$partner_id = $partnerData ? $partnerData['partner_id'] : null;


// **Step 2: Fetch Partner's max_delayed_payback if partner_id is available**
if ($partner_id) {
    $stmt = $db->prepare("SELECT max_delayed_payback FROM partners WHERE partner_id = ?");
    $stmt->execute([$partner_id]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($partnerData) {
        $max_delayed_payback = $partnerData['max_delayed_payback'];
    }
}
*/

// Get partner_id only ONCE
$partnerData = !empty($partner_code) ? $partner->getPartnerByCode($partner_code) : false;
$partner_id = $partnerData ? $partnerData['partner_id'] : null;

if (is_null($partner_id)){
$partner_id = $partner->getPartnerByID($user_partner_id);
}

Logger::logInfo('user_api', "Partner ID Retrieved: " . ($partner_id ?? 'NULL'));

// Step 2: Fetch Partner's max_delayed_payback
$max_delayed_payback = 0; // Default value in case no match
if ($partner_id) {
    $stmt = $db->prepare("SELECT max_delayed_payback FROM partners WHERE partner_id = ?");
    $stmt->execute([$partner_id]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partnerData) {
        $max_delayed_payback = (int) $partnerData['max_delayed_payback'];
        Logger::logInfo('user_api', "max_delayed_payback Retrieved: $max_delayed_payback for Partner ID: $partner_id");
    } else {
        Logger::logError('user_api', "No max_delayed_payback found for Partner ID: $partner_id");
    }
}

// **If the user does not exist, register them**
if (!$existingUser) {
    if (!empty($partner_code)) {
        
        // Ensure it's between 1 and 5 alphanumeric characters
        if (!preg_match("/^[0-9A-Za-z]{1,5}$/", $partner_code)) {
            Logger::logError('user_api', "Invalid Partner Code Format: $partner_code");
            //http_response_code(400);
            echo json_encode(["error" => "Invalid partner code format. Must be 1 to 5 alphanumeric characters."]);
            exit;
        }    
        
        $stmt = $db->prepare("SELECT partner_id FROM partners WHERE partner_code = ?");
        $stmt->execute([$partner_code]);
        $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$partnerData) {
            Logger::logError('user_api', "Invalid Partner Code: $partner_code");
            echo json_encode(["error" => "Invalid partner code"]);
            exit;
        }

        $partner_id = $partnerData['partner_id'];
    } else {
        $partner_id = null;
        $delayed_payback = 0;
    }

    try {
        Logger::logInfo("user_api", "Storing payday: $payday for user ID: $id_number");
        //$stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state, payday) VALUES (?, ?, 'Active', ?");
        $stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state, payday) VALUES (?, ?, 'Active', ?)");
        $stmt->execute([$id_number, $partner_id, $payday]);

        $user_id = $db->lastInsertId();
        if (!$user_id) {
            Logger::logError('user_api', "User registration failed.");
            echo json_encode(["error" => "User registration failed."]);
            exit;
        }
        Logger::logInfo('user_api', "New user registered: $user_id | Partner ID: " . ($partner_id ?? 'None'));
    } catch (Exception $e) {
        Logger::logError('user_api', "User registration failed: " . $e->getMessage());
        echo json_encode(["error" => "User registration failed."]);
        exit;
    }
}

// **Step 3: Check Existing Token and Phone Number**
$stmt = $db->prepare("SELECT phone_number, token, expires_at FROM user_tokens WHERE user_id = ? AND phone_number = ? LIMIT 1");
$stmt->execute([$user_id, $phone_number]);
$existingTokenData = $stmt->fetch(PDO::FETCH_ASSOC);

$expiry_days = $settings['security']['token_expiry_days'] ?? 180;
$expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// ✅ **Step 4: Update Expiry if Token Exists**
$new_token = $existingTokenData ? $existingTokenData['token'] : null;

if ($existingTokenData) {
    if (strtotime($existingTokenData['expires_at']) < time()) {
        // ✅ Token expired, update expiry without creating a new token
        $stmt = $db->prepare("UPDATE user_tokens SET expires_at = ? WHERE user_id = ? AND phone_number = ?");
        $stmt->execute([$expiry, $user_id, $phone_number]);
        Logger::logInfo('user_api', "Expired token updated for user_id: $user_id");
        $new_token = $existingTokenData['token']; // Ensure token is reused
    } else {
        Logger::logInfo('user_api', "Token still valid for user_id: $user_id, no update needed.");
        $new_token = $existingTokenData['token']; // Ensure token is reused
    }
} else {
    // ✅ No existing token for this phone number, create a new one
    Logger::logInfo('user_api', "Generating new token for user_id: $user_id and phone_number: $phone_number");
    $new_token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $phone_number, $new_token, $device_fingerprint, $expiry]);
}

$response = [
    "message" => "Login successful",
    "user_token" => $new_token,
    "id_number" => $id_number,
    "phone_number" => $phone_number,
    "delayed_payback" => $delayed_payback,
    "max_delayed_payback" => $max_delayed_payback,
    "partner_id" => $partner_id
];

Logger::logInfo("user_api", "Response sent from user.php: " . json_encode($response));

// ✅ **Step 5: Return User Data**
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
