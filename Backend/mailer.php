<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendMail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kenke2003@gmail.com';
        $mail->Password   = 'nlnuvkvcylzosshn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('kenke2003@gmail.com', 'NovaTrust');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return false;
    }
}
//transfer notification
function sendTransferNotification($userEmail, $amount, $recipient, $reference, $status = 'processing') {
    $subject = "Transfer Notification - " . strtoupper($status);
    
    $statusText = ($status === 'success') ? 'completed successfully' : 'is being processed';
    $color = ($status === 'success') ? '#4CAF50' : '#FFC107';
    
    $body = "
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
            .header { background-color: #1976D2; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
            .status { display: inline-block; padding: 5px 10px; background-color: $color; color: white; border-radius: 3px; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>NovaTrust Bank</h2>
            </div>
            <div class='content'>
                <h3>Transfer Notification</h3>
                <p>Your transfer has been $statusText.</p>
                <div class='details'>
                    <p><strong>Amount:</strong> ₦" . number_format($amount, 2) . "</p>
                    <p><strong>Recipient:</strong> $recipient</p>
                    <p><strong>Reference:</strong> $reference</p>
                    <p><strong>Status:</strong> <span class='status'>$status</span></p>
                </div>
                <p>If you didn't initiate this transfer, please contact our support immediately.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " NovaTrust Bank. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($userEmail, $subject, $body);
}
?>