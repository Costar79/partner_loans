<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$settings = [
    'logging' => [
        'console' => true, 
        'new_phone_number' => true,
        'token_generation' => true,
        'general_errors' => true,
        'auth_api' => true,
        'user_events' => true,
        'user_errors' => true,
        'user_api' => true,
        'loan_api' => true,
        'errors' => true,
        'loan_history' => true
        
    ],
    'security' => [
        'token_expiry_days' => 180
    ]
];

// ✅ Ensure `console` is always explicitly set
$settings['logging']['console'] = (isset($settings['logging']['console']) && $settings['logging']['console'] !== "" && $settings['logging']['console'] !== null) ? filter_var($settings['logging']['console'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false : false;


// ✅ Debug log to check if `console` is being read correctly
//error_log("🔍 Debug: Console Logging Setting in settings.php = " . json_encode($settings['logging']['console']));
//error_log("🚀 settings.php is executing!()" . json_encode($settings['logging']['console']));

// ✅ Serve JSON output when accessed via browser
/*
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === "settings.php") {
    header("Content-Type: application/json");
    echo json_encode(["console" => $settings['logging']['console']]);
    exit;
}
*/
// ✅ Print settings when accessed via CLI (SSH)
//print_r($settings);

// ✅ Return settings array when included in PHP scripts
//return $settings;

if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === "settings.php") {
    header("Content-Type: application/json");
    echo json_encode(["console" => $settings['logging']['console']]);
    exit;
}

return $settings;
