<?php
// Baseline access is restricted to the authenticated user's own profile.
// Existing explicit grants remain unchanged. A denied/missing grant gains view only.
function selfProfilePermission($permission){
 if(!empty($permission['can_view']))return $permission;
 $result=['data_scope'=>'OWN'];foreach(['view','create','edit','delete','export','import','upload','download','approve','reject','assign','manage','print','share'] as $action)$result['can_'.$action]=0;$result['can_view']=1;return $result;
}
function selfProfileResources($resources){
 $found=false;foreach($resources as &$resource){if($resource['resource']!=='profile')continue;$found=true;
  if(empty($resource['permissions']['view'])){foreach($resource['permissions'] as &$allowed)$allowed=false;unset($allowed);$resource['permissions']['view']=true;$resource['scope']='OWN';}
  $resource['route']='/dashboard/my-profile';$resource['component_key']='profile';$resource['resource_type']='PAGE';
 }unset($resource);
 if(!$found)$resources[]=['id'=>0,'resource'=>'profile','display_name'=>'My Profile','icon'=>'user','route'=>'/dashboard/my-profile','component_key'=>'profile','resource_type'=>'PAGE','parent_id'=>null,'sort_order'=>0,'permissions'=>['view'=>true],'scope'=>'OWN'];
 return $resources;
}
