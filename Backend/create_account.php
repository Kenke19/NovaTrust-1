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

$account_type = strtolower(trim($data['account_type'] ?? ''));
$initial_balance = floatval($data['initial_balance'] ?? 0);

if (!$account_type || !in_array($account_type, ['checking', 'savings'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid account type']);
    exit();
}

// KYC check
$stmt = $pdo->prepare("SELECT kyc_status FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['kyc_status'] !== 'verified') {
    http_response_code(403);
    echo json_encode(['error' => 'You must complete KYC verification before opening an account.']);
    exit();
}

// Generate a random account number
do {
    $account_number = strval(rand(1000000000, 9999999999));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE account_number = ?");
    $stmt->execute([$account_number]);
} while ($stmt->fetchColumn() > 0);

//insert new account
$stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, ?, ?)");
$stmt->execute([$userId, $account_number, $account_type, $initial_balance]);

echo json_encode(['message' => 'Account created successfully', 'account_number' => $account_number]);
?>