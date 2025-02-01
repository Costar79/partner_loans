<?php

return [
    'logging' => [
        'new_phone_number' => true,   // ✅ Enable/Disable new phone number logging
        'token_generation' => true,   // ✅ Enable/Disable token creation logs
        'general_errors' => true,     // ✅ Enable/Disable system error logging
        'auth' => true,               // ✅ Enable/Disable authentication logs
    ],
    'security' => [
        'token_expiry_days' => 180,   // Token validity duration in days
    ]
];
?>