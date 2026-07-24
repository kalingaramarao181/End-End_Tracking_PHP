<?php
require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($method === 'POST' && $path === 'employee/login') {
    loginEmployee(json_decode(file_get_contents('php://input'), true) ?: []);
    exit;
}

$user = authenticate();
$employeePermission=getEffectivePermission('employees');
$attendancePermission=getEffectivePermission('attendance');
$isAdmin = ($employeePermission['data_scope']??'OWN')==='ALL';

if ($method === 'GET' && in_array($path, ['employee/list', 'employees'], true)) {
    requirePermission('employees','can_view');
    getEmployees($user);
    exit;
}
if ($method === 'GET' && $path === 'employees/available-users') {
    requirePermission('employees','can_assign');
    getAvailableCompanyNames();
    exit;
}
if ($method === 'GET' && $path === 'employees/me') {
    requirePermission('profile','can_view');
    $employee=fetchEmployeeForUser((int)$user['id']);
    if(!$employee){http_response_code(404);echo json_encode(['success'=>false,'message'=>'No employee profile is linked to this login.']);exit;}
    echo json_encode(['success'=>true,'data'=>$employee]);exit;
}
if ($method === 'GET' && preg_match('#^employees/(\d+)/attendance$#', $path, $matches)) {
    requirePermission('attendance','can_view');
    $employeeId=(int)$matches[1];
    if(($attendancePermission['data_scope']??'OWN')!=='ALL'){
        $own=fetchEmployeeForUser((int)$user['id']);
        if(!$own||(int)$own['id']!==$employeeId){http_response_code(403);echo json_encode(['success'=>false,'message'=>'You can only view your own attendance.']);exit;}
    }
    getEmployeeAttendance($employeeId);exit;
}
if ($method === 'GET' && preg_match('#^employees/(\d+)$#', $path, $matches)) {
    $employeeId=(int)$matches[1];
    $own=fetchEmployeeForUser((int)$user['id']);
    $isOwn=$own&&(int)$own['id']===$employeeId;
    if($isOwn){
        requirePermission('profile','can_view');
    }else{
        requirePermission('employees','can_view');
        if(!$isAdmin){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Permission denied.']);exit;}
    }
    getEmployeeDetails($employeeId);
    exit;
}
if ($method === 'POST' && preg_match('#^employees/(\d+)/assign-company-name$#', $path, $matches)) {
    requirePermission('employees','can_assign');
    assignEmployeeCompanyName((int)$matches[1]);
    exit;
}
if ($method === 'PUT' && preg_match('#^employees/(\d+)/remove-company-name$#', $path, $matches)) {
    requirePermission('employees','can_assign');
    removeEmployeeCompanyName((int)$matches[1]);
    exit;
}
if ($method === 'POST' && $path === 'employees') {
    requirePermission('employees','can_create');
    createEmployeeEntry();exit;
}
if ($method === 'PUT' && preg_match('#^employees/(\d+)$#', $path, $matches)) {
    requirePermission('employees','can_edit');
    updateEmployeeEntry((int)$matches[1]);exit;
}
if ($method === 'DELETE' && preg_match('#^employees/(\d+)$#', $path, $matches)) {
    requirePermission('employees','can_delete');
    deleteEmployeeEntry((int)$matches[1]);exit;
}
if ($method === 'POST' && $path === 'employee/create') {
    requirePermission('employees','can_create');
    createEmployee($user);
    exit;
}
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Employee route not found.', 'route' => $path]);
exit;

function employeeAdminDenied() {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator access required.']);
    exit;
}
