<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If ALL values are set to true any category (filename) used in the Logger functions will be saved
// Any category (filename) is being written in /logs/ no matter if its in the logging array or not

$settings = [
    'logging' => [
        'console' => false, 
        'security' => true,
        'config_debug' => false,
        'token_generation' => true,
        'general_errors' => true,
        'auth_api' => true,
        'user_events' => true,
        'user_errors' => true,
        'user_api' => true,
        'loan_api' => true,
        'errors' => true,
        'loan_history' => true,
        'admin_access' => false,
        'ussd_entry' => true
        
    ],
    'security' => [
        'token_expiry_days' => 180
    ]   
];

//  Ensure `console` is always explicitly set
$settings['logging']['console'] = (isset($settings['logging']['console']) && $settings['logging']['console'] !== "" && $settings['logging']['console'] !== null) ? filter_var($settings['logging']['console'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false : false;

//  Serve JSON output when accessed via browser
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === "settings.php") {
    header("Content-Type: application/json");
    echo json_encode(["console" => $settings['logging']['console']]);
    exit;
}

return $settings;
