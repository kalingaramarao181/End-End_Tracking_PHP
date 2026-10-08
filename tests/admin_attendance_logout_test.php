<?php
if(PHP_SAPI!=='cli'||getenv('E2E_COLLECTION_TEST_MODE')!=='1')exit;
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../modules/employee/model.php';
$conn->query("CREATE TEMPORARY TABLE attendance(id INT PRIMARY KEY,date DATE,time_in TIME,time_out TIME,num_hr DECIMAL(8,2),work_status VARCHAR(30),status INT,notes TEXT,source VARCHAR(30),updated_by INT,clock_out_utc DATETIME)");
$conn->query("INSERT INTO attendance VALUES(1,'2026-10-07','09:55:00','00:00:00',0,'working',1,'original','employee',NULL,NULL)");
function attempt($type){return adminLogoutAttendance(1,['day_type'=>$type],7);}
if(attempt('invalid')['success'])throw new RuntimeException('Invalid type accepted');
$r=attempt('full_day');if(!$r['success']||$r['hours']!=6||$r['time_out']!=='15:55:00'||$r['work_status']!=='completed')throw new RuntimeException('Automatic full day failed');
if(attempt('full_day')['success'])throw new RuntimeException('Duplicate logout replaced saved result');
$row=$conn->query('SELECT * FROM attendance WHERE id=1')->fetch_assoc();if($row['time_in']!=='09:55:00'||$row['status']!=1||$row['updated_by']!=7||!str_contains($row['notes'],'original')||$row['clock_out_utc']!=='2026-10-07 19:55:00')throw new RuntimeException('Audit/login/UTC data failed');
$conn->query("UPDATE attendance SET time_out='00:00:00'");$r=attempt('half_day');if(!$r['success']||$r['hours']!=4||$r['time_out']!=='13:55:00'||$r['work_status']!=='half_day')throw new RuntimeException('Automatic half day failed');
$conn->query("UPDATE attendance SET time_in='20:00:00',time_out='00:00:00'");if(attempt('full_day')['success']||attempt('half_day')['success'])throw new RuntimeException('Next-day duration accepted');
echo "PASS automatic full/half logout, invalid selection, duplicate protection, audit preservation and same-date restrictions (temporary table only).\n";
