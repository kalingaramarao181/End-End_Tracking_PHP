<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Check whether the authenticated user has a specific permission.
 *
 * Example:
 * requirePermission('users', 'can_create');
 * requirePermission('users', 'can_edit');
 * requirePermission('users', 'can_view');
 */
function requirePermission($resourceName, $action)
{
    $action = strpos($action, 'can_') === 0 ? $action : "can_$action";
    $permission=getEffectivePermission($resourceName);
    if(!empty($permission[$action])){
        auditLog($resourceName,substr($action,4),null,null,null,'Allowed');
        return true;
    }
    auditLog($resourceName,substr($action,4),null,null,null,'Denied');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Permission denied.']);
    exit;
}

function permissionScope($resourceName)
{
    $permission=getEffectivePermission($resourceName);
    return $permission['data_scope'] ?? 'OWN';
}

function authorizeRecordOwner($resourceName,$recordEmployeeId)
{
    $user=authUser();
    $scope=permissionScope($resourceName);
    if($scope==='ALL'||(int)$recordEmployeeId===(int)($user['employee_id']?:$user['id']))return true;
    auditLog($resourceName,'view',(string)$recordEmployeeId,null,null,'Denied');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Permission denied.']);
    exit;
}

function getEffectivePermission($resourceName)
{
    global $conn;
    $user = authUser();
    if(!$user){authenticate();$user=authUser();}
    $actions=['view','create','edit','delete','export','import','upload','download','approve','reject','assign','manage','print','share'];
    if((int)$user['position_id']===1){$result=['data_scope'=>'ALL'];foreach($actions as $a)$result["can_$a"]=1;return $result;}
    $select=[];foreach($actions as $a)$select[]="COALESCE(up.can_$a,p.can_$a,0) can_$a";
    $sql="SELECT ".implode(',',$select).",COALESCE(up.data_scope,p.data_scope,'OWN') data_scope
        FROM resources r LEFT JOIN permissions p ON p.resource_id=r.id AND p.position_id=?
        LEFT JOIN user_permissions up ON up.resource_id=r.id AND up.user_id=?
        WHERE r.resource_name=? AND r.status='Active' LIMIT 1";
    $stmt=$conn->prepare($sql);$stmt->bind_param('iis',$user['position_id'],$user['id'],$resourceName);$stmt->execute();
    return $stmt->get_result()->fetch_assoc()?:[];
}
