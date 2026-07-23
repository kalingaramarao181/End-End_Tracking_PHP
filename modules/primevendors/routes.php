<?php

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';

$primeVendorController = new PrimeVendorController();
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = str_replace('', '', $requestUri);

authenticate();

if ($method === 'POST' && $requestUri === '/primevendors/create') {
    requirePermission('prime_vendors', 'can_create');
    $primeVendorController->create();
}

if ($method === 'GET' && $requestUri === '/primevendors/list') {
    requirePermission('prime_vendors', 'can_view');
    $primeVendorController->index();
}

if (
    $method === 'GET' &&
    preg_match('#^/primevendors/([0-9]+)$#', $requestUri, $matches)
) {
    requirePermission('prime_vendors', 'can_view');
    $primeVendorController->show((int)$matches[1]);
}

if (
    ($method === 'PUT' || $method === 'POST') &&
    preg_match('#^/primevendors/update/([0-9]+)$#', $requestUri, $matches)
) {
    requirePermission('prime_vendors', 'can_edit');
    $primeVendorController->update((int)$matches[1]);
}

if (
    $method === 'DELETE' &&
    preg_match('#^/primevendors/delete/([0-9]+)$#', $requestUri, $matches)
) {
    requirePermission('prime_vendors', 'can_delete');
    $primeVendorController->delete((int)$matches[1]);
}

http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Route not found.',
    'request_uri' => $requestUri
]);
exit;
