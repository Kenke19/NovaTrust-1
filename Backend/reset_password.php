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

if (!isset($data['token'], $data['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Token and new password are required']);
    exit();
}

$token = $data['token'];
$newPassword = $data['new_password'];

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit();
}

try {
    // Fetch user by token and get email for optional notification
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token']);
        exit();
    }

    if (strtotime($user['reset_token_expiry']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Token expired']);
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
    $stmt->execute([$passwordHash, $user['id']]);

    
    require_once 'mailer.php';
    $subject = "Your NovaTrust password was changed";
    $body = "Hello,<br>Your password has been successfully reset. If you did not perform this action, please contact support immediately.";
    sendMail($user['email'], $subject, $body);
    

    echo json_encode(['message' => 'Password has been reset successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>