<?php
require_once 'config.php';
require_once 'jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
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

$from_account_id = intval($data['from_account_id'] ?? 0);
$to_account_number = trim($data['to_account_number'] ?? '');
$amount = floatval($data['amount'] ?? 0);

if ($from_account_id <= 0 || !$to_account_number || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Check from_account belongs to user and has enough balance
$stmt = $pdo->prepare("SELECT id, balance FROM accounts WHERE id = ? AND user_id = ?");
$stmt->execute([$from_account_id, $userId]);
$from_account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$from_account) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied or account not found']);
    exit();
}
if ($from_account['balance'] < $amount) {
    http_response_code(400);
    echo json_encode(['error' => 'Insufficient funds']);
    exit();
}

// Check to_account exists
$stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_number = ?");
$stmt->execute([$to_account_number]);
$to_account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$to_account) {
    http_response_code(404);
    echo json_encode(['error' => 'Destination account not found']);
    exit();
}

// Begin transaction
$pdo->beginTransaction();
try {
    // Deduct from sender
    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $from_account_id]);

    // Add to receiver
    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $to_account['id']]);

    // Record transactions
    $stmt = $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description, related_account) VALUES (?, 'transfer_out', ?, ?, ?)");
    $stmt->execute([$from_account_id, $amount, 'Transfer to ' . $to_account_number, $to_account_number]);

    $stmt = $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description, related_account) VALUES (?, 'transfer_in', ?, ?, ?)");
    $stmt->execute([$to_account['id'], $amount, 'Transfer from ' . $from_account_id, $from_account_id]);

    $pdo->commit();
    echo json_encode(['message' => 'Transfer successful']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Transfer failed: ' . $e->getMessage()]);
}
?>