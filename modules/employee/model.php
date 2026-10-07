<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/AttendancePolicyService.php';
require_once __DIR__ . '/EmployeeCollectionService.php';
require_once __DIR__ . '/EmployeeProfileCompletion.php';

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
    $employment = strtolower(trim((string)($filters['employment'] ?? 'current')));
    if ($employment === 'former') {
        $conditions[] = 'e.user_id IS NULL';
    } elseif ($employment !== 'all') {
        // A linked login is the source of truth for current employment.
        $conditions[] = 'e.user_id IS NOT NULL';
    }
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
            e.contact_info, e.gender, e.birthdate, e.payroll_email, e.candidate_collection, e.payroll_profile, e.position_id, e.photo, e.created_on,
            e.user_id, u.nick_name AS company_name, u.email AS username,
            u.status AS user_status, p.position_name AS role
        FROM employees e
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN positions p ON p.id = u.position_id
        $where
        ORDER BY (e.user_id IS NULL) ASC, e.id DESC
        LIMIT ? OFFSET ?");
    $dataParams = array_merge($params, [(int)$filters['limit'], (int)$filters['offset']]);
    $stmt->bind_param($types . 'ii', ...$dataParams);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $collection=employeeDecodeCollection($row['candidate_collection']);
        $payroll=payrollSecureData(json_decode($row['payroll_profile']??'[]',true)?:[],false);
        $row['profile_completion']=employeeProfileCompletionPercentage($row,$collection,$payroll);
        unset($row['candidate_collection'],$row['payroll_profile'],$row['payroll_email']);
        $rows[]=$row;
    }
    return ['data' => $rows, 'total' => $total];
}

function fetchAttendanceRoster($date = null) {
    global $conn;
    $date = $date ?: newYorkNow()->format('Y-m-d');
    $sql = "SELECT e.id,e.employee_id,e.user_id,
            TRIM(CONCAT_WS(' ',e.firstname,e.lastname)) legal_name,
            u.nick_name company_name,u.email username,p.position_name role,
            a.id attendance_id,a.time_in,a.time_out,a.status,a.work_status,a.source,
            ROUND(CASE WHEN a.time_out<>'00:00:00' AND a.time_out>a.time_in
                THEN TIMESTAMPDIFF(SECOND,a.time_in,a.time_out)/3600
                ELSE COALESCE(a.num_hr,0) END,2) hours
        FROM employees e
        INNER JOIN users u ON u.id=e.user_id
        LEFT JOIN positions p ON p.id=u.position_id
        LEFT JOIN attendance a ON a.employee_id=e.id AND a.date=?
        WHERE e.user_id IS NOT NULL
        ORDER BY (a.id IS NULL),a.time_in ASC,e.firstname ASC,e.lastname ASC";
    $stmt=$conn->prepare($sql);$stmt->bind_param('s',$date);$stmt->execute();
    $rows=[];$present=0;$late=0;$working=0;
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc()){
        $row['id']=(int)$row['id'];$row['present']=$row['attendance_id']!==null;
        $row['hours']=(float)($row['hours']??0);
        if($row['present']){$present++;if((int)$row['status']===0)$late++;if($row['time_out']==='00:00:00')$working++;}
        $rows[]=$row;
    }
    return ['success'=>true,'work_date'=>$date,'total_employees'=>count($rows),'present'=>$present,
        'absent'=>max(0,count($rows)-$present),'late'=>$late,'working'=>$working,'data'=>$rows];
}

