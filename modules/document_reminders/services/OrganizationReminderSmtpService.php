<?php
class OrganizationReminderSmtpService
{
    private function key()
    {
        $secret=(string)getenv('MAIL_CREDENTIAL_KEY');
        $localKey=__DIR__.'/../../../config/mail-credential-key.php';
        if(strlen($secret)<32&&is_file($localKey))$secret=(string)require $localKey;
        if(strlen($secret)<32)throw new RuntimeException('MAIL_CREDENTIAL_KEY must contain at least 32 random characters.');
        return hash('sha256',$secret,true);
    }
    private function encrypt($plain)
    {
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new RuntimeException('Unable to encrypt the reminder mailbox password.');
        return base64_encode($iv.$tag.$cipher);
    }
    private function decrypt($encoded)
    {
        $raw=base64_decode($encoded,true);
        if($raw===false||strlen($raw)<29)throw new RuntimeException('The stored reminder mailbox credential is invalid.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if($plain===false)throw new RuntimeException('Unable to decrypt the reminder mailbox. Check MAIL_CREDENTIAL_KEY.');
        return $plain;
    }
    private function row()
    {
        global $conn;
        if(!$conn)return null;
        $result=$conn->query('SELECT * FROM document_reminder_mail_settings WHERE id=1 LIMIT 1');
        return $result?$result->fetch_assoc():null;
    }
    public function status()
    {
        $row=$this->row();
        return ['configured'=>(bool)$row,'data'=>$row?[
            'smtp_host'=>$row['smtp_host'],'smtp_port'=>(int)$row['smtp_port'],'encryption'=>$row['encryption'],
            'from_email'=>$row['from_email'],'from_name'=>$row['from_name'],'to_email'=>$row['to_email'],
            'verified_at'=>$row['verified_at'],'updated_at'=>$row['updated_at']
        ]:null];
    }
    public function config()
    {
        $row=$this->row();
        if(!$row)throw new RuntimeException('An administrator must configure the Document Reminder mailbox before emails can be sent.');
        return ['host'=>$row['smtp_host'],'port'=>(int)$row['smtp_port'],'encryption'=>$row['encryption'],
            'username'=>$row['from_email'],'password'=>$this->decrypt($row['encrypted_password']),
            'from_address'=>$row['from_email'],'from_name'=>$row['from_name'],'to_address'=>$row['to_email']];
    }
    public function saveAndVerify(array $input)
    {
        global $conn;
        $host=trim((string)($input['host']??''));$port=(int)($input['port']??587);
        $encryption=strtolower(trim((string)($input['encryption']??'tls')));
        $from=strtolower(trim((string)($input['from_email']??'')));
        $to=strtolower(trim((string)($input['to_email']??'')));
        $name=trim((string)($input['from_name']??'BeeData Technologies'));
        $password=(string)($input['password']??'');$existing=$this->row();
        if($password===''&&$existing)$password=$this->decrypt($existing['encrypted_password']);
        if(!$host||$port<1||$port>65535||!in_array($encryption,['tls','ssl','none'],true))throw new InvalidArgumentException('Valid SMTP host, port, and encryption are required.');
        if(!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($to,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Valid From and To email addresses are required.');
        if($password==='')throw new InvalidArgumentException('The webmail password is required for initial configuration.');
        if($name==='')$name='BeeData Technologies';
        $config=['host'=>$host,'port'=>$port,'encryption'=>$encryption,'username'=>$from,'password'=>$password,'from_address'=>$from,'from_name'=>$name,'to_address'=>''];
        (new ReminderEmailService($config))->sendHtml($from,$name,'BeeData reminder mailbox verification','<p>The organization Document Reminder mailbox was authenticated successfully.</p>','The organization Document Reminder mailbox was authenticated successfully.');
        $encrypted=$this->encrypt($password);
        $stmt=$conn->prepare("INSERT INTO document_reminder_mail_settings(id,smtp_host,smtp_port,encryption,from_email,encrypted_password,from_name,to_email,verified_at) VALUES(1,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),encryption=VALUES(encryption),from_email=VALUES(from_email),encrypted_password=VALUES(encrypted_password),from_name=VALUES(from_name),to_email=VALUES(to_email),verified_at=NOW()");
        $stmt->bind_param('sisssss',$host,$port,$encryption,$from,$encrypted,$name,$to);$stmt->execute();
        return $this->status();
    }
}