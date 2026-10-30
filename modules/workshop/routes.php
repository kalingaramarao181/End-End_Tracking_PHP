<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../middleware/auth.php';
require_once __DIR__.'/../../middleware/role.php';
require_once __DIR__.'/model.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$conn->set_charset('utf8mb4');
authenticate();requirePermission('workshop','can_view');$user=authUser();$permission=getEffectivePermission('workshop');$scope=$permission['data_scope']??'OWN';
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');$method=$_SERVER['REQUEST_METHOD'];
function wsRespond($data,$status=200){http_response_code($status);header('Content-Type: application/json');echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
try{
 if($method==='POST'){$max=wsBytes(ini_get('post_max_size'));if($max>0&&(int)($_SERVER['CONTENT_LENGTH']??0)>$max)wsRespond(['success'=>false,'message'=>'The request exceeds the server upload limit. Use fewer or smaller photos.'],413);}
 if($method==='GET'&&$path==='/workshops/options'){
  $name=wsNameSql();$employeeJoin=wsEmployeeJoin();$users=$conn->query("SELECT u.id,$name AS name,$name AS employee_name,COALESCE(u.nick_name,'') AS company_name,e.id AS linked_employee_id,u.position_id,u.status,COALESCE(p.position_name,'') position_name FROM users u $employeeJoin LEFT JOIN positions p ON p.id=u.position_id WHERE e.id IS NOT NULL AND u.status='Active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
  $positions=$conn->query("SELECT id,position_name,status FROM positions ORDER BY position_name")->fetch_all(MYSQLI_ASSOC);
  $caps=[];foreach(['create','edit','delete','export','upload'] as $action)$caps[$action]=!empty($permission['can_'.$action]);
  wsRespond(['success'=>true,'data'=>['users'=>$users,'positions'=>$positions,'permissions'=>$caps,'limits'=>wsLimits()]]);
 }
 if($method==='GET'&&in_array($path,['/workshops','/workshops/export'],true)){
  $export=$path==='/workshops/export';if($export)requirePermission('workshop','can_export');wsRespond(['success'=>true,'data'=>wsList($conn,$_GET,$user,$permission,$export)]);
 }
 if($method==='GET'&&preg_match('#^/workshops/photos/([1-9][0-9]*)$#',$path,$match)){
  $photo=wsQuery($conn,'SELECT * FROM workshop_photos WHERE id=?',[(int)$match[1]])->get_result()->fetch_assoc();
  if(!$photo||!wsFind($conn,$photo['workshop_id'],$user,$scope))wsRespond(['success'=>false,'message'=>'Photo not found.'],404);
  if(!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/',$photo['storage_name']))throw new RuntimeException('Invalid stored photo path.');$file=wsStorage().'/'.$photo['storage_name'];if(!is_file($file))wsRespond(['success'=>false,'message'=>'The photo file is unavailable.'],404);
  header('Content-Type: '.$photo['mime_type']);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="meeting-photo-'.(int)$photo['id'].'.'.pathinfo($file,PATHINFO_EXTENSION).'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');readfile($file);exit;
 }
 if($method==='POST'&&$path==='/workshops'){
  requirePermission('workshop','can_create');$id=wsSave($conn,$_POST,$_FILES['photos']??null,$user,$permission);wsRespond(['success'=>true,'data'=>['id'=>$id],'message'=>'Meeting added.'],201);
 }
 if(preg_match('#^/workshops/([1-9][0-9]*)$#',$path,$match)){
  $row=wsFind($conn,(int)$match[1],$user,$scope);if(!$row)wsRespond(['success'=>false,'message'=>'Meeting not found.'],404);
  if($method==='POST'||$method==='DELETE'){
   requirePermission('workshop',$method==='DELETE'?'can_delete':'can_edit');if(!wsMayWrite($row,$user,$scope))wsRespond(['success'=>false,'message'=>'You cannot change this meeting.'],403);
   if($method==='DELETE'){wsQuery($conn,'UPDATE workshop_meetings SET deleted_at=?,updated_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),(int)$row['id']]);wsRespond(['success'=>true,'message'=>'Meeting removed.']);}
   wsSave($conn,$_POST,$_FILES['photos']??null,$user,$permission,$row);wsRespond(['success'=>true,'message'=>'Meeting updated.']);
  }
 }
 wsRespond(['success'=>false,'message'=>'Workshop route not found.'],404);
}catch(InvalidArgumentException $error){wsRespond(['success'=>false,'message'=>$error->getMessage()],422);}
catch(DomainException $error){wsRespond(['success'=>false,'message'=>$error->getMessage()],403);}
catch(Throwable $error){error_log('Workshop: '.$error->getMessage());if(strpos($error->getMessage(),'Photo storage')===0||strpos($error->getMessage(),'Photo move')===0)wsRespond(['success'=>false,'message'=>'Meeting photos cannot be saved. Ask your administrator to check write access to storage/workshop_photos.'],503);$missing=(int)$error->getCode()===1146;wsRespond(['success'=>false,'message'=>$missing?'Workshop is not configured. Apply migrations/20261030_workshop.sql.':'Workshop could not be saved or loaded. Please try again.'],$missing?503:500);}
