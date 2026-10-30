<?php
// Workshop data access and validation; private photos are streamed by authenticated routes.
function wsQuery($db,$sql,$params=[]){$stmt=$db->prepare($sql);if($params){$types='';foreach($params as $p)$types.=is_int($p)?'i':'s';$stmt->bind_param($types,...$params);}$stmt->execute();return $stmt;}
function wsIds($value,$label){if(is_string($value)){$value=json_decode($value,true);if(json_last_error()!==JSON_ERROR_NONE)throw new InvalidArgumentException("Invalid $label.");}if(!is_array($value)||count($value)>200)throw new InvalidArgumentException("Invalid $label.");$out=[];foreach($value as $id){if(!ctype_digit((string)$id)||(int)$id<1)throw new InvalidArgumentException("Invalid $label.");$out[]=(int)$id;}return array_values(array_unique($out));}
function wsValidate($data){
 foreach(['meeting_date','topic','remarks'] as $field)if(isset($data[$field])&&!is_string($data[$field]))throw new InvalidArgumentException('Invalid meeting details.');
 $date=trim((string)($data['meeting_date']??''));$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$parsed||$parsed->format('Y-m-d')!==$date||substr($date,0,4)<'1900'||substr($date,0,4)>'2100')throw new InvalidArgumentException('Choose a valid meeting date.');
 $topic=trim((string)($data['topic']??''));$remarks=trim((string)($data['remarks']??''));
 if($topic===''||mb_strlen($topic)>200)throw new InvalidArgumentException('Topic is required and must be 200 characters or fewer.');
 if(mb_strlen($remarks)>4000)throw new InvalidArgumentException('Remarks must be 4,000 characters or fewer.');
 foreach(['position_id','presenter_id'] as $key)if(!ctype_digit((string)($data[$key]??''))||(int)$data[$key]<($key==='position_id'?0:1))throw new InvalidArgumentException('Select a team and meeting presenter.');
 $ids=wsIds($data['attendee_ids']??[],'attendees');if(!$ids)throw new InvalidArgumentException('Select at least one meeting attendee.');
 $ids=array_values(array_unique(array_merge($ids,[(int)$data['presenter_id']])));if(count($ids)>200)throw new InvalidArgumentException('Select no more than 200 attendees.');
 return ['meeting_date'=>$date,'position_id'=>(int)$data['position_id'],'topic'=>$topic,'presenter_id'=>(int)$data['presenter_id'],'remarks'=>$remarks,'attendee_ids'=>$ids];
}
function wsVisibility($user,$scope,$alias='w'){
 $id=(int)$user['id'];if($scope==='ALL')return ['1=1',[]];
 $sql="($alias.created_by=? OR $alias.presenter_id=? OR EXISTS(SELECT 1 FROM workshop_attendees va WHERE va.workshop_id=$alias.id AND va.user_id=?)";$params=[$id,$id,$id];
 if(in_array($scope,['TEAM','DEPARTMENT','OFFICE'],true)){$sql.=" OR $alias.position_id=?";$params[]=(int)$user['position_id'];}return [$sql.')',$params];
}
function wsMayWrite($row,$user,$scope){return $scope==='ALL'||(int)$row['created_by']===(int)$user['id']||(int)$row['presenter_id']===(int)$user['id']||(in_array($scope,['TEAM','DEPARTMENT','OFFICE'],true)&&(int)$row['position_id']===(int)$user['position_id']);}
function wsNameSql($user='u',$employee='e'){return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE($employee.firstname,''),' ',COALESCE($employee.lastname,''))),''),NULLIF($user.nick_name,''),CONCAT('User #',$user.id))";}
function wsWhere($filters,$user,$scope){
 [$visible,$params]=wsVisibility($user,$scope);$parts=['w.deleted_at IS NULL',$visible];
 foreach(['start_date'=>'>=','end_date'=>'<='] as $key=>$operator){$v=trim((string)($filters[$key]??''));if($v!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$date||$date->format('Y-m-d')!==$v)throw new InvalidArgumentException('Invalid date filter.');$parts[]="w.meeting_date $operator ?";$params[]=$v;}}
 if(!empty($filters['start_date'])&&!empty($filters['end_date'])&&$filters['start_date']>$filters['end_date'])throw new InvalidArgumentException('Start date must not be after end date.');
 foreach(['position_id','presenter_id'] as $key)if(!empty($filters[$key])){if(!ctype_digit((string)$filters[$key]))throw new InvalidArgumentException('Invalid filter.');$parts[]="w.$key=?";$params[]=(int)$filters[$key];}
 if(!empty($filters['attendee_id'])){if(!ctype_digit((string)$filters['attendee_id']))throw new InvalidArgumentException('Invalid attendee filter.');$parts[]='EXISTS(SELECT 1 FROM workshop_attendees fa WHERE fa.workshop_id=w.id AND fa.user_id=?)';$params[]=(int)$filters['attendee_id'];}
 $q=trim((string)($filters['search']??''));if(mb_strlen($q)>200)throw new InvalidArgumentException('Search must be 200 characters or fewer.');if($q!==''){$parts[]="(w.topic LIKE ? OR w.remarks LIKE ?)";$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
 return [implode(' AND ',$parts),$params];
}
function wsHydrate($db,$rows,$user,$permission){
 if(!$rows)return [];$ids=array_map('intval',array_column($rows,'id'));$in=implode(',',$ids);$attendees=[];$photos=[];$name=wsNameSql();
 $r=$db->query("SELECT a.workshop_id,a.user_id AS id,COALESCE($name,CONCAT('Former user #',a.user_id)) AS name,u.position_id FROM workshop_attendees a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN employees e ON e.id=u.employee_id WHERE a.workshop_id IN($in) ORDER BY name");while($a=$r->fetch_assoc()){$a['id']=(int)$a['id'];$attendees[$a['workshop_id']][]=$a;}
 $r=$db->query("SELECT id,workshop_id,original_name,mime_type,file_size FROM workshop_photos WHERE workshop_id IN($in) ORDER BY id");while($p=$r->fetch_assoc()){$p['id']=(int)$p['id'];$p['file_size']=(int)$p['file_size'];$photos[$p['workshop_id']][]=$p;}
 foreach($rows as &$row){$row['id']=(int)$row['id'];$row['position_id']=(int)$row['position_id'];$row['presenter_id']=(int)$row['presenter_id'];$row['day']=(new DateTimeImmutable($row['meeting_date']))->format('l');$row['attendees']=$attendees[$row['id']]??[];$row['photos']=$photos[$row['id']]??[];$write=wsMayWrite($row,$user,$permission['data_scope']??'OWN');$row['can_edit']=$write&&!empty($permission['can_edit']);$row['can_delete']=$write&&!empty($permission['can_delete']);}unset($row);return $rows;
}
function wsList($db,$filters,$user,$permission,$export=false){
 [$where,$params]=wsWhere($filters,$user,$permission['data_scope']??'OWN');
 $stats=wsQuery($db,"SELECT COUNT(*) total,COUNT(DISTINCT w.position_id) teams,COUNT(DISTINCT w.presenter_id) presenters,COALESCE(SUM((SELECT COUNT(*) FROM workshop_photos sp WHERE sp.workshop_id=w.id)),0) photos FROM workshop_meetings w WHERE $where",$params)->get_result()->fetch_assoc();$total=(int)$stats['total'];foreach($stats as &$value)$value=(int)$value;unset($value);
 if($export&&$total>500)throw new InvalidArgumentException('More than 500 meetings match. Narrow the filters before exporting.');
 $limit=$export?500:max(5,min(100,(int)($filters['limit']??5)));$pages=max(1,(int)ceil($total/$limit));$page=min($pages,max(1,(int)($filters['page']??1)));$offset=($page-1)*$limit;
 $presenter=wsNameSql('pu','pe');$creator=wsNameSql('cu','ce');
 $sql="SELECT w.*,CASE WHEN w.position_id=0 THEN 'All teams' ELSE p.position_name END AS team_name,$presenter AS presenter_name,$creator AS created_by_name FROM workshop_meetings w LEFT JOIN positions p ON p.id=w.position_id LEFT JOIN users pu ON pu.id=w.presenter_id LEFT JOIN employees pe ON pe.id=pu.employee_id LEFT JOIN users cu ON cu.id=w.created_by LEFT JOIN employees ce ON ce.id=cu.employee_id WHERE $where ORDER BY w.meeting_date DESC,w.id DESC LIMIT $limit OFFSET $offset";
 $rows=wsQuery($db,$sql,$params)->get_result()->fetch_all(MYSQLI_ASSOC);
 return ['items'=>wsHydrate($db,$rows,$user,$permission),'total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>$pages,'stats'=>$stats];
}
function wsFind($db,$id,$user,$scope){[$where,$params]=wsVisibility($user,$scope);$params[]=(int)$id;$row=wsQuery($db,"SELECT w.* FROM workshop_meetings w WHERE w.deleted_at IS NULL AND $where AND w.id=?",$params)->get_result()->fetch_assoc();return $row?:null;}
function wsBytes($value){$value=trim($value);if($value==='')return 0;$last=strtolower(substr($value,-1));$n=(float)$value;return (int)($n*(['g'=>1073741824,'m'=>1048576,'k'=>1024][$last]??1));}
function wsLimits(){ $post=wsBytes(ini_get('post_max_size'));$upload=wsBytes(ini_get('upload_max_filesize'));$total=min(20*1024*1024,$post>0?max(0,$post-256*1024):20*1024*1024);return ['max_photos'=>min(6,(int)ini_get('max_file_uploads')?:6),'max_file_bytes'=>min(5*1024*1024,$upload?:5*1024*1024,$total),'max_total_bytes'=>$total];}
function wsStorage(){return __DIR__.'/../../storage/workshop_photos';}
function wsUnlink($names){foreach($names as $name)if(preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/',$name)){ $file=wsStorage().'/'.$name;if(is_file($file))unlink($file);}}
function wsUploads($files){
 if(!$files||!isset($files['error']))return [];$limits=wsLimits();$errors=is_array($files['error'])?$files['error']:[$files['error']];if(count($errors)>$limits['max_photos'])throw new InvalidArgumentException('Too many meeting photos.');$out=[];$total=0;
 foreach($errors as $i=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException('A photo could not be uploaded. Check the server upload limit.');$value=fn($key)=>is_array($files[$key])?$files[$key][$i]:$files[$key];$tmp=$value('tmp_name');$size=(int)$value('size');$total+=$size;if($size<1||$size>$limits['max_file_bytes']||$total>$limits['max_total_bytes'])throw new InvalidArgumentException('Photos exceed the displayed upload limits.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$dimensions=@getimagesize($tmp);if(!isset($extensions[$mime])||!$dimensions||$dimensions[0]*$dimensions[1]>24000000)throw new InvalidArgumentException('Use valid JPG, PNG or WebP photos up to 24 megapixels.');$out[]=['tmp'=>$tmp,'mime'=>$mime,'size'=>$size,'original'=>mb_substr(basename(str_replace('\\','/',$value('name'))),0,190),'name'=>bin2hex(random_bytes(20)).'.'.$extensions[$mime]];}
 return $out;
}
function wsSave($db,$input,$files,$user,$permission,$existing=null){
 $data=wsValidate($input);if($data['position_id']!==0&&!wsQuery($db,'SELECT id FROM positions WHERE id=?',[$data['position_id']])->get_result()->fetch_assoc())throw new InvalidArgumentException('The selected team no longer exists.');
 $ids=$data['attendee_ids'];$in=implode(',',$ids);$found=$db->query("SELECT u.id FROM users u INNER JOIN employees e ON e.id=u.employee_id WHERE u.id IN($in) AND u.status='Active'")->num_rows;if($found!==count($ids))throw new InvalidArgumentException('Select only active users linked to employees. Refresh the form.');
 if($data['position_id']!==0){$matched=wsQuery($db,"SELECT COUNT(*) total FROM users WHERE id IN($in) AND position_id=?",[$data['position_id']])->get_result()->fetch_assoc();if((int)$matched['total']!==count($ids))throw new InvalidArgumentException('Select presenter and attendees from the selected team, or choose All teams.');}
 $old=[];if($existing)$old=wsQuery($db,'SELECT id,storage_name FROM workshop_photos WHERE workshop_id=?',[(int)$existing['id']])->get_result()->fetch_all(MYSQLI_ASSOC);
 $keep=$existing?wsIds($input['keep_photo_ids']??array_column($old,'id'),'saved photos'):[];$oldIds=array_map('intval',array_column($old,'id'));if(array_diff($keep,$oldIds))throw new InvalidArgumentException('Invalid saved photo selection.');
 $uploads=wsUploads($files);if(isset($input['photo_count'])&&(!ctype_digit((string)$input['photo_count'])||(int)$input['photo_count']!==count($uploads)))throw new InvalidArgumentException('Not all photos reached the server. Use fewer photos or ask your administrator to check PHP upload limits.');$removed=array_filter($old,fn($p)=>!in_array((int)$p['id'],$keep,true));if(($uploads||$removed)&&empty($permission['can_upload']))throw new DomainException('Photo upload permission is required.');if(count($keep)+count($uploads)>wsLimits()['max_photos'])throw new InvalidArgumentException('Too many photos for one meeting.');
 $moved=[];$db->begin_transaction();
 try{
  $now=gmdate('Y-m-d H:i:s');if($existing){$id=(int)$existing['id'];wsQuery($db,'UPDATE workshop_meetings SET meeting_date=?,position_id=?,topic=?,presenter_id=?,remarks=?,updated_at=? WHERE id=?',[$data['meeting_date'],$data['position_id'],$data['topic'],$data['presenter_id'],$data['remarks'],$now,$id]);wsQuery($db,'DELETE FROM workshop_attendees WHERE workshop_id=?',[$id]);}else{wsQuery($db,'INSERT INTO workshop_meetings(meeting_date,position_id,topic,presenter_id,remarks,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)',[$data['meeting_date'],$data['position_id'],$data['topic'],$data['presenter_id'],$data['remarks'],(int)$user['id'],$now,$now]);$id=(int)$db->insert_id;}
  foreach($ids as $uid)wsQuery($db,'INSERT INTO workshop_attendees(workshop_id,user_id) VALUES(?,?)',[$id,$uid]);
  if($uploads){$directory=wsStorage();if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new RuntimeException('Photo storage unavailable.');if(!is_writable($directory))throw new RuntimeException('Photo storage is not writable.');file_put_contents($directory.'/.htaccess',"Require all denied\nDeny from all\n");foreach($uploads as $photo){if(!move_uploaded_file($photo['tmp'],$directory.'/'.$photo['name']))throw new RuntimeException('Photo move failed.');$moved[]=$photo['name'];wsQuery($db,'INSERT INTO workshop_photos(workshop_id,storage_name,original_name,mime_type,file_size,created_at) VALUES(?,?,?,?,?,?)',[$id,$photo['name'],$photo['original'],$photo['mime'],$photo['size'],$now]);}}
  foreach($removed as $photo)wsQuery($db,'DELETE FROM workshop_photos WHERE id=?',[(int)$photo['id']]);$db->commit();wsUnlink(array_column($removed,'storage_name'));return $id;
 }catch(Throwable $error){$db->rollback();wsUnlink($moved);throw $error;}
}
