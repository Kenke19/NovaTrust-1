<?php
require_once 'config.php';
require_once 'jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header('Content-Type: application/json');

// Get JWT from Authorization header
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch profile including previous_login
    $stmt = $pdo->prepare("SELECT username, email, full_name, phone, address, created_at, last_login, previous_login, kyc_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        // Format dates for better readability
        $profile['created_at'] = $profile['created_at'] ? date('Y-m-d H:i:s', strtotime($profile['created_at'])) : null;
        $profile['last_login'] = $profile['last_login'] ? date('Y-m-d H:i:s', strtotime($profile['last_login'])) : null;
        $profile['previous_login'] = $profile['previous_login'] ? date('Y-m-d H:i:s', strtotime($profile['previous_login'])) : null;
        
        echo json_encode($profile);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile (doesn't affect login timestamps)
    $data = json_decode(file_get_contents('php://input'), true);
    $full_name = trim($data['full_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $address, $userId]);
        
        // Return updated profile including login timestamps
        $stmt = $pdo->prepare("SELECT username, email, full_name, phone, address, created_at, last_login, previous_login, kyc_status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'message' => 'Profile updated successfully',
            'profile' => $updatedProfile
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// If request method is not GET or POST
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit();
?>