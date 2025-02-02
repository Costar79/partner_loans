<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../../app/models/User.php';
require_once '../../app/models/Partner.php';
require_once '../../app/utils/Logger.php';

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

// ✅ Debugging: Log received data
Logger::logError('user_api', "Received Data: " . json_encode($data));

if (!isset($data['id_number']) || !isset($data['phone_number'])) {
    http_response_code(400);
    Logger::logError('user_api', "Missing required fields. Received: " . json_encode($data));
    echo json_encode(["error" => "Missing ID Number or Phone Number."]);
    exit;
}


if (!isset($data['id_number']) || !isset($data['phone_number'])) {
    http_response_code(400);
    Logger::logError('user_api', "Missing required fields.");
    echo json_encode(["error" => "Missing required fields."]);
    exit;
}

$id_number = strip_tags(trim($data['id_number']));
$phone_number = strip_tags(trim($data['phone_number']));
$partner_code = isset($data['partner_code']) ? strip_tags(trim($data['partner_code'])) : null;

// ✅ **Step 1: Validate ID Number (Must be 13 digits, valid date, age check, citizenship check, and checksum validation)**
function isValidSouthAfricanID($id_number) {
    if (!preg_match('/^[0-9]{13}$/', $id_number)) {
        Logger::logError('user_api', "Failed ID Validation - Incorrect Format: $id_number");
        return false;
    }

    $dob = substr($id_number, 0, 6);
    $birth_date = DateTime::createFromFormat('ymd', $dob);
    if (!$birth_date || $birth_date->format('ymd') !== $dob) {
        Logger::logError('user_api', "Failed ID Validation - Invalid Date: $dob in ID $id_number");
        return false;
    }

    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    if ($age > 63) {
        Logger::logError('user_api', "Failed ID Validation - Age Exceeded ($age years) for ID $id_number");
        return false;
    }

    // Validate citizenship (11th digit must be 0 or 1)
    $citizenship = substr($id_number, 10, 1);
    if ($citizenship !== '0' && $citizenship !== '1') {
        Logger::logError('user_api', "Failed ID Validation - Invalid Citizenship Digit ($citizenship) in ID $id_number");
        return false;
    }

    // Validate Luhn checksum
    if (!validateLuhn($id_number)) {
        Logger::logError('user_api', "Failed ID Validation - Invalid Luhn Checksum for ID $id_number");
        return false;
    }

    return true;
}

function validateLuhn($number) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10 == 0);
}

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

// ✅ **Step 2: Validate Phone Number (Must start with 06, 07, or 08 and be 10 digits)**
if (!preg_match("/^(06|07|08)[0-9]{8}$/", $phone_number)) {
    Logger::logError('user_api', "Invalid Phone Number: $phone_number");
    echo json_encode(["error" => "Invalid Phone Number."]);
    exit;
}

// ✅ **Step 3: Sanitize & Validate `partner_code`**
if (!empty($partner_code)) {
    $partner_code = filter_var($partner_code, FILTER_SANITIZE_STRING);
    
    // Ensure it's between 1 and 5 alphanumeric characters
    if (!preg_match("/^[0-9A-Za-z]{1,5}$/", $partner_code)) {
        Logger::logError('user_api', "Invalid Partner Code: $partner_code");
        http_response_code(400);
        echo json_encode(["error" => "Invalid partner code format. Must be 1 to 5 alphanumeric characters."]);
        exit;
    }

    $stmt = $db->prepare("SELECT partner_id FROM partners WHERE partner_code = ?");
    $stmt->execute([$partner_code]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partnerData) {
        $partner_id = $partnerData['partner_id'];
    } else {
        Logger::logError('user_api', "Invalid Partner Code Lookup: $partner_code");
        http_response_code(400);
        echo json_encode(["error" => "Invalid partner code"]);
        exit;
    }
} else {
    $partner_id = null; // No partner code provided
}

// ✅ **Step 5: Check if ID Exists**
$existingUser = $user->getUserByIdNumber($id_number);

if (!$existingUser) {
    // ✅ **Auto-register new user**
    try {
        $stmt = $db->prepare("INSERT INTO users (id_number, state) VALUES (?, 'Active')");
        $stmt->execute([$id_number]);

        $user_id = $db->lastInsertId();
        if (!$user_id) {
            throw new Exception("User ID not found after registration.");
        }

        Logger::logInfo('user_api', "New user registered: $user_id");

    } catch (Exception $e) {
        Logger::logError('general_errors', "User registration failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "User registration failed."]);
        exit;
    }
} else {
    $user_id = $existingUser['user_id'];
}

// ✅ **Generate Token & Log In User**
$new_token = bin2hex(random_bytes(32));
Logger::logError('user_api', "Debugging settings: " . json_encode($settings));
//$expiry = date('Y-m-d H:i:s', strtotime('+' . $settings['security']['token_expiry_days'] . ' days'));
$expiry_days = $settings['security']['token_expiry_days'] ?? 180; // Fallback to 180 days if missing
$expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));

$device_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    // ✅ Check if a token already exists for this user & device
    $stmt = $db->prepare("SELECT token_id FROM user_tokens WHERE user_id = ? AND device_fingerprint = ? LIMIT 1");
    $stmt->execute([$user_id, $device_fingerprint]);
    $existingToken = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingToken) {
        // ✅ **Update the existing token instead of creating a new row**
        $stmt = $db->prepare("UPDATE user_tokens SET token = ?, expires_at = ? WHERE token_id = ?");
        $stmt->execute([$new_token, $expiry, $existingToken['token_id']]);
        Logger::logInfo('user_api', "Updated token for user_id: $user_id on existing device.");
    } else {
        // ✅ **Insert a new token for a new device**
        $stmt = $db->prepare("INSERT INTO user_tokens (user_id, phone_number, token, device_fingerprint, expires_at) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $phone_number, $new_token, $device_fingerprint, $expiry]);
        Logger::logInfo('user_api', "Inserted new token for user_id: $user_id on a new device.");
    }

    echo json_encode(["message" => "Login successful", "user_token" => $new_token]);
    exit;
} catch (PDOException $e) {
    Logger::logError('general_errors', "Token generation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Token generation failed."]);
    exit;
}



// ✅ **Step 6: Register New User**
try {
    $stmt = $db->prepare("INSERT INTO users (id_number, partner_id, state) VALUES (?, ?, 'Active')");
    $stmt->execute([$id_number, $partner_id]);

    $user_id = $db->lastInsertId();
    if (!$user_id) {
        Logger::logError('general_errors', "Exception Error: User ID not found after registration.");
        throw new Exception("User ID not found after registration.");
    }

    Logger::logInfo('user_api', "New user registered: $user_id");
    echo json_encode(["message" => "User registered successfully"]);
    exit;
} catch (Exception $e) {
    Logger::logError('general_errors', "Registration failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Registration failed."]);
    exit;
}
