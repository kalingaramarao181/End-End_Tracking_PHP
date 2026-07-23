<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../modules/document_reminders/services/ReminderEmailService.php';

$recipient = trim($argv[1] ?? '');
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php cron/test_reminder_email.php recipient@example.com\n");
    exit(1);
}

try {
    (new ReminderEmailService())->send([
        'candidate_email' => $recipient,
        'candidate_name' => 'SMTP Test Candidate',
        'expiry_date' => date('Y-m-d', strtotime('+6 months')),
    ]);
    echo "SMTP test reminder sent successfully to {$recipient}.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "SMTP test failed: {$exception->getMessage()}\n");
    exit(1);
}
