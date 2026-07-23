<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../modules/document_reminders/model.php';
require_once __DIR__ . '/../modules/document_reminders/services/ReminderEmailService.php';

$model=new DocumentReminderModel(); $mailer=new ReminderEmailService(); $model->expirePastReminders();
$sent=0; $failed=0;
foreach($model->dueReminders() as $reminder){
    if($reminder['expiry_date']<=date('Y-m-d')) continue;
    try{$mailer->send($reminder);$model->markSent($reminder['id'],$reminder['next_reminder_date']);$sent++;echo "Sent reminder {$reminder['id']}\n";}
    catch(Throwable $e){$failed++;fwrite(STDERR,"Reminder {$reminder['id']} failed: {$e->getMessage()}\n");}
}
echo "Complete. Sent: {$sent}; Failed: {$failed}\n"; exit($failed?1:0);
