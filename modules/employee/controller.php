<?php
require_once "model.php";
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../document_reminders/services/ReminderEmailService.php';

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
        "employment"  => $_GET['employment'] ?? 'current',
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

function attendanceToday($user, $all = false) {
    if($all){echo json_encode(fetchTodayAttendanceRoster());return;}
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
    if($result['success']&&!empty($result['approval_token'])){
        try{sendLeaveApprovalEmail((int)$result['leave_id'],$result['approval_token']);}
        catch(Throwable $error){error_log('Leave approval email failed: '.$error->getMessage());$result['message'].=' The request is saved, but the approval email could not be sent.';}
        unset($result['approval_token']);
    }
    http_response_code($result['success']?201:422);echo json_encode($result);
}
function leaveApprovalBaseUrl(){
    $configured=rtrim((string)getenv('APP_URL'),'/');if($configured!=='')return $configured;
    $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
    $directory=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??''));
    return $scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').($directory==='/'?'':$directory);
}
function sendLeaveApprovalEmail($leaveId,$token){
    $leave=fetchLeaveEmailDetails($leaveId);if(!$leave)throw new RuntimeException('Leave request not found for email.');
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));$isLocal=strpos($host,'localhost')!==false||strpos($host,'127.0.0.1')!==false;
    $to=getenv('LEAVE_APPROVAL_TO')?:($isLocal?'kalingaramarao181@gmail.com':'hr_ind@bedatatech.com');
    $reviewer=getenv('LEAVE_APPROVER_NAME')?:'HR India';$base=leaveApprovalBaseUrl();
    $approve=$base.'/attendance/leaves/email-review?decision=approved&token='.rawurlencode($token);
    $reject=$base.'/attendance/leaves/email-review?decision=rejected&token='.rawurlencode($token);
    $employee=htmlspecialchars($leave['employee_name'],ENT_QUOTES,'UTF-8');$reason=htmlspecialchars($leave['reason'],ENT_QUOTES,'UTF-8');
    $html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden">'
        .'<div style="background:#312e81;color:white;padding:22px"><h2 style="margin:0">Leave approval required</h2></div>'
        .'<div style="padding:22px;color:#1e293b"><p><strong>'.$employee.'</strong> requested '.htmlspecialchars($leave['leave_type']).' leave.</p>'
        .'<table style="width:100%;border-collapse:collapse"><tr><td style="padding:7px">Dates</td><td><strong>'.htmlspecialchars($leave['start_date']).' to '.htmlspecialchars($leave['end_date']).'</strong></td></tr>'
        .'<tr><td style="padding:7px">Duration</td><td>'.htmlspecialchars(str_replace('_',' ',$leave['duration'])).'</td></tr><tr><td style="padding:7px">Reason</td><td>'.$reason.'</td></tr></table>'
        .'<p style="margin-top:24px"><a href="'.$approve.'" style="background:#15803d;color:white;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Approve</a> '
        .'<a href="'.$reject.'" style="background:#dc2626;color:white;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Reject</a></p>'
        .'<p style="font-size:12px;color:#64748b">Each secure link works once and expires in 7 days.</p></div></div>';
    (new ReminderEmailService())->sendHtml($to,$reviewer,'Leave approval: '.$leave['employee_name'],$html,'Leave approval required for '.$leave['employee_name'].'. Use the Approve or Reject link in this email.');
}
function reviewLeaveFromEmail(){
    $token=strtolower(trim((string)($_GET['token']??'')));$decision=strtolower(trim((string)($_GET['decision']??'')));
    $reviewer=(getenv('LEAVE_APPROVER_NAME')?:'HR India').' (email)';$result=reviewLeaveByEmailToken($token,$decision,$reviewer);
    http_response_code($result['success']?200:410);header('Content-Type: text/html; charset=UTF-8');
    $title=$result['success']?'Decision recorded':'Link unavailable';$color=$result['success']?($decision==='approved'?'#15803d':'#dc2626'):'#475569';
    echo '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f8fafc;display:grid;place-content:center;min-height:100vh;margin:0"><main style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:32px;text-align:center;max-width:480px"><h1 style="color:'.$color.'">'.htmlspecialchars($title).'</h1><p>'.htmlspecialchars($result['message']).'</p><p style="color:#64748b">You may close this window.</p></main></body></html>';exit;
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
