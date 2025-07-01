<?php
require_once 'config.php';
require_once 'jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;

// Check if account belongs to user
$stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
$stmt->execute([$account_id, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Fetch transactions (include receipt_url)
$stmt = $pdo->prepare("SELECT type, amount, description, related_account, created_at, receipt_url FROM transactions WHERE account_id = ? ORDER BY created_at DESC");
$stmt->execute([$account_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['transactions' => $transactions]);
?>