<?php
require_once __DIR__.'/../modules/resume_match/model.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$base=['job_description'=>str_repeat('React Python AWS developer. ',3),'source'=>'upload','state_filter'=>'CA','candidate_count'=>2,'top_score'=>85];
check(resumeMatchValidateHistory($base)['candidate_count']===2,'Valid input rejected');
foreach([['source'=>'techfetch'],['candidate_count'=>0],['candidate_count'=>51],['top_score'=>101],['job_description'=>'Short'],['job_description'=>str_repeat('x',5001)],['state_filter'=>'XX'],['job_description'=>[]],['state_filter'=>[]]] as $change){
    $rejected=false;try{resumeMatchValidateHistory(array_merge($base,$change));}catch(InvalidArgumentException $e){$rejected=true;}
    check($rejected,'Invalid history accepted');
}
class ResumeHistoryTestResult{function fetch_assoc(){return null;}}
class ResumeHistoryTestStatement{
 public $types;public $args;
 function bind_param($types,&...$args){$this->types=$types;$this->args=$args;}
 function execute(){}
 function get_result(){return new ResumeHistoryTestResult();}
}
class ResumeHistoryTestDB{
 public $insert_id=42;public $query;public $statement;
 function prepare($query){$this->query=$query;return $this->statement=new ResumeHistoryTestStatement();}
}
$db=new ResumeHistoryTestDB();
resumeMatchHistoryList($db,7);
check(strpos($db->query,'WHERE user_id=?')!==false,'History list is not owner scoped');
check($db->statement->args[0]===7,'Owner filter was not bound');
$record=resumeMatchValidateHistory(array_merge($base,['user_id'=>999]));
resumeMatchHistorySave($db,7,$record);
check($db->statement->args[0]===7,'Client forged history owner');
check(strlen($db->statement->types)===6&&count($db->statement->args)===6,'Insert parameter count mismatch');
echo "Resume Match validation, ownership, and insert parameter tests passed.\n";
