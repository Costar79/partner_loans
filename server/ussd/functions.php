<?php
function handleMenu($state, $input, $session_id, $msisdn) {
    error_log("DEBUG: Handling menu for session '$session_id' - Current state: '$state', Input: '$input'");

    switch ($state) {
        case "start":
            appendSessionData($session_id, ['menu_state' => 'main_menu']);
            return [
                "message" => "Welcome to Co-Lend Loans\n1. Apply Loan\n2. Check Status",
                "next_state" => "main_menu"
            ];
        case "main_menu":
            if ($input == "1") {
                appendSessionData($session_id, ['menu_state' => 'enter_amount']);
                return ["message" => "Enter loan amount:", "next_state" => "enter_amount"];
            } elseif ($input == "2") {
                appendSessionData($session_id, ['menu_state' => 'end']);
                return ["message" => "Your loan status request is being processed. You will receive an update soon.", "next_state" => "end"];
            }
            return ["message" => "Invalid selection. Choose:\n1. Apply Loan\n2. Check Status", "next_state" => "main_menu"];
        case "enter_amount":
            appendSessionData($session_id, ['menu_state' => 'enter_term', 'loan_amount' => $input]);
            return ["message" => "Enter loan term (months):", "next_state" => "enter_term"];
        case "enter_term":
            appendSessionData($session_id, ['menu_state' => 'processing', 'loan_term' => $input]);
            return insertLoan($session_id, $msisdn);
        default:
            return ["message" => "Session expired. Dial again.", "next_state" => "start"];
    }
}


//*************************************************
//FOR TESTING ONLY insertLoan()
//*************************************************
function insertLoan($session_id, $msisdn) {
    global $db;

    // Fetch the latest session data
    $stmt = $db->prepare("SELECT session_data FROM ussd_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $session_data = isset($result['session_data']) && is_string($result['session_data'])
        ? json_decode($result['session_data'], true)
        : [];

    if (!is_array($session_data)) {
        $session_data = [];
    }

    $loan_amount = $session_data['loan_amount'] ?? null;
    $loan_term = $session_data['loan_term'] ?? null;
    $phone_number = $session_data['phone_number'] ?? $msisdn;

    error_log("DEBUG: Retrieving loan data in insertLoan(): phone_number='$phone_number', loan_amount='$loan_amount', loan_term='$loan_term'");

    if (!$loan_amount || !$loan_term || !$phone_number) {
        error_log("ERROR: Missing loan data in session: phone_number='$phone_number', loan_amount='$loan_amount', loan_term='$loan_term'");
        return ["message" => "Error processing loan request. Please try again.", "next_state" => "start"];
    }

    $stmt = $db->prepare("INSERT INTO loans (phone_number, loan_amount, loan_term, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$phone_number, $loan_amount, $loan_term]);

    appendSessionData($session_id, ['menu_state' => 'end']);

    return ["message" => "Your loan request for R$loan_amount over $loan_term months has been submitted. You will receive an SMS shortly.", "next_state" => "end"];
}
