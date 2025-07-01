<?php
require_once 'config.php';
require_once 'jwt.php';
require_once 'mailer.php';

// Headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/test_write.log', 'Test log at ' . date('c'));
// Validate JWT token
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

// Validate and sanitize input
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

$userId = $userData['id'];
$from_account_id = intval($data['from_account_id'] ?? 0);
$bank_code = trim($data['bank_code'] ?? '');
$account_number = trim($data['account_number'] ?? '');
$amount = floatval($data['amount'] ?? 0);

// Enhanced input validation
$errors = [];
if ($from_account_id <= 0) $errors[] = 'Invalid account';
if (empty($bank_code)) $errors[] = 'Bank code is required';
if (empty($account_number) || !preg_match('/^\d{10}$/', $account_number)) $errors[] = 'Account number must be 10 digits';
if ($amount <= 0 || $amount > 1000000) $errors[] = 'Amount must be between ₦0.01 and ₦1,000,000';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
    exit();
}

// Check account ownership and balance (with row locking)
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id, balance FROM accounts WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$from_account_id, $userId]);
    $from_account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$from_account) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Account not found or access denied']);
        exit();
    }
    
    if ($from_account['balance'] < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient funds']);
        exit();
    }
    
    // Deduct amount immediately
    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $from_account_id]);
    
    // Create pending transaction record
    $stmt = $pdo->prepare("INSERT INTO transactions 
        (account_id, type, amount, description, related_account, status) 
        VALUES (?, 'external_transfer', ?, ?, ?, 'pending')");
    $stmt->execute([
        $from_account_id,
        $amount,
        'External transfer initiated',
        $account_number
    ]);
    
    $transaction_id = $pdo->lastInsertId();
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
// replace for deployment
// 1. Create transfer recipient
$recipient_payload = [
    "type" => "nuban",
    "name" => "Test User",
    "account_number" => "0000000000",
    "bank_code" => "057",
    "currency" => "NGN"
];

$ch = curl_init("https://api.paystack.co/transferrecipient");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($recipient_payload),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 30
]);

$recipient_response = curl_exec($ch);
file_put_contents('paystack_recipient_error.log', $recipient_response);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
// Check for HTTP errors
if ($http_code !== 200 && $http_code !== 201) {
    logTransferError($transaction_id, "Recipient creation failed: HTTP $http_code");
    http_response_code(400);
    echo json_encode(['error' => 'Failed to create transfer recipient', 'code' => $http_code]);
    exit();
}

$recipient_result = json_decode($recipient_response, true);
if (!$recipient_result['status']) {
    logTransferError($transaction_id, "Recipient creation failed: " . ($recipient_result['message'] ?? 'Unknown error'));
    http_response_code(400);
    echo json_encode(['error' => 'Failed to create transfer recipient', 'details' => $recipient_result['message'] ?? '']);
    exit();
}

$recipient_code = $recipient_result['data']['recipient_code'];

// 2. Initiate transfer
$transfer_payload = [
    "source" => "balance",
    "amount" => intval($amount * 100), // Paystack uses kobo
    "recipient" => $recipient_code,
    "reason" => "External transfer",
    "reference" => "NOVATRX_" . $transaction_id . '_' . time()
];
file_put_contents('paystack_transfer_error.log', 'Transfer step reached');
$ch = curl_init("https://api.paystack.co/transfer");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($transfer_payload),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 30
]);

$transfer_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
file_put_contents('paystack_transfer_error.log', $transfer_response);

if ($http_code !== 200) {
    logTransferError($transaction_id, "Transfer initiation failed: HTTP $http_code");
    http_response_code(400);
    echo json_encode(['error' => 'Transfer failed', 'code' => $http_code]);
    exit();
}

$transfer_result = json_decode($transfer_response, true);
if (!$transfer_result['status']) {
    logTransferError($transaction_id, "Transfer failed: " . ($transfer_result['message'] ?? 'Unknown error'));
    http_response_code(400);
    echo json_encode(['error' => 'Transfer failed', 'details' => $transfer_result['message'] ?? '']);
    exit();
}
// 3. Send notification to user

try {
    // Get user email for notification
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['email']) {
        $recipientInfo = "Bank: $bank_code | Account: $account_number";
        sendTransferNotification(
            $user['email'],
            $amount,
            $recipientInfo,
            $transfer_result['data']['reference'],
            'processing'
        );
    }
} catch (Exception $e) {
    error_log("Failed to send transfer notification: " . $e->getMessage());
}
// Update transaction with reference and status
try {
    $stmt = $pdo->prepare("UPDATE transactions 
        SET reference = ?, status = 'processing', metadata = ?
        WHERE id = ?");
    $stmt->execute([
        $transfer_result['data']['reference'],
        json_encode($transfer_result['data']),
        $transaction_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transfer initiated successfully',
        'reference' => $transfer_result['data']['reference'],
        'status' => 'processing',
        'transaction_id' => $transaction_id
    ]);
} catch (PDOException $e) {
    // Even if we can't update the transaction, the transfer was successful
    error_log("Failed to update transaction $transaction_id: " . $e->getMessage());
    echo json_encode([
        'success' => true,
        'message' => 'Transfer initiated but status update failed',
        'reference' => $transfer_result['data']['reference'],
        'status' => 'processing',
        'transaction_id' => $transaction_id
    ]);
}

// Helper function to log transfer errors
function logTransferError($transaction_id, $error) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE transactions 
            SET status = 'failed', error_message = ?
            WHERE id = ?");
        $stmt->execute([$error, $transaction_id]);
        
        // Refund the amount if deduction was made
        $stmt = $pdo->prepare("UPDATE accounts a
            JOIN transactions t ON a.id = t.account_id
            SET a.balance = a.balance + t.amount
            WHERE t.id = ? AND t.status = 'failed'");
        $stmt->execute([$transaction_id]);
    } catch (PDOException $e) {
        error_log("Failed to log transfer error: " . $e->getMessage());
    }
}