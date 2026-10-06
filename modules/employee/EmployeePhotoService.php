<?php
// Portraits use the existing protected, encrypted employee-file storage.
function employeePhotoNameIsValid(string $name): bool {
    return (bool)preg_match('/^[a-f0-9]{32}\.photo\.enc$/D', $name);
}
function employeeValidatePhoto(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Photo upload failed. Choose an image up to 2 MB.');
    $path=$file['tmp_name'] ?? '';
    $size=is_file($path)?filesize($path):0;
    if (!$size || $size>2097152) throw new InvalidArgumentException('Choose an image up to 2 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    $info=@getimagesize($path);
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$info || ($info['mime']??'')!==$mime) throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP photo.');
    if ($info[0]<1 || $info[1]<1 || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>16000000) throw new InvalidArgumentException('Photo dimensions are too large. Choose an image up to 6000 pixels and 16 megapixels.');
    return ['path'=>$path,'mime'=>$mime];
}
function employeeSavePhoto(int $employeeId, array $file): array {
    global $conn;
    $newPath='';$transaction=false;
    try {
        $validated=employeeValidatePhoto($file);
        if (!is_uploaded_file($validated['path'])) throw new InvalidArgumentException('Invalid photo upload.');
        $bytes=file_get_contents($validated['path']);
        if ($bytes===false) throw new RuntimeException('Photo could not be read.');
        $name=bin2hex(random_bytes(16)).'.photo.enc';
        $newPath=employeeDocumentDirectory().DIRECTORY_SEPARATOR.$name;
        $encrypted=payrollEncryptValue($bytes);
        if (file_put_contents($newPath,$encrypted,LOCK_EX)!==strlen($encrypted)) throw new RuntimeException('Photo could not be stored.');
        @chmod($newPath,0600);
        $conn->begin_transaction();$transaction=true;
        $select=$conn->prepare('SELECT photo FROM employees WHERE id=? FOR UPDATE');$select->bind_param('i',$employeeId);employeeExecute($select);$row=$select->get_result()->fetch_assoc();
        if (!$row) throw new InvalidArgumentException('Employee not found.');
        $stmt=$conn->prepare('UPDATE employees SET photo=? WHERE id=?');$stmt->bind_param('si',$name,$employeeId);employeeExecute($stmt);
        $conn->commit();$transaction=false;
        $old=(string)($row['photo']??'');
        if(employeePhotoNameIsValid($old)) @unlink(employeeDocumentDirectory().DIRECTORY_SEPARATOR.$old);
        return ['success'=>true,'message'=>'Profile photo updated.','photo'=>$name];
    } catch(Throwable $error) {
        if($transaction)$conn->rollback();
        if($newPath && is_file($newPath))@unlink($newPath);
        if(!($error instanceof InvalidArgumentException))error_log('Employee photo: '.$error->getMessage());
        return ['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Photo could not be saved. Please try again.'];
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
