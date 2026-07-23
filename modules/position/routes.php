<?php
// File: modules/position/routes.php

require_once __DIR__ . '/controller.php';

$positionController = new PositionController();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| GET CURRENT ROUTE
|--------------------------------------------------------------------------
|
| Example:
| https://e2e.beedatatech.com/position/create
|
| route = position/create
|
*/

$route = $_GET['route'] ?? '';

$route = trim($route, '/');

/*
|--------------------------------------------------------------------------
| LOAD MIDDLEWARE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';

/*
|--------------------------------------------------------------------------
| CREATE POSITION
|--------------------------------------------------------------------------
| POST /position/create
|--------------------------------------------------------------------------
*/

if ($method === 'POST' && $route === 'position/create') {

    authenticate();

    requirePermission('positions', 'can_create');

    $positionController->create();

    exit;
}

/*
|--------------------------------------------------------------------------
| GET ALL POSITIONS
|--------------------------------------------------------------------------
| GET /position/list
|--------------------------------------------------------------------------
*/

elseif ($method === 'GET' && $route === 'position/list') {

    authenticate();

    requirePermission('positions', 'can_view');

    $positionController->index();

    exit;
}

/*
|--------------------------------------------------------------------------
| GET SINGLE POSITION
|--------------------------------------------------------------------------
| GET /position/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'GET' &&
    preg_match('#^position/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('positions', 'can_view');

    $positionController->show((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE POSITION
|--------------------------------------------------------------------------
| PUT /position/update/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'PUT' &&
    preg_match('#^position/update/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('positions', 'can_edit');

    $positionController->update((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE POSITION
|--------------------------------------------------------------------------
| DELETE /position/delete/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'DELETE' &&
    preg_match('#^position/delete/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('positions', 'can_delete');

    $positionController->delete((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| ROUTE NOT FOUND
|--------------------------------------------------------------------------
*/

http_response_code(404);

echo json_encode([
    'success' => false,
    'message' => 'Position route not found.',
    'route' => $route
]);

exit;