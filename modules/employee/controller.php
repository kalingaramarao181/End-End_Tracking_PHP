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

    $data = fetchEmployees($filters);

    echo json_encode([
        "page" => $filters['page'],
        "limit" => $filters['limit'],
        "data" => $data
    ]);
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