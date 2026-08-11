<?php
require_once __DIR__ . '/../../config/db.php';

class PermissionModel
{
    private $conn;
    private $actions=['view','create','edit','delete','export','import','upload','download','approve','reject','assign','manage','print','share'];

    public function __construct(){global $conn;$this->conn=$conn;}

    public function getPermissionsByPosition($positionId)
    {
        $select=[];foreach($this->actions as $action)$select[]="COALESCE(p.can_$action,0) AS can_$action";
        $sql="SELECT r.id AS resource_id,r.resource_name,r.display_name,".implode(',',$select).",
            COALESCE(p.data_scope,'OWN') AS data_scope
            FROM resources r LEFT JOIN permissions p ON p.resource_id=r.id AND p.position_id=?
            WHERE r.status='Active' ORDER BY r.sort_order,r.id";
        $stmt=$this->conn->prepare($sql);if(!$stmt)return $this->error('Failed to prepare query.');
        $stmt->bind_param('i',$positionId);$stmt->execute();
        return ['success'=>true,'position_id'=>(int)$positionId,'data'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
    }

    public function savePermissionsByPosition($positionId,$permissions)
    {
        $this->conn->begin_transaction();
        try{
            $columns=array_map(fn($a)=>"can_$a",$this->actions);
            $sql="INSERT INTO permissions(position_id,resource_id,".implode(',',$columns).",data_scope)
                VALUES (".implode(',',array_fill(0,17,'?')).") ON DUPLICATE KEY UPDATE ".
                implode(',',array_map(fn($c)=>"$c=VALUES($c)",$columns)).",data_scope=VALUES(data_scope)";
            $stmt=$this->conn->prepare($sql);if(!$stmt)throw new Exception($this->conn->error);
            foreach($permissions as $row){
                $values=[(int)$positionId,(int)$row['resource_id']];
                foreach($this->actions as $action)$values[]=(int)($row["can_$action"]??0);
                $scope=in_array(($row['data_scope']??'OWN'),['OWN','TEAM','DEPARTMENT','OFFICE','ALL'],true)?$row['data_scope']:'OWN';$values[]=$scope;
                $stmt->bind_param(str_repeat('i',16).'s',...$values);if(!$stmt->execute())throw new Exception($stmt->error);
            }
            $this->conn->commit();return ['success'=>true,'message'=>'Position permissions saved successfully.'];
        }catch(Exception $e){$this->conn->rollback();return $this->error('Failed to save permissions.',$e->getMessage());}
    }

    public function deletePermissionsByPosition($positionId)
    {
        $stmt=$this->conn->prepare('DELETE FROM permissions WHERE position_id=?');if(!$stmt)return $this->error('Failed to prepare delete query.');
        $stmt->bind_param('i',$positionId);if(!$stmt->execute())return $this->error('Failed to delete permissions.',$stmt->error);
        return ['success'=>true,'message'=>'Position permissions deleted successfully.','affected_rows'=>$stmt->affected_rows];
    }

    public function getPermissionsByUser($userId)
    {
        $userStmt=$this->conn->prepare('SELECT u.id,u.nick_name,u.email,u.position_id,p.position_name FROM users u JOIN positions p ON p.id=u.position_id WHERE u.id=? LIMIT 1');
        $userStmt->bind_param('i',$userId);$userStmt->execute();$user=$userStmt->get_result()->fetch_assoc();
        if(!$user)return ['success'=>false,'message'=>'User not found.'];
        $select=[];
        foreach($this->actions as $action){
            $select[]="p.can_$action AS position_can_$action";
            $select[]="up.can_$action AS can_$action";
            $select[]="COALESCE(up.can_$action,p.can_$action,0) AS effective_can_$action";
        }
        $sql="SELECT r.id AS resource_id,r.resource_name,r.display_name,".implode(',',$select).",
            p.data_scope AS position_data_scope,up.data_scope,
            COALESCE(up.data_scope,p.data_scope,'OWN') AS effective_data_scope
            FROM resources r
            LEFT JOIN permissions p ON p.resource_id=r.id AND p.position_id=?
            LEFT JOIN user_permissions up ON up.resource_id=r.id AND up.user_id=?
            WHERE r.status='Active' ORDER BY r.sort_order,r.id";
        $stmt=$this->conn->prepare($sql);if(!$stmt)return $this->error('Failed to prepare query.');
        $stmt->bind_param('ii',$user['position_id'],$userId);$stmt->execute();
        return ['success'=>true,'user'=>['id'=>(int)$user['id'],'nick_name'=>$user['nick_name'],'email'=>$user['email'],'position_id'=>(int)$user['position_id'],'position_name'=>$user['position_name'],'super_admin'=>(int)$user['position_id']===1],'data'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
    }

    public function savePermissionsByUser($userId,$permissions)
    {
        $user=$this->getPermissionsByUser($userId);if(!$user['success'])return $user;
        if(!empty($user['user']['super_admin']))return ['success'=>false,'message'=>'Super Admin permissions cannot be overridden.'];
        $this->conn->begin_transaction();
        try{
            $columns=array_map(fn($a)=>"can_$a",$this->actions);
            $upsert="INSERT INTO user_permissions(user_id,resource_id,".implode(',',$columns).",data_scope)
                VALUES (".implode(',',array_fill(0,17,'?')).") ON DUPLICATE KEY UPDATE ".
                implode(',',array_map(fn($c)=>"$c=VALUES($c)",$columns)).",data_scope=VALUES(data_scope)";
            $save=$this->conn->prepare($upsert);$remove=$this->conn->prepare('DELETE FROM user_permissions WHERE user_id=? AND resource_id=?');
            if(!$save||!$remove)throw new Exception($this->conn->error);
            foreach($permissions as $row){
                $resourceId=(int)$row['resource_id'];$values=[(int)$userId,$resourceId];$hasOverride=false;
                foreach($this->actions as $action){$value=array_key_exists("can_$action",$row)&&$row["can_$action"]!==null?(int)(bool)$row["can_$action"]:null;$values[]=$value;if($value!==null)$hasOverride=true;}
                $scope=in_array(($row['data_scope']??null),['OWN','TEAM','DEPARTMENT','OFFICE','ALL'],true)?$row['data_scope']:null;if($scope!==null)$hasOverride=true;$values[]=$scope;
                if(!$hasOverride){$remove->bind_param('ii',$userId,$resourceId);if(!$remove->execute())throw new Exception($remove->error);continue;}
                $save->bind_param(str_repeat('i',16).'s',...$values);if(!$save->execute())throw new Exception($save->error);
            }
            $this->conn->commit();return ['success'=>true,'message'=>'User permission overrides saved successfully.'];
        }catch(Exception $e){$this->conn->rollback();return $this->error('Failed to save user permissions.',$e->getMessage());}
    }

    public function deletePermissionsByUser($userId)
    {
        $stmt=$this->conn->prepare('DELETE FROM user_permissions WHERE user_id=?');if(!$stmt)return $this->error('Failed to prepare delete query.');
        $stmt->bind_param('i',$userId);if(!$stmt->execute())return $this->error('Failed to reset user permissions.',$stmt->error);
        return ['success'=>true,'message'=>'User overrides removed. Position permissions now apply.','affected_rows'=>$stmt->affected_rows];
    }

    private function error($message,$error=null){$result=['success'=>false,'message'=>$message];if($error!==null)$result['error']=$error;return $result;}
}