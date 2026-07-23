<?php
// File: modules/resource/routes.php

require_once __DIR__ . '/controller.php';

$resourceController = new ResourceController();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| GET CURRENT ROUTE
|--------------------------------------------------------------------------
|
| Example:
| https://e2e.beedatatech.com/resource/create
|
| route = resource/create
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
| CREATE RESOURCE
|--------------------------------------------------------------------------
| POST /resource/create
|--------------------------------------------------------------------------
*/

if ($method === 'POST' && $route === 'resource/create') {

    authenticate();

    requirePermission('resources', 'can_create');

    $resourceController->create();

    exit;
}

/*
|--------------------------------------------------------------------------
| GET ALL RESOURCES
|--------------------------------------------------------------------------
| GET /resource/list
|--------------------------------------------------------------------------
*/

elseif ($method === 'GET' && $route === 'resource/list') {

    authenticate();

    requirePermission('resources', 'can_view');

    $resourceController->index();

    exit;
}

/*
|--------------------------------------------------------------------------
| GET SINGLE RESOURCE
|--------------------------------------------------------------------------
| GET /resource/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'GET' &&
    preg_match('#^resource/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('resources', 'can_view');

    $resourceController->show((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GET MY RESOURCES
|--------------------------------------------------------------------------
| GET /resource/my
|--------------------------------------------------------------------------
*/

elseif ($method === 'GET' && $route === 'resource/my') {

    authenticate();

    $resourceController->myResources();

    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE RESOURCE
|--------------------------------------------------------------------------
| PUT /resource/update/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'PUT' &&
    preg_match('#^resource/update/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('resources', 'can_edit');

    $resourceController->update((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE RESOURCE
|--------------------------------------------------------------------------
| DELETE /resource/delete/{id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'DELETE' &&
    preg_match('#^resource/delete/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('resources', 'can_delete');

    $resourceController->delete((int)$matches[1]);

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
    'message' => 'Resource route not found.',
    'route' => $route
]);

exit;