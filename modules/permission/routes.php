<?php
// File: modules/permission/routes.php

require_once __DIR__ . '/controller.php';

$permissionController = new PermissionController();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| GET CURRENT ROUTE
|--------------------------------------------------------------------------
|
| Example:
| https://e2e.beedatatech.com/permission/position/3
|
| route = permission/position/3
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
| GET POSITION PERMISSIONS
|--------------------------------------------------------------------------
| GET /permission/position/{position_id}
|--------------------------------------------------------------------------
*/

if (
    $method === 'GET' &&
    preg_match('#^permission/position/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('permissions', 'can_view');

    $permissionController->getByPosition((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE POSITION PERMISSIONS
|--------------------------------------------------------------------------
| PUT /permission/position/{position_id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'PUT' &&
    preg_match('#^permission/position/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('permissions', 'can_edit');

    $permissionController->saveByPosition((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE POSITION PERMISSIONS
|--------------------------------------------------------------------------
| DELETE /permission/position/{position_id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'DELETE' &&
    preg_match('#^permission/position/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('permissions', 'can_delete');

    $permissionController->deleteByPosition((int)$matches[1]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GET USER PERMISSIONS
|--------------------------------------------------------------------------
| GET /permission/user/{user_id}
|--------------------------------------------------------------------------
*/

elseif (
    $method === 'GET' &&
    preg_match('#^permission/user/(\d+)$#', $route, $matches)
) {

    authenticate();

    requirePermission('permissions', 'can_view');

    $permissionController->getByUser((int)$matches[1]);

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
    'message' => 'Permission route not found.',
    'route' => $route
]);

exit;