<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'jwt.php';

header('Content-Type: application/json');

// Auth
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

// Card creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $account_id = intval($data['account_id'] ?? 0);

    // Verify account belongs to user
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$account_id, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid account']);
        exit();
    }
    //Fetch users fullname from database
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $full_name = $user ? $user['full_name'] : 'Card Holder';

    // Proper card number generation
    $card_number = '';
    for ($i = 0; $i < 16; $i++) {
        $card_number .= rand(0, 9);
    }
    $expiry_date = date('Y-m-d', strtotime('+3 years'));
    $cvv = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
    // Accept pin in the POST data
    $pin = $data['pin'] ?? null;
    if (!$pin || strlen($pin) < 4) {
        http_response_code(400);
        echo json_encode(['error' => 'PIN is required and must be at least 4 digits']);
        exit();
    }
    $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
    // Insert card into database
    $stmt = $pdo->prepare("INSERT INTO virtual_cards (account_id, card_number, card_holder, expiry_date, cvv, pin) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$account_id, $card_number, $full_name, $expiry_date, $cvv, $pin_hash]);
    echo json_encode([
        'message' => 'Virtual card created successfully',
        'card' => [
            'card_number' => $card_number,
            'expiry_date' => $expiry_date,
            'cvv' => $cvv
        ]
    ]);
    exit;
}

// List cards
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT vc.id, vc.card_number, vc.expiry_date, vc.status, vc.created_at, a.account_number, a.account_type, a.balance AS account_balance
                        FROM virtual_cards vc
                        JOIN accounts a ON vc.account_id = a.id
                        WHERE a.user_id = ?");
    $stmt->execute([$userId]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cards as &$card) {
        $card['card_number'] = '**** **** **** ' . substr($card['card_number'], -4);
    }
    echo json_encode(['cards' => $cards]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
