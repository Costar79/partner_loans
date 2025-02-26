<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Assuming this script is in /home/costarne/public_html/test/ussd/classes/mail.php

function sendEmail($to, $cc = null, $subject, $body) {
    require __DIR__ . '/../../../wp-includes/PHPMailer/Exception.php';
    require __DIR__ . '/../../../wp-includes/PHPMailer/PHPMailer.php';
    require __DIR__ . '/../../../wp-includes/PHPMailer/SMTP.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'mail.co-star.net';
        $mail->SMTPAuth = true;
        $mail->Username = 'admin@co-star.net';
        $mail->Password = 'b,KKp_4{XNkk';
        $mail->SMTPSecure = 'ssl'; // Use 'tls' or 'ssl' depending on your server
        $mail->Port = 465; // Adjust the port if necessary
        $mail->isHTML(false); // Set to false for plain text 

        $mail->setFrom('admin@co-star.net', 'Co-Lend');
        $mail->addAddress($to, 'Co-Lend Finance Admin');
        // Set CC email if provided
        if ($cc !== null) {
            $mail->addCC($cc);
        }        
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Error: ' . $mail->ErrorInfo;
    }
}
