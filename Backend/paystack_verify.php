<?php

require_once 'config.php';
require_once 'jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
$reference = $data['reference'] ?? '';
$amount = floatval($data['amount'] ?? 0);
$account_id = isset($data['account_id']) ? intval($data['account_id']) : 0;

if (!$reference || $amount <= 0 || $account_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Verify payment with Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . $reference);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!$result || !$result['status'] || $result['data']['status'] !== 'success') {
    http_response_code(400);
    echo json_encode(['error' => 'Payment not verified']);
    exit();
}

// Verify that the account belongs to the user
$stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
$stmt->execute([$account_id, $userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    http_response_code(400);
    echo json_encode(['error' => 'Account not found or does not belong to user']);
    exit();
}

// Update balance
$stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
$stmt->execute([$amount, $account_id]);

// Record transaction (add reference column if you have it)
$stmt = $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description, related_account) VALUES (?, 'deposit', ?, 'Card funding via Paystack', ?)");
$stmt->execute([$account_id, $amount, $reference]);

echo json_encode(['success' => true]);
?>