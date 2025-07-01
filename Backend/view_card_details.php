<?php
require_once 'config.php';
require_once 'jwt.php';

header('Content-Type: application/json');
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header missing']);
    exit();
}
$jwt = str_replace('Bearer ', '', $headers['Authorization']);
$userData = jwtDecode($jwt, $jwt_secret);
if (!$userData || !isset($userData['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit();
}
$userId = $userData['id'];

$data = json_decode(file_get_contents('php://input'), true);
$card_id = intval($data['card_id'] ?? 0);
$pin = $data['pin'] ?? '';

$stmt = $pdo->prepare("SELECT vc.*, a.user_id FROM virtual_cards vc JOIN accounts a ON vc.account_id = a.id WHERE vc.id = ?");
$stmt->execute([$card_id]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card || $card['user_id'] != $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Card not found']);
    exit();
}

if (!password_verify($pin, $card['pin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid PIN']);
    exit();
}

// Return full card details
echo json_encode([
    'card_number' => $card['card_number'],
    'expiry_date' => $card['expiry_date'],
    'cvv' => $card['cvv'],
    'card_holder' => $card['card_holder'],
    'account_id' => $card['account_id']
    // any other details you want to show
]);
