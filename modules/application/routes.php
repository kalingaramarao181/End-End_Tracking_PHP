<?php
// File: modules/application/routes.php

require_once __DIR__ . '/controller.php';

$applicationController = new ApplicationController();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|--------------------------------------------------------------------------
| Remove Base Path (/api)
|--------------------------------------------------------------------------
| Example:
| http://localhost/api/application/create
| REQUEST_URI = /api/application/create
| After removing /api => /application/create
*/
$basePath = '';

if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

/*
|--------------------------------------------------------------------------
| CREATE APPLICATION
|--------------------------------------------------------------------------
| POST /api/application/create
|--------------------------------------------------------------------------
| Access:
| - Requires applications.can_create permission
| - employee_id is automatically taken from JWT
*/
if ($method === 'POST' && $requestUri === '/application/create') {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('applications', 'can_create');

    $applicationController->create();
    exit;
}

/*
|--------------------------------------------------------------------------
| LIST APPLICATIONS
|--------------------------------------------------------------------------
| GET /api/application/list?page=1&limit=10&search=John&position_id=2
|--------------------------------------------------------------------------
| Access:
| - Requires applications.can_view permission
|
| Behavior:
| - Admin / Super Admin:
|     * Can view all applications
|     * Can filter by ?position_id=
|
| - Bench Sales / Recruiters / Others:
|     * Can view only their own applications
*/
if ($method === 'GET' && $requestUri === '/application/list') {
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
| GET /api/application/{id}+`
|--------------------------------------------------------------------------
| Access:
| - Requires applications.can_view permission
*/
if (
    $method === 'GET' &&
    preg_match('#^/application/(\d+)$#', $requestUri, $matches)
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
| PUT /api/application/update/{id}
|--------------------------------------------------------------------------
| Access:
| - Requires applications.can_edit permission
*/
if (
    $method === 'PUT' &&
    preg_match('#^/application/update/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('applications', 'can_edit');

    $applicationController->update((int)$matches[1]);
    exit;
}


/*
|--------------------------------------------------------------------------
| SUBMISSION DASHBOARD
|--------------------------------------------------------------------------
| GET /api/application/dashboard/submissions
|--------------------------------------------------------------------------
| Query Params:
| - start_date=2026-01-01
| - end_date=2026-01-31
| - category=recruiters|benchsales
|
| Examples:
| /api/application/dashboard/submissions
| /api/application/dashboard/submissions?category=recruiters
| /api/application/dashboard/submissions?start_date=2026-01-01&end_date=2026-01-31
|
| Access:
| - Requires dashboard.can_view permission
*/
if (
    $method === 'GET' &&
    $requestUri === '/application/dashboard/submissions'
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();

    // Change module name if needed
    requirePermission('dashboard', 'can_view');

    $applicationController->submissionDashboard();
    exit;
}


if (
    $method === 'GET' &&
    $requestUri === '/application/dashboard/summary'
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('dashboard', 'can_view');

    $applicationController->dashboardSummary();
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE APPLICATION
|--------------------------------------------------------------------------
| DELETE /api/application/delete/{id}
|--------------------------------------------------------------------------
| Access:
| - Requires applications.can_delete permission
*/
if (
    $method === 'DELETE' &&
    preg_match('#^/application/delete/(\d+)$#', $requestUri, $matches)
) {
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('applications', 'can_delete');

    $applicationController->delete((int)$matches[1]);
    exit;
}



if (
    $method === 'GET' &&
    $requestUri === '/application/dashboard/recent-activities'
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('dashboard', 'can_view');

    $applicationController->recentActivities();

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
