<?php

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../middleware/upload.php';
require_once __DIR__ . '/services/ReminderEmailService.php';

class DocumentReminderController
{
    private $model;
    public function __construct(){ $this->model=new DocumentReminderModel(); }
    private function respond($code,$data){ http_response_code($code); echo json_encode($data,JSON_UNESCAPED_SLASHES); exit; }
    private function input(){ $data=json_decode(file_get_contents('php://input'),true); return is_array($data)?$data:$_POST; }

    public function upload()
    {
        $candidateId=(int)($_POST['candidate_id']??0); $type=$this->normalizeDocumentType($_POST['document_type']??'H1B');
        if(!$type) $this->respond(422,['success'=>false,'message'=>'document_type must be H1B, PERM Labor, or I-140.']);
        if(!$candidateId||!$this->model->candidateExists($candidateId)) $this->respond(404,['success'=>false,'message'=>'Candidate not found.']);

        $expiryDate=ReminderDateService::normalizeDate($_POST['expiry_date']??null);
        $issueDate=ReminderDateService::normalizeDate($_POST['issue_date']??null);
        $appliedDate=ReminderDateService::normalizeDate($_POST['applied_date']??null);
        $approvedDate=ReminderDateService::normalizeDate($_POST['approved_date']??null);
        if(!empty($_POST['expiry_date'])&&!$expiryDate) $this->respond(422,['success'=>false,'message'=>'expiry_date must use YYYY-MM-DD.']);
        if(!empty($_POST['issue_date'])&&!$issueDate) $this->respond(422,['success'=>false,'message'=>'issue_date must use YYYY-MM-DD.']);
        if(!empty($_POST['applied_date'])&&!$appliedDate) $this->respond(422,['success'=>false,'message'=>'applied_date must use YYYY-MM-DD.']);
        if(!empty($_POST['approved_date'])&&!$approvedDate) $this->respond(422,['success'=>false,'message'=>'approved_date must use YYYY-MM-DD.']);
        if($issueDate&&$expiryDate&&$issueDate>$expiryDate) $this->respond(422,['success'=>false,'message'=>'Issue date cannot be after expiry date.']);
        if($appliedDate&&$approvedDate&&$appliedDate>$approvedDate) $this->respond(422,['success'=>false,'message'=>'Applied date cannot be after approved date.']);
        if($type!=='H1B'&&!$appliedDate) $this->respond(422,['success'=>false,'message'=>'applied_date is required for PERM Labor and I-140 documents.']);

        [$reminderDate,$reminderReason]=$this->reminderPlan($type,$expiryDate,$appliedDate,$approvedDate);

        $details=[
            'visa_type'=>$this->nullableText($_POST['visa_type']??$type),
            'visa_number'=>$this->nullableText($_POST['visa_number']??null),
            'candidate_name'=>$this->nullableText($_POST['candidate_name']??null),
            'expiry_date'=>$expiryDate,
            'issue_date'=>$issueDate,
            'passport_number'=>$this->nullableText($_POST['passport_number']??null),
            'receipt_number'=>$this->nullableText($_POST['receipt_number']??null),
            'case_number'=>$this->nullableText($_POST['case_number']??null),
            'applied_date'=>$appliedDate,
            'approved_date'=>$approvedDate,
            'case_status'=>$approvedDate?'Approved':($type==='H1B'?null:'Applied'),
            'reminder_date'=>$reminderDate,
            'reminder_reason'=>$reminderReason,
            'confidence'=>100,
            'entry_method'=>'Manual'
        ];

        $file=$_FILES['document_file']??null;
        $extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));
        if(!$file||!in_array($extension,['pdf','png','jpg','jpeg','webp'],true)) $this->respond(422,['success'=>false,'message'=>'Document must be a PDF, PNG, JPG, JPEG, or WEBP file.']);
        $path=uploadFile($file,'candidates/documents');
        if(!$path) $this->respond(400,['success'=>false,'message'=>'A valid document_file is required.']);
        try {
            $documentId=$this->model->createDocument($candidateId,$type,$path);
            $this->model->saveDetails($documentId,$details);
            $reminder=null;
            if($reminderDate) $reminder=$this->model->createOrUpdateReminder($documentId,$reminderDate,$type);
            $this->respond(201,['success'=>true,'message'=>$reminder?'Document details saved and reminder created successfully.':'Document saved. A reminder date could not be calculated.','document'=>$this->model->getDocument($documentId),'reminder'=>$reminder]);
        } catch(Throwable $e) { $this->respond(500,['success'=>false,'message'=>'Document could not be saved.','error'=>$e->getMessage()]); }
    }

    private function nullableText($value)
    {
        $value=trim((string)$value);
        return $value===''?null:$value;
    }

    private function normalizeDocumentType($value)
    {
        $key=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$value));
        return ['H1B'=>'H1B','PERM'=>'PERM Labor','PERMLABOR'=>'PERM Labor','I140'=>'I-140'][$key]??null;
    }

    private function reminderPlan($type,$expiryDate,$appliedDate,$approvedDate)
    {
        if($type==='H1B') return [$expiryDate,$expiryDate?'H1B visa renewal before expiry':null];
        if($type==='PERM Labor'&&$approvedDate) return [ReminderDateService::addDays($approvedDate,180)->format('Y-m-d'),'File I-140 within 180 days of PERM certification'];
        if($type==='PERM Labor') return [ReminderDateService::addMonths($appliedDate,12)->format('Y-m-d'),'Review pending PERM case'];
        if($approvedDate) return [ReminderDateService::addMonths($approvedDate,12)->format('Y-m-d'),'Annual approved I-140 case review'];
        return [ReminderDateService::addMonths($appliedDate,6)->format('Y-m-d'),'Review pending I-140 petition'];
    }

    public function list(){ $this->respond(200,$this->model->listReminders($_GET)); }
    public function show($id){ $row=$this->model->getReminder($id); $this->respond($row?200:404,$row?['success'=>true,'data'=>$row]:['success'=>false,'message'=>'Reminder not found.']); }
    public function update($id){ try{$row=$this->model->updateReminder($id,$this->input());$this->respond($row?200:404,$row?['success'=>true,'data'=>$row]:['success'=>false,'message'=>'Reminder not found.']);}catch(Throwable $e){$this->respond(422,['success'=>false,'message'=>$e->getMessage()]);} }
    public function disable($id){ $ok=$this->model->disable($id); $this->respond($ok?200:404,['success'=>$ok,'message'=>$ok?'Reminder disabled.':'Reminder not found.']); }
    public function createManual($documentId){ try{$data=$this->input();$expiry=ReminderDateService::normalizeDate($data['expiry_date']??null);if(!$expiry)throw new InvalidArgumentException('A target date using YYYY-MM-DD is required.');$document=$this->model->getDocument($documentId);if(!$document)throw new InvalidArgumentException('Document not found.');$details=$document['document_details']?:[];$details[$document['document_type']==='H1B'?'expiry_date':'reminder_date']=$expiry;$details['entry_method']='Manual';$details['confidence']=100;$this->model->saveDetails($documentId,$details);$row=$this->model->createOrUpdateReminder($documentId,$expiry);$this->respond(201,['success'=>true,'data'=>$row]);}catch(Throwable $e){$this->respond(422,['success'=>false,'message'=>$e->getMessage()]);} }
    public function sendNow($id){ $row=$this->model->getReminder($id); if(!$row)$this->respond(404,['success'=>false,'message'=>'Reminder not found.']); try{(new ReminderEmailService())->send($row);$this->respond(200,['success'=>true,'message'=>'Reminder sent. Future schedule was not changed.']);}catch(Throwable $e){$this->respond(502,['success'=>false,'message'=>$e->getMessage()]);} }
    public function dashboard(){ $this->respond(200,['success'=>true,'data'=>$this->model->dashboard()]); }
}
