<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../../app/utils/Logger.php';
require_once 'loan_validation.php';

Logger::logInfo('loan_api', "API script executed: " . __FILE__);

$database = new Database();
$db = $database->connect();

// ✅ Capture raw input for debugging
$rawData = file_get_contents("php://input");
Logger::logInfo('loan_api', "🚀 Raw input received: " . $rawData);

$data = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    Logger::logError('loan_api', "❌ JSON Decode Error: " . json_last_error_msg());
    echo json_encode(["error" => "Invalid JSON format."]);
    exit;
}

// ✅ Ensure `user_token` is provided
if (!isset($data['user_token'])) {
    Logger::logError('loan_api', "Missing user token in request.");
    http_response_code(400);
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}

// ✅ Step 1: Validate User Token & Fetch User State
$stmt = $db->prepare("
    SELECT u.user_id, u.state, u.delayed_payback, u.partner_id, u.payday, p.max_delayed_payback
    FROM users u
    JOIN user_tokens ut ON u.user_id = ut.user_id
    LEFT JOIN partners p ON u.partner_id = p.partner_id
    WHERE ut.token = ?
    LIMIT 1
");
$stmt->execute([$data['user_token']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    Logger::logError('loan_api', "Unauthorized loan request: Invalid token.");
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user_id = $user['user_id'];
$user_state = $user['state'];
$delayed_payback = $user['delayed_payback'] ?? 0;
$max_delayed_payback = $user['max_delayed_payback'] ?? 0;
$payday= $user['payday'];
$hasPartner = !is_null($user['partner_id']);

Logger::logInfo('loan_api', "DEBUG: User ID: $user_id, Partner ID: {$user['partner_id']}, Delayed Payback: $delayed_payback, Max Allowed: $max_delayed_payback");

// ✅ Step 2: Restrict Suspended Users
if ($user_state === "Suspended") {
    Logger::logError('loan_api', "Loan denied: Suspended user (User ID: $user_id)");
    http_response_code(403);
    echo json_encode(["error" => "Your account is suspended. You cannot apply for new loans."]);
    exit;
}

// ✅ Step 3: Handle Loan Status Check (No `amount` or `term` Required)
if (!isset($data['amount']) && !isset($data['term'])) {
    Logger::logInfo('loan_api', "✅ Processing loan status check for User ID: " . $user_id);

    $stmt = $db->prepare("SELECT COUNT(*) AS pending_loans FROM loans WHERE user_id = ? AND status = 'Pending'");
    $stmt->execute([$user_id]);
    $pendingLoan = $stmt->fetch(PDO::FETCH_ASSOC)['pending_loans'] > 0;

    Logger::logInfo('loan_api', "✅ Loan check: User ID: $user_id, Pending: " . ($pendingLoan ? "Yes" : "No"));

    echo json_encode([
        "hasPendingLoan" => $pendingLoan,
        "followingPaydayEligible" => $hasPartner && $max_delayed_payback > 0
    ]);
    exit;
}

// ✅ Step 4: Process Loan Application
$validationResult = validateLoanRequest($data);
if (isset($validationResult['error'])) {
    Logger::logError('loan_api', "❌ Loan validation failed: " . $validationResult['error']);
    echo json_encode(["error" => $validationResult['error']]);
    exit;
}

$amount = $validationResult['amount'];
$term = $validationResult['term'];

Logger::logInfo('loan_api', "🚀 Processing new loan application for User ID: " . $user_id);

// ✅ Step 5: Handle "Following Payday" Option
$followingPayday = $data['followingPayday'] ?? false;
if ($followingPayday) {
    Logger::logInfo('loan_api', "DEBUG: Checking Following Payday (User ID: $user_id, Delayed Payback: $delayed_payback, Max Allowed: $max_delayed_payback)");

    if ($delayed_payback >= $max_delayed_payback) {
        Logger::logError('loan_api', "Following Payday denied: Limit reached (User ID: $user_id)");
        echo json_encode(["error" => "You have reached your maximum 'Following Payday' limit."]);
        exit;
    } 
/*
This must be done on approval only
    // ✅ Increment `delayed_payback`
    $stmt = $db->prepare("UPDATE users SET delayed_payback = delayed_payback + 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
*/    
}

$start_date = calculateNextPayday($payday,date('Y-m-d'),$followingPayday);

    // ✅ Step 6: Store Loan Application in Database
    try {
        $stmt = $db->prepare("INSERT INTO loans (user_id, amount, term, status, settled, start_date) VALUES (?, ?, ?, 'Pending', 'No',?)");
        $stmt->execute([$user_id, $amount, $term, $start_date]);
    
        Logger::logInfo('loan_api', "✅ Loan application submitted successfully (User ID: $user_id, Amount: $amount, Term: $term)");
    
        echo json_encode([
            "message" => "Loan application submitted successfully.",
            "followingPaydayEligible" => $hasPartner && $max_delayed_payback > 0
        ]);
        exit;
    
    } catch (PDOException $e) {
        Logger::logError('general_errors', "Loan application failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Loan application failed."]);
        exit;
    } 

Logger::logError('loan_api', "❌ Invalid API request.");
http_response_code(400);
echo json_encode(["error" => "Invalid API request"]);
exit;
?>
