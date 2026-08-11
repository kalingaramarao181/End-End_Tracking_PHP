<?php
require_once __DIR__ . '/model.php';
class PermissionController
{
 private $permissionModel;
 public function __construct(){$this->permissionModel=new PermissionModel();}
 private function jsonResponse($status,$data){http_response_code($status);header('Content-Type: application/json');echo json_encode($data);exit;}
 private function requestData(){$data=json_decode(file_get_contents('php://input'),true);return is_array($data)?$data:[];}
 private function validateRows($data){if(empty($data)||!is_array($data))$this->jsonResponse(400,['success'=>false,'message'=>'Permissions array is required.']);foreach($data as $index=>$row)if(!isset($row['resource_id']))$this->jsonResponse(400,['success'=>false,'message'=>"resource_id is required at index $index."]);}
 public function getByPosition($id){$r=$this->permissionModel->getPermissionsByPosition($id);$this->jsonResponse($r['success']?200:500,$r);}
 public function saveByPosition($id){$data=$this->requestData();$this->validateRows($data);$before=$this->permissionModel->getPermissionsByPosition($id);$r=$this->permissionModel->savePermissionsByPosition($id,$data);if($r['success']){require_once __DIR__.'/../../middleware/audit.php';auditLog('permissions','UPDATE',(string)$id,$before['data']??null,$data);}$this->jsonResponse($r['success']?200:500,$r);}
 public function deleteByPosition($id){$r=$this->permissionModel->deletePermissionsByPosition($id);$this->jsonResponse($r['success']?200:500,$r);}
 public function getByUser($id){$r=$this->permissionModel->getPermissionsByUser($id);$this->jsonResponse($r['success']?200:404,$r);}
 public function saveByUser($id){$data=$this->requestData();$this->validateRows($data);$before=$this->permissionModel->getPermissionsByUser($id);$r=$this->permissionModel->savePermissionsByUser($id,$data);if($r['success']){require_once __DIR__.'/../../middleware/audit.php';auditLog('permissions','USER_OVERRIDE',(string)$id,$before['data']??null,$data);}$this->jsonResponse($r['success']?200:400,$r);}
 public function deleteByUser($id){$before=$this->permissionModel->getPermissionsByUser($id);$r=$this->permissionModel->deletePermissionsByUser($id);if($r['success']){require_once __DIR__.'/../../middleware/audit.php';auditLog('permissions','USER_RESET',(string)$id,$before['data']??null,null);}$this->jsonResponse($r['success']?200:500,$r);}
}