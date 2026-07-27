<?php
require_once "model.php";
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;



function loginEmployee($data) {
    global $secret_key, $issuer;

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(["message" => "Username and password required"]);
        return;
    }

    $employee = getEmployeeByUsername($username);

    if (!$employee) {
        http_response_code(401);
        echo json_encode(["message" => "Invalid username"]);
        return;
    }

    // ⚠️ If password is hashed use password_verify()
    if ($employee['password'] !== $password) {
        http_response_code(401);
        echo json_encode(["message" => "Invalid password"]);
        return;
    }

    // ✅ Generate JWT
    $payload = [
        "iss" => $issuer,
        "iat" => time(),
        "exp" => time() + 3600,
        "user" => [
            "id" => $employee['id'],
            "username" => $employee['email']
        ]
    ];

    $jwt = JWT::encode($payload, $secret_key, 'HS256');

    echo json_encode([      
        "message" => "Login successful",
        "token" => $jwt
    ]);
}

// 📥 GET Employees
function getEmployees($user) {

    $filters = [
        "search"      => $_GET['search'] ?? null,
        "position_id" => $_GET['position_id'] ?? null,
        "page"        => $_GET['page'] ?? 1,
        "limit"       => $_GET['limit'] ?? 10
    ];

    $filters['offset'] = ($filters['page'] - 1) * $filters['limit'];

    $result = fetchEmployees($filters);

    echo json_encode([
        "success" => true,
        "page" => $filters['page'],
        "limit" => $filters['limit'],
        "total" => $result['total'],
        "total_pages" => (int)ceil($result['total'] / max(1, $filters['limit'])),
        "data" => $result['data']
    ]);
}

function getEmployeeDetails($id) {
    $employee = fetchEmployeeById($id);
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $employee]);
}

function getAvailableCompanyNames() {
    echo json_encode([
        'success' => true,
        'data' => fetchAvailableCompanyUsers($_GET['search'] ?? '')
    ]);
}

function assignEmployeeCompanyName($id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid user_id is required.']);
        return;
    }
    $result = assignCompanyUser((int)$id, (int)$userId);
    http_response_code($result['success'] ? 200 : (!empty($result['not_found']) ? 404 : 409));
    echo json_encode($result);
}

function removeEmployeeCompanyName($id) {
    $result = removeCompanyUser((int)$id);
    http_response_code($result['success'] ? 200 : (!empty($result['not_found']) ? 404 : 409));
    echo json_encode($result);
}

function createEmployeeEntry() {
    $result=createEmployeeRecord(json_decode(file_get_contents('php://input'),true)?:[]);
    http_response_code($result['success']?201:422);echo json_encode($result);
}
function updateEmployeeEntry($id) {
    $result=updateEmployeeRecord($id,json_decode(file_get_contents('php://input'),true)?:[]);
    http_response_code($result['success']?200:(!empty($result['not_found'])?404:422));echo json_encode($result);
}
function deleteEmployeeEntry($id) {
    $result=deleteEmployeeRecord($id);
    http_response_code($result['success']?200:404);echo json_encode($result);
}
function getEmployeeAttendance($id) {
    $start=$_GET['start_date']??date('Y-m-01');
    $end=$_GET['end_date']??date('Y-m-d');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||$start>$end){
        http_response_code(422);echo json_encode(['success'=>false,'message'=>'Valid start_date and end_date are required.']);return;
    }
    echo json_encode(fetchAttendance($id,$start,$end,$_GET['page']??1,$_GET['limit']??20));
}

function setEmployeeAttendanceDate($id, $date) {
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $present=filter_var($input['present']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
    if($present===null){
        http_response_code(422);echo json_encode(['success'=>false,'message'=>'present must be true or false.']);return;
    }
    if($date>date('Y-m-d')){
        http_response_code(422);echo json_encode(['success'=>false,'message'=>'Future attendance cannot be added.']);return;
    }
    $result=saveAdminAttendanceDate($id,$date,$present);
    http_response_code($result['success']?200:(!empty($result['not_found'])?404:409));
    echo json_encode($result);
}

function attendanceToday($user) {
    $employee=fetchEmployeeForUser((int)$user['id']);
    if(!$employee){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Your login is not linked to an employee profile.']);return;}
    echo json_encode(getTodayAttendanceState((int)$employee['id']));
}

function attendanceClock($user, $action) {
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $employee=fetchEmployeeForUser((int)$user['id']);
    if(!$employee){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Your login is not linked to an employee profile.']);return;}
    $confirmed=strtoupper(trim((string)($input['employee_id']??'')));
    if($confirmed!==strtoupper((string)$employee['employee_id'])){
        http_response_code(422);echo json_encode(['success'=>false,'message'=>'Employee ID does not match your profile.']);return;
    }
    $result=clockAttendance((int)$employee['id'],(int)$user['id'],$action);
    http_response_code($result['success']?200:409);echo json_encode($result);
}

function listHolidays() {
    $year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));
    echo json_encode(['success'=>true,'data'=>fetchHolidays($year)]);
}
function saveHoliday($user) {
    $result=upsertHoliday(json_decode(file_get_contents('php://input'),true)?:[],(int)$user['id']);
    http_response_code($result['success']?201:422);echo json_encode($result);
}
function removeHoliday($id) {
    $result=deleteHoliday($id);http_response_code($result['success']?200:404);echo json_encode($result);
}
function listLeaves($user,$all) {
    $employee=$all?null:fetchEmployeeForUser((int)$user['id']);
    if(!$all&&!$employee){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Your login is not linked to an employee profile.']);return;}
    echo json_encode(['success'=>true,'data'=>fetchLeaves($employee?(int)$employee['id']:null,$_GET)]);
}
function createLeave($user) {
    $employee=fetchEmployeeForUser((int)$user['id']);
    if(!$employee){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Your login is not linked to an employee profile.']);return;}
    $result=submitLeave((int)$employee['id'],json_decode(file_get_contents('php://input'),true)?:[]);
    http_response_code($result['success']?201:422);echo json_encode($result);
}
function createEmployeeLeaveByAdmin($user) {
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $employeeId=filter_var($input['employee_id']??null,FILTER_VALIDATE_INT);
    if(!$employeeId){http_response_code(422);echo json_encode(['success'=>false,'message'=>'A valid employee is required.']);return;}
    $input['status']='approved';
    $result=submitLeave((int)$employeeId,$input,(int)$user['id']);
    http_response_code($result['success']?201:422);echo json_encode($result);
}
function reviewLeave($id,$user) {
    $result=updateLeaveStatus($id,json_decode(file_get_contents('php://input'),true)?:[],(int)$user['id']);
    http_response_code($result['success']?200:422);echo json_encode($result);
}
function editAttendanceRecord($id,$user) {
    $result=adminEditAttendance($id,json_decode(file_get_contents('php://input'),true)?:[],(int)$user['id']);
    http_response_code($result['success']?200:422);echo json_encode($result);
}

// ➕ ADD Employee
function createEmployee() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);

    $name = $input['name'];
    $email = $input['email'];
    $password = password_hash($input['password'], PASSWORD_BCRYPT);
    $position_id = $input['position_id'];

    $stmt = $conn->prepare("
        INSERT INTO employee (name, email, password, position_id)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("sssi", $name, $email, $password, $position_id);
    $stmt->execute();

    echo json_encode(["message" => "Employee created"]);
}
