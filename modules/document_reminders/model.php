<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/services/ReminderDateService.php';

class DocumentReminderModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    public function candidateExists($id)
    {
        $stmt = $this->conn->prepare('SELECT id FROM candidate WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    }

    public function createDocument($candidateId, $type, $path)
    {
        $stmt = $this->conn->prepare('INSERT INTO candidate_documents (candidate_id, document_type, document) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $candidateId, $type, $path);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
        return (int)$this->conn->insert_id;
    }

    public function saveDetails($documentId, array $details)
    {
        $json = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt = $this->conn->prepare('UPDATE candidate_documents SET document_details = ? WHERE id = ?');
        $stmt->bind_param('si', $json, $documentId);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    }

    public function getDocument($id)
    {
        $stmt = $this->conn->prepare('SELECT cd.*, c.name AS candidate_name, c.email AS candidate_email FROM candidate_documents cd JOIN candidate c ON c.id = cd.candidate_id WHERE cd.id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $this->hydrateDocument($row ?: null);
    }

    public function createOrUpdateReminder($documentId, $expiryDate, $type = null)
    {
        $document = $this->getDocument($documentId);
        if (!$document) throw new RuntimeException('Document not found.');
        $normalized = ReminderDateService::normalizeDate($expiryDate);
        if (!$normalized) throw new InvalidArgumentException('expiry_date must use YYYY-MM-DD.');

        $next = ReminderDateService::initialReminderDate($normalized);
        $status = $next ? 'Pending' : 'Expired';
        $documentType = $type ?: $document['document_type'];
        $sql = 'INSERT INTO document_reminders (candidate_id, document_id, document_type, expiry_date, next_reminder_date, status) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE document_type = VALUES(document_type), expiry_date = VALUES(expiry_date), next_reminder_date = VALUES(next_reminder_date), status = VALUES(status)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iissss', $document['candidate_id'], $documentId, $documentType, $normalized, $next, $status);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
        return $this->getReminderByDocument($documentId);
    }

    public function getReminder($id)
    {
        $sql = $this->baseSelect() . ' WHERE dr.id = ? LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $this->hydrateReminder($stmt->get_result()->fetch_assoc() ?: null);
    }

    public function getReminderByDocument($documentId)
    {
        $sql = $this->baseSelect() . ' WHERE dr.document_id = ? LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $documentId);
        $stmt->execute();
        return $this->hydrateReminder($stmt->get_result()->fetch_assoc() ?: null);
    }

    public function listReminders(array $filters)
    {
        $conditions = ['1=1']; $params = []; $types = '';
        if (!empty($filters['candidate'])) { $conditions[] = '(c.name LIKE ? OR c.email LIKE ?)'; $v = '%' . $filters['candidate'] . '%'; $params[]=$v; $params[]=$v; $types.='ss'; }
        if (!empty($filters['document_type'])) { $conditions[]='dr.document_type = ?'; $params[]=$filters['document_type']; $types.='s'; }
        if (!empty($filters['status'])) { $conditions[]='dr.status = ?'; $params[]=$filters['status']; $types.='s'; }
        if (!empty($filters['expiry_from'])) { $conditions[]='dr.expiry_date >= ?'; $params[]=$filters['expiry_from']; $types.='s'; }
        if (!empty($filters['expiry_to'])) { $conditions[]='dr.expiry_date <= ?'; $params[]=$filters['expiry_to']; $types.='s'; }
        if (isset($filters['days_left']) && $filters['days_left'] !== '') { $conditions[]='DATEDIFF(dr.expiry_date, CURDATE()) <= ?'; $params[]=(int)$filters['days_left']; $types.='i'; }
        $page=max(1,(int)($filters['page']??1)); $limit=min(100,max(1,(int)($filters['limit']??20))); $offset=($page-1)*$limit;
        $where=implode(' AND ', $conditions);
        $count=$this->conn->prepare('SELECT COUNT(*) total FROM document_reminders dr JOIN candidate c ON c.id=dr.candidate_id WHERE '.$where);
        if ($params) $count->bind_param($types, ...$params); $count->execute(); $total=(int)$count->get_result()->fetch_assoc()['total'];
        $stmt=$this->conn->prepare($this->baseSelect().' WHERE '.$where.' ORDER BY dr.expiry_date ASC LIMIT ? OFFSET ?');
        $dataParams=$params; $dataParams[]=$limit; $dataParams[]=$offset; $stmt->bind_param($types.'ii', ...$dataParams); $stmt->execute();
        $rows=[]; $result=$stmt->get_result(); while($row=$result->fetch_assoc()) $rows[]=$this->hydrateReminder($row);
        return ['success'=>true,'page'=>$page,'limit'=>$limit,'total_records'=>$total,'total_pages'=>(int)ceil($total/$limit),'data'=>$rows];
    }

    public function exportReminders(array $filters)
    {
        $filters['limit']=100; $filters['page']=1; $rows=[];
        do {
            $result=$this->listReminders($filters);
            $rows=array_merge($rows,$result['data']);
            $filters['page']++;
        } while($filters['page'] <= $result['total_pages']);
        return $rows;
    }
    public function updateReminder($id, array $data)
    {
        $existing=$this->getReminder($id); if(!$existing) return null;
        $expiry=ReminderDateService::normalizeDate($data['expiry_date']??$existing['expiry_date']);
        if(!$expiry) throw new InvalidArgumentException('A valid expiry_date is required.');
        $next=array_key_exists('next_reminder_date',$data)
            ? ReminderDateService::normalizeDate($data['next_reminder_date'])
            : (array_key_exists('expiry_date',$data) ? ReminderDateService::initialReminderDate($expiry) : $existing['next_reminder_date']);
        $status=$data['status']??$existing['status'];
        if(!in_array($status,['Pending','Completed','Expired','Disabled'],true)) throw new InvalidArgumentException('Invalid reminder status.');
        $stmt=$this->conn->prepare('UPDATE document_reminders SET expiry_date=?, next_reminder_date=?, status=? WHERE id=?');
        $stmt->bind_param('sssi',$expiry,$next,$status,$id); if(!$stmt->execute()) throw new RuntimeException($stmt->error);
        $document=$this->getDocument($existing['document_id']); $details=$document['document_details']?:[];
        $details[$document['document_type']==='H1B'?'expiry_date':'reminder_date']=$expiry;
        $this->saveDetails($existing['document_id'],$details);
        return $this->getReminder($id);
    }

    public function disable($id)
    {
        $stmt=$this->conn->prepare("UPDATE document_reminders SET status='Disabled', next_reminder_date=NULL WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute(); return $stmt->affected_rows>0;
    }

    public function dueReminders()
    {
        $result=$this->conn->query($this->baseSelect()." WHERE dr.status='Pending' AND dr.next_reminder_date <= CURDATE() AND NOT (dr.document_type='I-140' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cd.document_details,'$.approved_date')),'') <> '') ORDER BY dr.next_reminder_date");
        $rows=[]; while($row=$result->fetch_assoc()) $rows[]=$this->hydrateReminder($row); return $rows;
    }

    public function markSent($id, $currentDate)
    {
        $reminder=$this->getReminder($id); if(!$reminder) return;
        $next=ReminderDateService::addMonth($currentDate)->format('Y-m-d');
        while($next<=date('Y-m-d')&&$next<$reminder['expiry_date']) {
            $next=ReminderDateService::addMonth($next)->format('Y-m-d');
        }
        $status=$next >= $reminder['expiry_date'] ? 'Completed' : 'Pending';
        if($status==='Completed') $next=null;
        $stmt=$this->conn->prepare('UPDATE document_reminders SET last_sent_date=NOW(), next_reminder_date=?, status=? WHERE id=?');
        $stmt->bind_param('ssi',$next,$status,$id); if(!$stmt->execute()) throw new RuntimeException($stmt->error);
    }

    public function expirePastReminders()
    {
        $this->conn->query("UPDATE document_reminders SET status='Expired', next_reminder_date=NULL WHERE status='Pending' AND expiry_date <= CURDATE()");
    }

    public function dashboard()
    {
        $sql="SELECT horizon.days, COUNT(dr.id) count FROM (SELECT 30 days UNION ALL SELECT 60 UNION ALL SELECT 90 UNION ALL SELECT 180) horizon LEFT JOIN document_reminders dr ON dr.status='Pending' AND DATEDIFF(dr.expiry_date,CURDATE()) BETWEEN 0 AND horizon.days GROUP BY horizon.days ORDER BY horizon.days";
        $result=$this->conn->query($sql); $data=[]; while($row=$result->fetch_assoc()) $data[(int)$row['days']]=(int)$row['count']; return $data;
    }

    private function baseSelect()
    {
        return 'SELECT dr.*, c.name candidate_name, c.email candidate_email, cd.document, cd.document_details, DATEDIFF(dr.expiry_date,CURDATE()) days_left FROM document_reminders dr JOIN candidate c ON c.id=dr.candidate_id JOIN candidate_documents cd ON cd.id=dr.document_id';
    }

    private function hydrateDocument($row)
    {
        if(!$row) return null; $row['id']=(int)$row['id']; $row['candidate_id']=(int)$row['candidate_id']; $row['document_details']=$row['document_details'] ? json_decode($row['document_details'],true) : null; return $row;
    }

    private function hydrateReminder($row)
    {
        if(!$row) return null; foreach(['id','candidate_id','document_id','days_left'] as $key) if(isset($row[$key])) $row[$key]=(int)$row[$key]; $row['document_details']=$row['document_details'] ? json_decode($row['document_details'],true) : null; return $row;
    }
}
