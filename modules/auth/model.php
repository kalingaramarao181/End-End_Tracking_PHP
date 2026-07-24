<?php
require_once __DIR__ . '/../../config/db.php';

class AuthModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Find active user by email
     */
    public function findByEmail($email)
    {
        $sql = "
            SELECT
                u.id,
                u.employee_id,
                u.nick_name,
                u.email,
                u.password,
                u.position_id,
                u.status,
                p.status AS position_status
            FROM users u
            LEFT JOIN positions p ON p.id=u.position_id
            WHERE u.email = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId)
    {
        $stmt = $this->conn->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
    }

    public function getAuthorizationProfile($userId)
    {
        $userStmt=$this->conn->prepare("SELECT u.id,u.employee_id,u.nick_name,u.email,u.position_id,
            u.status,u.last_login,p.position_name,p.status position_status
            FROM users u JOIN positions p ON p.id=u.position_id WHERE u.id=? LIMIT 1");
        $userStmt->bind_param('i',$userId);$userStmt->execute();
        $user=$userStmt->get_result()->fetch_assoc();
        if(!$user)return null;
        $isSuper=(int)$user['position_id']===1;
        $columns=['view','create','edit','delete','export','import','upload','download','approve','reject','assign','manage','print','share'];
        $select=[];
        foreach($columns as $action){
            $select[]=$isSuper?"1 AS can_$action":"COALESCE(up.can_$action,pn.can_$action,0) AS can_$action";
        }
        $scope=$isSuper?"'ALL'":"COALESCE(up.data_scope,pn.data_scope,'OWN')";
        $sql="SELECT r.id,r.resource_name resource,r.display_name,r.icon,r.route,r.component_key,r.resource_type,r.parent_id,r.sort_order,
            ".implode(',',$select).",$scope AS data_scope
            FROM resources r
            LEFT JOIN permissions pn ON pn.resource_id=r.id AND pn.position_id=?
            LEFT JOIN user_permissions up ON up.resource_id=r.id AND up.user_id=?
            WHERE r.status='Active' ORDER BY r.sort_order,r.id";
        $stmt=$this->conn->prepare($sql);$stmt->bind_param('ii',$user['position_id'],$userId);$stmt->execute();
        $resources=[];$result=$stmt->get_result();
        while($row=$result->fetch_assoc()){
            $permissions=[];foreach($columns as $action)$permissions[$action]=(bool)$row["can_$action"];
            $resources[]=['id'=>(int)$row['id'],'resource'=>$row['resource'],'display_name'=>$row['display_name'],
                'icon'=>$row['icon'],'route'=>$row['route'],'component_key'=>$row['component_key'],
                'resource_type'=>$row['resource_type'],'parent_id'=>$row['parent_id'],'sort_order'=>(int)$row['sort_order'],
                'permissions'=>$permissions,'scope'=>$row['data_scope']];
        }
        return ['user'=>['id'=>(int)$user['id'],'employee_id'=>(int)$user['employee_id'],'username'=>$user['nick_name'],
            'email'=>$user['email'],'position_id'=>(int)$user['position_id'],'position'=>$user['position_name'],
            'status'=>$user['status'],'last_login'=>$user['last_login'],'super_admin'=>$isSuper],'resources'=>$resources];
    }

    public function isRateLimited($identifier,$ip)
    {
        $stmt=$this->conn->prepare("SELECT COUNT(*) attempts FROM login_attempts
            WHERE identifier=? AND ip_address=? AND succeeded=0 AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
        $stmt->bind_param('ss',$identifier,$ip);$stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['attempts']>=5;
    }
    public function recordLoginAttempt($identifier,$ip,$succeeded)
    {
        $stmt=$this->conn->prepare('INSERT INTO login_attempts(identifier,ip_address,succeeded) VALUES(?,?,?)');
        $success=(int)$succeeded;$stmt->bind_param('ssi',$identifier,$ip,$success);$stmt->execute();
    }
}
