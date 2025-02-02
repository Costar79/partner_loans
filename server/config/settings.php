<?php

return [
    'logging' => [
        'new_phone_number' => true,   // ✅ Enable/Disable new phone number logging
        'token_generation' => true,   // ✅ Enable/Disable token creation logs
        'general_errors' => true,     // ✅ Enable/Disable system error logging
        'auth_api' => true,               // ✅ Enable/Disable authentication logs
        'user_events' => true,        // ✅ Enable/Disable user event logging (registrations, logins)
        'user_errors' => true,        // ✅ Enable/Disable user error logging (invalid input, failed registration)
        'user_api' => true,  
        'errors' => true,
        'console' => true // ✅ Enables logging to the browser console
    ],
    'security' => [
        'token_expiry_days' => 180,   // Token validity duration in days
    ]
];
?>

