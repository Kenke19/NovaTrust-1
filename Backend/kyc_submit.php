<?php
require_once 'config.php';
require_once 'jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

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

// Validate input
$required = ['kyc_full_name', 'kyc_dob', 'kyc_address', 'kyc_id_type', 'kyc_id_number'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit();
    }
}

// Handle file upload
if (!isset($_FILES['kyc_id_document']) || $_FILES['kyc_id_document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'ID document upload failed']);
    exit();
}

$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$fileType = mime_content_type($_FILES['kyc_id_document']['tmp_name']);
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, PDF allowed.']);
    exit();
}

$uploadDir = __DIR__ . '/uploads/kyc/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$ext = pathinfo($_FILES['kyc_id_document']['name'], PATHINFO_EXTENSION);
$filename = 'kyc_' . $userId . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;
if (!move_uploaded_file($_FILES['kyc_id_document']['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit();
}

// Save KYC info to database
$stmt = $pdo->prepare("UPDATE users SET 
    kyc_status = 'pending',
    kyc_full_name = ?,
    kyc_dob = ?,
    kyc_address = ?,
    kyc_id_type = ?,
    kyc_id_number = ?,
    kyc_id_document = ?,
    kyc_submitted_at = NOW()
    WHERE id = ?");
$stmt->execute([
    $_POST['kyc_full_name'],
    $_POST['kyc_dob'],
    $_POST['kyc_address'],
    $_POST['kyc_id_type'],
    $_POST['kyc_id_number'],
    'uploads/kyc/' . $filename,
    $userId
]);

echo json_encode(['success' => true, 'message' => 'KYC submitted successfully. Awaiting verification.']);
?>