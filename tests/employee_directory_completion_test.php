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
