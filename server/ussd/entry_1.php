<?php
require_once 'sessions.php';

header('Content-Type: text/plain');

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

// Handle session based on ussd_type
if (!sessionExists($session_id)) {
    if ($ussd_type == 1) {
        saveSession($session_id, [
            'phone_number' => $msisdn,
            'ussd_type' => $ussd_type,
            'menu_state' => 'start'
        ]);
        $menu_state = "start";
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
    'input_data' => json_encode($ussd_request)
]);

// Return response as plain text
echo $response['message'];

function handleMenu($state, $input, $session_id, $msisdn) {
    switch ($state) {
        case "start":
            return [
                "message" => "Welcome to Co-Lend Loans\n1. Apply Loan\n2. Check Status",
                "next_state" => "main_menu"
            ];
        case "main_menu":
            if ($input == "1") {
                return ["message" => "Enter loan amount:", "next_state" => "enter_amount"];
            } elseif ($input == "2") {
                return ["message" => "Checking loan status...", "next_state" => "end"];
            }
            return ["message" => "Invalid selection. Choose:\n1. Apply Loan\n2. Check Status", "next_state" => "main_menu"];
        case "enter_amount":
            saveSession($session_id, ['loan_amount' => $input]);
            return ["message" => "Enter loan term (months):", "next_state" => "enter_term"];
        case "enter_term":
            saveSession($session_id, ['loan_term' => $input]);
            return insertLoan($session_id, $msisdn); // Finalizes loan request
        default:
            endSession($session_id);
            return ["message" => "Session expired. Dial again.", "next_state" => "start"];
    }
}

function insertLoan($session_id, $msisdn) {
    global $db;

    $loan_amount = getSession($session_id, 'loan_amount');
    $loan_term = getSession($session_id, 'loan_term');

    if (!$loan_amount || !$loan_term) {
        return ["message" => "Error processing loan request. Please try again.", "next_state" => "start"];
    }

    // Insert loan into loans table
    $stmt = $db->prepare("INSERT INTO loans (phone_number, loan_amount, loan_term, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$msisdn, $loan_amount, $loan_term]);

    // Clear session after inserting loan
    deleteSession($session_id);

    return ["message" => "Loan request submitted for R$loan_amount over $loan_term months. Thank you!", "next_state" => "end"];
}
?>
