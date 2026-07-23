<?php

require_once __DIR__ . '/controller.php';

$candidateController = new CandidateController();

$method = $_SERVER['REQUEST_METHOD'];

$requestUri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$requestUri = str_replace('', '', $requestUri);

/*
|--------------------------------------------------------------------------
| CREATE
|--------------------------------------------------------------------------
*/
if ($method === 'POST' && $requestUri === '/candidate/create') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('candidates', 'can_create');

    $candidateController->create();
    exit;
}

/*
|--------------------------------------------------------------------------
| LIST
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && $requestUri === '/candidate/list') {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('candidates', 'can_view');

    $candidateController->index();
    exit;
}

/*
|--------------------------------------------------------------------------
| GET BY ID
|--------------------------------------------------------------------------
*/
if (
    $method === 'GET' &&
    preg_match('#^/candidate/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('candidates', 'can_view');

    $candidateController->show((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/
if (
    ($method === 'PUT' || $method === 'POST') &&
    preg_match('#^/candidate/update/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('candidates', 'can_edit');

    $candidateController->update((int)$matches[1]);
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (
    $method === 'DELETE' &&
    preg_match('#^/candidate/delete/([0-9]+)$#', $requestUri, $matches)
) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();
    requirePermission('candidates', 'can_delete');

    $candidateController->delete((int)$matches[1]);
    exit;
}