function fetchMonthlyAttendanceRoster($month) {
    global $conn;
    $start=$month.'-01';
    $end=(new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $today=newYorkNow()->format('Y-m-d');
    if($end>$today)$end=$today;
    $stmt=$conn->prepare("SELECT e.id,e.employee_id,
            TRIM(CONCAT_WS(' ',e.firstname,e.lastname)) legal_name,
            u.nick_name company_name,u.email username,p.position_name role,
            COUNT(DISTINCT a.date) present_days,
            SUM(CASE WHEN a.status=0 THEN 1 ELSE 0 END) late_days,
            SUM(CASE WHEN a.work_status='half_day' THEN 1 ELSE 0 END) half_days,
            ROUND(SUM(CASE WHEN a.time_out<>'00:00:00' AND a.time_out>a.time_in
                THEN TIMESTAMPDIFF(SECOND,a.time_in,a.time_out)/3600
                ELSE COALESCE(a.num_hr,0) END),2) total_hours
        FROM employees e
        INNER JOIN users u ON u.id=e.user_id
        LEFT JOIN positions p ON p.id=u.position_id
        LEFT JOIN attendance a ON a.employee_id=e.id AND a.date BETWEEN ? AND ?
        WHERE e.user_id IS NOT NULL
        GROUP BY e.id,e.employee_id,e.firstname,e.lastname,u.nick_name,u.email,p.position_name
        ORDER BY e.firstname,e.lastname");
    $stmt->bind_param('ss',$start,$end);$stmt->execute();
    $rows=[];$result=$stmt->get_result();
    while($row=$result->fetch_assoc()){
        $row['id']=(int)$row['id'];$row['present_days']=(int)$row['present_days'];
        $row['late_days']=(int)$row['late_days'];$row['half_days']=(int)$row['half_days'];
        $row['total_hours']=(float)($row['total_hours']??0);$row=array_merge($row,(new AttendancePolicyService())->calculate((int)$row['id'],$month));$rows[]=$row;
    }
    return ['success'=>true,'month'=>$month,'start_date'=>$start,'end_date'=>$end,'data'=>$rows];
}
function fetchEmployeeById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT e.id, e.employee_id, e.firstname, e.lastname,
            TRIM(CONCAT_WS(' ', e.firstname, e.lastname)) AS legal_name,
            e.address, e.birthdate, e.contact_info, e.gender, e.position_id,
            e.schedule_id, e.photo, e.created_on, e.user_id, e.date_of_joining, e.payroll_email, e.candidate_collection, e.payroll_profile,
            u.nick_name AS company_name, u.email AS username,
            u.status AS user_status, u.last_login, p.position_name AS role
        FROM employees e
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN positions p ON p.id = COALESCE(u.position_id,e.position_id)
        WHERE e.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return null;
    $row['collection'] = employeePublicCollection(employeeDecodeCollection($row['candidate_collection']));
    $row['upload_limits'] = employeeUploadLimits();
    $row['payroll_profile']=payrollSecureData(json_decode($row['payroll_profile']??'[]',true)?:[],false);
    unset($row['candidate_collection']);
    return $row;
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

// Call within the employee creation transaction. The singleton row also locks an empty employee table safely.
function employeeNextCode(): string {
 global $conn;
 $result=$conn->query('SELECT `last_value` FROM employee_id_sequence WHERE id=2 FOR UPDATE');
 if(!$result || !($sequence=$result->fetch_assoc())) throw new RuntimeException('Apply the attendance configurations and 20261005_employee_profile_editing migrations before creating employees.');
 $result=$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(employee_id,7) AS UNSIGNED)),0) maximum FROM employees WHERE employee_id REGEXP '^BDT-I-[0-9]+$'");
 if(!$result) throw new RuntimeException('Employee IDs could not be allocated.');
 $next=max((int)$sequence['last_value'],(int)$result->fetch_assoc()['maximum'])+1;
 $stmt=$conn->prepare('UPDATE employee_id_sequence SET `last_value`=? WHERE id=2');$stmt->bind_param('i',$next);employeeExecute($stmt);
 return 'BDT-I-'.str_pad((string)$next,3,'0',STR_PAD_LEFT);
}
function employeeValidateRecord(array $data, bool $create): array {
    foreach (['employee_id','firstname','lastname','address','birthdate','contact_info','gender'] as $field) {
        $data[$field]=employeeCollectionText($data[$field] ?? '',$field,$field==='address'?5000:250);
        if ($data[$field]==='' && !in_array($field,['employee_id','address'],true)) throw new InvalidArgumentException($field.' is required.');
    }
    $data['employee_id']=$data['employee_id']===''?null:$data['employee_id'];
    if ($data['employee_id']!==null && !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,49}$/',$data['employee_id'])) throw new InvalidArgumentException('Employee ID must contain up to 50 letters, numbers, hyphens or underscores.');
    $data['birthdate']=employeeCollectionDate($data['birthdate'],'Birth date',true);
    $data['date_of_joining']=employeeCollectionDate(($data['date_of_joining']??'')==='0000-00-00'?'':($data['date_of_joining']??''),'Date of joining');
    if (!in_array($data['gender'],['Male','Female','Other'],true)) throw new InvalidArgumentException('Select a valid gender.');
    if ($create && empty($data['position_id'])) throw new InvalidArgumentException('Position is required.');
    foreach (['position_id','schedule_id'] as $field) {
        $value=$data[$field] ?? null;
        if ($value===null || $value==='') {$data[$field]=null;continue;}
        if (filter_var($value,FILTER_VALIDATE_INT)===false || (int)$value<0 || ($field==='position_id'&&(int)$value===0)) throw new InvalidArgumentException('Invalid '.$field.'.');
        $data[$field]=(int)$value;
    }
    $data['photo']=employeeCollectionText($data['photo'] ?? '', 'Photo',500);
    return $data;
}
function createEmployeeRecord(array $data) {
    global $conn;
    $createdPaths=[];$transaction=false;
    try {
        $data=employeeValidateRecord($data,true);
        $conn->begin_transaction();$transaction=true;
        $stmt=$conn->prepare('INSERT INTO employees(employee_id,firstname,lastname,address,birthdate,contact_info,gender,position_id,schedule_id,photo,date_of_joining,created_on) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $joining=$data['date_of_joining']?:null;
        $stmt->bind_param('sssssssiiss',$data['employee_id'],$data['firstname'],$data['lastname'],$data['address'],$data['birthdate'],$data['contact_info'],$data['gender'],$data['position_id'],$data['schedule_id'],$data['photo'],$joining);
        employeeExecute($stmt);$id=(int)$conn->insert_id;
        employeeSaveCollection($id,$data,[],$createdPaths);
        $profile=[];
        foreach(['pan_number','uan_number','pf_account_number','esi_number','bank_name','bank_account_number','ifsc_code','pay_mode'] as $field) if(array_key_exists($field,$data))$profile[$field]=employeeCollectionText($data[$field],$field,250);
        if($profile){$secured=json_encode(payrollSecureData($profile,true),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$bank=$conn->prepare('UPDATE employees SET payroll_profile=? WHERE id=?');$bank->bind_param('si',$secured,$id);employeeExecute($bank);}
        $conn->commit();$transaction=false;
        return ['success'=>true,'message'=>'Employee information and documents saved.', 'employee_id'=>$data['employee_id'],'id'=>$id];
    } catch (Throwable $error) {
        if ($transaction) $conn->rollback();employeeCleanupDocuments($createdPaths);
        if ((int)$error->getCode()===1062) return ['success'=>false,'message'=>'This employee ID is already assigned to another employee.'];
        if (!($error instanceof InvalidArgumentException)) error_log('Create employee: '.$error->getMessage());
        return ['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Employee could not be saved. Check the employee ID and try again.'];
    }
}
function updateEmployeeRecord($id, array $data, bool $personalOnly=false) {
    global $conn;
    $createdPaths=[];$transaction=false;
    try {
        $conn->begin_transaction();$transaction=true;
        $lock=$conn->prepare('SELECT candidate_collection,payroll_profile FROM employees WHERE id=? FOR UPDATE');
        $lock->bind_param('i',$id);employeeExecute($lock);$record=$lock->get_result()->fetch_assoc();
        if (!$record) {$conn->rollback();return ['success'=>false,'not_found'=>true,'message'=>'Employee not found.'];}
        $existing=fetchEmployeeById($id);
        if(isset($data['edit_section'])){
            $section=(string)$data['edit_section'];
            $sections=['personal'=>['email'],'family'=>['father_name','father_phone','mother_name','mother_phone','siblings'],'education'=>['education'],'experience'=>['has_experience','employment'],'certifications'=>['certifications','achievements','professional_certifications'],'references'=>['references'],'documents'=>[],'bank'=>[]];
            if(!array_key_exists($section,$sections))throw new InvalidArgumentException('Invalid profile section.');
            $saved=employeeDecodeCollection($record['candidate_collection']);
            $patch=$data['collection']??[];if(!is_array($patch))throw new InvalidArgumentException('Invalid profile information.');
            $collection=array_replace($saved,array_intersect_key($patch,array_flip($sections[$section])));
            $allowed=$section==='personal'?['employee_id','firstname','lastname','address','birthdate','contact_info','gender','position_id','schedule_id','date_of_joining']:($section==='bank'?['pan_number','uan_number','pf_account_number','esi_number','bank_name','bank_account_number','ifsc_code','pay_mode']:[]);
            $data=array_intersect_key($data,array_flip(array_merge($allowed,['document_upload_count'])));
            if($section!=='bank')$data['collection']=$collection;
            if($section==='personal' && empty($collection['email']))throw new InvalidArgumentException('Personal email is required.');
            if($section==='family')foreach(['father_name','mother_name'] as $field)if(trim((string)($collection[$field]??''))==='')throw new InvalidArgumentException('Please complete the parent names.');
            if($section==='bank')foreach(['pan_number','bank_name','bank_account_number','ifsc_code'] as $field)if(trim((string)($data[$field]??''))==='')throw new InvalidArgumentException('Bank name, account number, IFSC and PAN are required.');
        }
        if($personalOnly){
            $data=array_intersect_key($data,array_flip(['firstname','lastname','address','birthdate','contact_info','gender','date_of_joining','collection','document_upload_count','pan_number','uan_number','pf_account_number','esi_number','bank_name','bank_account_number','ifsc_code','pay_mode']));
            if(isset($data['collection']) && is_array($data['collection'])){
                $saved=employeeDecodeCollection($record['candidate_collection']);
                $data['collection']['selected_role']=$saved['selected_role']??'';
                $data['collection']['review']=$saved['review']??[];
            }
        }
        $assigned=trim((string)($existing['employee_id']??''));
        $requested=trim((string)($data['employee_id']??$assigned));
        if (!$personalOnly && $assigned!=='' && $requested!==$assigned) throw new InvalidArgumentException('Employee ID has already been assigned and cannot be replaced or removed.');
        $data['employee_id']=$personalOnly?($assigned?:null):($requested?:null);
        // Portrait changes use the validated photo-upload endpoint only.
        $data['photo']=$existing['photo']??'';

        foreach (['employee_id','firstname','lastname','address','birthdate','contact_info','gender','position_id','schedule_id','photo','date_of_joining'] as $field) if (!array_key_exists($field,$data)) $data[$field]=$existing[$field] ?? '';
        $data=employeeValidateRecord($data,false);
        $stmt=$conn->prepare('UPDATE employees SET employee_id=?,firstname=?,lastname=?,address=?,birthdate=?,contact_info=?,gender=?,position_id=?,schedule_id=?,photo=?,date_of_joining=? WHERE id=?');
        $joining=$data['date_of_joining']?:null;
        $stmt->bind_param('sssssssiissi',$data['employee_id'],$data['firstname'],$data['lastname'],$data['address'],$data['birthdate'],$data['contact_info'],$data['gender'],$data['position_id'],$data['schedule_id'],$data['photo'],$joining,$id);employeeExecute($stmt);
        employeeSaveCollection((int)$id,$data,employeeDecodeCollection($record['candidate_collection']),$createdPaths,false,false);
        $profile=payrollSecureData(json_decode($record['payroll_profile']??'[]',true)?:[],false);
        $profileChanged=true;
        $profile['date_of_joining']=$data['date_of_joining'];$profile['date_of_birth']=$data['birthdate'];$profile['gender']=$data['gender'];$profile['permanent_address']=$data['address'];
        if(isset($data['collection']['father_name']))$profile['father_name']=employeeCollectionText($data['collection']['father_name'],'Father name',500);
        foreach(['pan_number','uan_number','pf_account_number','esi_number','bank_name','bank_account_number','ifsc_code','pay_mode'] as $field){if(array_key_exists($field,$data)){$profile[$field]=employeeCollectionText($data[$field],$field,250);$profileChanged=true;}}
        if($profileChanged){$secured=json_encode(payrollSecureData($profile,true),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$bank=$conn->prepare('UPDATE employees SET payroll_profile=? WHERE id=?');$bank->bind_param('si',$secured,$id);employeeExecute($bank);}
        $conn->commit();$transaction=false;
        return ['success'=>true,'message'=>'Employee information and documents updated.'];
    } catch (Throwable $error) {
        if ($transaction) $conn->rollback();employeeCleanupDocuments($createdPaths);
        if ((int)$error->getCode()===1062) return ['success'=>false,'message'=>'This employee ID is already assigned to another employee.'];
        if (!($error instanceof InvalidArgumentException)) error_log('Update employee: '.$error->getMessage());
        return ['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Employee could not be updated. Check the employee ID and try again.'];
    }
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

function fetchAttendance($employeeId, $startDate, $endDate, $page=1, $limit=20, array $filters=[]) {
    global $conn;
    $page=max(1,(int)$page);$limit=min(100,max(1,(int)$limit));$offset=($page-1)*$limit;
    $hoursExpression="CASE WHEN time_out<>'00:00:00' AND time_out>time_in
        THEN TIMESTAMPDIFF(SECOND,time_in,time_out)/3600 ELSE COALESCE(num_hr,0) END";
    $summaryStmt=$conn->prepare("SELECT COUNT(DISTINCT date) present_days,
        SUM(status=1) on_time_days, SUM(status=0) late_days,
        SUM(work_status='half_day') half_days,
        ROUND(AVG($hoursExpression),2) average_hours,
        ROUND(COALESCE(SUM($hoursExpression),0),2) total_hours
        FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?");
    $summaryStmt->bind_param('iss',$employeeId,$startDate,$endDate);$summaryStmt->execute();
    $summary=$summaryStmt->get_result()->fetch_assoc();
    foreach(['present_days','on_time_days','late_days','half_days'] as $key)$summary[$key]=(int)$summary[$key];
    foreach(['average_hours','total_hours'] as $key)$summary[$key]=(float)($summary[$key]??0);
    $historyWhere='employee_id=? AND date BETWEEN ? AND ?';$historyTypes='iss';$historyParams=[(int)$employeeId,$startDate,$endDate];
    $statusClause=['on_time'=>'status=1','late'=>'status=0','half_day'=>"work_status='half_day'",'working'=>"time_out='00:00:00'",'completed'=>"work_status='completed'"];
    if(isset($statusClause[$filters['status']??'all']))$historyWhere.=' AND '.$statusClause[$filters['status']];
    if(!empty($filters['month'])){$historyWhere.=' AND DATE_FORMAT(date,\'%Y-%m\')=?';$historyTypes.='s';$historyParams[]=$filters['month'];}
    if(!empty($filters['search_date'])){$historyWhere.=' AND date=?';$historyTypes.='s';$historyParams[]=$filters['search_date'];}
    $countStmt=$conn->prepare('SELECT COUNT(*) total FROM attendance WHERE '.$historyWhere);
    $countStmt->bind_param($historyTypes,...$historyParams);$countStmt->execute();
    $total=(int)$countStmt->get_result()->fetch_assoc()['total'];
    $stmt=$conn->prepare("SELECT id,date,time_in,time_out,status,admin_created,work_status,source,notes,
        ROUND($hoursExpression,2) hours
        FROM attendance WHERE $historyWhere ORDER BY date DESC,id DESC LIMIT ? OFFSET ?");
    $recordParams=array_merge($historyParams,[$limit,$offset]);$stmt->bind_param($historyTypes.'ii',...$recordParams);$stmt->execute();
    $records=[];$result=$stmt->get_result();while($row=$result->fetch_assoc()){$row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$row['admin_created']=(int)$row['admin_created'];$records[]=$row;}
    $trendStmt=$conn->prepare("SELECT date,ROUND(SUM($hoursExpression),2) hours,
        MAX(status) status FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ? GROUP BY date ORDER BY date");
    $trendStmt->bind_param('iss',$employeeId,$startDate,$endDate);$trendStmt->execute();
    $trend=[];$result=$trendStmt->get_result();while($row=$result->fetch_assoc())$trend[]=$row;
    $calendarStmt=$conn->prepare('SELECT date,MAX(admin_created) admin_created,MAX(work_status=\'half_day\') half_day FROM attendance
        WHERE employee_id=? AND date BETWEEN ? AND ? GROUP BY date ORDER BY date');
    $calendarStmt->bind_param('iss',$employeeId,$startDate,$endDate);$calendarStmt->execute();
    $calendar=[];$result=$calendarStmt->get_result();while($row=$result->fetch_assoc()){$row['admin_created']=(int)$row['admin_created'];$calendar[]=$row;}
    $holidayStmt=$conn->prepare('SELECT id,holiday_date date,name,description,is_optional FROM holidays
        WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date');
    $holidayStmt->bind_param('ss',$startDate,$endDate);$holidayStmt->execute();
    $holidays=$holidayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $leaveStmt=$conn->prepare("SELECT id,start_date,end_date,duration,leave_type,reason FROM leave_requests
        WHERE employee_id=? AND status='approved' AND start_date<=? AND end_date>=? ORDER BY start_date");
    $leaveStmt->bind_param('iss',$employeeId,$endDate,$startDate);$leaveStmt->execute();
    $leaves=$leaveStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $holidayDates=[];foreach($holidays as $holiday)if(!(int)$holiday['is_optional'])$holidayDates[$holiday['date']]=true;
    $leaveDays=[];foreach($leaves as $leave){$first=new DateTimeImmutable(max($startDate,$leave['start_date']));$last=min($endDate,$leave['end_date']);for($day=$first;$day->format('Y-m-d')<=$last;$day=$day->modify('+1 day')){if((int)$day->format('N')>=6||isset($holidayDates[$day->format('Y-m-d')]))continue;$key=$day->format('Y-m-d');$leaveDays[$key]=max($leaveDays[$key]??0,$leave['duration']==='full_day'?1:.5);}}
    $summary['leave_days']=array_sum($leaveDays);
    return ['success'=>true,'summary'=>$summary,'trend'=>$trend,'calendar'=>$calendar,'holidays'=>$holidays,
        'leaves'=>$leaves,'data'=>$records,'total'=>$total,'page'=>$page,'limit'=>$limit,
        'pagination'=>[
            'total'=>$total,
            'page'=>$page,
            'limit'=>$limit,
            'total_pages'=>max(1,(int)ceil($total/$limit)),
        ]];
}

function attendanceSettings() {
    global $conn;
    $result=$conn->query('SELECT * FROM attendance_settings WHERE id=1');
    $settings=$result->fetch_assoc();
    $settings['grace_minutes']=30;
    return $settings;
}

function attendanceOnTime(string $time,array $settings):int {
    $cutoff=(new DateTimeImmutable('2000-01-01 '.$settings['work_start'],new DateTimeZone('America/New_York')))->modify('+30 minutes')->format('H:i:s');
    return $time<=$cutoff?1:0;
}

function newYorkNow() {
    return new DateTimeImmutable('now',new DateTimeZone('America/New_York'));
}

function fetchAttendanceIpPermissions() {
    global $conn;
    $result=$conn->query("SELECT e.id employee_id,e.employee_id employee_code,
            TRIM(CONCAT_WS(' ',e.firstname,e.lastname)) legal_name,
            u.nick_name company_name,u.email username,p.position_name role,
            COALESCE(w.wfh_allowed,0) wfh_allowed,w.updated_at
        FROM employees e INNER JOIN users u ON u.id=e.user_id
        LEFT JOIN positions p ON p.id=u.position_id
        LEFT JOIN attendance_wfh_permissions w ON w.employee_id=e.id
        WHERE e.user_id IS NOT NULL ORDER BY e.firstname,e.lastname");
    if(!$result)return ['success'=>false,'message'=>'Run the per-user WFH permission migration first.'];
    $rows=[];while($row=$result->fetch_assoc()){$row['employee_id']=(int)$row['employee_id'];$row['wfh_allowed']=(bool)$row['wfh_allowed'];$rows[]=$row;}
    return ['success'=>true,'data'=>$rows];
}
function employeeWfhAllowed($employeeId) {
    global $conn;$stmt=$conn->prepare('SELECT wfh_allowed FROM attendance_wfh_permissions WHERE employee_id=? LIMIT 1');
    if(!$stmt)return false;$stmt->bind_param('i',$employeeId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    return (bool)($row['wfh_allowed']??false);
}
function saveEmployeeWfhPermission($employeeId,$allowed,$updatedBy) {
    global $conn;$value=$allowed?1:0;
    $stmt=$conn->prepare('SELECT id FROM employees WHERE id=? AND user_id IS NOT NULL LIMIT 1');
    $stmt->bind_param('i',$employeeId);$stmt->execute();if(!$stmt->get_result()->fetch_assoc())return ['success'=>false,'not_found'=>true,'message'=>'Active employee not found.'];
    $stmt=$conn->prepare('INSERT INTO attendance_wfh_permissions(employee_id,wfh_allowed,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE wfh_allowed=VALUES(wfh_allowed),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
    if(!$stmt)return ['success'=>false,'message'=>'Run the per-user WFH permission migration first.'];
    $stmt->bind_param('iii',$employeeId,$value,$updatedBy);$stmt->execute();
    return ['success'=>true,'message'=>$allowed?'WFH attendance access granted for this employee.':'WFH attendance access removed. Company IP restriction now applies.','employee_id'=>$employeeId,'wfh_allowed'=>(bool)$allowed];
}
function attendanceClientIp() {
    $remote=trim((string)($_SERVER['REMOTE_ADDR']??''));
    $isTrustedLocalProxy=in_array($remote,['127.0.0.1','::1','::'],true);
    if($isTrustedLocalProxy){
        $forwarded=trim((string)($_SERVER['HTTP_X_FORWARDED_FOR']??''));
        $candidates=$forwarded!==''?explode(',',$forwarded):[];
        $realIp=trim((string)($_SERVER['HTTP_X_REAL_IP']??''));
        if($realIp!=='')$candidates[]=$realIp;
        foreach($candidates as $candidate){
            $candidate=trim($candidate);
            if(filter_var($candidate,FILTER_VALIDATE_IP)!==false&&!in_array($candidate,['127.0.0.1','::1','::'],true))return $candidate;
        }
        // Never treat the reverse proxy's loopback address as the employee IP.
        return '';
    }
    return filter_var($remote,FILTER_VALIDATE_IP)!==false?$remote:'';
}
function ipMatchesNetwork($ip,$network) {
    $network=trim($network);
    if($network===''||filter_var($ip,FILTER_VALIDATE_IP)===false)return false;
    if(strpos($network,'/')===false)return hash_equals(strtolower($network),strtolower($ip));
    [$subnet,$prefix]=array_pad(explode('/',$network,2),2,null);
    $ipBinary=@inet_pton($ip);$subnetBinary=@inet_pton($subnet);
    if($ipBinary===false||$subnetBinary===false||strlen($ipBinary)!==strlen($subnetBinary))return false;
    $prefix=filter_var($prefix,FILTER_VALIDATE_INT);
    $maxBits=strlen($ipBinary)*8;
    if($prefix===false||$prefix<0||$prefix>$maxBits)return false;
    $wholeBytes=intdiv($prefix,8);$remainingBits=$prefix%8;
    if(substr($ipBinary,0,$wholeBytes)!==substr($subnetBinary,0,$wholeBytes))return false;
    if($remainingBits===0)return true;
    $mask=(0xFF<<(8-$remainingBits))&0xFF;
    return (ord($ipBinary[$wholeBytes])&$mask)===(ord($subnetBinary[$wholeBytes])&$mask);
}

function companyIpAllows($ip,$configured) {
    $networks=array_filter(array_map('trim',preg_split('/[\s,;]+/',(string)$configured)));
    if(!$networks)return false;
    foreach($networks as $network)if(ipMatchesNetwork($ip,$network))return true;
    return false;
}

function getTodayAttendanceState($employeeId) {
    global $conn;
    $now=newYorkNow();$date=$now->format('Y-m-d');
    $stmt=$conn->prepare('SELECT id,date,time_in,time_out,status,work_status,source,num_hr hours
        FROM attendance WHERE employee_id=? AND date=? LIMIT 1');
    $stmt->bind_param('is',$employeeId,$date);$stmt->execute();
    $record=$stmt->get_result()->fetch_assoc();
    if($record){$record['id']=(int)$record['id'];$record['hours']=(float)$record['hours'];}
    $settings=attendanceSettings();$clientIp=attendanceClientIp();$wfhAllowed=employeeWfhAllowed($employeeId);
    return ['success'=>true,'timezone'=>'America/New_York','current_time'=>$now->format(DateTimeInterface::ATOM),
        'work_date'=>$date,'schedule'=>$settings,'record'=>$record,
        'network'=>['restriction_enabled'=>(bool)($settings['ip_restriction_enabled']??1),'wfh_allowed'=>$wfhAllowed,
            'allowed'=>!(bool)($settings['ip_restriction_enabled']??1)||$wfhAllowed||companyIpAllows($clientIp,$settings['allowed_ip_addresses']??''),'ip'=>$clientIp]];
}

function clockAttendance($employeeId,$userId,$action) {
    global $conn;
    $now=newYorkNow();$date=$now->format('Y-m-d');$time=$now->format('H:i:s');
    $utc=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $settings=attendanceSettings();
    $clientIp=attendanceClientIp();
    if((bool)($settings['ip_restriction_enabled']??1)&&!employeeWfhAllowed($employeeId)&&!companyIpAllows($clientIp,$settings['allowed_ip_addresses']??'')){
        return ['success'=>false,'message'=>'Time In and Time Out are only available from the company network. Your current IP is '.$clientIp.'.'];
    }
    $conn->begin_transaction();
    try{
        $stmt=$conn->prepare('SELECT id FROM employees WHERE id=? FOR UPDATE');
        $stmt->bind_param('i',$employeeId);$stmt->execute();
        if(!$stmt->get_result()->fetch_assoc())throw new DomainException('Employee profile was not found.');
        $stmt=$conn->prepare('SELECT id,name FROM holidays WHERE holiday_date=? LIMIT 1');
        $stmt->bind_param('s',$date);$stmt->execute();$holiday=$stmt->get_result()->fetch_assoc();
        if($holiday)throw new DomainException('Today is a company holiday: '.$holiday['name'].'.');
        $stmt=$conn->prepare("SELECT id FROM leave_requests WHERE employee_id=? AND status='approved'
            AND ? BETWEEN start_date AND end_date LIMIT 1");
        $stmt->bind_param('is',$employeeId,$date);$stmt->execute();
        if($stmt->get_result()->fetch_assoc())throw new DomainException('You have approved leave for this date.');
        $stmt=$conn->prepare('SELECT * FROM attendance WHERE employee_id=? AND date=? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('is',$employeeId,$date);$stmt->execute();$record=$stmt->get_result()->fetch_assoc();
        if($action==='in'){
            if($record)throw new DomainException($record['time_out']==='00:00:00'?'You are already timed in.':'Attendance is already completed for today.');
            $onTime=attendanceOnTime($time,$settings);
            $zero='00:00:00';$hours=0.0;$admin=0;$workStatus='working';$source='self_service';
            $stmt=$conn->prepare('INSERT INTO attendance
                (employee_id,date,time_in,time_out,status,admin_created,work_status,source,num_hr,clock_in_utc,clock_in_ip,updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isssiissdssi',$employeeId,$date,$time,$zero,$onTime,$admin,$workStatus,$source,$hours,$utc,$clientIp,$userId);
            $stmt->execute();$message='Time in recorded at '.$now->format('g:i A').' ET.';
        }else{
            if(!$record)throw new DomainException('Time in before timing out.');
            if($record['time_out']!=='00:00:00')throw new DomainException('You are already timed out.');
            $start=new DateTimeImmutable($date.' '.$record['time_in'],new DateTimeZone('America/New_York'));
            $hours=round(max(0,$now->getTimestamp()-$start->getTimestamp())/3600,2);
            $workStatus=$hours<(float)$settings['half_day_below_hours']?'half_day':'completed';
            $stmt=$conn->prepare('UPDATE attendance SET time_out=?,num_hr=?,work_status=?,clock_out_utc=?,clock_out_ip=?,updated_by=? WHERE id=?');
            $stmt->bind_param('sdsssii',$time,$hours,$workStatus,$utc,$clientIp,$userId,$record['id']);$stmt->execute();
            $message='Time out recorded at '.$now->format('g:i A').' ET. '.$hours.' hours worked'.($workStatus==='half_day'?' (half day).':'.');
        }
        $conn->commit();
        $state=getTodayAttendanceState($employeeId);$state['message']=$message;return $state;
    }catch(Throwable $error){$conn->rollback();return ['success'=>false,'message'=>$error->getMessage()];}
}

function fetchHolidays($year) {
    global $conn;$start="$year-01-01";$end="$year-12-31";
    $stmt=$conn->prepare('SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date');
    $stmt->bind_param('ss',$start,$end);$stmt->execute();return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function upsertHoliday($data,$userId) {
    global $conn;$date=trim((string)($data['holiday_date']??''));$name=trim((string)($data['name']??''));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$name==='')return ['success'=>false,'message'=>'Holiday date and name are required.'];
    $description=trim((string)($data['description']??''));$optional=!empty($data['is_optional'])?1:0;
    $stmt=$conn->prepare('INSERT INTO holidays(holiday_date,name,description,is_optional,created_by) VALUES(?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),is_optional=VALUES(is_optional)');
    $stmt->bind_param('sssii',$date,$name,$description,$optional,$userId);
    return $stmt->execute()?['success'=>true,'message'=>'Holiday saved.']:['success'=>false,'message'=>'Holiday could not be saved.'];
}
function deleteHoliday($id) {
    global $conn;$stmt=$conn->prepare('DELETE FROM holidays WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();
    return $stmt->affected_rows?['success'=>true,'message'=>'Holiday removed.']:['success'=>false,'message'=>'Holiday not found.'];
}
function fetchLeaves($employeeId,$filters=[]) {
    global $conn;$where='';$types='';$params=[];
    if($employeeId){$where='WHERE l.employee_id=?';$types='i';$params[]=$employeeId;}
    elseif(!empty($filters['status'])){$where='WHERE l.status=?';$types='s';$params[]=$filters['status'];}
    $stmt=$conn->prepare("SELECT l.*,e.employee_id,TRIM(CONCAT_WS(' ',e.firstname,e.lastname)) employee_name,
        COALESCE(NULLIF(l.reviewer_name,''),NULLIF(TRIM(u.nick_name),''),u.email) reviewer_display_name
        FROM leave_requests l JOIN employees e ON e.id=l.employee_id
        LEFT JOIN users u ON u.id=l.reviewed_by $where ORDER BY l.created_at DESC LIMIT 200");
    if($params)$stmt->bind_param($types,...$params);$stmt->execute();return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function submitLeave($employeeId,$data,$reviewedBy=null) {
    global $conn;$type=$data['leave_type']??'paid';$start=$data['start_date']??'';$end=$data['end_date']??'';
    $duration=$data['duration']??'full_day';$reason=trim((string)($data['reason']??''));
    $types=['paid','sick','casual','unpaid','bereavement','other'];$durations=['full_day','first_half','second_half'];
    if(!in_array($type,$types,true)||!in_array($duration,$durations,true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||$end<$start||$reason==='')
        return ['success'=>false,'message'=>'Valid leave type, dates, duration, and reason are required.'];
    $stmt=$conn->prepare("SELECT id FROM leave_requests WHERE employee_id=? AND status IN('pending','approved')
        AND start_date<=? AND end_date>=? LIMIT 1");$stmt->bind_param('iss',$employeeId,$end,$start);$stmt->execute();
    if($stmt->get_result()->fetch_assoc())return ['success'=>false,'message'=>'A leave request already overlaps these dates.'];
    if($reviewedBy){
        $status='approved';
        $stmt=$conn->prepare('INSERT INTO leave_requests
            (employee_id,leave_type,start_date,end_date,duration,reason,status,reviewed_by,reviewed_at)
            VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $stmt->bind_param('issssssi',$employeeId,$type,$start,$end,$duration,$reason,$status,$reviewedBy);
        $message='Employee leave added and approved.';
    }else{
        $approvalToken=bin2hex(random_bytes(32));$approvalTokenHash=hash('sha256',$approvalToken);
        $stmt=$conn->prepare("INSERT INTO leave_requests(employee_id,leave_type,start_date,end_date,duration,reason,approval_token_hash,approval_token_expires_at)
            VALUES(?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))");
        $stmt->bind_param('issssss',$employeeId,$type,$start,$end,$duration,$reason,$approvalTokenHash);
        $message='Leave request submitted for approval.';
    }
    if(!$stmt->execute())return ['success'=>false,'message'=>'Leave request could not be submitted.'];
    $result=['success'=>true,'message'=>$message,'leave_id'=>(int)$conn->insert_id];
    if(isset($approvalToken))$result['approval_token']=$approvalToken;
    return $result;
}
function fetchLeaveEmailDetails($id) {
    global $conn;$stmt=$conn->prepare("SELECT l.*,e.employee_id,TRIM(CONCAT_WS(' ',e.firstname,e.lastname)) employee_name
        FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.id=? LIMIT 1");
    $stmt->bind_param('i',$id);$stmt->execute();return $stmt->get_result()->fetch_assoc();
}
function updateLeaveStatus($id,$data,$userId) {
    global $conn;$status=$data['status']??'';$comment=trim((string)($data['manager_comment']??''));
    if(!in_array($status,['approved','rejected','cancelled'],true))return ['success'=>false,'message'=>'Invalid leave status.'];
    $stmt=$conn->prepare("SELECT COALESCE(NULLIF(TRIM(nick_name),''),email) reviewer_name FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$userId);$stmt->execute();$reviewer=$stmt->get_result()->fetch_assoc();$reviewerName=$reviewer['reviewer_name']??'HR user';$source='Website';
    $stmt=$conn->prepare("UPDATE leave_requests SET status=?,manager_comment=?,reviewed_by=?,reviewer_name=?,review_source=?,reviewed_at=UTC_TIMESTAMP(),approval_token_hash=NULL,approval_token_expires_at=NULL
        WHERE id=? AND status='pending'");$stmt->bind_param('ssissi',$status,$comment,$userId,$reviewerName,$source,$id);$stmt->execute();
    return $stmt->affected_rows?['success'=>true,'message'=>'Leave request '.$status.'.']:['success'=>false,'message'=>'Pending leave request not found.'];
}
function reviewLeaveByEmailToken($token,$status,$reviewerName) {
    global $conn;if(!preg_match('/^[a-f0-9]{64}$/',$token)||!in_array($status,['approved','rejected'],true))return ['success'=>false,'message'=>'This approval link is invalid.'];
    $hash=hash('sha256',$token);$source='Email';
    $stmt=$conn->prepare("UPDATE leave_requests SET status=?,reviewer_name=?,review_source=?,reviewed_at=UTC_TIMESTAMP(),approval_token_hash=NULL,approval_token_expires_at=NULL
        WHERE approval_token_hash=? AND approval_token_expires_at>=UTC_TIMESTAMP() AND status='pending'");
    $stmt->bind_param('ssss',$status,$reviewerName,$source,$hash);$stmt->execute();
    return $stmt->affected_rows?['success'=>true,'message'=>'Leave request '.$status.' successfully.']:['success'=>false,'message'=>'This approval link has expired or was already used.'];
}
function adminEditAttendance($id,$data,$userId) {
    global $conn;$timeIn=$data['time_in']??'';$timeOut=$data['time_out']??'';$notes=trim((string)($data['notes']??''));
    if(!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$timeIn)||!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$timeOut)||$timeOut<=$timeIn)
        return ['success'=>false,'message'=>'Valid time in and later time out are required.'];
    $hours=round((strtotime($timeOut)-strtotime($timeIn))/3600,2);$settings=attendanceSettings();
    $workStatus=$hours<(float)$settings['half_day_below_hours']?'half_day':'completed';
    $status=attendanceOnTime($timeIn,$settings);
    $stmt=$conn->prepare("UPDATE attendance SET time_in=?,time_out=?,num_hr=?,work_status=?,status=?,notes=?,source='admin',updated_by=? WHERE id=?");
    $stmt->bind_param('ssdsssii',$timeIn,$timeOut,$hours,$workStatus,$status,$notes,$userId,$id);$stmt->execute();
    return $stmt->affected_rows>=0?['success'=>true,'message'=>'Attendance updated.','hours'=>$hours,'work_status'=>$workStatus]:['success'=>false,'message'=>'Attendance record not found.'];
}

function saveAdminAttendanceDate($employeeId, $date, $present) {
    global $conn;
    $conn->begin_transaction();
    try {
        $stmt=$conn->prepare('SELECT id FROM employees WHERE id=? FOR UPDATE');
        $stmt->bind_param('i',$employeeId);$stmt->execute();
        if(!$stmt->get_result()->fetch_assoc())throw new RuntimeException('Employee not found.');

        $stmt=$conn->prepare('SELECT id,admin_created FROM attendance WHERE employee_id=? AND date=? FOR UPDATE');
        $stmt->bind_param('is',$employeeId,$date);$stmt->execute();
        $records=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if($present){
            if($records){$conn->commit();return ['success'=>true,'message'=>'Attendance is already recorded for this date.'];}
            $timeIn='09:30:00';$timeOut='18:30:00';$status=1;$hours=9.0;$adminCreated=1;
            $workStatus='completed';$source='admin';
            $stmt=$conn->prepare('INSERT INTO attendance
                (employee_id,date,time_in,time_out,status,num_hr,admin_created,work_status,source)
                VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isssidiss',$employeeId,$date,$timeIn,$timeOut,$status,$hours,$adminCreated,$workStatus,$source);
            if(!$stmt->execute())throw new RuntimeException('Attendance could not be added.');
            $message='Attendance added for '.$date.'.';
        }else{
            if(!$records){$conn->commit();return ['success'=>true,'message'=>'No attendance exists for this date.'];}
            foreach($records as $record)if((int)$record['admin_created']!==1)throw new DomainException('Clocked attendance cannot be removed from the calendar.');
            $stmt=$conn->prepare('DELETE FROM attendance WHERE employee_id=? AND date=? AND admin_created=1');
            $stmt->bind_param('is',$employeeId,$date);
            if(!$stmt->execute())throw new RuntimeException('Attendance could not be removed.');
            $message='Admin attendance removed for '.$date.'.';
        }
        $conn->commit();
        return ['success'=>true,'message'=>$message];
    }catch(Throwable $error){
        $conn->rollback();
        return ['success'=>false,'not_found'=>$error->getMessage()==='Employee not found.','message'=>$error->getMessage()];
    }
}
