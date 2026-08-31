<?php
require_once __DIR__.'/../../middleware/auth.php';
require_once __DIR__.'/../../middleware/role.php';
require_once __DIR__.'/model.php';
authenticate(); requirePermission('chat','view');
$m=new ChatModel(); $u=(int)authUser()['id']; $method=$_SERVER['REQUEST_METHOD']; $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
function chatBody(){return json_decode(file_get_contents('php://input'),true)?:[];}
function chatOut($code,$data){http_response_code($code);echo json_encode($data);exit;}
try{
 if($method==='GET'&&$path==='/chat/conversations')chatOut(200,['success'=>true,'data'=>$m->conversations($u)]);
 if($method==='POST'&&$path==='/chat/conversations'){requirePermission('chat','create');$body=chatBody();chatOut(201,['success'=>true,'data'=>($body['type']??'')==='contextual'?$m->context($u,$body):$m->create($u,$body)]);}
 if($method==='GET'&&preg_match('#^/chat/conversations/(\d+)/messages$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->messages($u,(int)$x[1],(int)($_GET['before']??0))]);
 if($method==='POST'&&preg_match('#^/chat/conversations/(\d+)/messages$#',$path,$x)){requirePermission('chat','create');chatOut(201,['success'=>true,'data'=>$m->send($u,(int)$x[1],chatBody())]);}
 if($method==='POST'&&preg_match('#^/chat/conversations/(\d+)/read$#',$path,$x)){chatOut(200,['success'=>true,'data'=>$m->read($u,(int)$x[1])]);}
 if($method==='GET'&&preg_match('#^/chat/messages/(\d+)/receipts$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->receiptInfo($u,(int)$x[1])]);
 if($method==='PATCH'&&preg_match('#^/chat/messages/(\d+)$#',$path,$x)){requirePermission('chat','edit');chatOut(200,['success'=>true,'data'=>$m->editMessage($u,(int)$x[1],chatBody())]);}
 if($method==='DELETE'&&preg_match('#^/chat/messages/(\d+)$#',$path,$x)){requirePermission('chat','delete');chatOut(200,['success'=>true,'data'=>$m->deleteMessage($u,(int)$x[1])]);}
 if($method==='GET'&&preg_match('#^/chat/conversations/(\d+)/members$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->members($u,(int)$x[1])]);
 if($method==='POST'&&preg_match('#^/chat/conversations/(\d+)/members$#',$path,$x)){chatOut(200,['success'=>true,'data'=>$m->addMembers($u,(int)$x[1],chatBody()['member_ids']??[])]);}
 if($method==='GET'&&$path==='/chat/admin/conversations'){requirePermission('chat_admin','view');chatOut(200,['success'=>true,'data'=>$m->adminConversations($_GET['q']??'')]);}
 if($method==='DELETE'&&preg_match('#^/chat/admin/conversations/(\d+)$#',$path,$x)){requirePermission('chat_admin','delete');chatOut(200,['success'=>true,'data'=>$m->adminDeleteConversation((int)$x[1])]);}
 if($method==='DELETE'&&preg_match('#^/chat/conversations/(\d+)/empty$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->deleteEmptyDiscussion($u,(int)$x[1])]);
 if($method==='DELETE'&&preg_match('#^/chat/conversations/(\d+)$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->deleteConversationForMe($u,(int)$x[1])]);
 if($method==='POST'&&preg_match('#^/chat/conversations/(\d+)/exit$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->exitGroup($u,(int)$x[1])]);
 if($method==='PATCH'&&preg_match('#^/chat/conversations/(\d+)/members/(\d+)/role$#',$path,$x)){chatOut(200,['success'=>true,'data'=>$m->setMemberRole($u,(int)$x[1],(int)$x[2],chatBody()['role']??'')]);}
 if($method==='DELETE'&&preg_match('#^/chat/conversations/(\d+)/members/(\d+)$#',$path,$x)){chatOut(200,['success'=>true,'data'=>$m->removeMember($u,(int)$x[1],(int)$x[2])]);} if($method==='GET'&&$path==='/chat/users/search')chatOut(200,['success'=>true,'data'=>$m->users($u,$_GET['q']??'')]);
 if($method==='GET'&&$path==='/chat/notifications')chatOut(200,['success'=>true,'data'=>$m->notifications($u),'meta'=>['unread'=>$m->notificationSummary($u)]]);
 if($method==='PATCH'&&preg_match('#^/chat/notifications/(\d+)/read$#',$path,$x))chatOut(200,['success'=>true,'data'=>$m->readNotification($u,(int)$x[1])]);
 if($method==='PATCH'&&$path==='/chat/notifications/read-all')chatOut(200,['success'=>true,'data'=>$m->readAllNotifications($u)]);
 chatOut(404,['success'=>false,'message'=>'Chat route not found.']);
}catch(InvalidArgumentException $e){chatOut(422,['success'=>false,'message'=>$e->getMessage()]);}catch(DomainException $e){chatOut(403,['success'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log($e);chatOut(500,['success'=>false,'message'=>'Chat request could not be completed.']);}
