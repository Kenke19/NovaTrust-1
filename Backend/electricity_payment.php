<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'jwt.php';

// Authenticate user
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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$disco = trim($data['disco'] ?? ''); // e.g., ikeja-electric
$meter_number = trim($data['meter_number'] ?? '');
$meter_type = trim($data['meter_type'] ?? ''); // prepaid or postpaid
$amount = floatval($data['amount'] ?? 0);
$account_id = intval($data['account_id'] ?? 0);

if (!$disco || !$meter_number || !$meter_type || $amount <= 0 || !$account_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Check user account and balance
$stmt = $pdo->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
$stmt->execute([$account_id, $userId]);
$account = $stmt->fetch();
if (!$account) {
    http_response_code(403);
    echo json_encode(['error' => 'Account not found']);
    exit();
}
if ($account['balance'] < $amount) {
    http_response_code(400);
    echo json_encode(['error' => 'Insufficient balance']);
    exit();
}

// VTpass API details
$apiUrl = 'https://sandbox.vtpass.com/api/pay'; // Use live URL in production
$apiKey = VTPASS_API_KEY;

// Unique request ID
$request_id = date('YmdHi') . uniqid();

$payload = [
    'serviceID' => $disco,
    'billersCode' => $meter_number,
    'variation_code' => $meter_type,
    'amount' => $amount,
    'request_id' => $request_id,
];

// Call VTpass API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result && isset($result['code']) && $result['code'] == '000') {
    // Deduct balance and log transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $account_id]);

    $stmt = $pdo->prepare("INSERT INTO transactions 
        (account_id, type, amount, description, receipt_url, related_account, status, reference, metadata) 
        VALUES (?, 'bill_payment', ?, ?, ?, ?, 'completed', ?, ?)");
    $description = "Electricity payment to " . ucfirst($disco) . " for meter $meter_number";
    $receipt_url = $result['content']['voucher'] ?? null;
    $related_account = $meter_number;
    $metadata = json_encode($result);

    $stmt->execute([
        $account_id,
        $amount,
        $description,
        $receipt_url,
        $related_account,
        $request_id,
        $metadata
    ]);

    $pdo->commit();

    echo json_encode(['message' => 'Electricity payment successful', 'details' => $result]);
} else {
    echo json_encode(['error' => 'Electricity payment failed', 'details' => $result]);
}
