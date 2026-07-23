<?php

require_once __DIR__ . '/controller.php';

$authController = new AuthController();

$route = $_GET['route'] ?? '';

$route = trim($route, '/');

$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if ($method === 'POST' && $route === 'auth/login') {

    $authController->login();
    exit;
}

/*
|--------------------------------------------------------------------------
| NOT FOUND
|--------------------------------------------------------------------------
*/

http_response_code(404);

echo json_encode([
    "success" => false,
    "message" => "Auth route not found"
]);