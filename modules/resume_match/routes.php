<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../middleware/auth.php';
require_once __DIR__.'/../../middleware/role.php';
require_once __DIR__.'/model.php';
$conn->set_charset('utf8mb4');
authenticate();
requirePermission('recruiting','can_view');
$userId=(int)authUser()['id'];
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method=$_SERVER['REQUEST_METHOD'];
function resumeMatchRespond($data,$status=200) {
    http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
try {
    if ($method==='GET' && $path==='/resume-match/status') {
        resumeMatchRespond(['success'=>true,'data'=>['history_ready'=>resumeMatchHistoryReady($conn),'sources'=>['upload','demo'],'live_techfetch_enabled'=>false]]);
    }
    if ($path==='/resume-match/history' && in_array($method,['GET','POST'],true)) {
        if (!resumeMatchHistoryReady($conn)) resumeMatchRespond(['success'=>false,'message'=>'Resume Match history is not configured. Apply the Resume Match database migration.'],503);
        if ($method==='GET') resumeMatchRespond(['success'=>true,'data'=>resumeMatchHistoryList($conn,$userId)]);
        $length=(int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if($length>65536)resumeMatchRespond(['success'=>false,'message'=>'History request is too large.'],413);
        $body=file_get_contents('php://input',false,null,0,65537);
        if(strlen($body)>65536)resumeMatchRespond(['success'=>false,'message'=>'History request is too large.'],413);
        $input=json_decode($body,true);
        if(json_last_error()!==JSON_ERROR_NONE)throw new InvalidArgumentException('Invalid JSON request.');
        $record=resumeMatchValidateHistory($input);
        resumeMatchRespond(['success'=>true,'data'=>resumeMatchHistorySave($conn,$userId,$record)],201);
    }
    resumeMatchRespond(['success'=>false,'message'=>'Resume Match route not found.'],404);
} catch (InvalidArgumentException $error) {
    resumeMatchRespond(['success'=>false,'message'=>$error->getMessage()],422);
} catch (Throwable $error) {
    error_log('Resume Match: '.$error->getMessage());
    resumeMatchRespond(['success'=>false,'message'=>'Resume Match history could not be accessed. Please try again.'],500);
}
