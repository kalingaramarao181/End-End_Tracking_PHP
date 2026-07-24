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
if ($method === 'GET' && in_array($route,['auth/me','auth/permissions'],true)) {
    require_once __DIR__ . '/../../middleware/auth.php';
    authenticate();
    $authController->me();
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
