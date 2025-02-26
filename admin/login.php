<?php
require_once __DIR__ . '/core/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

require_once __DIR__ . '/../server/config/settings.php';
require_once __DIR__ . '/../app/utils/Logger.php';
require_once __DIR__ . '/../server/config/database.php';

$error = '';
$max_attempts = 5;  // Limit failed login attempts
$lockout_time = 300; // Lockout for 5 minutes (300 seconds)

//  Check if the user is locked out
if (isset($_SESSION['lockout']) && $_SESSION['lockout'] > time()) {
    $remaining_time = $_SESSION['lockout'] - time();
    die("Too many failed attempts. Please try again in {$remaining_time} seconds.");
}

//  Initialize login attempt counter
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die("CSRF validation failed.");
    }

    $admin_user = getenv('ADMIN_USERNAME') ?: 'admin';
    $admin_pass = getenv('ADMIN_PASSWORD_HASH') ?: password_hash('$2y$10$XcwzLNLSz1JUpLAaYYja5Op9o7PHeCY6OrSUt6gxMCZlb8vUnQ2yK', PASSWORD_DEFAULT);

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    //  Verify credentials
    if ($username === $admin_user && password_verify($password, $admin_pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['failed_attempts'] = 0; //  Reset failed attempts on success

        Logger::logInfo('admin_access', "Admin logged in from {$_SERVER['REMOTE_ADDR']}");

        header("Location: index.php");
        exit;
    } else {
        $_SESSION['failed_attempts']++; //  Increment failed attempts
        Logger::logError('admin_access', "Failed login attempt from {$_SERVER['REMOTE_ADDR']}");

        if ($_SESSION['failed_attempts'] >= $max_attempts) {
            $_SESSION['lockout'] = time() + $lockout_time; //  Set lockout time
            die("Too many failed attempts. Please try again in 5 minutes.");
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>

    <div class="header">
        <img src="/img/CL_logo_350x79.png" alt="Co-Lend Finance Logo" loading="lazy">
    </div> 
    <div class="top-bar">Welcome!</div>
    
    <?php if (!empty($error)): ?>
        <div class="notification-messages error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>    
    
    <div class="wrapper">
        <div class="container">
            <h2>Sign in</h2>

            <form action="login.php" method="POST">
                <?php csrfField(); ?>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Login</button>
            </form>
        </div>
    </div>
<script src="/admin/assets/js/admin.js"></script>
</body>
</html>

