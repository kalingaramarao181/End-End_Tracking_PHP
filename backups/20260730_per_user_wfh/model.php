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
            e.contact_info, e.gender, e.position_id, e.photo, e.created_on,
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
    while ($row = $result->fetch_assoc()) $rows[] = $row;
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
        $row['total_hours']=(float)($row['total_hours']??0);$rows[]=$row;
    }
    return ['success'=>true,'month'=>$month,'start_date'=>$start,'end_date'=>$end,'data'=>$rows];
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
    $countStmt=$conn->prepare('SELECT COUNT(*) total FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?');
    $countStmt->bind_param('iss',$employeeId,$startDate,$endDate);$countStmt->execute();
    $total=(int)$countStmt->get_result()->fetch_assoc()['total'];
    $stmt=$conn->prepare("SELECT id,date,time_in,time_out,status,admin_created,work_status,source,notes,
        ROUND($hoursExpression,2) hours
        FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ? ORDER BY date DESC,id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('issii',$employeeId,$startDate,$endDate,$limit,$offset);$stmt->execute();
    $records=[];$result=$stmt->get_result();while($row=$result->fetch_assoc()){$row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$row['admin_created']=(int)$row['admin_created'];$records[]=$row;}
    $trendStmt=$conn->prepare("SELECT date,ROUND(SUM($hoursExpression),2) hours,
        MAX(status) status FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ? GROUP BY date ORDER BY date");
    $trendStmt->bind_param('iss',$employeeId,$startDate,$endDate);$trendStmt->execute();
    $trend=[];$result=$trendStmt->get_result();while($row=$result->fetch_assoc())$trend[]=$row;
    $calendarStmt=$conn->prepare('SELECT date,MAX(admin_created) admin_created FROM attendance
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
    return ['success'=>true,'summary'=>$summary,'trend'=>$trend,'calendar'=>$calendar,'holidays'=>$holidays,
        'leaves'=>$leaves,'data'=>$records,'total'=>$total,'page'=>$page,'limit'=>$limit];
}

function attendanceSettings() {
    global $conn;
    $result=$conn->query('SELECT * FROM attendance_settings WHERE id=1');
    return $result->fetch_assoc();
}

function newYorkNow() {
    return new DateTimeImmutable('now',new DateTimeZone('America/New_York'));
}

function publicAttendanceSettings() {
    $settings=attendanceSettings();
    return ['ip_restriction_enabled'=>(bool)($settings['ip_restriction_enabled']??1),
        'allowed_ip_addresses'=>(string)($settings['allowed_ip_addresses']??'')];
}

function saveAttendanceIpSettings($enabled) {
    global $conn;$value=$enabled?1:0;
    $stmt=$conn->prepare('UPDATE attendance_settings SET ip_restriction_enabled=? WHERE id=1');
    if(!$stmt)return ['success'=>false,'message'=>'Run the attendance IP restriction migration first.'];
    $stmt->bind_param('i',$value);
    return $stmt->execute()
        ?['success'=>true,'message'=>$enabled?'IP restriction enabled for attendance.':'IP restriction disabled. Employees can clock attendance from WFH.','settings'=>publicAttendanceSettings()]
        :['success'=>false,'message'=>'Attendance restriction could not be updated.'];
}
function attendanceClientIp() {
    // REMOTE_ADDR is intentionally used instead of user-controlled forwarding
    // headers. If the API is behind a trusted reverse proxy, configure that
    // proxy/web server to replace REMOTE_ADDR with the verified client address.
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
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
    $settings=attendanceSettings();$clientIp=attendanceClientIp();
    return ['success'=>true,'timezone'=>'America/New_York','current_time'=>$now->format(DateTimeInterface::ATOM),
        'work_date'=>$date,'schedule'=>$settings,'record'=>$record,
        'network'=>['restriction_enabled'=>(bool)($settings['ip_restriction_enabled']??1),
            'allowed'=>!(bool)($settings['ip_restriction_enabled']??1)||companyIpAllows($clientIp,$settings['allowed_ip_addresses']??''),'ip'=>$clientIp]];
}

function clockAttendance($employeeId,$userId,$action) {
    global $conn;
    $now=newYorkNow();$date=$now->format('Y-m-d');$time=$now->format('H:i:s');
    $utc=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $settings=attendanceSettings();
    $clientIp=attendanceClientIp();
    if((bool)($settings['ip_restriction_enabled']??1)&&!companyIpAllows($clientIp,$settings['allowed_ip_addresses']??'')){
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
            $onTime=$time<=date('H:i:s',strtotime($settings['work_start'].' +'.(int)$settings['grace_minutes'].' minutes'))?1:0;
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
    $status=$timeIn<=date('H:i:s',strtotime($settings['work_start'].' +'.(int)$settings['grace_minutes'].' minutes'))?1:0;
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
