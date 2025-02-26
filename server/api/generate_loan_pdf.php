<?php
ob_start(); // Start output buffering to prevent accidental output

require_once __DIR__ . '/../../server/config/database.php';
require_once __DIR__ . '/../../server/vendor/tcpdf/tcpdf.php';

// Create PDF instance
$pdf = new TCPDF();
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// Fetch Loan Data
if (!isset($_GET['loan_id'])) {
    die("Invalid loan request.");
}

$loan_id = intval($_GET['loan_id']);

$db = new Database();
$conn = $db->connect();

$query = "SELECT l.*, c.* FROM loans AS l INNER JOIN user_contracts AS c ON l.user_contract_id = c.user_contract_id WHERE l.loan_id = :loan_id";

$stmt = $conn->prepare($query);
$stmt->bindParam(":loan_id", $loan_id, PDO::PARAM_INT);
$stmt->execute();
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    die("Loan not found.");
}

$user_contract_id = $loan['user_contract_id'];

$stmt = $conn->prepare("SELECT * FROM user_contracts WHERE user_contract_id = ?");
$stmt->execute([$user_contract_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

$issued_at = $contract['issued_at'];
$contract_uuid = $contract['contract_uuid'];
$reference = substr_replace($contract['reference'], '-', 4, 0);


// Replace placeholders
$data = [
    '{{issued_at}}' => $issued_at,
    '{{contract_uuid}}' => $contract_uuid,
    '{{reference}}' => $reference
];

$contract_html = str_replace(array_keys($data), array_values($data), $contract['user_contract_html']);

class MYPDF extends TCPDF {

    //Page header
    public function Header() {
        // Logo
        $image_file = K_PATH_IMAGES . 'CL_logo_350x79.png';
        $this->Image($image_file, 10, 6, 68, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Set font
        $this->SetFont('helvetica', 'i', 6);

        
        $business_detail = "<b>Co-LAM Financial Services (Pty) Ltd.</b><br>
                            2023/895511/07<br>
                            NCRCP18394<br>";

        // Move to desired position
        //$this->SetXY(0, 15);
        
        // Use writeHTMLCell() to support HTML formatting
        $this->writeHTMLCell(0, 0, 28, 22, $business_detail, 0, 1, false, true, 'L');
        
        // Set font
        $this->SetFont('helvetica', 'I', 8);        
        
        $address = "Postnet Suite 1873<br>
                    Private Bag X9013<br>
                    Ermelo<br>
                    2350<br><br>
                    <b>Tel:</b> 087 265 7920";
        
        $this->writeHTMLCell(0, 0, 24, 8, $address, 0, 1, false, true, 'R');    
        
        $this->SetFont('helvetica', 'I', 8);
        $this->SetY(25);
        $this->Cell(0, 10, 'Digitally Signed Document', 0, 1, 'C');
        
        $this->Line(10, 33, 200, 33);
    
    }

    // Page footer
    public function Footer() {
        // Get page height dynamically
        $pageHeight = $this->getPageHeight();
        
        // Define footer Y position dynamically (e.g., 15mm from bottom)
        $footerY = $pageHeight - 20;
        
        // Draw footer line
        $this->Line(10, $footerY, 200, $footerY);
    
        // Move cursor to correct footer position
        $this->SetY($footerY + 2);
    
        // Set font
        $this->SetFont('helvetica', 'I', 8);
    
        // Footer text (Centered)
        $this->MultiCell(0, 5, "This document has been digitally signed.", 0, 'C');
    
        // Move down slightly for page number
        $this->SetY($footerY + 7); // Adjust Y positioning
    
        // Move to the far right before printing page number
        $this->SetX($this->GetPageWidth() - 30); // Adjust this value as needed
    
        // Page number (aligned right)
        $this->Cell(30, 0, 'Page '.$this->getAliasNumPage().' / '.$this->getAliasNbPages(), 0, 0, 'R');
    }


   
    
}
/*
// Generate PDF Content
$html = "
    <h1>Loan Details</h1>
    <p><strong>Loan ID:</strong> {$loan['loan_id']}</p>
    <p><strong>Applicant:</strong> {$loan['user_id']}</p>
    <p><strong>Amount:</strong> {$loan['amount']}</p>
    <p><strong>Status:</strong> {$loan['status']}</p>
    <p><strong>Date Applied:</strong> {$loan['created_at']}</p>
    <p><strong>UUID:</strong> {$loan['contract_uuid']}</p>
    <p><strong>Contract Data:</strong> {$loan['user_contract_html']}</p>
";
*/

// Create PDF instance
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set margins and header/footer settings
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set document properties
$pdf->SetCreator('Co-Lend Finance');
$pdf->SetAuthor('Co-Lend Finance');
$pdf->SetTitle('Digitally Signed Agreement');

$pdf->SetFont('helvetica', '', 8, '', 'false');

$pdf->AddPage();

$pdf->writeHTML($contract_html);

// Load the certificate file
///home/costarne/public_html/t/server/config
//$certificate = '/home/costarne/public_html/t/server/config/certificate.p12';// Full Certificate path
$certificate = '../../server/config/certificate.p12'; 
$cert_password = 'TMJVsoV@3RND'; // Change to your certificate password

// Read the certificate
$cert = file_get_contents($certificate);

// Set the digital signature
$pdf->setSignature($cert, $cert, $cert_password, '', 2, ['Name' => 'Co-Lend Finance', 'Reason' => 'Loan Agreement', 'Location' => 'South Africa']);

$pdf->Image('../../img/digital_signature.png', 160, 250, 40, 20, 'PNG'); // Visible image (optional)
$pdf->setSignatureAppearance(160, 250, 40, 20);

ob_end_clean(); // Clean the output buffer before sending the PDF
$pdf->Output("Loan_{$loan_id}.pdf", "I"); // Output the PDF to the browser
exit;
?>

