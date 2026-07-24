<?php
require_once __DIR__ . '/controller.php';


$userController = new UserController();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


$basePath = '/api';

if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTE - REGISTER USER (NO TOKEN REQUIRED)
|--------------------------------------------------------------------------
| Temporarily use this route to create the first Admin/Super Admin.
|
| POST http://localhost/api/user/register
*/
if ($method === 'POST' && $requestUri === '/user/register') {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';
    authenticate();
    requirePermission('users', 'can_create');
    $userController->register();
    exit;
}

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES - TOKEN REQUIRED
|--------------------------------------------------------------------------
| Middleware is loaded only inside these routes.
*/

// ---------------------------------------------------------------------
// GET /api/user/list
// Permission: users.can_view
// ---------------------------------------------------------------------
if ($method === 'GET' && $requestUri === '/user/list') {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('users', 'can_view');

    $userController->paginatedList();
    exit;
}

// ---------------------------------------------------------------------
// GET /api/user/{id}
// Permission: users.can_view
// Example: GET /api/user/1
// ---------------------------------------------------------------------
if (
    $method === 'GET' &&
    preg_match('#^/user/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('users', 'can_view');

    $userController->show((int)$matches[1]);
    exit;
}

// ---------------------------------------------------------------------
// PUT /api/user/update/{id}
// Permission: users.can_edit
// Example: PUT /api/user/update/1
// ---------------------------------------------------------------------
if (
    $method === 'PUT' &&
    preg_match('#^/user/update/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('users', 'can_edit');

    $userController->update((int)$matches[1]);
    exit;
}

// DELETE /api/user/delete/{id}
if (
    $method === 'DELETE' &&
    preg_match('#^/user/delete/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('users', 'can_delete');

    $userController->delete((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| ROUTE NOT FOUND
|--------------------------------------------------------------------------
*/
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Route not found.',
    'request_uri' => $requestUri
]);
exit;
