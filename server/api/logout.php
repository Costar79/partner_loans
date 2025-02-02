<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../../app/utils/Logger.php';

$database = new Database();
$db = $database->connect();

session_start();

if (!isset($_COOKIE['user_token'])) {
    http_response_code(400);
    Logger::logError('logout', "Logout failed: No user token provided.");
    echo json_encode(["error" => "No active session"]);
    exit;
}

$user_token = $_COOKIE['user_token'];

// ✅ **Step 1: Verify Token Exists**
$stmt = $db->prepare("SELECT user_id FROM user_tokens WHERE token = ?");
$stmt->execute([$user_token]);
$existingToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingToken) {
    http_response_code(401);
    Logger::logError('logout', "Logout failed: Invalid token.");
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}

$user_id = $existingToken['user_id'];

try {
    // ✅ **Step 2: Delete Only the Current Token**
    $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ?");
    $stmt->execute([$user_token]);

    // ✅ **Step 3: Clear Cookies & Session**
    setcookie("user_token", "", time() - 3600, "/", "", true, true);
    session_destroy();

    Logger::logInfo('logout', "User ID $user_id logged out successfully.");

    echo json_encode(["message" => "Logout successful"]);
    exit;
} catch (PDOException $e) {
    Logger::logError('logout', "Logout failed for User ID $user_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Logout failed. Please try again"]);
    exit;
}
?>
