<?php

$db_host = 'localhost';
$db_name = 'novatrust';
$db_user = 'root';
$db_pass = '';
$jwt_secret = '9f74b8c3a5d2e1f0k6c7d8e9f0127640929holnef0188760989abcdef0760956';


try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}
// Paystack API keys

define('PAYSTACK_SECRET_KEY', "sk_test_accc9dcbc89207840a4600a269e0c47e1b53d122");
define('PAYSTACK_PUBLIC_KEY', "pk_test_e90e6c587ebd25fe4222f01a142324ef07660033");
define('VTPASS_API_KEY', "230453f425f40a8cbbc1ecd0aa50f78b" );
?>
