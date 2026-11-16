<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../modules/teamflow/model.php';
try{echo json_encode(['closed'=>tfCloseExpiredShifts()]).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,"TeamFlow shift reconciliation failed. Check the server error log.\n");error_log($e->getMessage());exit(1);}
