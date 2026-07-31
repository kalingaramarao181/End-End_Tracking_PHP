<?php

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
require_once __DIR__ . '/controller.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && $requestUri === '/dashboard/executive-summary') {
    authenticate();
    requirePermission('dashboard', 'can_view');
    (new DashboardController())->executiveSummary();
}

if ($method === 'GET' && $requestUri === '/dashboard/activities') {
    authenticate();
    requirePermission('dashboard', 'can_view');
    (new DashboardController())->activities();
}

if ($method === 'GET' && $requestUri === '/dashboard/workforce-analytics') {
    authenticate();
    requirePermission('dashboard', 'can_view');
    (new DashboardController())->workforceAnalytics();
}

if ($method === 'GET' && $requestUri === '/dashboard/profile-performance') {
    authenticate();
    requirePermission('dashboard', 'can_view');
    (new DashboardController())->profilePerformance();
}

if (
    $method === 'GET'
    && preg_match('#^/dashboard/(submissions|active-candidates|interviews|placements)/(\d+)$#', $requestUri, $matches)
) {
    authenticate();
    $resource='dashboard_'.str_replace('-','_',$matches[1]);
    requirePermission($resource, 'can_view');
    (new DashboardController())->show($matches[1], (int)$matches[2]);
}

if (
    $method === 'GET'
    && preg_match('#^/dashboard/(submissions|active-candidates|interviews|placements)$#', $requestUri, $matches)
) {
    authenticate();
    $resource='dashboard_'.str_replace('-','_',$matches[1]);
    requirePermission($resource, isset($_GET['export']) ? 'can_export' : 'can_view');
    (new DashboardController())->table($matches[1]);
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Dashboard route not found.'
]);
exit;
