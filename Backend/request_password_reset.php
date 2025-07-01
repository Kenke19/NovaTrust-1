<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header('Content-Type: application/json');
require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email is required']);
    exit();
}

$email = trim($data['email']);

try {
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['message' => 'If the email exists, a reset link will be sent']);
        exit();
    }

    $token = bin2hex(random_bytes(50));
    $expiry = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
    $stmt->execute([$token, $expiry, $user['email']]);

    // Send email with reset link to the email from the database
    require_once 'mailer.php';
    $resetLink = "https://localhost/NovaTrust/frontend/reset-password.php?token=$token";
    $subject = "NovaTrust Password Reset";
    $body = "Click the following link to reset your password: <a href='$resetLink'>$resetLink</a>";

    sendMail($user['email'], $subject, $body);

    echo json_encode(['message' => 'If the email exists, a reset link will be sent']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>