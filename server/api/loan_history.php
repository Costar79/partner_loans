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
    Logger::logError('loan_history', "❌ JSON Decode Error: " . json_last_error_msg());
    echo json_encode(["error" => "Invalid JSON format."]);
    exit;
}
/*
if (!isset($rawData['user_token'])) {
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}
*/
if (!isset($data['user_token']) || empty($data['user_token'])) {
    Logger::logError('loan_history', "❌ Missing authentication token. Received: " . json_encode($data));
    echo json_encode(["error" => "Missing authentication token."]);
    exit;
}
$user_token = trim($data['user_token']);
Logger::logInfo('loan_history', "✅ Using user_token: " . $user_token);


// ✅ Fetch Loan History for the User
try {
    $stmt = $db->prepare("
        SELECT created_at, amount, term, status, settled
        FROM loans 
        WHERE user_id = (SELECT user_id FROM user_tokens WHERE token = ?) 
        AND (settled = 'No' OR (settled = 'Yes' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)))
        ORDER BY created_at DESC
    ");
    $stmt->execute([$data['user_token']]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Logger::logInfo('loan_history', "✅ Loan history retrieved successfully.");
    echo json_encode(["loans" => $loans]);
    exit;

} catch (PDOException $e) {
    Logger::logError('loan_history', "❌ Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Failed to retrieve loan history."]);
    exit;
}


echo json_encode(["loans" => $loans]);
exit;
?>
