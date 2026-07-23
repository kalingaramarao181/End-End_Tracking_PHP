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
| CREATE BENCH SALES APPLICATION
|--------------------------------------------------------------------------
| POST /api/benchsales/create
*/
if ($method === 'POST' && $requestUri === '/benchsales/create') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_create');

    $applicationController->create();
    exit;
}

/*
|--------------------------------------------------------------------------
| LIST BENCH SALES APPLICATIONS
|--------------------------------------------------------------------------
| GET /api/benchsales/list
*/
if ($method === 'GET' && $requestUri === '/benchsales/list') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_view');

    $applicationController->index();
    exit;
}

/*
|--------------------------------------------------------------------------
| GET SINGLE APPLICATION
|--------------------------------------------------------------------------
| GET /api/benchsales/{id}
*/
if (
    $method === 'GET' &&
    preg_match('#^/benchsales/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_view');

    $applicationController->show((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE APPLICATION
|--------------------------------------------------------------------------
| PUT /api/benchsales/update/{id}
*/
if (
    $method === 'PUT' &&
    preg_match('#^/benchsales/update/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_edit');

    $applicationController->update((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE APPLICATION
|--------------------------------------------------------------------------
| DELETE /api/benchsales/delete/{id}
*/
if (
    $method === 'DELETE' &&
    preg_match('#^/benchsales/delete/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_delete');

    $applicationController->delete((int)$matches[1]);
    exit;
}

if (
    $method === 'GET' &&
    $requestUri === '/benchsales/dashboard/performance'
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();

    $applicationController->performanceDashboard();
    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE APPLICATION PROCESS
|--------------------------------------------------------------------------
| PUT /api/benchsales/process/{id}
*/

if (
    $method === 'PUT' &&
    preg_match('#^/benchsales/process/(\d+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('bench_sales', 'can_edit');

    $applicationController->updateProcess((int)$matches[1]);
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
    'message' => 'Bench Sales route not found',
    'request_uri' => $requestUri
]);

exit;