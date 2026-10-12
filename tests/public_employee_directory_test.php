<?php
if (PHP_SAPI !== 'cli' || getenv('E2E_COLLECTION_TEST_MODE') !== '1') {http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/public_employees/service.php';
function directoryAssert($condition,string $message): void {if (!$condition) throw new RuntimeException($message);}
foreach (['https://beedatatechnology.com','https://beedatatech.com'] as $origin) directoryAssert(publicEmployeeAllowedOrigin($origin),'Approved origin rejected');
foreach ([null,'null','https://evil.com','http://beedatatech.com','https://beedatatech.com.evil.com','https://evilbeedatatech.com','https://beedatatech.com:8443','https://beedatatech.com/path','https://beedatatech.com@evil.com','https://www.beedatatech.com'] as $origin) directoryAssert(!publicEmployeeAllowedOrigin($origin),'Unsafe origin accepted');
foreach ([['limit'=>101],['page'=>0],['page'=>[]],['employment'=>[]],['employee_id'=>[]]] as $query) {
    try {publicEmployeeFilters($query);throw new RuntimeException('Invalid filter accepted');} catch (InvalidArgumentException $expected) {}
}
$today=new DateTimeImmutable('2026-10-09');
$row=['id'=>7,'employee_id'=>'BDT-I-007','firstname'=>'Test','lastname'=>'Employee','role'=>'HR','linked_user_id'=>1,'photo'=>str_repeat('a',32).'.photo.enc','date_of_joining'=>'2024-01-01','date_of_leaving'=>null,'recorded_workdays'=>12,'email'=>'private@example.test','payroll_profile'=>'secret'];
$data=publicEmployeeProjection($row,'/E2E_Tracking',$today);
directoryAssert($data['is_working'] && $data['tenure']['years']===2 && $data['recorded_workdays']===12,'Current tenure incorrect');
directoryAssert(count($data)===10 && !isset($data['email']) && !isset($data['payroll_profile']) && !isset($data['linked_user_id']),'Private data exposed');
directoryAssert($data['profile_image_url']==='/E2E_Tracking/public/employees/7/photo','Photo URL incorrect');
$row['linked_user_id']=null;$row['date_of_leaving']='2025-01-01';$data=publicEmployeeProjection($row,'',$today);
directoryAssert($data['employment_status']==='left' && $data['tenure']['calendar_days']===366 && $data['tenure']['years']===1,'Former tenure incorrect');
$row['date_of_leaving']=null;$row['photo']='../../private';$data=publicEmployeeProjection($row,'',$today);
directoryAssert($data['employment_status']==='not_working' && $data['tenure']===null && $data['profile_image_url']===null,'Unknown dates or unsafe photo invented');
foreach (['2026-02-30','2030-01-01','2023-01-01',[]] as $date) {
    try {employeeValidateLeavingDate($date,'2024-01-01');throw new RuntimeException('Invalid departure accepted');} catch (InvalidArgumentException $expected) {}
}
require __DIR__.'/../modules/employee/model.php';
require __DIR__.'/../modules/user/model.php';
// These temporary tables shadow real tables only for this connection.
$conn->query('CREATE TEMPORARY TABLE employees(id INT PRIMARY KEY,user_id INT NULL,employee_id VARCHAR(100),firstname VARCHAR(100),lastname VARCHAR(100),photo VARCHAR(100),date_of_joining DATE,date_of_leaving DATE,position_id INT)');
$conn->query('CREATE TEMPORARY TABLE users(id INT PRIMARY KEY,nick_name VARCHAR(100),position_id INT,status VARCHAR(20))');
$conn->query('CREATE TEMPORARY TABLE positions(id INT PRIMARY KEY,position_name VARCHAR(100))');
$conn->query('CREATE TEMPORARY TABLE attendance(id INT PRIMARY KEY,employee_id INT,date DATE,time_in TIME,work_status VARCHAR(30))');
$conn->query("INSERT INTO positions VALUES(3,'HR'),(4,'IT Recruiter')");
$conn->query("INSERT INTO users VALUES(1,'Test',4,'Inactive'),(2,'Delete test',3,'Active')");
$conn->query("INSERT INTO employees VALUES(7,1,'BDT-I-007','Test','Current','', '2024-01-01',NULL,3),(8,NULL,'BDT-I-008','Test','Former','', '2024-01-01','2025-01-01',3),(9,NULL,'BDT-I-009','Test','Unknown','',NULL,NULL,3),(10,999,'BDT-I-010','Test','Orphan','',NULL,NULL,3),(11,2,'BDT-I-011','Delete','Employee','', '2024-01-01',NULL,3)");
$conn->query("INSERT INTO attendance VALUES(1,7,'2026-01-01','09:00:00','completed'),(2,7,'2026-01-01','09:01:00','completed'),(3,7,'2026-01-02','09:00:00','half_day'),(4,7,'2026-01-03','00:00:00','absent'),(5,7,'2030-01-01','09:00:00','completed'),(6,7,'2026-01-04','00:00:00','leave')");
$result=publicEmployeeList($conn,publicEmployeeFilters([]),'/E2E_Tracking');
directoryAssert($result['total']===5 && $result['data'][0]['role']==='IT Recruiter' && $result['data'][0]['is_working'],'Linked account status/role incorrect');
directoryAssert($result['data'][0]['recorded_workdays']===2,'Attendance duplicate/future/absence exclusion failed');
directoryAssert(!$result['data'][3]['is_working'],'Orphaned account considered employed');
directoryAssert(publicEmployeeList($conn,publicEmployeeFilters(['employment'=>'working']),'')['total']===2,'Employment filter incorrect');
directoryAssert(publicEmployeeList($conn,publicEmployeeFilters(['employee_id'=>"' OR 1=1 --"]),'')['total']===0,'Unsafe filter input');
directoryAssert(count(publicEmployeeList($conn,publicEmployeeFilters(['limit'=>1,'page'=>2]),'')['data'])===1,'Pagination failed');
$conn->query("INSERT INTO positions VALUES(1,'Super Admin'),(2,'Admin'),(12,'Super-Administrator')");
$conn->query("INSERT INTO users VALUES(20,'Administrator',2,'Active'),(21,'Super administrator',1,'Active'),(22,'Current HR',3,'Active')");
$conn->query("INSERT INTO employees VALUES(20,20,'ADMIN-20','Private','Admin','',NULL,NULL,3),(21,21,'ADMIN-21','Private','Super','',NULL,NULL,3),(22,NULL,'ADMIN-22','Private','Former','',NULL,NULL,2),(23,NULL,'ADMIN-23','Private','Super alias','',NULL,NULL,12),(24,22,'ADMIN-24','Private','Saved admin','',NULL,NULL,1)");
$result=publicEmployeeList($conn,publicEmployeeFilters([]),'');directoryAssert($result['total']===6 && count($result['data'])===6,'Admin exclusion/count mismatch');
directoryAssert(publicEmployeeList($conn,publicEmployeeFilters(['employee_id'=>'ADMIN-20']),'')['total']===0,'Admin ID filter bypass');
foreach([20,21,22,23] as $adminId) directoryAssert(!publicEmployeeIsVisible($conn,$adminId),'Admin photo access bypass');
directoryAssert(publicEmployeeIsVisible($conn,7),'Normal employee hidden');
directoryAssert(publicEmployeeIsVisible($conn,24),'Current HR hidden by stale saved admin role');
directoryAssert(publicEmployeeList($conn,publicEmployeeFilters(['employee_id'=>'ADMIN-24']),'')['data'][0]['role']==='HR','Current HR role incorrect');
$conn->query('DELETE FROM employees WHERE id>=20');
$result=removeCompanyUser(7,'2025-02-01');directoryAssert($result['success'],'Unlink failed');
$row=$conn->query('SELECT * FROM employees WHERE id=7')->fetch_assoc();directoryAssert($row['user_id']===null && $row['date_of_leaving']==='2025-02-01' && (int)$row['position_id']===4,'Departure/role not preserved');
$result=employeeSetLeavingDate(7,'2025-03-01');directoryAssert($result['success'],'Historical date correction failed');
$result=assignCompanyUser(7,1);directoryAssert($result['success'],'Relink failed');directoryAssert($conn->query('SELECT date_of_leaving FROM employees WHERE id=7')->fetch_assoc()['date_of_leaving']===null,'Relink left departure date');
try {employeeSetLeavingDate(7,'2025-03-01');throw new RuntimeException('Linked employee departure accepted');} catch (InvalidArgumentException $expected) {}
// Fail DELETE after the real departure UPDATE and verify a real database rollback.
class DirectoryFailingDeleteConnection {
    private $database;
    public function __construct($database) {$this->database=$database;}
    public function query($sql) {return $this->database->query($sql);}
    public function prepare($sql) {
        if (strpos($sql,'DELETE FROM users')===0) throw new RuntimeException('Simulated deletion failure');
        return $this->database->prepare($sql);
    }
    public function begin_transaction() {return $this->database->begin_transaction();}
    public function commit() {return $this->database->commit();}
    public function rollback() {return $this->database->rollback();}
}
$userModel=new UserModel();$property=new ReflectionProperty(UserModel::class,'conn');$property->setAccessible(true);
$property->setValue($userModel,new DirectoryFailingDeleteConnection($conn));
directoryAssert(!$userModel->deleteUser(2)['success'],'Failed deletion incorrectly succeeded');
directoryAssert($conn->query('SELECT date_of_leaving FROM employees WHERE id=11')->fetch_assoc()['date_of_leaving']===null,'Failed deletion did not roll back departure');
$property->setValue($userModel,$conn);
directoryAssert($userModel->deleteUser(2)['success'],'User deletion failed');
$departure=$conn->query('SELECT date_of_leaving FROM employees WHERE id=11')->fetch_assoc()['date_of_leaving'];directoryAssert($departure===date('Y-m-d'),'User deletion did not record departure');
// Exercise encrypted portrait readback without changing real stored portraits.
require_once __DIR__.'/../modules/employee/payroll.php';
require_once __DIR__.'/../modules/employee/EmployeePhotoService.php';
$photoDirectory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'e2e-public-photo-'.bin2hex(random_bytes(8));
$previousDirectory=getenv('EMPLOYEE_DOCUMENT_DIR');$previousKey=getenv('MAIL_CREDENTIAL_KEY');
mkdir($photoDirectory,0700);putenv('EMPLOYEE_DOCUMENT_DIR='.$photoDirectory);putenv('MAIL_CREDENTIAL_KEY=isolated-directory-test-key-at-least-32-chars');
$photoName=str_repeat('a',32).'.photo.enc';
try {
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII=');
    file_put_contents($photoDirectory.DIRECTORY_SEPARATOR.$photoName,payrollEncryptValue($png));
    $statement=$conn->prepare('UPDATE employees SET photo=? WHERE id=7');$statement->bind_param('s',$photoName);$statement->execute();
    ob_start();employeeReadPhoto(7);$bytes=ob_get_clean();header_remove('Content-Length');
    directoryAssert($bytes===$png,'Encrypted public photo readback corrupted');
} finally {
    if (is_file($photoDirectory.DIRECTORY_SEPARATOR.$photoName)) unlink($photoDirectory.DIRECTORY_SEPARATOR.$photoName);
    rmdir($photoDirectory);putenv($previousDirectory===false?'EMPLOYEE_DOCUMENT_DIR':'EMPLOYEE_DOCUMENT_DIR='.$previousDirectory);putenv($previousKey===false?'MAIL_CREDENTIAL_KEY':'MAIL_CREDENTIAL_KEY='.$previousKey);
}
// Tests also run before the migration: preserve old account operations and return unknown dates.
$conn->query('ALTER TABLE employees DROP COLUMN date_of_leaving');
directoryAssert(removeCompanyUser(7)['success'],'Pre-migration unlink compatibility failed');
$result=publicEmployeeList($conn,publicEmployeeFilters([]),'');directoryAssert($result['data'][0]['tenure']===null,'Pre-migration former tenure invented');
echo "PASS admin list/count/filter/photo exclusion, origin policy, validation, private data exclusion, current/former tenure, pagination, SQL input safety, attendance counts, unlink/relink, date correction, user deletion/rollback, encrypted photo readback, pre-migration compatibility (temporary tables only).\n";