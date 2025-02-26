<?php
function getDayOrLast($day) {
    // Get the total number of days in the current month
    $daysInCurrentMonth = date('t');

    // If the value is 0 or greater/equal than the last day of the month, return 'last'
    if ($day == 0 || $day >= $daysInCurrentMonth) {
        return 'last';
    }

    // Otherwise, return the original day
    return $day;
}

function getMaxLoanTerm($amount) {
    // Define loan term limits based on amount
    $loanTerms = [
        [500, 749, 1],
        [750, 1000, 2],
        [1001, 1500, 3],
        [1501, 2000, 4],
        [2001, 4000, 5],
        [4001, 8000, 6]
    ];

    foreach ($loanTerms as $range) {
        if ($amount >= $range[0] && $amount <= $range[1]) {
            return $range[2]; // Return the maximum term for the amount
        }
    }

    return null; // No valid term found
}

function handleMenu($state, $input, $session_id, $msisdn) {
    global $db, $userModel, $settings;

    error_log("DEBUG: Handling menu for session '$session_id' - Current state: '$state', Input: '$input'");

    $expiry_days = $settings['security']['token_expiry_days'] ?? 180;
    $expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
    $device_fingerprint = "USSD Session";

    // Fetch session data
    $session_data = getSession($session_id);
    $user_state = $session_data['user_state'] ?? 'Inactive'; // Use stored state

    switch ($state) {

        case "enter_payday":
            
            appendSessionData($session_id, ['menu_state' => 'enter_payday', 'id_number' => $input]);    
            
            return [
                "message" => "NEXT PAY DAY\n\n"
                           . "Enter a number between 1 & 31.\n\n"
                           . "To indicate you next payday.\n\n",
                "next_state" => "validate_id"
            ];
       
        case "validate_id":
            
            appendSessionData($session_id, ['menu_state' => 'validate_id', 'pay_day' => $input]);
            
            $id_number = $session_data['id_number'] ?? null;

            error_log("DEBUG: Current ID Number '$id_number'");
            
            if (!isValidSouthAfricanID($id_number)) {
                return [
                    "message" => endSession($session_id, "Invalid ID Number."),
                    "next_state" => "end"
                ];
            }
            
            // Fetch session data
            $partner_id = $session_data['partner_id'] ?? null;
            
            //error_log("DEBUG - SESSION INFO: '$session_id' - Phone: '$msisdn', Input: '$input'");
            
            $pay_day = getDayOrLast($input);
                
            // Create user with valid ID
            $user_id = $userModel->createUserWithPhone($id_number, $pay_day, $msisdn, $session_id, $expiry, $device_fingerprint, $partner_id);

            // **If user creation failed, immediately end session with an error message**
            if (is_string($user_id) && str_contains($user_id, "Error:")) {
                return [
                    "message" => endSession($session_id, $user_id),
                    "next_state" => "end"
                ];
            }
                return [
                    "message" => endSession($session_id, "Co-Lend Finance\n\n"
                                                            . "NCRCP : 18394\n\n"
                    .                                         "Thank you, please log in again and make your loan request."),
                    "next_state" => "end"
                ];

        case "main_menu":
            // **Check if user is Active**
            if ($user_state !== "Active") {
                return [
                    "message" => endSession($session_id, "We are still preparing to serve you, please try again later."),
                    "next_state" => "end"
                ];
            }
/*
            if ($input == "1") {
                appendSessionData($session_id, ['menu_state' => 'enter_amount']);
                return ["message" => "Enter loan amount:", "next_state" => "enter_amount"];
            } elseif ($input == "2") {
                appendSessionData($session_id, ['menu_state' => 'end']);
                return ["message" => "Your loan status request is being processed. You will receive an update soon.", "next_state" => "end"];
            }
            $phoneNumber
*/

            //appendSessionData($session_id, ['menu_state' => 'enter_amount']);

            return [
                "message" => "Welcome to Co-Lend Finance\n\n"
                           . "NCRCP : 18394\n\n"
                           . "Enter your loan amount, below and send",
                "next_state" => "enter_term"
            ];

/*
// For future reference
        case "enter_amount":
            appendSessionData($session_id, ['menu_state' => 'enter_term', 'loan_amount' => $input]);
            return ["message" => "Enter loan term (months):", "next_state" => "enter_term"];
*/
        case "enter_term":
            
            appendSessionData($session_id, ['menu_state' => 'enter_term', 'loan_amount' => $input]);
            
            $max_term = getMaxLoanTerm($input);
            if ($max_term === null) {
                return [
                    "message" => "Co-Lend Finance\n\n"
                                ."Invalid loan amount.\n"
                                ."Please enter a valid amount between R500 and R8000.\n",
                    "next_state" => "main_menu"
                ];
            }
            
            return [
                "message" => "Co-Lend Finance\n\n"
                            ."Enter a loan term, below.\n\n"
                            ."Maximum $max_term months\n",
                "next_state" => "end_request"
            ];            
            
        case "end_request":
            appendSessionData($session_id, ['menu_state' => 'end_request', 'loan_term' => $input]);
            return applyLoanRequest($session_id, $msisdn);
            return ["message" => endSession($session_id,"Your loan status request is being processed. You will receive an update soon."), "next_state" => "end"];
        default:
            return ["message" => endSession($session_id,"Session ended. Dial again."), "next_state" => "end"];
    }
}


