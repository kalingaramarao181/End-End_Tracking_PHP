<?php
require_once __DIR__ . '/../../config/db.php';

function getEmployeeByUsername($username) {
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchEmployees($filters) {
    global $conn;
    $conditions = [];
    $params = [];
    $types = '';
    if (!empty($filters['search'])) {
        $conditions[] = "(TRIM(CONCAT_WS(' ', e.firstname, e.lastname)) LIKE ?
            OR e.employee_id LIKE ? OR u.nick_name LIKE ? OR u.email LIKE ?)";
        $value = '%' . trim($filters['search']) . '%';
        array_push($params, $value, $value, $value, $value);
        $types .= 'ssss';
    }
    if (!empty($filters['position_id'])) {
        $conditions[] = 'e.position_id = ?';
        $params[] = (int)$filters['position_id'];
        $types .= 'i';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM employees e
        LEFT JOIN users u ON u.id = e.user_id $where");
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("SELECT e.id, e.employee_id, e.firstname, e.lastname,
            TRIM(CONCAT_WS(' ', e.firstname, e.lastname)) AS legal_name,
            e.contact_info, e.gender, e.position_id, e.photo, e.created_on,
            e.user_id, u.nick_name AS company_name, u.email AS username,
            u.status AS user_status, p.position_name AS role
        FROM employees e
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN positions p ON p.id = u.position_id
        $where ORDER BY e.id DESC LIMIT ? OFFSET ?");
    $dataParams = array_merge($params, [(int)$filters['limit'], (int)$filters['offset']]);
    $stmt->bind_param($types . 'ii', ...$dataParams);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return ['data' => $rows, 'total' => $total];
}

function fetchEmployeeById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT e.id, e.employee_id, e.firstname, e.lastname,
            TRIM(CONCAT_WS(' ', e.firstname, e.lastname)) AS legal_name,
            e.address, e.birthdate, e.contact_info, e.gender, e.position_id,
            e.schedule_id, e.photo, e.created_on, e.user_id,
            u.nick_name AS company_name, u.email AS username,
            u.status AS user_status, u.last_login, p.position_name AS role
        FROM employees e
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN positions p ON p.id = u.position_id
        WHERE e.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function fetchAvailableCompanyUsers($search = '') {
    global $conn;
    $sql = "SELECT u.id, u.nick_name AS company_name, u.email AS username,
            u.status, p.position_name AS role
        FROM users u
        LEFT JOIN positions p ON p.id = u.position_id
        LEFT JOIN employees e ON e.user_id = u.id
        WHERE e.user_id IS NULL";
    $params = [];
    $types = '';
    if (trim($search) !== '') {
        $sql .= ' AND (u.nick_name LIKE ? OR u.email LIKE ?)';
        $value = '%' . trim($search) . '%';
        $params = [$value, $value];
        $types = 'ss';
    }
    $sql .= ' ORDER BY u.nick_name ASC LIMIT 100';
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $rows[] = $row;
    }
    return $rows;
}

function assignCompanyUser($employeeId, $userId) {
    global $conn;
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, user_id FROM employees WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if (!$employee) throw new RuntimeException('Employee not found.');
        if ($employee['user_id'] !== null) throw new DomainException('This employee already has a Company Name assigned.');

        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('User not found.');

        $stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) throw new DomainException('This Company Name is already assigned to another employee.');

        $stmt = $conn->prepare('UPDATE employees SET user_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $userId, $employeeId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to assign Company Name.');
        $conn->commit();
        return ['success' => true, 'message' => 'Company Name assigned successfully.'];
    } catch (Throwable $error) {
        $conn->rollback();
        return ['success' => false, 'not_found' => str_contains($error->getMessage(), 'not found'),
            'message' => $error->getMessage()];
    }
}

function removeCompanyUser($employeeId) {
    global $conn;
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, user_id FROM employees WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if (!$employee) throw new RuntimeException('Employee not found.');
        if ($employee['user_id'] === null) throw new DomainException('This employee does not have a Company Name assigned.');
        $stmt = $conn->prepare('UPDATE employees SET user_id = NULL WHERE id = ?');
        $stmt->bind_param('i', $employeeId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to remove Company Name.');
        $conn->commit();
        return ['success' => true, 'message' => 'Company Name mapping removed successfully.'];
    } catch (Throwable $error) {
        $conn->rollback();
        return ['success' => false, 'not_found' => str_contains($error->getMessage(), 'not found'),
            'message' => $error->getMessage()];
    }
}

