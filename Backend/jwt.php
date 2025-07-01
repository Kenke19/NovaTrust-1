<?php

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padLen = 4 - $remainder;
        $data .= str_repeat('=', $padLen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwtEncode($payload, $secret) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

function jwtDecode($jwt, $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    $signature = base64UrlDecode($signatureEncoded);
    $validSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);

    if (!hash_equals($validSignature, $signature)) {
        return null; // Invalid signature
    }

    $payload = json_decode(base64UrlDecode($payloadEncoded), true);

    if (isset($payload['exp']) && time() > $payload['exp']) {
        return null; // Token expired
    }

    return $payload;
}
?>