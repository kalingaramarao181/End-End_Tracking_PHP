<?php
require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';
$controller=new PermissionController();$method=$_SERVER['REQUEST_METHOD'];$route=trim($_GET['route']??'','/');
authenticate();
if($method==='GET'&&preg_match('#^permission/position/(\d+)$#',$route,$m)){requirePermission('permissions','can_view');$controller->getByPosition((int)$m[1]);}
if($method==='PUT'&&preg_match('#^permission/position/(\d+)$#',$route,$m)){requirePermission('permissions','can_edit');$controller->saveByPosition((int)$m[1]);}
if($method==='DELETE'&&preg_match('#^permission/position/(\d+)$#',$route,$m)){requirePermission('permissions','can_delete');$controller->deleteByPosition((int)$m[1]);}
if($method==='GET'&&preg_match('#^permission/user/(\d+)$#',$route,$m)){requirePermission('permissions','can_view');$controller->getByUser((int)$m[1]);}
if($method==='PUT'&&preg_match('#^permission/user/(\d+)$#',$route,$m)){requirePermission('permissions','can_edit');$controller->saveByUser((int)$m[1]);}
if($method==='DELETE'&&preg_match('#^permission/user/(\d+)$#',$route,$m)){requirePermission('permissions','can_delete');$controller->deleteByUser((int)$m[1]);}
http_response_code(404);header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>'Permission route not found.','route'=>$route]);exit;