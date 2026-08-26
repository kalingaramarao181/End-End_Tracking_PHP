<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$allowedOrigins = [
    "https://e2e.bedatatech.com",
    "http://localhost:3001",
    "http://localhost:3000"

    ];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header('Content-Type: application/json');
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/*
|--------------------------------------------------------------------------
| Get Request Path
|--------------------------------------------------------------------------
| Example:
| http://localhost/api/auth/login

| REQUEST_URI = /api/auth/login
*/
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|--------------------------------------------------------------------------
| Support deployments in an Apache subdirectory
|--------------------------------------------------------------------------
| Production may expose this application at the web root, while local XAMPP
| serves it from /E2E_Tracking. Normalize both forms to the same route path.
*/
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDirectory !== '/' && $scriptDirectory !== '.' &&
    ($request === $scriptDirectory || strpos($request, $scriptDirectory . '/') === 0)
) {
    $request = substr($request, strlen($scriptDirectory));
}

if ($request === '' || $request === false) {
    $request = '/';
}

// Module route files use either REQUEST_URI or the legacy `route` query value.
// Provide both in their normalized, deployment-independent form.
$_SERVER['REQUEST_URI'] = $request;
$_GET['route'] = trim($request, '/');

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
| Order matters:
| 1. Specific routes first
| 2. Generic routes later
*/

// ---------------------------------------------------------------------
// PUBLIC HOME DASHBOARD AGGREGATES
// URL: GET /home/summary
// ---------------------------------------------------------------------
if (strpos($request, '/home/summary') === 0) {
    require_once __DIR__ . '/modules/home/routes.php';
    exit;
}
// ---------------------------------------------------------------------
// EMPLOYEE LOGIN (legacy route)
// URL: POST /employee/login
// ---------------------------------------------------------------------
if (strpos($request, '/employee/login') === 0) {
    require_once __DIR__ . '/modules/employee/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// TEST ROUTES
// URL: /api/test
// ---------------------------------------------------------------------

elseif (strpos($request, '/api/test') === 0) {

    require_once __DIR__ . '/modules/test/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// AUTH ROUTES
// URL: POST /api/auth/login
// ---------------------------------------------------------------------
elseif (strpos($request, '/w2') === 0) {
    require_once __DIR__ . '/modules/w2/routes.php';
    exit;
}
// AUTH ROUTES
elseif ( strpos($request, '/auth') === 0
) {
    require_once __DIR__ . '/modules/auth/routes.php';
    exit;
}
// ---------------------------------------------------------------------
// USER ROUTES
// URL:
//   POST /api/user/register
//   GET  /api/user/list
//   PUT  /api/user/update/{id}
// ---------------------------------------------------------------------
elseif (strpos($request, '/user') === 0) {
    require_once __DIR__ . '/modules/user/routes.php';
    exit;
}

elseif (strpos($request, '/recruiters') === 0) {
    require_once __DIR__ . '/modules/recruiters/routes.php';
    exit;
}

elseif (strpos($request, '/benchsales') === 0) {
    require_once __DIR__ . '/modules/benchsales/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// APPLICATION ROUTES
// ---------------------------------------------------------------------
// Supported Endpoints:
//   POST   /api/application/create
//   GET    /api/application/list?page=1&limit=10&search=John
//   GET    /api/application/list?position_id=2
//   GET    /api/application/list?position_id=3&search=Google
//   GET    /api/application/1
//   PUT    /api/application/update/1
//   DELETE /api/application/delete/1
//
// Behavior:
//   - Admin / Super Admin:
//       ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Can view all applications
//       ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Can filter by position_id (Bench Sales, Recruiters, etc.)
//
//   - Bench Sales / Recruiters / Other Users:
//       ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Can view only their own applications
//
// Notes:
//   - applications.employee_id stores users.id
//   - employee_id is automatically taken from JWT during create
//   - candidate_id is supported for new records
//   - candidate_name is preserved for legacy records
// ---------------------------------------------------------------------
elseif (strpos($request, '/application') === 0) {
    require_once __DIR__ . '/modules/application/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// DASHBOARD DATA TABLES
// URL: GET /dashboard/{submissions|active-candidates|interviews|placements}
// ---------------------------------------------------------------------
elseif (strpos($request, '/dashboard') === 0) {
    require_once __DIR__ . '/modules/dashboard/routes.php';
    exit;
}

elseif (strpos($request, '/employees') === 0) {
    require_once __DIR__ . '/modules/employee/routes.php';
    exit;
}
elseif (strpos($request, '/attendance') === 0) {
    require_once __DIR__ . '/modules/employee/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// EMPLOYEE PROTECTED ROUTES
// URL: /api/employee/*
// ---------------------------------------------------------------------
elseif (strpos($request, '/employee') === 0) {
    require_once __DIR__ . '/modules/employee/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// PERMISSION ROUTES
// URL: /api/permission
// ---------------------------------------------------------------------

elseif (strpos($request, '/permission') === 0) {
    require_once __DIR__ . '/modules/permission/routes.php';
    exit;
}


// ---------------------------------------------------------------------
// RESOURCE ROUTES
// URL Examples:
//   POST   /api/resource/create
//   GET    /api/resource/list?page=1&limit=10&search=users
//   GET    /api/resource/1
//   PUT    /api/resource/update/1
//   DELETE /api/resource/delete/1
// ---------------------------------------------------------------------
elseif (strpos($request, '/resource') === 0 ||
    strpos($request, '/resource') === 0
) {
    require_once __DIR__ . '/modules/resource/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// POSITION ROUTES
// URL Examples:
//   POST   /api/position/create
//   GET    /api/position/list?page=1&limit=10&search=admin
//   GET    /api/position/1
//   PUT    /api/position/update/1
//   DELETE /api/position/delete/1
// ---------------------------------------------------------------------
elseif (strpos($request, '/position') === 0) {
    require_once __DIR__ . '/modules/position/routes.php';
    exit;
}


// ---------------------------------------------------------------------
// CANDIDATE ROUTES
// ---------------------------------------------------------------------
// Supported Endpoints:
//   POST   /api/candidate/create
//   GET    /api/candidate/list?page=1&limit=10&search=John
//   GET    /api/candidate/1
//   PUT    /api/candidate/update/1
//   DELETE /api/candidate/delete/1
// ---------------------------------------------------------------------
elseif (strpos($request, '/candidate') === 0) {
    require_once __DIR__ . '/modules/candidate/routes.php';
    exit;
}

// ---------------------------------------------------------------------
// DOCUMENT EXPIRY REMINDERS
// ---------------------------------------------------------------------
elseif (
    strpos($request, '/document-reminders') === 0 ||
    strpos($request, '/document-reminders') === 0
) {
    require_once __DIR__ . '/modules/document_reminders/routes.php';
    exit;
}



elseif (strpos($request, '/primevendors') === 0) {
    require_once __DIR__ . '/modules/primevendors/routes.php';
    exit;
}

elseif (strpos($request, '/jobs') === 0) {
    require_once __DIR__ . '/modules/jobs/routes.php';
    exit;
}

elseif (strpos($request, '/matching') === 0) {
    require_once __DIR__ . '/modules/ai_matching/routes.php';
    exit;
}



// ---------------------------------------------------------------------
// ROUTE NOT FOUND
// ---------------------------------------------------------------------
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Route not found',
    'path' => $request
]);
exit;

