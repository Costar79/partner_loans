<?php
require_once '../config/database.php';
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
?>
