<?php
// File: modules/jobs/routes.php

require_once __DIR__ . '/controller.php';

$jobController = new JobController();

$method = $_SERVER['REQUEST_METHOD'];

$requestUri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$requestUri = str_replace('', '', $requestUri);

/*
|--------------------------------------------------------------------------
| CREATE JOB
|--------------------------------------------------------------------------
*/
if (
    $method === 'POST' &&
    $requestUri === '/jobs/create'
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('jobs', 'can_create');

    $jobController->create();
    exit;
}

/*
|--------------------------------------------------------------------------
| GET JOBS LIST
|--------------------------------------------------------------------------
*/
if (
    $method === 'GET' &&
    $requestUri === '/jobs/list'
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('jobs', 'can_view');

    $jobController->index();
    exit;
}

/*
|--------------------------------------------------------------------------
| GET JOB BY ID
|--------------------------------------------------------------------------
*/
if (
    $method === 'GET' &&
    preg_match('#^/jobs/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('jobs', 'can_view');

    $jobController->show((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE JOB
|--------------------------------------------------------------------------
*/
if (
    ($method === 'PUT' || $method === 'POST') &&
    preg_match('#^/jobs/update/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('jobs', 'can_edit');

    $jobController->update((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE JOB
|--------------------------------------------------------------------------
*/
if (
    $method === 'DELETE' &&
    preg_match('#^/jobs/delete/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('jobs', 'can_delete');

    $jobController->delete((int)$matches[1]);
    exit;
}