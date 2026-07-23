<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

$secret_key = "my_super_secure_secret_key_1234567890";

$payload = [
    "iss" => "localhost",
    "iat" => time(),
    "exp" => time() + 3600,
    "user" => [
        "id" => 1
    ]
];

$jwt = JWT::encode($payload, $secret_key, 'HS256');

echo json_encode(["token" => $jwt]);