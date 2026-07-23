<?php

require_once __DIR__ . '/controller.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| GET CURRENT ROUTE
|--------------------------------------------------------------------------
|
| Example:
| https://e2e.beedatatech.com/employee/login
|
| route = employee/login
|
*/

$route = $_GET['route'] ?? '';

$route = trim($route, '/');

/*
|--------------------------------------------------------------------------
| LOGIN (NO AUTH REQUIRED)
|--------------------------------------------------------------------------
| POST /employee/login
|--------------------------------------------------------------------------
*/

if ($method === 'POST' && $route === 'employee/login') {

    $data = json_decode(file_get_contents("php://input"), true);

    loginEmployee($data);

    exit;
}

/*
|--------------------------------------------------------------------------
| PROTECTED EMPLOYEE ROUTES
|--------------------------------------------------------------------------
*/

elseif (strpos($route, 'employee') === 0) {

    require_once __DIR__ . '/../../middleware/auth.php';

    $user = authenticate();

/*
|--------------------------------------------------------------------------
| GET EMPLOYEES
|--------------------------------------------------------------------------
| GET /employee/list
|--------------------------------------------------------------------------
*/

    if ($method === 'GET' && $route === 'employee/list') {

        getEmployees($user);

        exit;
    }

/*
|--------------------------------------------------------------------------
| CREATE EMPLOYEE
|--------------------------------------------------------------------------
| POST /employee/create
|--------------------------------------------------------------------------
*/

    elseif ($method === 'POST' && $route === 'employee/create') {

        createEmployee($user);

        exit;
    }

    else {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Employee route not found",
            "route" => $route
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| INVALID ROUTE
|--------------------------------------------------------------------------
*/

http_response_code(404);

echo json_encode([
    "success" => false,
    "message" => "Invalid employee route",
    "route" => $route
]);

exit;