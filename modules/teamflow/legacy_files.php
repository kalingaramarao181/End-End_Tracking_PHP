<?php
// Resolve imported references only within the original TeamFlow upload directory.
function tfResolveAttachment($file){
 if(!empty($file['storage_name'])){
  if(!empty($file['missing'])||!preg_match('/^[a-f0-9]{48}\.bin$/',$file['storage_name']))return null;
  $path=tfStorage().DIRECTORY_SEPARATOR.$file['storage_name'];
  return is_file($path)?$path:null;
 }
 if(($file['entity_type']??'')==='candidate_photo')return null;
 $relative=str_replace('\\','/',(string)($file['original_path']??''));
 if($relative===''||str_contains($relative,"\0")||preg_match('#(^|/)\.\.(/|$)|^[a-z][a-z0-9+.-]*:|^/#i',$relative))return null;
 while(str_starts_with($relative,'./'))$relative=substr($relative,2);
 while(str_starts_with($relative,'uploads/'))$relative=substr($relative,8);
 if($relative==='')return null;
 $root=realpath(getenv('TEAMFLOW_LEGACY_UPLOAD_DIR')?:dirname(__DIR__,2).'/uploads/uploads');
 if(!$root)return null;
 $candidates=[$relative];
 if(($file['entity_type']??'')==='employee_status_documents'&&!str_contains($relative,'/'))$candidates[]='DailyStatusReports/'.$relative;
 foreach($candidates as $candidate){
  $path=realpath($root.DIRECTORY_SEPARATOR.$candidate);
  $prefix=$root.DIRECTORY_SEPARATOR;
  $inside=$path&&(DIRECTORY_SEPARATOR==='\\'?strncasecmp($path,$prefix,strlen($prefix))===0:strncmp($path,$prefix,strlen($prefix))===0);
  if($inside&&is_file($path)&&is_readable($path))return $path;
 }
 return null;
}
function tfAttachmentMetadata($file){
 $path=tfResolveAttachment($file);
 $file['missing']=$path?0:1;
 if($path){$file['mime_type']=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';$file['file_size']=filesize($path);}
 $file['download_url']='/teamflow/files/'.(int)$file['id'];
 unset($file['original_path'],$file['storage_name']);
 return $file;
}
