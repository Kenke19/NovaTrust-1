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

// Handle physical card request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $account_id = intval($data['account_id'] ?? 0);

    // Extract address components
    $street_address = trim($data['street_address'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $postal_code = trim($data['postal_code'] ?? '');
    $country = trim($data['country'] ?? '');

    // Validate required fields
    if (!$street_address || !$city || !$state || !$postal_code || !$country) {
        http_response_code(400);
        echo json_encode(['error' => 'All address fields are required']);
        exit();
    }

    // Check account ownership
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$account_id, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid account']);
        exit();
    }

    // Insert card request (status: pending)
    $stmt = $pdo->prepare("INSERT INTO physical_card_requests 
        (account_id, user_id, street_address, city, state, postal_code, country, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$account_id, $userId, $street_address, $city, $state, $postal_code, $country, 'pending']);

    echo json_encode(['message' => 'Physical card request submitted. You will be notified when it is processed.']);
    exit;
}

// List card requests for user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT r.id, r.account_id, a.account_number, a.account_type, 
                                  r.street_address, r.city, r.state, r.postal_code, r.country,
                                  r.status, r.created_at
                           FROM physical_card_requests r
                           JOIN accounts a ON r.account_id = a.id
                           WHERE r.user_id = ?
                           ORDER BY r.created_at DESC");
    $stmt->execute([$userId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optionally, concatenate address fields for easier frontend use
    foreach ($requests as &$req) {
        $req['full_address'] = "{$req['street_address']}, {$req['city']}, {$req['state']}, {$req['postal_code']}, {$req['country']}";
    }

    echo json_encode(['requests' => $requests]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
