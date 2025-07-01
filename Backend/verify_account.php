<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require 'config.php'; // Contains PAYSTACK_SECRET_KEY

// Validate input
$bankCode = $_GET['bank_code'] ?? '';
$accountNumber = $_GET['account_number'] ?? '';

if (empty($bankCode) || empty($accountNumber) || strlen($accountNumber) !== 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Call Paystack API securely from backend
$ch = curl_init("https://api.paystack.co/bank/resolve?account_number=$accountNumber&bank_code=$bankCode");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(400);
    echo json_encode(['error' => 'Account verification failed']);
    exit();
}

echo $response; // Forward Paystack's response
?>