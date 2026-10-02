<?php
/**
 * Employee candidate information is encrypted separately from payroll snapshots.
 * Documents are encrypted and stored outside the web root, served through authenticated routes.
 */
function employeeExecute($statement): void {
    if (!$statement->execute()) throw new RuntimeException('Employee database operation failed: '.$statement->error);
}
function employeeArrayIsList(array $value): bool {
    return $value === [] || array_keys($value) === range(0,count($value)-1);
}
function employeeCollectionCategories(array $collection = []): array {
    $keys=['highest_degree','intermediate','tenth_class','other_education','experience_1','experience_2','experience_3','computer_certifications','professional_certifications','achievements','signature'];
    foreach ($collection['education'] ?? [] as $row) {$keys[]='education_'.$row['id'].'_certificate';$keys[]='education_'.$row['id'].'_marksheets';}
    if ($collection['has_experience'] ?? false) foreach ($collection['employment'] ?? [] as $row) $keys[]='experience_'.$row['id'];
    foreach ($collection['certifications'] ?? [] as $row) $keys[]='certification_'.$row['id'];
    return $keys;
}
function employeeIniBytes(string $value): int {
    $value = trim($value); $number = (float)$value;
    return (int)($number * match(strtolower(substr($value, -1))) {'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1});
}
function employeeUploadLimits(): array {
    $upload = employeeIniBytes((string)ini_get('upload_max_filesize'));
    $post = employeeIniBytes((string)ini_get('post_max_size'));
    return ['max_file_bytes'=>min(5*1048576,$upload > 0 ? $upload : 5*1048576),
        'max_total_bytes'=>min(25*1048576,$post > 0 ? max(0,$post-262144) : 25*1048576),
        'max_files'=>min(20,max(1,(int)ini_get('max_file_uploads')))];
}
function employeeRequestData(): array {
    $postLimit = employeeIniBytes((string)ini_get('post_max_size'));
    if ($postLimit > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $postLimit) {
        throw new InvalidArgumentException('The submission exceeds the server upload limit. Please upload fewer or smaller documents.');
    }
    $multipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''),'multipart/form-data');
    $raw = $multipart ? ($_POST['payload'] ?? '') : file_get_contents('php://input');
    try { $data = json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new InvalidArgumentException('A valid form submission is required.'); }
    if (!is_array($data) || employeeArrayIsList($data)) throw new InvalidArgumentException('A valid form submission is required.');
    return $data;
}
function employeeCollectionText($value, string $label, int $max = 500): string {
    if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException($label.' must be text.');
    $text = trim((string)$value);
    if (strlen($text) > $max) throw new InvalidArgumentException($label.' is too long.');
    return $text;
}
function employeeCollectionDate($value, string $label, bool $required = false): string {
    $text = employeeCollectionText($value,$label,10);
    if ($text === '' && !$required) return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$text);
    if (!$date || $date->format('Y-m-d') !== $text) throw new InvalidArgumentException($label.' must be a valid date.');
    return $text;
}
function employeeDecodeCollection(?string $value): array {
    if (!$value) return [];
    $json = payrollDecryptValue($value);
    if ($json === '') throw new RuntimeException('Employee information could not be decrypted.');
    return employeeUpgradeCollection(json_decode($json,true,64,JSON_THROW_ON_ERROR));
}
function employeePublicCollection(array $collection): array {
    foreach ($collection['documents'] ?? [] as $index=>$document) unset($collection['documents'][$index]['storage_name']);
    return $collection;
}
function employeeUpgradeCollection(array $value): array {
    if (($value['schema_version'] ?? 1) === 2) return $value;
    $employment=[];
    foreach ($value['employment'] ?? [] as $index=>$row) {
        if (!is_array($row) || !(trim((string)($row['company'] ?? '')) || trim((string)($row['designation'] ?? '')) || trim((string)($row['period'] ?? '')))) continue;
        $row['id']=$row['id'] ?? 'legacy'.($index+1);$employment[]=$row;
    }
    $value['employment']=$employment;$value['has_experience']=count($employment)>0;
    $value['education']=$value['education'] ?? [];
    $value['certifications']=$value['certifications'] ?? [];
    if (!$value['certifications']) foreach ($value['computer_certifications'] ?? [] as $index=>$name) if (trim((string)$name)!=='') $value['certifications'][]=['id'=>'legacy'.($index+1),'name'=>$name,'issuer'=>'','year'=>''];
    $value['references']=$value['references'] ?? [];
    foreach ($value['documents'] ?? [] as $index=>$doc) if (preg_match('/^experience_([123])$/',$doc['category'] ?? '',$matches)) $value['documents'][$index]['category']='experience_legacy'.$matches[1];
    $value['schema_version']=2;
    return $value;
}
function employeeNormalizeCollection(array $input, array $existing = [], bool $public = false): array {
    $input=employeeUpgradeCollection($input);$existing=employeeUpgradeCollection($existing);
    $out=['schema_version'=>2];
    foreach (['email','facebook_profile','current_address','father_name','father_phone','mother_name','mother_phone','education_notes','professional_certifications','achievements','selected_role'] as $key) {
        $out[$key]=employeeCollectionText($input[$key] ?? $existing[$key] ?? '',$key,in_array($key,['current_address','education_notes','professional_certifications','achievements']) ? 5000 : 500);
    }
    if ($out['email']!=='' && !filter_var($out['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('A valid email address is required.');
    if ($public && $out['email']==='') throw new InvalidArgumentException('Email address is required.');
    $siblings=$input['siblings'] ?? [];
    if (!is_array($siblings) || !employeeArrayIsList($siblings) || count($siblings)>10) throw new InvalidArgumentException('Invalid sibling details.');
    $out['siblings']=[];
    foreach ($siblings as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid sibling details.');
        $clean=[];foreach (['name','relationship','phone'] as $key) $clean[$key]=employeeCollectionText($row[$key] ?? '',$key,250);
        $out['siblings'][]=$clean;
    }
    if (!is_bool($input['has_experience'] ?? false)) throw new InvalidArgumentException('Select whether you have previous experience.');
    $out['has_experience']=$input['has_experience'] ?? false;
    $spec=['employment'=>['company','designation','period'],'education'=>['degree','college','branch','passing_year'],'certifications'=>['name','issuer','year'],'references'=>['name','relationship','organization','phone','email']];
    foreach ($spec as $group=>$fields) {
        $rows=$group==='employment'&&!$out['has_experience']?[]:($input[$group] ?? []);
        if (!is_array($rows) || !employeeArrayIsList($rows) || count($rows)>($group==='references'?10:20)) throw new InvalidArgumentException('Too many or invalid '.$group.' entries.');
        $out[$group]=[];$ids=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Invalid '.$group.' entry.');
            $id=employeeCollectionText($row['id'] ?? '', 'Entry ID',32);
            if (!preg_match('/^[a-z0-9_-]{1,32}$/',$id) || isset($ids[$id])) throw new InvalidArgumentException('Invalid or duplicate '.$group.' entry ID.');
            $ids[$id]=true;$clean=['id'=>$id];
            foreach ($fields as $field) $clean[$field]=employeeCollectionText($row[$field] ?? '',$field,500);
            $required=match($group){'employment'=>['company','designation','period'],'education'=>['degree','college','passing_year'],default=>['name']};
            foreach ($required as $field) if ($clean[$field]==='') throw new InvalidArgumentException('Please complete '.$field.' in '.$group.'.');
            foreach (['passing_year','year'] as $field) if (isset($clean[$field]) && $clean[$field]!=='') {
                $year=filter_var($clean[$field],FILTER_VALIDATE_INT);
                if ($year===false || $year<1900 || $year>(int)date('Y')+1) throw new InvalidArgumentException('Please provide a valid year in '.$group.'.');
            }
            if ($group==='references' && $clean['email']!=='' && !filter_var($clean['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Please provide a valid reference email.');
            $out[$group][]=$clean;
        }
    }
    if ($public && !$out['education']) throw new InvalidArgumentException('Please add your highest qualification.');
    if ($out['has_experience'] && !$out['employment']) throw new InvalidArgumentException('Please add at least one company or uncheck previous experience.');
    $declaration=$public?($input['declaration'] ?? []):($existing['declaration'] ?? ['accepted'=>false]);
    if (!is_array($declaration)) throw new InvalidArgumentException('Invalid declaration.');
    if ($public && ($declaration['accepted'] ?? false)!==true) throw new InvalidArgumentException('Please confirm the declaration before submitting.');
    $out['declaration']=$public?['accepted'=>true,'candidate_name'=>employeeCollectionText($declaration['candidate_name'] ?? '','Candidate name',250),'date'=>date('Y-m-d'),'accepted_at'=>date(DATE_ATOM)]:$declaration;
    $review=$public?[]:($input['review'] ?? $existing['review'] ?? []);
    if (!is_array($review)) throw new InvalidArgumentException('Invalid review details.');
    $out['review']=['reviewed_by'=>employeeCollectionText($review['reviewed_by'] ?? '','Reviewed by',250),'date'=>employeeCollectionDate($review['date'] ?? '','Review date')];
    if (($out['review']['reviewed_by']==='')!==($out['review']['date']==='')) throw new InvalidArgumentException('Provide both reviewer name and review date.');
    $out['documents']=$existing['documents'] ?? [];
    $out['checklist']=$existing['checklist'] ?? [];
    return $out;
}
function employeeDocumentDirectory(): string {
    $directory = getenv('EMPLOYEE_DOCUMENT_DIR') ?: dirname(__DIR__,4).DIRECTORY_SEPARATOR.'e2e_employee_documents';
    if (is_file($directory)) throw new RuntimeException('Employee document storage is unavailable.');
    if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Employee document storage is unavailable.');
    $real = realpath($directory);
    $webRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: realpath(dirname(__DIR__,3));
    if ($webRoot && ($real === $webRoot || str_starts_with(strtolower($real),strtolower($webRoot).DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Employee document storage must be outside the web root.');
    }
    return $real;
}
function employeeReceiveDocuments(array &$collection, array &$createdPaths): void {
    $files = $_FILES['documents'] ?? [];
    $limits = employeeUploadLimits(); $total = 0; $count = 0;
    $mimeExtensions = ['application/pdf'=>['pdf'],'image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];
    if ($files && (!is_array($files['name'] ?? null) || !is_array($files['error'] ?? null))) throw new InvalidArgumentException('Invalid document upload.');
    $validated = [];
    foreach (($files['name'] ?? []) as $category=>$names) {
        if (!in_array($category,employeeCollectionCategories($collection),true) || !is_array($names)) throw new InvalidArgumentException('Invalid document category.');
        foreach ($names as $index=>$name) {
            $error = $files['error'][$category][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('A document could not be uploaded. Check the file size and retry.');
            $temp = $files['tmp_name'][$category][$index] ?? '';
            $size = is_file($temp) ? filesize($temp) : 0;
            if (!is_uploaded_file($temp) || $size < 1 || $size > $limits['max_file_bytes']) throw new InvalidArgumentException('A document exceeds the allowed file size or is invalid.');
            $count++; $total += $size;
            if ($count > $limits['max_files'] || $total > $limits['max_total_bytes']) throw new InvalidArgumentException('Too many documents or total upload size exceeded.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temp);
            $extension = strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));
            if (!isset($mimeExtensions[$mime]) || !in_array($extension,$mimeExtensions[$mime],true)) throw new InvalidArgumentException('Only genuine PDF, JPG, PNG and WebP documents are accepted.');
            $safeName = basename(str_replace('\\','/',(string)$name));
            $safeName = preg_replace('/[\x00-\x1F\x7F]/','',$safeName);
            if (strlen($safeName)>240) throw new InvalidArgumentException('Document filename is too long.');
            $validated[] = ['category'=>$category,'name'=>$safeName,'mime'=>$mime,'size'=>$size,'temp'=>$temp];
        }
    }
    if (count($collection['documents']) + count($validated) > 100) throw new InvalidArgumentException('An employee can have no more than 100 documents.');
    if ($validated) {
        $directory = employeeDocumentDirectory();
        foreach ($validated as $file) {
            $id = bin2hex(random_bytes(16)); $storage = $id.'.enc'; $path = $directory.DIRECTORY_SEPARATOR.$storage;
            $bytes = file_get_contents($file['temp']);
            if ($bytes === false) throw new RuntimeException('Document could not be read.');
            $encrypted = payrollEncryptValue($bytes);
            $createdPaths[] = $path;
            if (file_put_contents($path,$encrypted,LOCK_EX) !== strlen($encrypted)) throw new RuntimeException('Document could not be stored.');
            chmod($path,0600);
            unset($file['temp']); $file['id']=$id; $file['storage_name']=$storage; $file['uploaded_at']=date(DATE_ATOM);
            $collection['documents'][]=$file;
        }
    }
    foreach (employeeCollectionCategories($collection) as $category) {
        $hasFile = count(array_filter($collection['documents'],fn($document)=>$document['category']===$category))>0;
        $collection['checklist'][$category]['status'] = $hasFile ? 'submitted' : (($collection['checklist'][$category]['status'] ?? '')==='not_applicable' ? 'not_applicable' : 'pending');
    }
}
function employeeCleanupDocuments(array $paths): void {
    foreach ($paths as $path) if (is_file($path)) @unlink($path);
}
function employeeSaveCollection(int $employeeId, array $input, array $existing, array &$createdPaths, bool $public = false): void {
    global $conn;
    if (!array_key_exists('collection',$input)) {
        if ($public || !empty($_FILES['documents'])) throw new InvalidArgumentException('Candidate information is required with document uploads.');
        return;
    }
    if (!is_array($input['collection'])) throw new InvalidArgumentException('Invalid candidate information.');
    $collection = employeeNormalizeCollection($input['collection'],$existing,$public);
    if (isset($input['document_upload_count'])) {
        $expected=filter_var($input['document_upload_count'],FILTER_VALIDATE_INT);
        $actual=0;
        foreach ($_FILES['documents']['error'] ?? [] as $errors) {
            if (!is_array($errors)) throw new InvalidArgumentException('Invalid document upload.');
            foreach ($errors as $error) if ($error!==UPLOAD_ERR_NO_FILE) $actual++;
        }
        if ($expected===false || $expected<0 || $expected!==$actual) throw new InvalidArgumentException('Some documents were not received. Upload fewer files and retry.');
    }
    employeeReceiveDocuments($collection,$createdPaths);
    $encrypted = payrollEncryptValue(json_encode($collection,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $stmt = $conn->prepare('UPDATE employees SET candidate_collection=? WHERE id=?');
    $stmt->bind_param('si',$encrypted,$employeeId); employeeExecute($stmt);
}
function employeeDownloadDocument(int $employeeId, string $documentId): void {
    global $conn;
    $stmt=$conn->prepare('SELECT candidate_collection FROM employees WHERE id=?');
    $stmt->bind_param('i',$employeeId);employeeExecute($stmt);$row=$stmt->get_result()->fetch_assoc();
    if (!$row) {http_response_code(404);echo json_encode(['success'=>false,'message'=>'Employee not found.']);return;}
    try {
        $collection=employeeDecodeCollection($row['candidate_collection']);
        $document=null;foreach ($collection['documents'] ?? [] as $item) if ($item['id']===$documentId) {$document=$item;break;}
        if (!$document || !preg_match('/^[a-f0-9]{32}\.enc$/',$document['storage_name'] ?? '')) {http_response_code(404);echo json_encode(['success'=>false,'message'=>'Document not found.']);return;}
        $path=employeeDocumentDirectory().DIRECTORY_SEPARATOR.$document['storage_name'];
        if (!is_file($path)) throw new RuntimeException('Document file is unavailable.');
        $encrypted=file_get_contents($path);
        if ($encrypted===false) throw new RuntimeException('Document file is unavailable.');
        $bytes=payrollDecryptValue($encrypted);
        if ($bytes==='') throw new RuntimeException('Document could not be decrypted.');
        header('Content-Type: '.$document['mime']);
        header('Content-Length: '.strlen($bytes));
        header('Content-Disposition: attachment; filename="document"; filename*=UTF-8\'\''.rawurlencode($document['name']));
        header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
        echo $bytes;
    } catch (Throwable $error) {
        error_log('Employee document download: '.$error->getMessage());http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Document could not be downloaded.']);
    }
}
