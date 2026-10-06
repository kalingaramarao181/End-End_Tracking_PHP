<?php
// Development-only integration check. All writes use temporary tables.
if(PHP_SAPI!=='cli'||getenv('E2E_COLLECTION_TEST_MODE')!=='1'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';
function insightsCheck($condition,$message){if(!$condition)throw new RuntimeException($message);}
foreach(['attendance','holidays','leave_requests'] as $table){$definition=$conn->query('SHOW CREATE TABLE `'.$table.'`')->fetch_assoc()['Create Table'];$lines=array_filter(explode("\n",$definition),fn($line)=>!str_contains($line,'CONSTRAINT '));$definition=preg_replace('/,\s*\)/',"\n)",implode("\n",$lines));$conn->query(preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$definition));}
$conn->query("INSERT INTO attendance(employee_id,date,time_in,time_out,status,num_hr,work_status) VALUES(7,'2026-10-01','09:30:00','18:30:00',1,9,'completed'),(7,'2026-10-02','10:00:00','14:30:00',0,4.5,'half_day'),(7,'2026-10-05','09:30:00','18:30:00',1,9,'completed')");
$conn->query("INSERT INTO holidays(holiday_date,name,is_optional) VALUES('2026-10-06','Test Holiday',0)");
$conn->query("INSERT INTO leave_requests(employee_id,leave_type,start_date,end_date,duration,reason,status) VALUES(7,'paid','2026-10-06','2026-10-06','full_day','Test','approved'),(7,'paid','2026-10-07','2026-10-07','first_half','Test','approved')");
$all=fetchAttendance(7,'2026-10-01','2026-10-31',1,2);insightsCheck($all['summary']['total_hours']===22.5&&$all['summary']['half_days']===1,'Hours / half-day summary incorrect');insightsCheck($all['summary']['leave_days']===.5,'Leave totals should exclude holidays and preserve half leave');insightsCheck($all['pagination']['total']===3&&count($all['data'])===2,'Pagination incorrect');
$late=fetchAttendance(7,'2026-10-01','2026-10-31',1,10,['status'=>'late']);insightsCheck($late['total']===1&&$late['data'][0]['date']==='2026-10-02','Late filtering incorrect');insightsCheck($late['summary']['total_hours']===22.5,'History filter changed report summary');
$search=fetchAttendance(7,'2026-10-01','2026-10-31',1,10,['status'=>'half_day','month'=>'2026-10','search_date'=>'2026-10-02']);insightsCheck($search['total']===1&&count($search['data'])===1,'Combined filters incorrect');
$empty=fetchAttendance(7,'2026-10-01','2026-10-31',1,10,['month'=>'2026-09']);insightsCheck($empty['total']===0&&$empty['data']===[],'Empty month incorrect');
$second=fetchAttendance(7,'2026-10-01','2026-10-31',2,2);insightsCheck(count($second['data'])===1&&$second['data'][0]['date']==='2026-10-01','Second page incorrect');
insightsCheck((int)$all['calendar'][1]['half_day']===1,'Calendar half-day missing');
echo "PASS attendance summary, leave totals, server filters, pagination and calendar half-days (temporary tables only).\n";
