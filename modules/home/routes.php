<?php
require_once __DIR__.'/controller.php';
header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']==='GET'){publicHomeSummary();exit;}
http_response_code(405);echo json_encode(['success'=>false,'message'=>'Method not allowed.']);