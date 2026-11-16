<?php
const TF_SHIFT_SECONDS = 32400;
function tfShiftDeadline($row){return tfSessionEpoch($row,'login')+TF_SHIFT_SECONDS;}
// Called while the candidate row is locked. The cutoff is elapsed UTC time.
function tfExpireLockedShift($row,$now){
 if(!$row||!empty($row['logout_time'])||empty($row['login_status'])||($row['clock_source']??'legacy')!=='profile'||tfShiftDeadline($row)>$now)return false;
 $end=(new DateTimeImmutable('@'.tfShiftDeadline($row)));
 $update=['logout_utc'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'logout_time'=>$end->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d H:i:s'),'login_status'=>0,'status'=>'Auto clocked out'];
 tfWriteRow('teamflow_employee_work_status',$update,$row['id']);tfAutomaticTimesheets(array_merge($row,$update));
 auditLog('teamflow_attendance','auto_clock_out',(string)$row['id'],$row,$update,'Allowed');return true;
}
function tfCloseExpiredShifts(){
 global $conn;$count=0;$now=time();
 $rows=tfRows("SELECT DISTINCT user_id FROM teamflow_employee_work_status WHERE clock_source='profile' AND logout_time IS NULL AND login_status=1 AND deleted_at IS NULL AND (login_utc<=? OR login_utc IS NULL)",[gmdate('Y-m-d H:i:s',$now-TF_SHIFT_SECONDS)]);
 foreach($rows as $target){$conn->begin_transaction();try{
  tfQ('SELECT id FROM teamflow_candidates WHERE user_id=? FOR UPDATE',[$target['user_id']]);
  $open=tfRows("SELECT * FROM teamflow_employee_work_status WHERE user_id=? AND clock_source='profile' AND logout_time IS NULL AND login_status=1 AND deleted_at IS NULL FOR UPDATE",[$target['user_id']]);
  foreach($open as $row)if(tfExpireLockedShift($row,$now))$count++;
  $conn->commit();
 }catch(Throwable $e){$conn->rollback();throw $e;}}
 return $count;
}
function tfCorrectionDate($value){
 $value=str_replace('T',' ',trim((string)$value));if(strlen($value)===16)$value.=':00';
 $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('America/New_York'));
 if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new InvalidArgumentException('Enter a valid date and time in US Eastern Time.');return $date;
}
function tfCorrectAttendance($id,$input,$user,$permission){
 global $conn;if(!tfAllScope($permission)||empty($permission['can_edit']))throw new DomainException('Administrator attendance edit permission is required.');
 $reason=tfText($input,'reason',1000,true);$before=tfFind('attendance',$id,$user,$permission);if(!$before)throw new DomainException('Attendance record not found.');
 $start=tfCorrectionDate($input['login_time']??'');$end=tfCorrectionDate($input['logout_time']??'');
 if($end<$start||$end->getTimestamp()-$start->getTimestamp()>TF_SHIFT_SECONDS||$end->getTimestamp()>time())throw new InvalidArgumentException('The corrected session must end after login, be no longer than nine hours, and cannot end in the future.');
 $conn->begin_transaction();try{
  tfQ('SELECT id FROM teamflow_candidates WHERE user_id=? FOR UPDATE',[$before['user_id']]);
  $before=tfOne('SELECT * FROM teamflow_employee_work_status WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);if(!$before)throw new DomainException('Attendance record not found.');
  $sheets=tfRows('SELECT DISTINCT t.* FROM teamflow_weekly_timesheets t JOIN teamflow_timesheet_worklogs w ON w.timesheet_id=t.id WHERE w.session_id=? FOR UPDATE',[$id]);
  foreach($sheets as $sheet)if(!in_array($sheet['status'],['Draft','Rejected'],true))throw new DomainException('Return the linked timesheet to draft or rejected before correcting attendance.');
  $update=['login_time'=>$start->format('Y-m-d H:i:s'),'logout_time'=>$end->format('Y-m-d H:i:s'),'login_utc'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'logout_utc'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'login_status'=>0,'status'=>'Corrected'];
  tfWriteRow('teamflow_employee_work_status',$update,$id);
  tfQ('DELETE FROM teamflow_timesheet_worklogs WHERE session_id=?',[$id]);
  foreach($sheets as $sheet)tfRebuildAttendanceSheet($sheet);
  tfAutomaticTimesheets(array_merge($before,$update));
  auditLog('teamflow_attendance','correct',(string)$id,$before,['reason'=>$reason,'changes'=>$update],'Allowed');$conn->commit();return $id;
 }catch(Throwable $e){$conn->rollback();throw $e;}
}
