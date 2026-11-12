<?php
// Candidate attendance is available remotely. Employee network restrictions are separate.
function tfSessionDate($row,$kind){$utc=$row[$kind.'_utc']??null;return $utc?(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/New_York')):new DateTimeImmutable($row[$kind.'_time'],new DateTimeZone('America/New_York'));}
function tfSessionEpoch($row,$kind){$utc=$row[$kind.'_utc']??null;return $utc?(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->getTimestamp():(new DateTimeImmutable($row[$kind.'_time'],new DateTimeZone('America/New_York')))->getTimestamp();}
function tfAttendanceTarget($input,$user,$permission){
 $candidate=tfOne('SELECT * FROM teamflow_candidates WHERE id=?',[(int)($input['candidate_id']??0)]);
 if(empty($input['candidate_id']))$candidate=tfOne('SELECT * FROM teamflow_candidates WHERE user_id=?',[$user['id']]);
 if(!$candidate)throw new InvalidArgumentException('Candidate profile not found.');
 if((int)$candidate['user_id']!==(int)$user['id']&&!tfAllScope($permission))throw new DomainException('You cannot view another candidate’s attendance.');
 return $candidate;
}
function tfCandidateInsights($input,$user,$permission){
 $candidate=tfAttendanceTarget($input,$user,$permission);$month=$input['month']??newYorkNow()->format('Y-m');
 if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)||substr($month,0,4)<'2000'||substr($month,0,4)>'2100')throw new InvalidArgumentException('Choose a valid month.');
 $zone=new DateTimeZone('America/New_York');$start=new DateTimeImmutable($month.'-01 00:00:00',$zone);$end=$start->modify('+1 month');$now=newYorkNow();$nowEpoch=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->getTimestamp();
 $rows=tfRows("SELECT a.*,p.name project_name FROM teamflow_employee_work_status a LEFT JOIN teamflow_projects p ON p.id=a.project_id WHERE a.user_id=? AND a.deleted_at IS NULL AND a.login_time<? AND COALESCE(a.logout_time,IF(a.clock_source='profile',?,a.login_time))>=? ORDER BY a.login_time DESC,a.id DESC",[$candidate['user_id'],$end->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')]);
 foreach($rows as &$row){$in=tfSessionEpoch($row,'login');$out=$row['logout_time']?tfSessionEpoch($row,'logout'):(($row['clock_source']??'legacy')==='profile'?$nowEpoch:$in);$row['duration_seconds']=max(0,$out-$in);}unset($row);
 $holidays=tfRows('SELECT date,title FROM teamflow_work_holidays WHERE date>=? AND date<?',[$start->format('Y-m-d'),$end->format('Y-m-d')]);$holidayMap=array_column($holidays,'title','date');$days=[];$seconds=0;
 for($d=$start;$d<$end;$d=$d->modify('+1 day')){$date=$d->format('Y-m-d');$next=$d->modify('+1 day');$elapsed=0;$working=false;$ids=[];
  foreach($rows as $row){$in=tfSessionEpoch($row,'login');$legacyOpen=!$row['logout_time']&&($row['clock_source']??'legacy')!=='profile';$out=$row['logout_time']?tfSessionEpoch($row,'logout'):($legacyOpen?$in:$nowEpoch);$overlap=max(0,min($out,$next->getTimestamp(),$nowEpoch)-max($in,$d->getTimestamp()));if($overlap>0||substr($row['login_time'],0,10)===$date){$elapsed+=$overlap;$ids[]=(int)$row['id'];if(!$row['logout_time']&&!$legacyOpen)$working=true;}}
  $seconds+=$elapsed;$days[]=['date'=>$date,'hours'=>round($elapsed/3600,2),'status'=>$working?'Working':($ids?'Present':(isset($holidayMap[$date])?'Holiday':((int)$d->format('N')>=6?'Weekend':($date>$now->format('Y-m-d')?'Upcoming':'No entry')))),'holiday'=>$holidayMap[$date]??null,'session_ids'=>$ids];
 }
 $open=tfOne("SELECT a.*,p.name project_name FROM teamflow_employee_work_status a LEFT JOIN teamflow_projects p ON p.id=a.project_id WHERE a.user_id=? AND a.clock_source='profile' AND a.logout_time IS NULL AND a.login_status=1 AND a.deleted_at IS NULL ORDER BY a.id DESC LIMIT 1",[$candidate['user_id']]);
 if($open)$open['duration_seconds']=max(0,$nowEpoch-tfSessionEpoch($open,'login'));
 $sheets=tfRows('SELECT DISTINCT t.id,t.year,t.week_no,t.total_hours,t.status FROM teamflow_weekly_timesheets t JOIN teamflow_timesheet_worklogs w ON w.timesheet_id=t.id WHERE w.user_id=? AND w.work_date>=? AND w.work_date<? AND t.deleted_at IS NULL ORDER BY t.year DESC,t.week_no DESC',[$candidate['user_id'],$start->format('Y-m-d'),$end->format('Y-m-d')]);
 return ['month'=>$month,'timezone'=>'America/New_York','current_time'=>$now->format(DATE_ATOM),'days'=>$days,'sessions'=>$rows,'open_session'=>$open,'timesheets'=>$sheets,'summary'=>['total_hours'=>round($seconds/3600,2),'days_worked'=>count(array_filter($days,fn($day)=>count($day['session_ids'])>0)),'completed_sessions'=>count(array_filter($rows,fn($row)=>!empty($row['logout_time']))),'holidays'=>count($holidays)],'can_clock'=>(int)$candidate['user_id']===(int)$user['id']&&!empty($permission['can_create'])];
}
function tfWorkProgress($input){$n=$input['progress_percent']??0;if(!is_numeric($n)||(float)$n<0||(float)$n>100)throw new InvalidArgumentException('Progress must be between 0 and 100.');return round((float)$n,2);}
function tfUpdateWork($input,$user){global $conn;$conn->begin_transaction();try{tfQ('SELECT id FROM teamflow_candidates WHERE user_id=? FOR UPDATE',[$user['id']]);$open=tfOne('SELECT * FROM teamflow_employee_work_status WHERE user_id=? AND logout_time IS NULL AND login_status=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',[$user['id']]);if(!$open)throw new DomainException('Clock in before updating work progress.');tfWriteRow('teamflow_employee_work_status',['worked_on'=>tfText($input,'worked_on',20000,true),'progress_percent'=>tfWorkProgress($input),'blockers'=>tfText($input,'blockers',5000)],$open['id']);$conn->commit();return $open['id'];}catch(Throwable $e){$conn->rollback();throw $e;}}
function tfAutomaticTimesheets($session){
 if(tfOne('SELECT id FROM teamflow_timesheet_worklogs WHERE session_id=? LIMIT 1',[$session['id']]))return;
 $zone=new DateTimeZone('America/New_York');$cursorEpoch=tfSessionEpoch($session,'login');$endEpoch=tfSessionEpoch($session,'logout');
 // Include even a zero-second session once, so its work details remain traceable.
 do{$cursor=(new DateTimeImmutable('@'.$cursorEpoch))->setTimezone($zone);$midnight=new DateTimeImmutable($cursor->format('Y-m-d').' 00:00:00',$zone);$nextEpoch=min($midnight->modify('+1 day')->getTimestamp(),$endEpoch);$seconds=max(0,$nextEpoch-$cursorEpoch);$year=(int)$cursor->format('o');$week=(int)$cursor->format('W');
  $sheet=tfOne("SELECT * FROM teamflow_weekly_timesheets WHERE user_id=? AND project_id=? AND year=? AND week_no=? AND source_type='attendance' AND status IN('Draft','Rejected') AND deleted_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE",[$session['user_id'],$session['project_id'],$year,$week]);
  if(!$sheet){$id=tfWriteRow('teamflow_weekly_timesheets',['user_id'=>$session['user_id'],'project_id'=>$session['project_id'],'year'=>$year,'week_no'=>$week,'entry_type'=>'project','task_name'=>'Daily work (automatic)','source_type'=>'attendance','status'=>'Draft']);$sheet=['id'=>$id];}
  tfWriteRow('teamflow_timesheet_worklogs',['session_id'=>$session['id'],'user_id'=>$session['user_id'],'timesheet_id'=>$sheet['id'],'work_date'=>$cursor->format('Y-m-d'),'duration_seconds'=>$seconds,'created_at'=>date('Y-m-d H:i:s')]);
  $logs=tfRows('SELECT w.*,a.worked_on,a.module_name FROM teamflow_timesheet_worklogs w JOIN teamflow_employee_work_status a ON a.id=w.session_id WHERE w.timesheet_id=? ORDER BY w.work_date,w.session_id',[$sheet['id']]);$daily=array_fill(1,7,0);$notes=[];foreach($logs as $log){$day=(int)(new DateTimeImmutable($log['work_date'],$zone))->format('N');$daily[$day]+=(int)$log['duration_seconds'];$notes[]=$log['work_date'].' · '.$log['module_name'].': '.$log['worked_on'];}
  $update=['worked_on'=>mb_substr(implode("\n",$notes),0,20000),'status'=>'Draft'];foreach(['mon','tue','wed','thu','fri','sat','sun'] as $index=>$key)$update[$key.'_hours']=round($daily[$index+1]/3600,2);$update['total_hours']=round(array_sum(array_intersect_key($update,array_flip(['mon_hours','tue_hours','wed_hours','thu_hours','fri_hours','sat_hours','sun_hours']))),2);tfWriteRow('teamflow_weekly_timesheets',$update,$sheet['id']);$cursorEpoch=$nextEpoch;
 }while($cursorEpoch<$endEpoch);
}
function tfSubmitAutomaticSheet($input,$existing){
 $existing=tfOne('SELECT * FROM teamflow_weekly_timesheets WHERE id=? FOR UPDATE',[$existing['id']]);
 if(!in_array($existing['status'],['Draft','Rejected'],true))throw new DomainException('Submitted or approved timesheets are locked.');
 $status=$input['status']??'Draft';if(!in_array($status,['Draft','Pending'],true))throw new InvalidArgumentException('Choose Draft or Submit for approval.');
 foreach(['user_id','project_id','year','week_no','mon_hours','tue_hours','wed_hours','thu_hours','fri_hours','sat_hours','sun_hours','total_hours'] as $field)if(isset($input[$field])&&(float)$input[$field]!== (float)$existing[$field])throw new DomainException('Attendance timesheet hours and dates come from recorded work sessions.');
 tfWriteRow('teamflow_weekly_timesheets',['status'=>$status],$existing['id']);return (int)$existing['id'];
}
