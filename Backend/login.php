<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
require 'config.php';
require 'jwt.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit();
}

$username = trim($data['username']);
$password = $data['password'];
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit();
    }
    $stmt = $pdo->prepare("UPDATE users SET previous_login = last_login, last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    global $jwt_secret;
    $issuedAt = time();
    $expire = $issuedAt + 3600; // 1 hour

    $payload = [
        'iat' => $issuedAt,
        'exp' => $expire,
        'id' => $user['id'],
        'username' => $user['username']
    ];

    $jwt = jwtEncode($payload, $jwt_secret);

    echo json_encode(['token' => $jwt]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
