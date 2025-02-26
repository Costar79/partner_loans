<?php
require_once 'config.php';

class SMSSender {

    public function sendSMS($recipient, $message) {
        // Define the permanent variables inside the function
        $apiUsername = SMS_USER;
        $apiPassword = SMS_PASS;
        $apiBaseUrl = SMS_URL;
        
        $data = [
            'Type' => 'sendparam',
            'username' => $apiUsername,
            'password' => $apiPassword,
            'numto' => $recipient,
            'data1' => $message,
        ];

        $ch = curl_init($apiBaseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Error: ' . curl_error($ch));
        } else {
            error_log('Result: ' . $result);
        }

        curl_close($ch);
    }
}