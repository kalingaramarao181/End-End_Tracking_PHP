<?php
function tfUserResource($uid,$resource){
 return tfOne("SELECT u.id,u.position_id,p.position_name,IF(u.position_id=1,1,COALESCE(up.can_view,rp.can_view,0)) can_view,IF(u.position_id=1,1,COALESCE(up.can_approve,rp.can_approve,0)) can_approve,IF(u.position_id=1,'ALL',COALESCE(up.data_scope,rp.data_scope,'OWN')) data_scope FROM users u JOIN positions p ON p.id=u.position_id JOIN resources r ON r.resource_name=? AND r.status='Active' LEFT JOIN permissions rp ON rp.position_id=u.position_id AND rp.resource_id=r.id LEFT JOIN user_permissions up ON up.user_id=u.id AND up.resource_id=r.id WHERE u.id=? AND u.status='Active'",[$resource,$uid]);
}
function tfNotice($uid,$actor,$type,$title,$body,$url,$key){
 tfQ('INSERT IGNORE INTO chat_notifications(user_id,actor_id,type,title,body,destination_url,dedupe_key) VALUES(?,?,?,?,?,?,?)',[$uid,$actor,$type,mb_substr($title,0,180),mb_substr($body,0,500),$url,mb_substr($key,0,160)]);
}
function tfSheetNotice($rows,$actor,$status){
 if(!$rows)return;$first=$rows[0];$candidate=tfOne('SELECT id,firstname,lastname,user_id FROM teamflow_candidates WHERE user_id=?',[$first['user_id']]);if(!$candidate)return;
 $name=tfOne('SELECT COALESCE(NULLIF(TRIM(CONCAT_WS(\' \',c.firstname,c.lastname)),\'\'),u.nick_name,u.email) name FROM users u LEFT JOIN teamflow_candidates c ON c.user_id=u.id WHERE u.id=?',[$first['user_id']])['name'];
 $hours=array_sum(array_map(fn($r)=>(float)$r['total_hours'],$rows));$version=hash('sha256',json_encode(array_map(fn($r)=>[$r['id'],$r['revision']??0],$rows)));$key='tf-sheet:'.strtolower($status).':'.$version;
 $url='/dashboard/teamflow/timesheets?year='.$first['year'].'&week_no='.$first['week_no'].'&candidate_id='.$candidate['id'];
 if($status==='Pending'){
  foreach(tfRows("SELECT id FROM users WHERE status='Active'") as $u){$p=tfUserResource($u['id'],'teamflow_timesheets');if(!$p||!$p['can_view']||$p['data_scope']!=='ALL'||(int)$u['id']===(int)$actor)continue;if(!in_array((int)$p['position_id'],[1,2],true)&&stripos($p['position_name'],'develop')===false&&!$p['can_approve'])continue;tfNotice($u['id'],$actor,'timesheet_submitted','Timesheet awaiting review',"$name submitted $hours hours for week ".$first['week_no'].' / '.$first['year'].'.',$url,$key);}
 }else tfNotice($candidate['user_id'],$actor,'timesheet_'.strtolower($status),'Timesheet '.strtolower($status),'Week '.$first['week_no'].' / '.$first['year'].' · '.$hours.' hours. '.($first['review_note']??''),$url,$key);
}
function tfProjectMembers($projectId){
 $project=tfOne('SELECT id,lead_user_id,created_by FROM teamflow_projects WHERE id=? AND deleted_at IS NULL',[$projectId]);if(!$project)throw new InvalidArgumentException('Project not found.');$ids=[(int)$project['lead_user_id'],(int)$project['created_by']];
 foreach([['teamflow_user_project_assignments','user_id','project_id'],['teamflow_issues','assignee_user_id','project'],['teamflow_weekly_timesheets','user_id','project_id'],['teamflow_employee_work_status','user_id','project_id'],['teamflow_project_task_breakdowns','assignee_id','project_id']] as [$table,$owner,$key])foreach(tfRows('SELECT DISTINCT `'.$owner.'` uid FROM `'.$table.'` WHERE `'.$key.'`=?',[$projectId]) as $r)$ids[]=(int)$r['uid'];
 foreach(tfRows('SELECT team_members FROM teamflow_teams WHERE project_id=?',[$projectId]) as $r)foreach(json_decode($r['team_members'],true)?:[] as $uid)$ids[]=(int)$uid;
 foreach(tfRows('SELECT m.user_id FROM teamflow_project_team_members m JOIN teamflow_teams t ON t.id=m.team_id WHERE t.project_id=?',[$projectId]) as $r)$ids[]=(int)$r['user_id'];
 $ids=array_values(array_unique(array_filter($ids)));return $ids?array_map('intval',array_column(tfRows("SELECT id FROM users WHERE status='Active' AND id IN(".implode(',',array_fill(0,count($ids),'?')).')',$ids),'id')):[];
}
function tfProjectChatAccess($uid,$project){$permission=tfUserResource($uid,'teamflow_projects');return $permission&&$permission['can_view']&&($permission['data_scope']==='ALL'||in_array((int)$uid,tfProjectMembers($project),true));}
function tfProjectContext($uid,$context){
 $type=$context['type']??'';$id=(int)($context['id']??0);$task=null;
 if($type==='teamflow_task'){$task=tfOne('SELECT * FROM teamflow_issues WHERE id=? AND deleted_at IS NULL',[$id]);if(!$task)throw new InvalidArgumentException('Task not found.');$project=(int)$task['project'];}else $project=$id;
 $p=tfOne('SELECT id,name FROM teamflow_projects WHERE id=? AND deleted_at IS NULL',[$project]);if(!$p||!tfProjectChatAccess($uid,$project))throw new DomainException('This discussion is only available to the project team.');
 return ['type'=>$type,'id'=>(string)$id,'project_id'=>$project,'title'=>mb_substr($task?'Task #'.$id.' · '.$task['summary']:$p['name'].' · Project discussion',0,200),'url'=>'/dashboard/teamflow/projects/'.$project,'member_ids'=>array_values(array_unique(array_merge(tfProjectMembers($project),[$uid])))];
}
function tfCheckConversationProject($uid,$conversation){$c=tfOne('SELECT context_type type,context_id id FROM chat_contexts WHERE conversation_id=?',[$conversation]);if($c&&in_array($c['type'],['teamflow_project','teamflow_task'],true))return tfProjectContext($uid,$c);return null;}
function tfProjectAssigned($project,$uids,$actor){$p=tfOne('SELECT name FROM teamflow_projects WHERE id=?',[$project]);foreach($uids as $uid){tfNotice($uid,$actor,'project_assigned','Project assigned',$p['name'].' has been assigned to you.','/dashboard/teamflow/projects/'.$project,'tf-project-assignment:'.$project.':'.$uid);foreach(tfRows("SELECT conversation_id FROM chat_contexts WHERE context_type='teamflow_project' AND context_id=?",[(string)$project]) as $c)tfQ("INSERT INTO chat_members(conversation_id,user_id,role,status) VALUES(?,?,'member','active') ON DUPLICATE KEY UPDATE status='active'",[$c['conversation_id'],$uid]);}}
