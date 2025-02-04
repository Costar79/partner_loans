<?php
$staging_domain = 't.co-lend.finance';
$production_domain = 'a.co-lend.finance';

$current_domain = $_SERVER['HTTP_HOST'];

// ✅ Load credentials from `.env`
$env_file = __DIR__ . "/.env";
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
} else {
    die("⚠️ Missing .env file. Please create one.");
}

if ($current_domain === $staging_domain) {
    define('ENV', 'staging');
    define('DB_NAME', $env['DB_NAME']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);
} else {
    define('ENV', 'production');
    define('DB_NAME', $env['PROD_DB_NAME']);
    define('DB_USER', $env['PROD_DB_USER']);
    define('DB_PASS', $env['PROD_DB_PASS']);
}

define('DB_HOST', $env['DB_HOST']);
?>
