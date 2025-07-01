<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header('Content-Type: application/json');
require_once 'config.php';

$network = strtolower(trim($_GET['network'] ?? ''));

if (!$network) {
    http_response_code(400);
    echo json_encode(['error' => 'Network parameter is required']);
    exit();
}

$apiUrl = "https://sandbox.vtpass.com/api/service-variations?serviceID=$network";
$apiKey = trim(VTPASS_API_KEY);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to VTpass API']);
    exit();
}
curl_close($ch);

$result = json_decode($response, true);

if ($result && isset($result['code']) && $result['code'] == '000' && !empty($result['content'])) {
    echo json_encode(['variations' => $result['content']]);
} else {
    // Log or return error message from VTpass if available
    $errorMsg = $result['message'] ?? 'No bundles found or API error';
    echo json_encode(['error' => $errorMsg, 'variations' => []]);
}
