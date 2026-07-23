<?php

require_once __DIR__ . '/../application/controller.php';
require_once __DIR__.'/../../middleware/putMultipart.php';
parsePutMultipart();


$applicationController = new ApplicationController();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|--------------------------------------------------------------------------
| Remove Base Path (/api)
|--------------------------------------------------------------------------
*/
$basePath = '';

if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

/*
|--------------------------------------------------------------------------
| CREATE RECRUITER APPLICATION
|--------------------------------------------------------------------------
| POST /api/recruiters/create
*/
if ($method === 'POST' && $requestUri === '/recruiters/create') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('recruiters', 'can_create');

    $applicationController->create();
    exit;
}

/*
|--------------------------------------------------------------------------
| LIST RECRUITER APPLICATIONS
|--------------------------------------------------------------------------
| GET /api/recruiters/list
*/
if ($method === 'GET' && $requestUri === '/recruiters/list') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('recruiters', 'can_view');

    $applicationController->index(4);
    exit;
}

/*
|--------------------------------------------------------------------------
| GET SINGLE APPLICATION
|--------------------------------------------------------------------------
| GET /api/recruiters/{id}
*/
if (
    $method === 'GET' &&
    preg_match('#^/recruiters/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('recruiters', 'can_view');

    $applicationController->show((int)$matches[1], 4);
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE APPLICATION
|--------------------------------------------------------------------------
| PUT /api/recruiters/update/{id}
*/
if (
    $method === 'PUT' &&
    preg_match('#^/recruiters/update/(\d+)$#', $requestUri, $matches)
) { 

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('recruiters', 'can_edit');

    $applicationController->update((int)$matches[1], 4);
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE APPLICATION
|--------------------------------------------------------------------------
| DELETE /api/recruiters/delete/{id}

*/
if (
    $method === 'DELETE' &&
    preg_match('#^/recruiters/delete/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';


    authenticate();
    requirePermission('recruiters', 'can_delete');

    $applicationController->delete((int)$matches[1], 4);
    exit;
}

if (
    $method === 'PUT' &&
    preg_match('#^/recruiters/process/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';
    authenticate();
    requirePermission('recruiters', 'can_edit');
    $applicationController->updateProcess((int)$matches[1], 4);
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
    'message' => 'Recruiter route not found',
    'request_uri' => $requestUri
]);

exit;
