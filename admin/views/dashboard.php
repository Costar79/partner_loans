<?php

//require_once '/../core/csrf.php';
require_once __DIR__ . '/../core/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die("CSRF validation failed.");
    }

    // Continue processing settings...
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    
    <div class="header">
        <img src="/img/CL_logo_350x79.png" alt="Co-Lend Finance Logo" loading="lazy">
    </div>    

    <div class="top-bar">Admin Panel</div>
    
    <div class="notification-container">
        <div class="notification-messages success" id="successMsg"></div>
    </div>

    <div class="wrapper">
        <div class="admin-navbar">
            <a href="index.php">🏠 Dashboard</a>
            <a href="applications.php">📜 Loan Applications</a>
            <a href="users.php">👤 Manage Users</a>
            <a href="settings.php">⚙️ Settings</a>            
        </div>
        <div class="container">
            <h2>Pending Applications</h2>
            <table class="loan-table">
                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>User ID</th>
                        <th>Amount</th>
                        <th>Term</th>
                        <th>Start</th>
                        <th>Status</th>                        
                    </tr>
                </thead>
                <tbody id="loanTableBody">
                    <!-- Loan applications will be dynamically inserted here -->
                </tbody>
            </table>
        </div>

        <button id="logoutButton" onclick="window.location.href='logout.php'">Logout</button>    
    </div>
<script src="/admin/assets/js/admin.js"></script>
</body>
</html>

