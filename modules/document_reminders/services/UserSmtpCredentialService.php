<?php
require_once __DIR__.'/ReminderEmailService.php';
class UserSmtpCredentialService {
 private function key(){
  $secret=(string)getenv('MAIL_CREDENTIAL_KEY');
  $localKey=__DIR__.'/../../../config/mail-credential-key.php';
  if(strlen($secret)<32&&is_file($localKey))$secret=(string)require $localKey;
  if(strlen($secret)<32) throw new RuntimeException('MAIL_CREDENTIAL_KEY must be configured with at least 32 random characters.');
  return hash('sha256',$secret,true);
 }
 private function encrypt($plain){
  $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag);
  if($cipher===false)throw new RuntimeException('Unable to encrypt SMTP credentials.');
  return base64_encode($iv.$tag.$cipher);
 }
 private function decrypt($encoded){
  $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Stored SMTP credentials are invalid.');
  $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
  if($plain===false)throw new RuntimeException('Unable to decrypt SMTP credentials. Check MAIL_CREDENTIAL_KEY.');
  return $plain;
 }
 public function status($userId){
  global $conn;$s=$conn->prepare('SELECT smtp_host,smtp_port,encryption,smtp_username,from_name,verified_at,updated_at FROM user_smtp_credentials WHERE user_id=?');$s->bind_param('i',$userId);$s->execute();$row=$s->get_result()->fetch_assoc();
  return ['configured'=>(bool)$row,'data'=>$row?:null];
 }
 public function config($userId){
  global $conn;$s=$conn->prepare('SELECT * FROM user_smtp_credentials WHERE user_id=?');$s->bind_param('i',$userId);$s->execute();$row=$s->get_result()->fetch_assoc();
  if(!$row)throw new RuntimeException('Configure and verify your sender mailbox before sending payslips.');
  return ['host'=>$row['smtp_host'],'port'=>(int)$row['smtp_port'],'encryption'=>$row['encryption'],'username'=>$row['smtp_username'],'password'=>$this->decrypt($row['encrypted_password']),'from_address'=>$row['smtp_username'],'from_name'=>$row['from_name'],'to_address'=>''];
 }
 public function saveAndVerify(array $user,array $input){
  global $conn;$email=strtolower(trim((string)$user['email']));$username=strtolower(trim((string)($input['email']??'')));
  $isSuperAdmin=(int)($user['position_id']??0)===1||!empty($user['super_admin']);if(!filter_var($username,FILTER_VALIDATE_EMAIL)||(!$isSuperAdmin&&$username!==$email))throw new DomainException($isSuperAdmin?'Enter a valid sender email.':'Sender email must exactly match the logged-in user email: '.$email);
  $host=trim((string)($input['host']??''));$port=(int)($input['port']??587);$encryption=strtolower(trim((string)($input['encryption']??'tls')));$password=(string)($input['password']??'');$name=trim((string)($input['from_name']??'BeeData Technologies'));
  if(!$host||$port<1||$port>65535||!in_array($encryption,['tls','ssl','none'],true)||$password==='')throw new InvalidArgumentException('Host, valid port, encryption, and webmail password are required.');
  $config=['host'=>$host,'port'=>$port,'encryption'=>$encryption,'username'=>$username,'password'=>$password,'from_address'=>$username,'from_name'=>$name,'to_address'=>''];
  (new ReminderEmailService($config))->sendHtml($username,$user['nick_name']?:$username,'BeeData sender verification','<p>Your BeeData website sender mailbox was authenticated successfully.</p>','Your BeeData sender mailbox was authenticated successfully.');
  $encrypted=$this->encrypt($password);$s=$conn->prepare("INSERT INTO user_smtp_credentials(user_id,smtp_host,smtp_port,encryption,smtp_username,encrypted_password,from_name,verified_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),encryption=VALUES(encryption),smtp_username=VALUES(smtp_username),encrypted_password=VALUES(encrypted_password),from_name=VALUES(from_name),verified_at=NOW()");
  $uid=(int)$user['id'];$s->bind_param('isissss',$uid,$host,$port,$encryption,$username,$encrypted,$name);$s->execute();return $this->status($uid);
 }
 public function remove($userId){global $conn;$s=$conn->prepare('DELETE FROM user_smtp_credentials WHERE user_id=?');$s->bind_param('i',$userId);$s->execute();}
}