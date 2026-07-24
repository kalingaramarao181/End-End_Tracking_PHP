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
