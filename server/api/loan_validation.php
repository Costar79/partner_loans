<?php
//require_once '../config/database.php';
require_once '../../app/utils/Logger.php';

function validateLoanRequest($data) {
    if (!isset($data['user_token'])) {
        Logger::logError('loan_validation', "Missing user token in request.");
        return ["error" => "Missing authentication token."];
    }

if (!isset($data['amount']) || !isset($data['term'])) {
    Logger::logError('loan_validation', "❌ Missing loan amount or term. Raw Data: " . json_encode($data));
    return ["error" => "Missing loan amount or term."];
}

$amount = floatval($data['amount']);
$term = intval($data['term']);

if ($amount <= 0 || $term <= 0) {
    Logger::logError('loan_validation', "❌ Invalid loan amount or term. Amount: $amount, Term: $term");
    return ["error" => "Invalid loan amount or term."];
}
/*
    if (!isset($data['amount']) || !isset($data['term'])) {
        Logger::logError('loan_validation', "Missing loan amount or term.");
        return ["error" => "Missing loan amount or term."];
    }

    $amount = floatval($data['amount']);
    $term = intval($data['term']);
*/

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
        Logger::logError('loan_validation', "Invalid loan amount or term.");
        return ["error" => "Invalid loan amount or term."];
    }

    return ["valid" => true, "amount" => $amount, "term" => $term];
}

function daysUntilPayday($payday, $apply_date) {
    $apply_date = new DateTime($apply_date);
    
    // Get the last day of the month for the apply date
    $last_day_of_month = new DateTime($apply_date->format('Y-m-t'));
    $last_day_number = (int)$last_day_of_month->format('d');

    // If payday is 'last', set it to the last day of the month
    if ($payday === 'last') {
        $payday = $last_day_number;
    }

    // Ensure payday is a valid integer between 1 and 31
    if (!is_numeric($payday) || $payday < 1 || $payday > 31) {
        return 0;
    }

    // If payday is greater than the last day of the apply date's month, return 0
    if ((int)$payday > $last_day_number) {
        return 0;
    }

    // Determine the next payday based on the apply date's month
    $next_payday = new DateTime($apply_date->format('Y-m-') . $payday);

    // Return the number of days until payday
    return (int)$apply_date->diff($next_payday)->days;
}

function calculateNextPayday($payday, $apply_date, $following_payday) {
    $apply_date = new DateTime($apply_date); // Ensure apply_date is a DateTime object
    $apply_date_str = $apply_date->format('Y-m-d'); // Convert it to a string

    $year = (int)$apply_date->format('Y');
    $month = (int)$apply_date->format('m');

    // Get the days until the next payday
    $days_until_payday = daysUntilPayday($payday, $apply_date_str); // Pass string

    // Step 1: If ≤ 2 days until payday, move to next payday
    if ($days_until_payday <= 2) {
        $month++;
    }

    // Step 2: If "Following Payday" is checked, move one additional payday forward
    if ($following_payday) {
        $month++;
    }

    // Get the last day of the updated month
    $last_day_of_month = new DateTime("last day of $year-$month");
    $last_day_number = (int)$last_day_of_month->format('d');

    // If payday is 'last', set it to the last day of the new month
    if ($payday === 'last') {
        $payday = $last_day_number;
    }

    // Ensure payday is valid
    if (!is_numeric($payday) || $payday < 1 || $payday > 31 || $payday > $last_day_number) {
        return null; // Invalid payday
    }

    // Determine the next payday
    try {
        $next_payday = new DateTime("$year-$month-$payday");
    } catch (Exception $e) {
        return null; // Handle invalid dates
    }

    return $next_payday->format('Y-m-d');
}

?>
