<?php
function auditLog($resource,$action,$recordId=null,$oldValue=null,$newValue=null,$status='Allowed') {
    global $conn;
    $user=authUser();
    $userId=$user['id']??null;$ip=$_SERVER['REMOTE_ADDR']??null;
    $agent=substr($_SERVER['HTTP_USER_AGENT']??'',0,500);
    $old=$oldValue===null?null:json_encode($oldValue);
    $new=$newValue===null?null:json_encode($newValue);
    $employeeId=$user['employee_id']??null;
    $stmt=$conn->prepare('INSERT INTO audit_logs(user_id,employee_id,resource,action,record_id,old_value,new_value,ip_address,user_agent,status)
        VALUES(?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('iissssssss',$userId,$employeeId,$resource,$action,$recordId,$old,$new,$ip,$agent,$status);
    $stmt->execute();
}
