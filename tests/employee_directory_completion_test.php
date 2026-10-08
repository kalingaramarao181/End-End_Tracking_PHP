<?php
if(PHP_SAPI!=='cli'||getenv('E2E_COLLECTION_TEST_MODE')!=='1'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';require_once __DIR__.'/../modules/employee/payroll.php';
$fixtures=json_decode(file_get_contents($argv[1]),true,64,JSON_THROW_ON_ERROR);
foreach($fixtures as $fixture){$e=$fixture['employee'];if(employeeProfileCompletionPercentage($e,employeeUpgradeCollection($e['collection']),$e['payroll_profile']??[])!==$fixture['expected'])throw new RuntimeException('Profile parity failed');}
$definition=$conn->query('SHOW CREATE TABLE employees')->fetch_assoc()['Create Table'];$lines=array_filter(explode("\n",$definition),fn($line)=>!str_contains($line,'CONSTRAINT '));$conn->query(preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',preg_replace('/,\s*\)/',')',implode("\n",$lines))));
$e=$fixtures[1]['employee'];$collection=payrollEncryptValue(json_encode($e['collection']));$payroll=json_encode(payrollSecureData($e['payroll_profile'],true));
$stmt=$conn->prepare("INSERT INTO employees(firstname,lastname,contact_info,gender,birthdate,candidate_collection,payroll_profile) VALUES('Test','Employee','123','Male','2000-01-01',?,?)");$stmt->bind_param('ss',$collection,$payroll);$stmt->execute();
$rows=fetchEmployees(['employment'=>'all','limit'=>10,'offset'=>0])['data'];
if(count($rows)!==1||$rows[0]['profile_completion']!==100)throw new RuntimeException('List percentage incorrect');
foreach(['candidate_collection','payroll_profile','payroll_email','collection'] as $key)if(array_key_exists($key,$rows[0]))throw new RuntimeException('List exposed private profile data');
echo "PASS profile calculation parity, encrypted list integration, and private data exclusion (temporary table only).\n";

$conn->query("CREATE TEMPORARY TABLE users(id INT PRIMARY KEY,nick_name VARCHAR(100),email VARCHAR(100),status VARCHAR(30),position_id INT)");
$conn->query("CREATE TEMPORARY TABLE positions(id INT PRIMARY KEY,position_name VARCHAR(100))");
$conn->query("INSERT INTO positions VALUES(1,'IT Recruiter'),(2,'Bench Sales'),(3,'HR')");
$conn->query("INSERT INTO users VALUES(1,'Beedata','test@example.test','Active',1),(2,'Other','other@example.test','Inactive',2)");
$conn->query("UPDATE employees SET user_id=1,position_id=1,date_of_joining='2026-10-01'");
$conn->query("INSERT INTO employees(firstname,lastname,contact_info,gender,birthdate,user_id,position_id,date_of_joining) VALUES('Inactive','Employee','123','Male','2000-01-01',2,2,'2026-10-02'),('Unassigned','Employee','123','Male','2000-01-01',NULL,3,'2026-10-03')");
$base=['employment'=>'all','limit'=>1,'offset'=>0];$result=fetchEmployees($base);
if($result['total']!==3||count($result['data'])!==1||$result['summary']!==['total'=>3,'active'=>1,'inactive'=>2,'recruiters'=>1,'bench_sales'=>1,'other_roles'=>1])throw new RuntimeException('Global metrics/pagination incorrect');
foreach(['active'=>1,'inactive'=>1,'unassigned'=>1] as $status=>$expected){if(fetchEmployees($base+['status'=>$status])['total']!==$expected)throw new RuntimeException('Status filter failed');}
$result=fetchEmployees($base+['company'=>'Beedata','role'=>'IT Recruiter','joining_date'=>'2026-10-01']);
if($result['total']!==1||$result['data'][0]['date_of_joining']!=='2026-10-01'||count($result['options']['roles'])!==3||$result['summary']['total']!==3)throw new RuntimeException('Combined filters/options failed');
if(fetchEmployees($base+['company'=>"Beedata' OR 1=1 --"])['total']!==0)throw new RuntimeException('Unsafe company filter');
echo "PASS global directory metrics, pagination, combined filters, unassigned roles, and prepared filter inputs (temporary tables only).\n";

$conn->query("CREATE TEMPORARY TABLE attendance(id INT PRIMARY KEY,employee_id INT,date DATE,time_in TIME,time_out TIME,status INT,work_status VARCHAR(30),source VARCHAR(30),num_hr DECIMAL(8,2))");
$id=(int)$conn->query("SELECT id FROM employees WHERE user_id=1")->fetch_assoc()['id'];
$conn->query("INSERT INTO attendance VALUES(1,$id,'2026-10-07','09:55:00','18:30:00',1,'completed','employee',8.58)");
$roster=fetchAttendanceRoster('2026-10-07');$settings=attendanceSettings();
if($roster['present']!==1||$roster['late']!==0||$roster['data'][0]['shift_start']!==$settings['work_start']||$roster['data'][0]['shift_end']!==$settings['work_end'])throw new RuntimeException('Daily roster/shift data failed');
echo "PASS attendance roster presence, on-time status, and configured shift data (temporary tables only).\n";
