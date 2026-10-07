<?php
// Portraits use the existing protected, encrypted employee-file storage.
function employeePhotoNameIsValid(string $name): bool {
    return (bool)preg_match('/^[a-f0-9]{32}\.photo\.enc$/D', $name);
}
function employeeValidatePhoto(array $file): array {
    $limits=employeeUploadLimits();$max=min(2097152,$limits['max_file_bytes'],$limits['max_total_bytes']);
    $limit=number_format($max/1048576,1);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Photo upload failed. Choose an image up to '.$limit.' MB.');
    $path=$file['tmp_name'] ?? '';
    $size=is_file($path)?filesize($path):0;
    if (!$size || $size>$max) throw new InvalidArgumentException('Choose an image up to '.$limit.' MB.');
    $info=@getimagesize($path);
    $mime=class_exists('finfo')?(new finfo(FILEINFO_MIME_TYPE))->file($path):($info['mime']??'');
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$info || ($info['mime']??'')!==$mime) throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP photo.');
    if ($info[0]<1 || $info[1]<1 || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>16000000) throw new InvalidArgumentException('Photo dimensions are too large. Choose an image up to 6000 pixels and 16 megapixels.');
    return ['path'=>$path,'mime'=>$mime];
}
function employeeSavePhoto(int $employeeId, array $file): array {
    global $conn;
    $newPath='';$transaction=false;$stage='validation';
    try {
        $validated=employeeValidatePhoto($file);
        if (!is_uploaded_file($validated['path'])) throw new InvalidArgumentException('Invalid photo upload.');
        $bytes=file_get_contents($validated['path']);
        if ($bytes===false) throw new RuntimeException('Photo could not be read.');
        $name=bin2hex(random_bytes(16)).'.photo.enc';
        $stage='storage';
        $newPath=employeeDocumentDirectory().DIRECTORY_SEPARATOR.$name;
        $stage='encryption';
        $encrypted=payrollEncryptValue($bytes);
        $stage='storage';
        if (@file_put_contents($newPath,$encrypted,LOCK_EX)!==strlen($encrypted)) throw new RuntimeException('Photo could not be stored.');
        @chmod($newPath,0600);
        $stage='database';
        $conn->begin_transaction();$transaction=true;
        $select=$conn->prepare('SELECT photo FROM employees WHERE id=? FOR UPDATE');$select->bind_param('i',$employeeId);employeeExecute($select);$row=$select->get_result()->fetch_assoc();
        if (!$row) throw new InvalidArgumentException('Employee not found.');
        $stmt=$conn->prepare('UPDATE employees SET photo=? WHERE id=?');$stmt->bind_param('si',$name,$employeeId);employeeExecute($stmt);
        $conn->commit();$transaction=false;
        $old=(string)($row['photo']??'');
        if(employeePhotoNameIsValid($old)) @unlink(employeeDocumentDirectory().DIRECTORY_SEPARATOR.$old);
        return ['success'=>true,'message'=>'Profile photo updated.','photo'=>$name];
    } catch(Throwable $error) {
        if($transaction){try{$conn->rollback();}catch(Throwable $rollbackError){error_log('Employee photo rollback failed: '.$rollbackError->getMessage());}}
        if($newPath && is_file($newPath))@unlink($newPath);
        if(!($error instanceof InvalidArgumentException))error_log('Employee photo ['.$stage.']: '.$error->getMessage());
        $messages=[
            'storage'=>'Photo storage is unavailable. Ask the server administrator to configure EMPLOYEE_DOCUMENT_DIR as a writable private folder outside the website root.',
            'encryption'=>'Photo encryption is unavailable. Ask the server administrator to verify the existing MAIL_CREDENTIAL_KEY configuration.',
            'database'=>'Photo could not be saved in the employee record. Ask the server administrator to check the employee photo column and database error log.',
        ];
        return ['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():($messages[$stage]??'Photo could not be processed. Ask the server administrator to check the PHP error log.')];
    }
}
function employeeReadPhoto(int $employeeId): void {
    global $conn;
    $stmt=$conn->prepare('SELECT photo FROM employees WHERE id=?');$stmt->bind_param('i',$employeeId);employeeExecute($stmt);$row=$stmt->get_result()->fetch_assoc();
    $name=(string)($row['photo']??'');
    if(!employeePhotoNameIsValid($name)){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Profile photo not found.']);return;}
    try {
        $path=employeeDocumentDirectory().DIRECTORY_SEPARATOR.$name;
        if(!is_file($path))throw new RuntimeException('Photo not found.');
        $stored=file_get_contents($path);if($stored===false)throw new RuntimeException('Photo not found.');
        $bytes=payrollDecryptValue($stored);
        $info=@getimagesizefromstring($bytes);
        if(!$info || !in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Photo could not be read.');
        header('Content-Type: '.$info['mime']);header('Content-Length: '.strlen($bytes));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
        echo $bytes;
    } catch(Throwable $error){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Profile photo not found.']);}
}
