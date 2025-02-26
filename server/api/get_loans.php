<?php
require_once __DIR__ . '/../../server/config/database.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->connect();

// Detect the referring page
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

if (strpos($referrer, 'index.php') !== false) {
    $query = "SELECT loan_id, user_id, amount, term, status, start_date, created_at FROM loans WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 10";
    $pageType = "dashboard";
//} elseif (strpos($referrer, 'applications.php') !== false) {
} elseif (strpos($referrer, 'applications.php') !== false || strpos($_SERVER['REQUEST_URI'], 'applications.php') !== false) {    
    $query = "SELECT loan_id, user_id, amount, term, status, start_date, created_at, user_contract_id FROM loans WHERE status != 'Pending' ORDER BY created_at DESC LIMIT 10";
    $pageType = "applications";
} else {
    echo json_encode(["success" => false, "message" => "Invalid request source"]);
    exit;
}

$stmt = $conn->prepare($query);
$stmt->execute();
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$loans || count($loans) === 0) {
    echo json_encode(["success" => true, "html" => "<tr><td colspan='8'>No loans found</td></tr>"]);
    exit;
}

// Generate HTML for the loan table
$html = "";
foreach ($loans as $row) {
    $html .= "<tr>
                <td>{$row['loan_id']}</td>
                <td>{$row['user_id']}</td>
                <td>{$row['amount']}</td>
                <td>{$row['term']}</td>
                <td>{$row['start_date']}</td>";

    if ($pageType === "dashboard") {
        // Dashboard: Status dropdown (no print button)
        $html .= "<td>
                    <select class='loan-status' data-loan-id='{$row['loan_id']}'>
                        <option value='Pending' " . ($row['status'] == 'Pending' ? 'selected' : '') . ">Pending</option>
                        <option value='Approved' " . ($row['status'] == 'Approved' ? 'selected' : '') . ">Approved</option>
                        <option value='Rejected' " . ($row['status'] == 'Rejected' ? 'selected' : '') . ">Rejected</option>
                    </select>
                  </td>";
    } elseif ($pageType === "applications") {
        // Applications Page: Status (text) + Print Button
        $html .= "<td>
                    <select class='loan-status' data-loan-id='{$row['loan_id']}'>
                        <option value='Pending' " . ($row['status'] == 'Pending' ? 'selected' : '') . ">Pending</option>
                        <option value='Approved' " . ($row['status'] == 'Approved' ? 'selected' : '') . ">Approved</option>
                        <option value='Rejected' " . ($row['status'] == 'Rejected' ? 'selected' : '') . ">Rejected</option>
                    </select>
                  </td>
                  <td>" . ($row['user_contract_id'] > 0 
                            ? "<button class='print-loan' data-loan-id='{$row['loan_id']}'>PDF</button>" 
                            : "No Contract") . "</td>";

    }

    $html .= "</tr>";
}

// Ensure no extra whitespace in JSON response
$html = trim(preg_replace('/\s+/', ' ', $html));

echo json_encode(["success" => true, "html" => $html]);
exit;
?>
