<?php
if(PHP_SAPI!=='cli'||getenv('E2E_COLLECTION_TEST_MODE')!=='1'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';
foreach(['attendance','attendance_settings'] as $table){$definition=$conn->query('SHOW CREATE TABLE `'.$table.'`')->fetch_assoc()['Create Table'];$lines=array_filter(explode("\n",$definition),fn($line)=>!str_contains($line,'CONSTRAINT '));$conn->query(preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',implode("\n",$lines)));}
$conn->query("INSERT INTO attendance_settings(id,work_start,grace_minutes) VALUES(1,'09:30:00',0)");
$times=['09:30:01'=>1,'09:55:18'=>1,'10:00:00'=>1,'10:00:01'=>0,'10:00:28'=>0,'10:11:00'=>0];
foreach($times as $time=>$expected){if(attendanceOnTime($time,attendanceSettings())!==$expected)throw new RuntimeException('Incorrect cutoff: '.$time);$conn->query("INSERT INTO attendance(employee_id,date,time_in,time_out,status,num_hr,work_status) VALUES(7,'2026-09-30','$time','18:30:00',0,9,'completed')");}
$conn->multi_query(file_get_contents(__DIR__.'/../migrations/20261007_attendance_grace_period.sql'));do{if($r=$conn->store_result())$r->free();}while($conn->more_results()&&$conn->next_result());
foreach($conn->query('SELECT time_in,status,num_hr FROM attendance') as $row){if((int)$row['status']!==$times[$row['time_in']]||(float)$row['num_hr']!==9.0)throw new RuntimeException('Historical migration incorrect');}
$id=(int)$conn->query('SELECT id FROM attendance LIMIT 1')->fetch_assoc()['id'];
adminEditAttendance($id,['time_in'=>'09:55:18','time_out'=>'18:30:00','notes'=>'Verified'],1);
if((int)$conn->query('SELECT status FROM attendance WHERE id='.$id)->fetch_assoc()['status']!==1)throw new RuntimeException('Admin correction grace failed');
echo "PASS grace boundaries, historical migration, preserved hours and admin correction (temporary tables only).\n";
