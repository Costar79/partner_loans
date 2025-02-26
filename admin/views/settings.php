<?php

require_once __DIR__ . '/core/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die("CSRF validation failed.");
    }

    // Continue processing settings...
}