<?php
if(getenv('E2E_COLLECTION_TEST_MODE')!=='1'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';
require __DIR__.'/../modules/employee/payroll.php';
require __DIR__.'/../modules/employee/EmployeePhotoService.php';
function photoTest($condition,$message){if(!$condition)throw new RuntimeException($message);}
try {
 $conn->query('CREATE TEMPORARY TABLE employees(id INT PRIMARY KEY,photo VARCHAR(100) NOT NULL)');$conn->query("INSERT INTO employees VALUES(7,'')");
 $file=$_FILES['photo'];$bytes=file_get_contents($file['tmp_name']);
 $first=employeeSavePhoto(7,$file);photoTest($first['success'],'Valid upload failed: '.json_encode($first));
 $old=employeeDocumentDirectory().DIRECTORY_SEPARATOR.$first['photo'];photoTest(is_file($old),'File not stored');photoTest(file_get_contents($old)!==$bytes,'Photo was not encrypted');
 ob_start();employeeReadPhoto(7);$read=ob_get_clean();header_remove('Content-Length');header('Content-Type: application/json');photoTest($bytes===$read,'Portrait download corrupted');
 $second=employeeSavePhoto(7,$file);photoTest($second['success'] && $second['photo']!==$first['photo'],'Replacement failed');photoTest(!is_file($old),'Replaced portrait was not cleaned up');
 $current=$conn->query('SELECT photo FROM employees WHERE id=7')->fetch_assoc()['photo'];photoTest($current===$second['photo'],'Replacement not saved in database');
 $files=count(glob(employeeDocumentDirectory().'/*.photo.enc'));$missing=employeeSavePhoto(999,$file);photoTest(!$missing['success'] && count(glob(employeeDocumentDirectory().'/*.photo.enc'))===$files,'Failed save left a file');
 $bad=employeeSavePhoto(7,$_FILES['invalid']);photoTest(!$bad['success'],'Disguised non-image accepted');
 $large=employeeSavePhoto(7,$_FILES['large']);photoTest(!$large['success'],'Oversize photo accepted');
 photoTest(!employeePhotoNameIsValid('../../photo.enc'),'Unsafe storage path accepted');
 photoTest($conn->query('SELECT photo FROM employees WHERE id=7')->fetch_assoc()['photo']===$current,'Rejected upload altered existing portrait');
 http_response_code(200);echo json_encode(['passed'=>true,'checks'=>['multipart upload','encrypted storage','download roundtrip','replacement','old file cleanup','failed save cleanup','disguised file rejected','oversize file rejected','path validation','existing portrait preserved']]);
} catch(Throwable $error){http_response_code(500);echo json_encode(['passed'=>false,'message'=>$error->getMessage()]);}
