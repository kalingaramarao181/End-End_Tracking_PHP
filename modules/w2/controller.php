<?php
require_once __DIR__.'/model.php';
class W2Controller{
 private $m;function __construct(){$this->m=new W2Model();}private function body(){return json_decode(file_get_contents('php://input'),true)?:[];}private function out($code,$data){http_response_code($code);echo json_encode($data);exit;}
 function create(){try{$d=$this->body();$this->out(201,['success'=>true,'message'=>'W-2 consultant form submitted successfully.','data'=>$this->m->create($d)]);}catch(Throwable $e){$this->out(500,['success'=>false,'message'=>'Unable to save W-2 form.']);}}
 function index(){try{$this->out(200,['success'=>true]+$this->m->list(max(1,(int)($_GET['page']??1)),min(100,max(1,(int)($_GET['limit']??20))),trim($_GET['search']??'')));}catch(Throwable $e){$this->out(500,['success'=>false,'message'=>'Unable to load W-2 forms.']);}}
 function show($id){$r=$this->m->get($id);$r?$this->out(200,['success'=>true,'data'=>$r]):$this->out(404,['success'=>false,'message'=>'W-2 form not found.']);}
 function update($id){try{$d=$this->body();$r=$this->m->update($id,$d);$r?$this->out(200,['success'=>true,'message'=>'W-2 form updated.','data'=>$r]):$this->out(404,['success'=>false,'message'=>'W-2 form not found.']);}catch(Throwable $e){$this->out(500,['success'=>false,'message'=>'Unable to update W-2 form.']);}}
 function delete($id){$this->m->delete($id)?$this->out(200,['success'=>true,'message'=>'W-2 form deleted.']):$this->out(404,['success'=>false,'message'=>'W-2 form not found.']);}
}
