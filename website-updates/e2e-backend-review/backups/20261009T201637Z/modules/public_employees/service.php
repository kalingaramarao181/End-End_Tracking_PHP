<?php
require_once __DIR__.'/../employee/EmployeeEmploymentService.php';

function publicEmployeeAllowedOrigin($origin): bool {
    return is_string($origin) && in_array($origin, [
        'https://beedatatechnology.com', 'https://beedatatech.com'
    ], true);
}
function publicEmployeeError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success'=>false,'message'=>$message]);
}
// Run before any database access, including image requests and OPTIONS.
function publicEmployeeAccess(): bool {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('Vary: Origin');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: cross-origin');
    if (!publicEmployeeAllowedOrigin($_SERVER['HTTP_ORIGIN'] ?? null)) {
        publicEmployeeError(403,'This API is available only to the approved BeeData website origins.');return false;
    }
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '') !== 'GET') {
            publicEmployeeError(403,'Only GET requests are allowed.');return false;
        }
        $headers=explode(',',strtolower($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? ''));
        foreach ($headers as $value) {
            if (trim($value) !== '' && trim($value) !== 'content-type') {
                publicEmployeeError(403,'Requested header is not allowed.');return false;
            }
        }
        http_response_code(204);return false;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, OPTIONS');publicEmployeeError(405,'Only GET requests are allowed.');return false;
    }
    return true;
}
function publicEmployeeFilters(array $query): array {
    $out=[];
    foreach (['page'=>1,'limit'=>25] as $key=>$default) {
        $value=$query[$key] ?? $default;
        if (is_array($value) || filter_var($value,FILTER_VALIDATE_INT) === false || (int)$value<1 || (int)$value>($key==='limit'?100:1000000)) {
            throw new InvalidArgumentException($key==='limit'?'limit must be between 1 and 100.':'page must be between 1 and 1000000.');
        }
        $out[$key]=(int)$value;
    }
    $out['employment']=$query['employment'] ?? 'all';
    if (!in_array($out['employment'],['all','working','not_working'],true)) throw new InvalidArgumentException('employment must be all, working, or not_working.');
    $out['employee_id']=$query['employee_id'] ?? '';
    if (!is_string($out['employee_id']) || strlen($out['employee_id'])>100) throw new InvalidArgumentException('Invalid employee_id.');
    return $out;
}
function publicEmployeeDate($value): ?DateTimeImmutable {
    if (!is_string($value)) return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $date && $date->format('Y-m-d')===$value ? $date : null;
}
function publicEmployeeProjection(array $row, string $basePath, DateTimeImmutable $today): array {
    $working=$row['linked_user_id'] !== null;
    $joining=publicEmployeeDate($row['date_of_joining'] ?? null);
    $leaving=$working?null:publicEmployeeDate($row['date_of_leaving'] ?? null);
    $end=$working?$today:$leaving;
    $tenure=null;
    if ($joining && $end && $joining <= $end && $end <= $today) {
        $interval=$joining->diff($end);
        $tenure=['calendar_days'=>(int)$interval->days,'years'=>$interval->y,'months'=>$interval->m,'days'=>$interval->d,'through_date'=>$end->format('Y-m-d')];
    }
    $photo=(string)($row['photo'] ?? '');
    $hasPhoto=(bool)preg_match('/^[a-f0-9]{32}\.photo\.enc$/D',$photo);
    return [
        'employee_id'=>$row['employee_id'],
        'name'=>trim((string)$row['firstname'].' '.(string)$row['lastname']),
        'role'=>$row['role'] ?: null,
        'profile_image_url'=>$hasPhoto?$basePath.'/public/employees/'.(int)$row['id'].'/photo':null,
        'is_working'=>$working,
        'employment_status'=>$working?'working':($leaving?'left':'not_working'),
        'date_of_joining'=>$joining?$joining->format('Y-m-d'):null,
        'date_of_leaving'=>$leaving?$leaving->format('Y-m-d'):null,
        'tenure'=>$tenure,
        'recorded_workdays'=>(int)($row['recorded_workdays'] ?? 0)
    ];
}
function publicEmployeeList($database, array $filters, string $basePath): array {
    $clauses=[];$params=[];$types='';
    if ($filters['employment']==='working') $clauses[]='u.id IS NOT NULL';
    if ($filters['employment']==='not_working') $clauses[]='u.id IS NULL';
    if ($filters['employee_id']!=='') {$clauses[]='e.employee_id=?';$params[]=$filters['employee_id'];$types.='s';}
    $where=$clauses?' WHERE '.implode(' AND ',$clauses):'';
    $statement=$database->prepare('SELECT COUNT(*) total FROM employees e LEFT JOIN users u ON u.id=e.user_id'.$where);
    if ($params) $statement->bind_param($types,...$params);
    $statement->execute();$total=(int)$statement->get_result()->fetch_assoc()['total'];
    $leaving=employeeHasLeavingDate($database)?'e.date_of_leaving':'NULL AS date_of_leaving';
    $statement=$database->prepare("SELECT e.id,e.employee_id,e.firstname,e.lastname,e.photo,e.date_of_joining,$leaving,
        u.id linked_user_id,p.position_name role FROM employees e LEFT JOIN users u ON u.id=e.user_id
        LEFT JOIN positions p ON p.id=COALESCE(u.position_id,e.position_id)$where ORDER BY e.id ASC LIMIT ? OFFSET ?");
    $values=array_merge($params,[$filters['limit'],($filters['page']-1)*$filters['limit']]);
    $statement->bind_param($types.'ii',...$values);$statement->execute();
    $rows=$statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $today=new DateTimeImmutable('today');
    $workdays=[];
    if ($rows) {
        $ids=array_map(static function($row){return (int)$row['id'];},$rows);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $statement=$database->prepare("SELECT employee_id,COUNT(DISTINCT date) workdays FROM attendance
            WHERE employee_id IN ($marks) AND date<=? AND work_status IN ('working','completed','half_day')
            AND time_in IS NOT NULL AND time_in<>'00:00:00' GROUP BY employee_id");
        $values=array_merge($ids,[$today->format('Y-m-d')]);$statement->bind_param(str_repeat('i',count($ids)).'s',...$values);
        $statement->execute();foreach($statement->get_result() as $row) $workdays[(int)$row['employee_id']]=(int)$row['workdays'];
    }
    $data=[];
    foreach ($rows as $row) {$row['recorded_workdays']=$workdays[(int)$row['id']] ?? 0;$data[]=publicEmployeeProjection($row,$basePath,$today);}
    return ['success'=>true,'page'=>$filters['page'],'limit'=>$filters['limit'],'total'=>$total,
        'total_pages'=>(int)ceil($total/$filters['limit']),'as_of_date'=>$today->format('Y-m-d'),'timezone'=>date_default_timezone_get(),'data'=>$data];
}