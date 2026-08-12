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
        $noticeDate=ReminderDateService::normalizeDate($_POST['notice_date']??null);
        $receivedDate=ReminderDateService::normalizeDate($_POST['received_date']??null);
        $priorityDate=ReminderDateService::normalizeDate($_POST['priority_date']??null);
        if(!empty($_POST['expiry_date'])&&!$expiryDate) $this->respond(422,['success'=>false,'message'=>'expiry_date must use YYYY-MM-DD.']);
        if(!empty($_POST['issue_date'])&&!$issueDate) $this->respond(422,['success'=>false,'message'=>'issue_date must use YYYY-MM-DD.']);
        if(!empty($_POST['applied_date'])&&!$appliedDate) $this->respond(422,['success'=>false,'message'=>'applied_date must use YYYY-MM-DD.']);
        if(!empty($_POST['approved_date'])&&!$approvedDate) $this->respond(422,['success'=>false,'message'=>'approved_date must use YYYY-MM-DD.']);
        foreach(['notice_date'=>$noticeDate,'received_date'=>$receivedDate,'priority_date'=>$priorityDate] as $field=>$date) if(!empty($_POST[$field])&&!$date) $this->respond(422,['success'=>false,'message'=>$field.' must use YYYY-MM-DD.']);
        if($issueDate&&$expiryDate&&$issueDate>$expiryDate) $this->respond(422,['success'=>false,'message'=>'Issue date cannot be after expiry date.']);
        if($appliedDate&&$approvedDate&&$appliedDate>$approvedDate) $this->respond(422,['success'=>false,'message'=>'Applied date cannot be after approved date.']);
        if($type==='PERM Labor'&&!$appliedDate) $this->respond(422,['success'=>false,'message'=>'applied_date is required for PERM Labor documents.']);
        if($type==='I-140'&&(!$noticeDate||!$receivedDate||!$priorityDate)) $this->respond(422,['success'=>false,'message'=>'notice_date, received_date, and priority_date are required for I-140 documents.']);
        if($type==='I-140'&&$receivedDate>$noticeDate) $this->respond(422,['success'=>false,'message'=>'Received date cannot be after notice date.']);
        if($type==='I-140'&&$approvedDate&&$receivedDate>$approvedDate) $this->respond(422,['success'=>false,'message'=>'Received date cannot be after approved date.']);

        [$reminderDate,$reminderReason]=$this->reminderPlan($type,$expiryDate,$appliedDate,$approvedDate,$priorityDate);

        // Parse privileges (dollars)
        $privileges=null;
        if(!empty($_POST['privileges'])) {
            $privilegesValue=(float)($_POST['privileges']??0);
            if($privilegesValue>0) $privileges=$privilegesValue;
        }

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
            'notice_date'=>$noticeDate,
            'received_date'=>$receivedDate,
            'priority_date'=>$priorityDate,
            'case_status'=>$approvedDate?'Approved':($type==='H1B'?null:'Applied'),
            'reminder_date'=>$reminderDate,
            'reminder_reason'=>$reminderReason,
            'confidence'=>100,
            'entry_method'=>'Manual',
            'privileges'=>$privileges
        ];

        $file=$_FILES['document_file']??null;
        $details['document_name']=basename((string)($file['name']??''));
        $extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));
        if(!$file||!in_array($extension,['pdf','png','jpg','jpeg','webp'],true)) $this->respond(422,['success'=>false,'message'=>'Document must be a PDF, PNG, JPG, JPEG, or WEBP file.']);
        $path=uploadFile($file,'candidates/documents');
        if(!$path) $this->respond(400,['success'=>false,'message'=>'A valid document_file is required.']);
        try {
            $documentId=$this->model->createDocument($candidateId,$type,$path);
            $this->model->saveDetails($documentId,$details);
            $reminder=null;
            if($reminderDate) {
                $reminder=$this->model->createOrUpdateReminder($documentId,$reminderDate,$type,$privileges);
                if($type==='I-140'&&$approvedDate) { $this->model->disable($reminder['id']); $reminder=$this->model->getReminder($reminder['id']); }
            }
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

    private function reminderPlan($type,$expiryDate,$appliedDate,$approvedDate,$priorityDate)
    {
        if($type==='H1B') return [$expiryDate,$expiryDate?'H1B visa renewal before expiry':null];
        if($type==='PERM Labor'&&$approvedDate) return [ReminderDateService::addDays($approvedDate,180)->format('Y-m-d'),'File I-140 within 180 days of PERM certification'];
        if($type==='PERM Labor') return [ReminderDateService::addMonths($appliedDate,12)->format('Y-m-d'),'Review pending PERM case'];
        return [$priorityDate, $approvedDate ? 'I-140 approved; email reminders disabled' : 'Prepare to apply for the Green Card when the priority date becomes current'];
    }

    public function list(){ $this->respond(200,$this->model->listReminders($_GET)); }
    public function export()
    {
        $rows=$this->model->exportReminders($_GET);
        $filename='filtered-immigration-documents-'.date('Y-m-d').'.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        $xml=function($value){ return htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_XML1,'UTF-8'); };
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<Styles>';
        echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
        echo '<Style ss:ID="Title"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#4338CA" ss:Pattern="Solid"/></Style>';
        echo '<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7DCE5"/></Borders></Style>';
        echo '<Style ss:ID="Left"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>';
        echo '<Style ss:ID="Center"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>';
        echo '<Style ss:ID="Currency"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Number ss:Format="$#,##0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>';
        echo '<Style ss:ID="Link"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:Color="#0563C1" ss:Underline="Single"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>';
        echo '</Styles><Worksheet ss:Name="Immigration Documents"><Table>';
        foreach([150,210,210,110,105,80,105,90,100,120] as $width) echo '<Column ss:AutoFitWidth="0" ss:Width="'.$width.'"/>';
        echo '<Row ss:Height="30"><Cell ss:MergeAcross="9" ss:StyleID="Title"><Data ss:Type="String">Filtered Immigration Documents</Data></Cell></Row>';
        echo '<Row ss:Height="28">';
        foreach(['Candidate','Email','Document Name','Document Type','Target Date','Days Left','Next Reminder','Status','Prevailing Wages','Document Link'] as $heading) echo '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$xml($heading).'</Data></Cell>';
        echo '</Row>';
        foreach($rows as $row){
            $path=ltrim((string)$row['document'],'/');
            $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
            $link=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').'/E2E_Tracking/'.$path;
            $details=is_array($row['document_details']??null)?$row['document_details']:[];
            $name=$details['document_name']??basename($path);
            $privileges=$row['privileges']??null;
            echo '<Row ss:Height="23">';
            foreach([$row['candidate_name'],$row['candidate_email'],$name] as $value) echo '<Cell ss:StyleID="Left"><Data ss:Type="String">'.$xml($value).'</Data></Cell>';
            foreach([$row['document_type'],$row['expiry_date'],$row['days_left'],$row['next_reminder_date']?:'-',$row['status']] as $value) echo '<Cell ss:StyleID="Center"><Data ss:Type="String">'.$xml($value).'</Data></Cell>';
            echo $privileges?'<Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$xml($privileges).'</Data></Cell>':'<Cell ss:StyleID="Center"><Data ss:Type="String">-</Data></Cell>';
            echo $link?'<Cell ss:StyleID="Link" ss:HRef="'.$xml($link).'"><Data ss:Type="String">Open Document</Data></Cell>':'<Cell ss:StyleID="Center"><Data ss:Type="String">-</Data></Cell>';
            echo '</Row>';
        }
        echo '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>';
        echo '<AutoFilter x:Range="R2C1:R'.(count($rows)+2).'C10" xmlns="urn:schemas-microsoft-com:office:excel"/>';
        echo '</Worksheet></Workbook>';
        exit;
    }

    public function show($id){ $row=$this->model->getReminder($id); $this->respond($row?200:404,$row?['success'=>true,'data'=>$row]:['success'=>false,'message'=>'Reminder not found.']); }
    public function update($id){ try{$row=$this->model->updateReminder($id,$this->input());$this->respond($row?200:404,$row?['success'=>true,'data'=>$row]:['success'=>false,'message'=>'Reminder not found.']);}catch(Throwable $e){$this->respond(422,['success'=>false,'message'=>$e->getMessage()]);} }
    public function disable($id){ $ok=$this->model->disable($id); $this->respond($ok?200:404,['success'=>$ok,'message'=>$ok?'Reminder disabled.':'Reminder not found.']); }
    public function createManual($documentId){ try{$data=$this->input();$expiry=ReminderDateService::normalizeDate($data['expiry_date']??null);if(!$expiry)throw new InvalidArgumentException('A target date using YYYY-MM-DD is required.');$document=$this->model->getDocument($documentId);if(!$document)throw new InvalidArgumentException('Document not found.');$details=$document['document_details']?:[];$details[$document['document_type']==='H1B'?'expiry_date':'reminder_date']=$expiry;$details['entry_method']='Manual';$details['confidence']=100;$privileges=$data['privileges']??null;if($privileges) $details['privileges']=$privileges;$this->model->saveDetails($documentId,$details);$row=$this->model->createOrUpdateReminder($documentId,$expiry,null,$privileges);$this->respond(201,['success'=>true,'data'=>$row]);}catch(Throwable $e){$this->respond(422,['success'=>false,'message'=>$e->getMessage()]);} }
    public function sendNow($id){ $row=$this->model->getReminder($id); if(!$row)$this->respond(404,['success'=>false,'message'=>'Reminder not found.']); if($row['document_type']==='I-140'&&!empty($row['document_details']['approved_date'])) $this->respond(409,['success'=>false,'message'=>'Approved I-140 documents do not send email reminders.']); try{(new ReminderEmailService())->send($row);$this->respond(200,['success'=>true,'message'=>'Reminder sent. Future schedule was not changed.']);}catch(Throwable $e){$this->respond(502,['success'=>false,'message'=>$e->getMessage()]);} }
    public function dashboard(){ $this->respond(200,['success'=>true,'data'=>$this->model->dashboard()]); }
}
