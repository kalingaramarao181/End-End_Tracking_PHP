<?php

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/role.php';

authenticate();
$controller=new DocumentReminderController();
$method=$_SERVER['REQUEST_METHOD'];
$uri=str_replace('/api','',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));

if($method==='POST'&&$uri==='/document-reminders/documents/upload'){requirePermission('candidates','can_edit');$controller->upload();}
if($method==='GET'&&$uri==='/document-reminders'){requirePermission('candidates','can_view');$controller->list();}
if($method==='GET'&&$uri==='/document-reminders/dashboard'){requirePermission('candidates','can_view');$controller->dashboard();}
if($method==='GET'&&preg_match('#^/document-reminders/(\d+)$#',$uri,$m)){requirePermission('candidates','can_view');$controller->show((int)$m[1]);}
if(in_array($method,['PUT','POST'],true)&&preg_match('#^/document-reminders/(\d+)/edit$#',$uri,$m)){requirePermission('candidates','can_edit');$controller->update((int)$m[1]);}
if($method==='POST'&&preg_match('#^/document-reminders/(\d+)/send$#',$uri,$m)){requirePermission('candidates','can_edit');$controller->sendNow((int)$m[1]);}
if($method==='POST'&&preg_match('#^/document-reminders/(\d+)/disable$#',$uri,$m)){requirePermission('candidates','can_edit');$controller->disable((int)$m[1]);}
if($method==='POST'&&preg_match('#^/document-reminders/documents/(\d+)/manual$#',$uri,$m)){requirePermission('candidates','can_edit');$controller->createManual((int)$m[1]);}
http_response_code(404); echo json_encode(['success'=>false,'message'=>'Document reminder route not found.']);