function applyLoanRequest($session_id, $msisdn) {

    global $db;

    // Fetch session data
    $session_data = getSession($session_id);
    $loan_amount = $session_data['loan_amount'] ?? null;
    $loan_term = $session_data['loan_term'] ?? null;
    $user_token = $session_id; // Use USSD session_id as token
 
    error_log("Loan Amount: $loan_amount | Loan Term: $loan_term | User Token: $user_token");

    if (!$loan_amount || !$loan_term) {
        return [
            "message" => endSession($session_id, "Error: Loan request data is missing."),
            "next_state" => "end"
        ];
    }

    // API URL using localhost for fast internal API calls
    //$loan_api_url = "http://localhost/t/server/api/loan.php";
    $loan_api_url = "https://t.co-lend.finance/server/api/loan.php"; 

    // Prepare the request data
    $postData = json_encode([
        "user_token" => $user_token,
        "amount" => $loan_amount,
        "term" => $loan_term
    ]);

    error_log("DEBUG: Loan API Request Data: " . $postData);

    // Set HTTP headers and request options
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n",
            "method" => "POST",
            "content" => $postData,
            "timeout" => 10, // Set timeout for stability
            "ignore_errors" => true // Capture HTTP errors
        ]
    ];

    $context = stream_context_create($options);

    // Make the API request
    $response = file_get_contents($loan_api_url, false, $context);

    error_log("DEBUG: Loan API Response: " . $response);

    // Decode API response
    $response_data = json_decode($response, true);

    // Handle API errors
    if (!$response_data || isset($response_data['error'])) {
        $error_message = $response_data['error'] ?? "Loan request failed.";
        return [
            "message" => endSession($session_id, "ERROR\n\n"
                                                . "Co-Lend Finance\n\n"
                                                . "NCRCP : 18394\n\n"
                                                . "$error_message"),
            "next_state" => "end"
        ];
    }

    // Success response
    return [
        "message" => endSession($session_id, "Your loan request has been submitted successfully."),
        "next_state" => "end"
    ];
}


//*************************************************
//IN TESTING
//*************************************************
function callUserAPI(){
    // Define API URL for user registration/login
    $userApiUrl = "https://yourdomain.com/server/api/user.php";
    
    // Prepare data to send
    $postData = [
        "id_number" => "1234567890123", // Replace with actual ID from user input
        "phone_number" => $phoneNumber,
        "partner_code" => null, // Modify if you retrieve a partner code
        "payday" => "last"
    ];
    
    // Convert data to JSON
    $jsonData = json_encode($postData);
    
    // Initialize cURL
    $ch = curl_init($userApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    
    // Execute request and fetch response
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log response
    Logger::logInfo("ussd_entry", "Response from user.php: $response");
    
    // Process API response
    $responseData = json_decode($response, true);
    if ($http_code !== 200 || !isset($responseData['user_token'])) {
        echo "Error processing request. Please try again later.";
        exit;
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



function handleMenu_OLD($state, $input, $session_id, $msisdn) {
    global $db, $userModel, $settings;

    error_log("DEBUG: Handling menu for session '$session_id' - Current state: '$state', Input: '$input'");

    $expiry_days = $settings['security']['token_expiry_days'] ?? 180;
    $expiry = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
    $device_fingerprint = "USSD Session";

    switch ($state) {
        case "validate_id":
            if (!isValidSouthAfricanID($input)) {
                return [
                    "message" => endSession($session_id, "Invalid ID Number. Session closed."),
                    "next_state" => "end"
                ];
            }

            // Fetch session data
            $session_data = getSession($session_id);
            $partner_id = $session_data['partner_id'] ?? null;

            // Create user with valid ID, using session_id as token
            $user_id = $userModel->createUserWithPhone($input, $msisdn, $partner_id, $session_id, $expiry, $device_fingerprint);

            // **If user creation failed, immediately end session with an error message**
            if (is_string($user_id) && str_contains($user_id, "Error:")) {
                return [
                    "message" => endSession($session_id, $user_id), // **Show error & close session**
                    "next_state" => "end"
                ];
            }

            // Store user_id and move to main menu
            appendSessionData($session_id, ['menu_state' => 'main_menu', 'user_id' => $user_id, 'id_number' => $input]);

            return [
                "message" => "Welcome to Co-Lend Loans\n1. Apply Loan\n2. Check Status",
                "next_state" => "main_menu"
            ];
    }
}
