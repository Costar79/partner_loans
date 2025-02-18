<?php
require_once 'sessions.php';
require_once 'functions.php';
require_once '../../app/models/Partner.php';
require_once '../../app/models/User.php';
require_once '../../app/utils/Logger.php';
require_once '../api/user_functions.php';
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: text/plain');

Logger::logInfo("ussd_entry", "Incoming USSD Request: " . json_encode($_GET));

// Initialize database connection
$database = new Database();
$db = $database->connect();

Logger::logInfo("ussd_entry", "Connection to DB:" . $database->getDbName());

// Load settings
$settings = require '../config/settings.php';
$expiry_days = $settings['security']['token_expiry_days'] ?? 180;
$expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));

// Initialize models
$partnerModel = new Partner($db);
$userModel = new User($db);

// Retrieve GET parameters
$msisdn = $_GET['ussd_msisdn'] ?? null;
$session_id = $_GET['ussd_session_id'] ?? null;
$ussd_request = $_GET['ussd_request'] ?? '';
$ussd_type = $_GET['ussd_type'] ?? null;

// Validate request
if (!$msisdn || !$session_id || is_null($ussd_type)) {
    echo "Invalid request.";
    exit;
}

// Handle new session (ussd_type = 1)
if (!sessionExists($session_id)) {
    if ($ussd_type == 1) {
        // **Extract the USSD code from the first request**
        $ussd_code = $ussd_request; 

        // Retrieve user by phone number
        $phoneNumber = preg_replace('/^27/', '0', $msisdn);
        $user = $userModel->getUserByPhoneNumber($phoneNumber);
        
        
        if ($user) {
            // Existing user
            $user_id = $user['user_id'];
            $partner_id = $user['partner_id'];
            $user_state = $user['state']; // Fetch user state
            $menu_state = "main_menu"; // INITIAL Display menu item
            
            error_log("DEBUG: User Information:" . $user_id);
        
            $stmt = $db->prepare("SELECT token_id FROM user_tokens WHERE user_id = ? AND phone_number = ? LIMIT 1");
            $stmt->execute([$user_id, $phoneNumber]);
            $existingTokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $token_id = $existingTokenData['token_id'];
            
            $stmt = $db->prepare("UPDATE user_tokens SET expires_at = ?, token = ? WHERE token_id = ?");
            $stmt->execute([$expiry, $session_id, $token_id]);            
            
        } else {
            // New User - Extract partner ID from USSD code
            preg_match('/\*120\*9902\*(\d{1,3})#/', $ussd_code, $matches);
            $partner_ussd_id = $matches[1] ?? null;

            // Retrieve partner_id using the USSD partner code
            $partner = $partnerModel->getPartnerByUSSD_ID($partner_ussd_id);
            $partner_id = $partner ? $partner['partner_id'] : null;

            // New users must first enter their ID number
            $menu_state = "validate_id";
        }

        // **Store all required session data**
        saveSession($session_id, [
            'user_id' => $user_id ?? null,
            'phone_number' => $msisdn,
            'partner_id' => $partner_id,
            'ussd_code' => $ussd_code, // Store the dialed USSD code
            'user_state' => $user_state ?? 'Inactive', // New users should be 'Inactive' by default
            'ussd_type' => $ussd_type,
            'menu_state' => $menu_state // Determine if user goes to validate_id or main_menu
        ]);

        if ($menu_state === "validate_id") {
            echo "Enter your South African ID Number:";
            exit;
        }
        
    } else {
        echo "Session not found. Dial again.";
        exit;
    }
} else {
    $menu_state = getSession($session_id, 'menu_state');
}

// Process menu logic
$response = handleMenu($menu_state, $ussd_request, $session_id, $msisdn);

// Update session state
saveSession($session_id, [
    'menu_state' => $response['next_state'],
    'ussd_type' => $ussd_type,
    'input_data' => $ussd_request
]);

// **Centralized Session Closure: Check if session should end**
if ($response['next_state'] === "end") {
    echo endSession($session_id, "Co-Lend Finance\n\n"
                                . "NCRCP : 18394\n\n"
                                . $response['message']);
} else {
    echo $response['message'];
}
?>
