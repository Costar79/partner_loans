<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../../app/utils/Logger.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_token'])) {
    Logger::logError('loan_api', "Missing user token in request.");
    http_response_code(400);
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}

// ✅ Determine if this is only a "Following Payday" eligibility check
$checkOnly = !isset($data['amount']) || !isset($data['term']);

// ✅ **Step 1: Validate User Token & Fetch User State**
$stmt = $db->prepare("
    SELECT u.user_id, u.state, u.delayed_payback, u.partner_id, p.max_delayed_payback
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

// ✅ Extract user details
$user_id = $user['user_id'];
$user_state = $user['state'];
$delayed_payback = $user['delayed_payback'] ?? 0;
$max_delayed_payback = $user['max_delayed_payback'] ?? 0;
$hasPartner = !is_null($user['partner_id']);

Logger::logInfo('loan_api', "DEBUG: User ID: $user_id, Partner ID: {$user['partner_id']}, Delayed Payback: $delayed_payback, Max Allowed: $max_delayed_payback");

// ✅ If only checking eligibility for "Following Payday", return immediately
if ($checkOnly) {
    $response = ["followingPaydayEligible" => $hasPartner && $max_delayed_payback > 0];
    Logger::logInfo('loan_api', "Returning Following Payday eligibility check: " . json_encode($response));
    echo json_encode($response);
    exit;
}

// ✅ **Step 2: Restrict `Suspended` Users**
if ($user_state === "Suspended") {
    Logger::logError('loan_api', "Loan denied: Suspended user (User ID: $user_id)");
    http_response_code(403);
    echo json_encode(["error" => "Your account is suspended. You cannot apply for new loans."]);
    exit;
}

// ✅ **Step 3: Validate Loan Amount & Terms**
$amount = floatval($data['amount']);
$term = intval($data['term']);

$loanTerms = [
    [500, 749, 1],
    [750, 1000, 2],
    [1001, 1500, 3],
    [1501, 2000, 4],
    [2001, 4000, 5],
    [4001, 8000, 6]
];

$validTerm = false;
foreach ($loanTerms as $range) {
    if ($amount >= $range[0] && $amount <= $range[1]) {
        if ($term <= $range[2]) {
            $validTerm = true;
        }
    }
}

if (!$validTerm) {
    Logger::logError('loan_api', "Invalid loan amount or term (User ID: $user_id)");
    http_response_code(400);
    echo json_encode(["error" => "Invalid loan amount or term."]);
    exit;
}

// ✅ **Step 4: Handle "Following Payday" Option**
$followingPayday = $data['followingPayday'] ?? false;
if ($followingPayday) {
    Logger::logInfo('loan_api', "DEBUG: Checking Following Payday (User ID: $user_id, Delayed Payback: $delayed_payback, Max Allowed: $max_delayed_payback)");

    if ($delayed_payback >= $max_delayed_payback) {
        Logger::logError('loan_api', "Following Payday denied: Limit reached (User ID: $user_id)");
        echo json_encode(["error" => "You have reached your maximum 'Following Payday' limit."]);
        exit;
    }

    // ✅ Increment `delayed_payback`
    $stmt = $db->prepare("UPDATE users SET delayed_payback = delayed_payback + 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// ✅ **Step 5: Store Loan Application in Database**
try {
    $stmt = $db->prepare("INSERT INTO loans (user_id, amount, term, status, settled) VALUES (?, ?, ?, 'Pending', 'No')");
    $stmt->execute([$user_id, $amount, $term]);

    Logger::logInfo('loan_api', "Loan application submitted successfully (User ID: $user_id, Amount: $amount, Term: $term)");
    
    $response = [
        "message" => "Loan application submitted successfully.",
        "followingPaydayEligible" => $hasPartner && $max_delayed_payback > 0
    ];
    
    echo json_encode($response);
    exit;

} catch (PDOException $e) {
    Logger::logError('general_errors', "Loan application failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Loan application failed."]);
    exit;
}
?>
