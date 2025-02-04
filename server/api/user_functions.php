<?php
require_once '../../app/utils/Logger.php';

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
?>