<?php
// Run only through the isolated Python harness; this is never a production route.
if (getenv('E2E_COLLECTION_TEST_MODE') !== '1') {http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';
require __DIR__.'/../modules/employee/payroll.php';
function testCheck($condition,$message) { if (!$condition) throw new RuntimeException($message); }
$conn->query('CREATE TEMPORARY TABLE employee_id_sequence(id TINYINT PRIMARY KEY,`last_value` BIGINT NOT NULL)');$conn->query('INSERT INTO employee_id_sequence VALUES(1,0),(2,0)');
foreach (['employees','employee_onboarding_invites'] as $table) {
    $definition=$conn->query('SHOW CREATE TABLE `'.$table.'`')->fetch_assoc()['Create Table'];
    $lines=explode("\n",$definition);
    $lines=array_filter($lines,fn($line)=>!str_contains($line,'CONSTRAINT '));
    $definition=implode("\n",$lines);
    $definition=preg_replace('/,\s*\)/',"\n)",$definition);
    $definition=preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE',$definition);
    $conn->query($definition);
    testCheck((int)$conn->query('SELECT COUNT(*) n FROM `'.$table.'`')->fetch_assoc()['n']===0,'Test table is not isolated');
}
$conn->query('ALTER TABLE employees MODIFY employee_id VARCHAR(50) NULL DEFAULT NULL');
$token=str_repeat('b',64);$hash=hash('sha256',$token);$email='candidate@example.invalid';
$stmt=$conn->prepare('INSERT INTO employee_onboarding_invites(personal_email,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))');
$stmt->bind_param('ss',$email,$hash);$stmt->execute();
$conn->query("UPDATE employee_onboarding_invites SET selected_role=\"web_developer\"");
$mode=$_GET['mode'] ?? 'success';
$testDirectory=getenv('EMPLOYEE_DOCUMENT_DIR').DIRECTORY_SEPARATOR.$mode;
putenv('EMPLOYEE_DOCUMENT_DIR='.$testDirectory);
if ($mode==='database_failure') {
    $conn->query('ALTER TABLE employees MODIFY COLUMN candidate_collection VARCHAR(8) NULL');
    $conn->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
    mysqli_report(MYSQLI_REPORT_OFF);
}
if ($mode==='expired') $conn->query("UPDATE employee_onboarding_invites SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY)");
if ($mode==='storage_failure') putenv('EMPLOYEE_DOCUMENT_DIR='.__FILE__);
ob_start();payrollSubmitOnboarding($token);$response=json_decode(ob_get_clean(),true);
$status=http_response_code() ?: 200;
$employeeCount=(int)$conn->query('SELECT COUNT(*) n FROM employees')->fetch_assoc()['n'];
$completed=$conn->query('SELECT completed_at FROM employee_onboarding_invites')->fetch_assoc()['completed_at'];
try {
    if (!in_array($mode,['success','no_files','no_experience'],true)) {
        testCheck(!$response['success'],'Expected submission rejection');
        testCheck($employeeCount===0 && $completed===null,'Rejected submission left employee or consumed invite');
        testCheck(!is_dir($testDirectory) || count(glob($testDirectory.DIRECTORY_SEPARATOR.'*.enc'))===0,'Rejected submission left uploaded files');
        http_response_code(200);echo json_encode(['passed'=>true,'mode'=>$mode,'rejection_status'=>$status,'message'=>$response['message']]);exit;
    }
    testCheck($response['success'] && $employeeCount===1 && $completed!==null,'Valid submission failed: '.json_encode([$response,$employeeCount,$completed]));
    $record=$conn->query('SELECT * FROM employees')->fetch_assoc();$id=(int)$record['id'];
    $collection=employeeDecodeCollection($record['candidate_collection']);
    testCheck(str_starts_with($record['candidate_collection'],'enc:v1:'),'Collection was not encrypted');
    testCheck(!str_contains($record['candidate_collection'],'Test Father'),'Sensitive collection leaked');
    testCheck($collection['father_name']==='Test Father' && $collection['mother_name']==='Test Mother','Family data lost');
    testCheck($mode==='no_experience' ? !$collection['has_experience'] && $collection['employment']===[] : $collection['employment'][0]['company']==='Test Company' && count($collection['employment'])===5,'Employment data lost');
    testCheck($collection['declaration']['accepted']===true && $collection['declaration']['candidate_name']==='Test Candidate' && !empty($collection['declaration']['accepted_at']),'Declaration lost');
    testCheck($collection['selected_role']==='web_developer','Invitation role lost');
    testCheck($collection['education'][0]['college']==='Test College' && $collection['certifications'][0]['name']==='Test Certification' && $collection['references'][0]['name']==='Test Reference','Dynamic row details lost');
    testCheck($collection['review']['reviewed_by']==='','Public candidate supplied reviewer');
    $public=fetchEmployeeById($id);
    foreach ($public['collection']['documents'] as $document) testCheck(!isset($document['storage_name']),'Storage path exposed');
    if ($mode!=='no_files') {
        testCheck(count($collection['documents'])===($mode==='no_experience'?1:2),'Documents were not persisted');
        foreach ($collection['documents'] as $document) {
            $encrypted=file_get_contents(employeeDocumentDirectory().DIRECTORY_SEPARATOR.$document['storage_name']);
            testCheck(str_starts_with($encrypted,'enc:v1:') && !str_contains($encrypted,'%PDF'),'Document not encrypted');
            ob_start();employeeDownloadDocument($id,$document['id']);$download=ob_get_clean();header_remove("Content-Length");header_remove("Content-Disposition");header("Content-Type: application/json");
            testCheck(strlen($download)===$document['size'],'Document download corrupted');
        }
        testCheck($collection['checklist']['education_edu1_certificate']['status']==='submitted','Upload did not mark submitted');
    } else {
        testCheck($collection['checklist']['education_edu1_certificate']['status']==='pending','Fake submitted status accepted without file');
    }
    $_FILES=[];
    $edited=employeePublicCollection($collection);
    $edited['mother_phone']='9876543210';
    $edited['review']=['reviewed_by'=>'HR Reviewer','date'=>'2026-10-02'];
    $edited['declaration']['signature']='Forged admin signature';
    $edited['documents'][]=['id'=>'fake','storage_name'=>'../../bad'];
    $save=updateEmployeeRecord($id,['collection'=>$edited]);
    testCheck($save['success'],'Employee edit failed: '.json_encode($save));
    $updated=$conn->query('SELECT * FROM employees')->fetch_assoc();
    $saved=employeeDecodeCollection($updated['candidate_collection']);
    testCheck($saved['mother_phone']==='9876543210' && $saved['review']['reviewed_by']==='HR Reviewer','Staff changes lost');
    testCheck($saved['declaration']===$collection['declaration'],'Staff overwrote candidate declaration');
    testCheck(count($saved['documents'])===count($collection['documents']),'Client injected document metadata');
    testCheck($updated['position_id']===null && $updated['schedule_id']===null,'Unassigned employee position or schedule was changed');
    $beforeProfile=payrollSecureData(json_decode($record['payroll_profile'],true),false);$afterProfile=payrollSecureData(json_decode($updated['payroll_profile'],true),false);
    foreach(['pan_number','uan_number','pf_account_number','esi_number','bank_name','bank_account_number','ifsc_code'] as $key)testCheck(($afterProfile[$key]??'')===($beforeProfile[$key]??''),'Employee edit overwrote '.$key);

    // Reopening and editing must target the original employee without extending the deadline.
    http_response_code(200);ob_start();payrollOnboardingStatus($token);$reopened=json_decode(ob_get_clean(),true);
    testCheck($reopened['success'] && $reopened['submitted'] && $reopened['employee_id']===$record['employee_id'],'Submitted link could not reopen');
    testCheck($reopened['data']['collection']['mother_phone']==='9876543210','Reopened link did not load current data');
    if(!empty($collection['documents'])){
        $document=$collection['documents'][0];ob_start();payrollOnboardingDocument($token,$document['id']);$linkedDownload=ob_get_clean();header_remove('Content-Length');header_remove('Content-Disposition');header('Content-Type: application/json');
        testCheck(strlen($linkedDownload)===$document['size'],'Token-scoped document download failed');
    }
    $firstCompleted=$conn->query('SELECT completed_at FROM employee_onboarding_invites')->fetch_assoc()['completed_at'];
    $repeatPayload=employeeRequestData();$repeatPayload['document_upload_count']=0;$_POST['payload']=json_encode($repeatPayload);$_FILES=[];
    ob_start();payrollSubmitOnboarding($token);$repeat=json_decode(ob_get_clean(),true);
    testCheck($repeat['success'] && $repeat['employee_id']===$record['employee_id'],'Repeat submission did not edit same employee: '.json_encode($repeat));
    testCheck((int)$conn->query('SELECT COUNT(*) n FROM employees')->fetch_assoc()['n']===1,'Repeat submission duplicated employee');
    testCheck($conn->query('SELECT completed_at FROM employee_onboarding_invites')->fetch_assoc()['completed_at']===$firstCompleted,'Editing extended link deadline');
    $afterEdit=$conn->query('SELECT * FROM employees')->fetch_assoc();
    testCheck(employeeDecodeCollection($afterEdit['candidate_collection'])['review']['reviewed_by']==='HR Reviewer','Public edit erased HR review');
    $conn->query("UPDATE employee_onboarding_invites SET completed_at=DATE_SUB(NOW(),INTERVAL 5 DAY)");
    ob_start();payrollSubmitOnboarding($token);$expiredEdit=json_decode(ob_get_clean(),true);
    testCheck(!$expiredEdit['success'] && http_response_code()===410,'Expired edit window allowed mutation');
    ob_start();payrollOnboardingStatus($token);$expiredGet=json_decode(ob_get_clean(),true);testCheck(!$expiredGet['success'],'Expired link exposed personal data');
    http_response_code(200);

    foreach (employeePreOfferRoles() as $key=>$role) {
        $mail=employeePreOfferContent($key,'Test <Candidate>','https://e2e.beedatatech.com/employee-onboarding/test');
        testCheck(str_contains($mail['subject'],$role['name']) && str_contains($mail['html'],'Complete My Information Form'),'Pre-offer role or CTA missing');
        testCheck(str_contains($mail['html'],'Test &lt;Candidate&gt;') && !str_contains($mail['html'],'Test <Candidate>'),'Candidate name was not escaped');
        foreach ($role['responsibilities'] as $item) testCheck(str_contains($mail['plain'],$item),'Role responsibility missing');
    }
    testCheck(count(employeePreOfferRoles())===6,'Expected six pre-offer roles');
    try {employeePreOfferContent('invalid_role','Candidate','https://example.invalid');throw new RuntimeException('Invalid role accepted');}
    catch (InvalidArgumentException $expected) {}
    // Add Employee supports the same collection and does not claim a candidate signed it.
    $data=employeeRequestData();$data['document_upload_count']=0;$data['employee_id']='TEST-ADMIN-1';$data['position_id']=1;
    $data['pan_number']='ABCDE1234F';$data['bank_account_number']='123456789012';
    $data['collection']['review']=['reviewed_by'=>'HR Reviewer','date'=>'2026-10-02'];
    $created=createEmployeeRecord($data);testCheck($created['success'],'Admin employee create failed: '.json_encode($created));
    $admin=fetchEmployeeById($created['id']);
    testCheck($admin['payroll_profile']['pan_number']==='ABCDE1234F' && $admin['payroll_profile']['bank_account_number']==='123456789012','HR-created banking details were not saved');
    $optional=updateEmployeeRecord($created['id'],['edit_section'=>'family','collection'=>['father_name'=>'Father','mother_name'=>'Mother','father_phone'=>'','mother_phone'=>'']]);testCheck($optional['success'],'Optional parent phones rejected');
    testCheck($record['employee_id']===null && $admin['employee_id']==='TEST-ADMIN-1','Public ID should remain null; staff manual ID was not saved');
    $assigned=updateEmployeeRecord($id,['employee_id'=>'BDT-I-133','address'=>'','date_of_joining'=>'2026-10-01']);
    $assignedProfile=fetchEmployeeById($id);testCheck($assignedProfile['payroll_profile']['date_of_joining']==='2026-10-01' && $assignedProfile['payroll_profile']['permanent_address']==='','DOJ/address were not synchronized');
    testCheck($assigned['success'],'Staff ID assignment / optional address / DOJ save failed: '.json_encode($assigned));
    $replacement=updateEmployeeRecord($id,['employee_id'=>'BDT-I-134']);
    testCheck(!$replacement['success'],'Assigned employee ID could be replaced');
    $duplicate=updateEmployeeRecord($admin['id'],['employee_id'=>'BDT-I-133']);
    testCheck(!$duplicate['success'],'Assigned staff ID could be replaced');
    $unassigned=createEmployeeRecord(array_merge($data,['employee_id'=>null]));testCheck($unassigned['success'],'Nullable staff employee creation failed');
    $duplicate=updateEmployeeRecord($unassigned['id'],['employee_id'=>'BDT-I-133']);testCheck(!$duplicate['success'],'Duplicate employee ID accepted');
    $personal=updateEmployeeRecord($id,['firstname'=>'Updated','employee_id'=>'FORGED','position_id'=>99,'schedule_id'=>99,'date_of_joining'=>'2020-01-01','monthly_salary'=>999999,'collection'=>array_merge($saved,['selected_role'=>'hr','review'=>['reviewed_by'=>'Forged','date'=>'2026-10-01']])],true);
    testCheck($personal['success'],'Owner edit failed');$ownerAfter=$conn->query('SELECT * FROM employees WHERE id='.(int)$id)->fetch_assoc();
    testCheck($ownerAfter['firstname']==='Updated' && $ownerAfter['employee_id']==='BDT-I-133' && $ownerAfter['position_id']===null && $ownerAfter['schedule_id']===null && $ownerAfter['date_of_joining']==='2026-10-01','Personal edit changed protected fields');
    testCheck($ownerAfter['monthly_salary']===$record['monthly_salary'],'Personal edit changed salary');
    $ownerCollection=employeeDecodeCollection($ownerAfter['candidate_collection']);testCheck($ownerCollection['selected_role']==='web_developer' && $ownerCollection['review']['reviewed_by']==='HR Reviewer','Personal edit forged role or review');
    if(!empty($ownerCollection['documents'])){
        $doc=$ownerCollection['documents'][0];$_FILES=['documents'=>['error'=>[$doc['category']=>[UPLOAD_ERR_OK]]]];
        $blocked=updateEmployeeRecord($id,['collection'=>$ownerCollection],true);testCheck(!$blocked['success'] && str_contains($blocked['message'],'already uploaded'),'Self edit replaced a locked document');$_FILES=[];
        $detached=$ownerCollection;$detached['education']=[];
        $blocked=updateEmployeeRecord($id,['collection'=>$detached],true);testCheck(!$blocked['success'],'Self edit removed an entry with a locked document');
    }
    $sectionEdit=updateEmployeeRecord($id,['edit_section'=>'education','firstname'=>'Should not change','bank_name'=>'Should not change','collection'=>array_merge($ownerCollection,['father_name'=>'Should not change'])],true);
    testCheck($sectionEdit['success'],'Education section save failed');$sectionAfter=fetchEmployeeById($id);testCheck($sectionAfter['firstname']==='Updated' && $sectionAfter['collection']['father_name']===$ownerCollection['father_name'],'Section save overwrote unrelated profile details');
    testCheck(!$admin['collection']['declaration']['accepted'],'Admin forged candidate declaration');
    testCheck((int)$conn->query('SELECT COUNT(*) n FROM employees')->fetch_assoc()['n']===3,'Admin create did not persist');
    http_response_code(200);
    echo json_encode(['passed'=>true,'mode'=>$mode,'checks'=>['public submit','encrypted details','family/experience/declaration','encrypted uploads','download roundtrip','staff edit','payroll preserved','null assignments preserved','metadata injection rejected','four-day reusable invite','admin create']]);
} catch (Throwable $error) {
    http_response_code(500);echo json_encode(['passed'=>false,'message'=>$error->getMessage()]);
}
