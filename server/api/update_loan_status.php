<?php
ob_clean();

header('Content-Type: application/json');

require_once __DIR__ . '/../../server/config/database.php';
require_once 'loan_validation.php';

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = $_POST['loan_id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if (!$loan_id || !$new_status) {
        echo json_encode(["success" => false, "message" => "Invalid request"]);
        exit;
    }

    // Check user_contract_id for this loan
    $stmt = $conn->prepare("SELECT user_contract_id FROM loans WHERE loan_id = ?");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $existing_contract_id = $loan['user_contract_id'];
    
    Logger::logInfo('loan_api', '$existing_contract_id:' . $existing_contract_id);

    if (!$loan) {
        echo json_encode(["success" => false, "message" => "Loan not found"]);
        exit;
    }

    // If user_contract_id is greater than 0, a contract was already created
    if ($new_status === "Approved" && $existing_contract_id === 0) {
        $contract = getLatestContractId($db);
        $contract_id = $contract->contract_id;
        $raw_contract_html = $contract->raw_contract_html;
        $uuid = generateUUID();
        $reference = generateReference();
    
        // Insert new contract into user_contracts
        $stmt = $conn->prepare("INSERT INTO user_contracts (contract_uuid, reference, user_contract_html, contract_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$uuid, $reference, $raw_contract_html, $contract_id]);
        $user_contract_id = $conn->lastInsertId();
    }else{
        $user_contract_id = $existing_contract_id;   
    }

    // Update loans table with user_contract_id and new status
    $query = "
        UPDATE loans
        SET status = :status,
            user_contract_id = :user_contract_id
        WHERE loan_id = :loan_id
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
    $stmt->bindParam(':user_contract_id', $user_contract_id, PDO::PARAM_INT);
    $stmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        if ($new_status === "Approved" && $existing_contract_id === 0) {
            echo json_encode(["success" => true, "message" => "Loan status updated and new contract created."]);
        } else {
            echo json_encode(["success" => true, "message" => "Loan status updated."]);    
        }    
    } else {
        echo json_encode(["success" => false, "message" => "Database update failed"]);
    }
}
?>
