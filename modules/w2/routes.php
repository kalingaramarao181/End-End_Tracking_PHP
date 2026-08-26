<?php
require_once __DIR__.'/controller.php';require_once __DIR__.'/../../middleware/auth.php';require_once __DIR__.'/../../middleware/role.php';
$c=new W2Controller();$method=$_SERVER['REQUEST_METHOD'];$route=trim($_GET['route']??'','/');
if($method==='POST'&&$route==='w2/public')$c->create();
authenticate();
if($method==='GET'&&$route==='w2'){requirePermission('w2_forms','view');$c->index();}
elseif($method==='GET'&&preg_match('#^w2/(\d+)$#',$route,$m)){requirePermission('w2_forms','view');$c->show((int)$m[1]);}
elseif($method==='PUT'&&preg_match('#^w2/(\d+)$#',$route,$m)){requirePermission('w2_forms','edit');$c->update((int)$m[1]);}
elseif($method==='DELETE'&&preg_match('#^w2/(\d+)$#',$route,$m)){requirePermission('w2_forms','delete');$c->delete((int)$m[1]);}
else{http_response_code(404);echo json_encode(['success'=>false,'message'=>'W-2 route not found.']);}
