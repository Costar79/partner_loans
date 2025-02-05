<?php
header("Content-Type: application/json");
require_once '../config/database.php';
require_once '../../app/utils/Logger.php';

$database = new Database();
$db = $database->connect();

$rawData = file_get_contents("php://input");
Logger::logInfo('loan_history', "Raw input received: " . $rawData);

$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Logger::logError('loan_history', "JSON Decode Error: " . json_last_error_msg());
    echo json_encode(["error" => "Invalid JSON format."]);
    exit;
}

if (!isset($data['user_token']) || empty($data['user_token'])) {
    Logger::logError('loan_history', "Missing authentication token. Received: " . json_encode($data));
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}

$user_token = trim($data['user_token']);
Logger::logInfo('loan_history', "Using user_token: " . $user_token);

try {
    // ✅ Retrieve `user_id` from `user_token`
    $stmt = $db->prepare("SELECT user_id FROM user_tokens WHERE token = ?");
    $stmt->execute([$user_token]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        Logger::logError('loan_history', "Invalid user token: " . $user_token);
        echo json_encode(["error" => "Invalid user token"]);
        exit;
    }

    $user_id = $userRow['user_id'];
    Logger::logInfo('loan_history', "User ID Retrieved: " . $user_id);

    // ✅ Fetch Loan History
    $stmt = $db->prepare("
        SELECT created_at, amount, term, status, settled
        FROM loans 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$loans) {
        Logger::logInfo('loan_history', "No loan history found for user ID: " . $user_id);
        echo json_encode(["loans" => []]);
        exit;
    }

    Logger::logInfo('loan_history', "Loan History Query Result: " . json_encode($loans));

    echo json_encode(["loans" => $loans]);
    exit;

} catch (PDOException $e) {
    Logger::logError('loan_history', "Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Failed to retrieve loan history."]);
    exit;
}
?>