function createEmployeeRecord(array $data) {
    global $conn;
    $required = ['employee_id','firstname','lastname','address','birthdate','contact_info','gender','position_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            return ['success' => false, 'message' => "$field is required."];
        }
    }
    $stmt = $conn->prepare('SELECT id FROM employees WHERE employee_id = ? LIMIT 1');
    $stmt->bind_param('s', $data['employee_id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) return ['success'=>false,'message'=>'Employee ID already exists.'];
    $next = $conn->query('SELECT COALESCE(MAX(id),0)+1 next_id FROM employees')->fetch_assoc()['next_id'];
    $schedule = (int)($data['schedule_id'] ?? 0);
    $photo = trim((string)($data['photo'] ?? ''));
    $created = $data['created_on'] ?? date('Y-m-d');
    $stmt = $conn->prepare('INSERT INTO employees
        (id,employee_id,firstname,lastname,address,birthdate,contact_info,gender,position_id,schedule_id,photo,created_on)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $position = (int)$data['position_id'];
    $stmt->bind_param('isssssssiiss', $next,$data['employee_id'],$data['firstname'],$data['lastname'],
        $data['address'],$data['birthdate'],$data['contact_info'],$data['gender'],$position,$schedule,$photo,$created);
    if (!$stmt->execute()) return ['success'=>false,'message'=>'Employee could not be created.'];
    return ['success'=>true,'message'=>'Employee created successfully.','id'=>(int)$next];
}

function updateEmployeeRecord($id, array $data) {
    global $conn;
    $existing = fetchEmployeeById($id);
    if (!$existing) return ['success'=>false,'not_found'=>true,'message'=>'Employee not found.'];
    $fields = ['employee_id','firstname','lastname','address','birthdate','contact_info','gender','position_id','schedule_id','photo'];
    foreach ($fields as $field) if (!array_key_exists($field,$data)) $data[$field]=$existing[$field] ?? '';
    $stmt=$conn->prepare('UPDATE employees SET employee_id=?,firstname=?,lastname=?,address=?,birthdate=?,
        contact_info=?,gender=?,position_id=?,schedule_id=?,photo=? WHERE id=?');
    $position=(int)$data['position_id']; $schedule=(int)$data['schedule_id'];
    $stmt->bind_param('sssssssiisi',$data['employee_id'],$data['firstname'],$data['lastname'],$data['address'],
        $data['birthdate'],$data['contact_info'],$data['gender'],$position,$schedule,$data['photo'],$id);
    if(!$stmt->execute()) return ['success'=>false,'message'=>'Employee could not be updated.'];
    return ['success'=>true,'message'=>'Employee updated successfully.'];
}

function deleteEmployeeRecord($id) {
    global $conn;
    $conn->begin_transaction();
    try {
        $stmt=$conn->prepare('SELECT id FROM employees WHERE id=? FOR UPDATE');
        $stmt->bind_param('i',$id); $stmt->execute();
        if(!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Employee not found.');
        // Attendance is retained as an HR history record.
        $stmt=$conn->prepare('DELETE FROM employees WHERE id=?');
        $stmt->bind_param('i',$id); $stmt->execute();
        $conn->commit();
        return ['success'=>true,'message'=>'Employee removed. Attendance history was retained.'];
    } catch(Throwable $e){$conn->rollback();return ['success'=>false,'not_found'=>true,'message'=>$e->getMessage()];}
}

function fetchEmployeeForUser($userId) {
    global $conn;
    $stmt=$conn->prepare('SELECT id FROM employees WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$userId);$stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    return $row ? fetchEmployeeById((int)$row['id']) : null;
}

function fetchAttendance($employeeId, $startDate, $endDate, $page=1, $limit=20) {
    global $conn;
    $page=max(1,(int)$page);$limit=min(100,max(1,(int)$limit));$offset=($page-1)*$limit;
    $hoursExpression="CASE WHEN time_out<>'00:00:00' AND time_out>time_in
        THEN TIMESTAMPDIFF(SECOND,time_in,time_out)/3600 ELSE NULL END";
    $summaryStmt=$conn->prepare("SELECT COUNT(DISTINCT date) present_days,
        SUM(status=1) on_time_days, SUM(status=0) late_days,
        ROUND(AVG($hoursExpression),2) average_hours,
        ROUND(COALESCE(SUM($hoursExpression),0),2) total_hours
        FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?");
    $summaryStmt->bind_param('iss',$employeeId,$startDate,$endDate);$summaryStmt->execute();
    $summary=$summaryStmt->get_result()->fetch_assoc();
    foreach(['present_days','on_time_days','late_days'] as $key)$summary[$key]=(int)$summary[$key];
    foreach(['average_hours','total_hours'] as $key)$summary[$key]=(float)($summary[$key]??0);
    $countStmt=$conn->prepare('SELECT COUNT(*) total FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?');
    $countStmt->bind_param('iss',$employeeId,$startDate,$endDate);$countStmt->execute();
    $total=(int)$countStmt->get_result()->fetch_assoc()['total'];
    $stmt=$conn->prepare("SELECT id,date,time_in,time_out,status,
        ROUND($hoursExpression,2) hours
        FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ? ORDER BY date DESC,id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('issii',$employeeId,$startDate,$endDate,$limit,$offset);$stmt->execute();
    $records=[];$result=$stmt->get_result();while($row=$result->fetch_assoc()){$row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$records[]=$row;}
    $trendStmt=$conn->prepare("SELECT date,ROUND(SUM($hoursExpression),2) hours,
        MAX(status) status FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ? GROUP BY date ORDER BY date");
    $trendStmt->bind_param('iss',$employeeId,$startDate,$endDate);$trendStmt->execute();
    $trend=[];$result=$trendStmt->get_result();while($row=$result->fetch_assoc())$trend[]=$row;
    return ['success'=>true,'summary'=>$summary,'trend'=>$trend,'data'=>$records,'total'=>$total,'page'=>$page,'limit'=>$limit];
}
