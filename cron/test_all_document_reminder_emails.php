<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../modules/document_reminders/services/ReminderEmailService.php';

$tests = [
    [
        'document_type' => 'H1B',
        'candidate_name' => 'H1B Test Candidate',
        'expiry_date' => date('Y-m-d', strtotime('+6 months')),
        'document_details' => [
            'reminder_reason' => 'H1B visa renewal before expiry',
        ],
    ],
    [
        'document_type' => 'PERM Labor',
        'candidate_name' => 'PERM Test Candidate',
        'expiry_date' => date('Y-m-d', strtotime('+180 days')),
        'document_details' => [
            'reminder_reason' => 'File I-140 within 180 days of PERM certification',
        ],
    ],
    [
        'document_type' => 'I-140',
        'candidate_name' => 'I-140 Test Candidate',
        'expiry_date' => date('Y-m-d', strtotime('+6 months')),
        'document_details' => [
            'reminder_reason' => 'Review pending I-140 petition',
        ],
    ],
];

$mailer = new ReminderEmailService();
$failed = 0;

foreach ($tests as $test) {
    try {
        $mailer->send($test);
        echo "{$test['document_type']} test reminder sent successfully.\n";
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "{$test['document_type']} test failed: {$exception->getMessage()}\n");
    }
}

exit($failed === 0 ? 0 : 1);
