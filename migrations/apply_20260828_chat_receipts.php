<?php
require_once __DIR__ . '/../config/db.php';
$queries = [
    'ALTER TABLE chat_members ADD COLUMN IF NOT EXISTS last_delivered_message_id BIGINT UNSIGNED NULL AFTER joined_at',
    'ALTER TABLE chat_members ADD COLUMN IF NOT EXISTS last_delivered_at DATETIME NULL AFTER last_delivered_message_id',
];
foreach ($queries as $query) {
    if (!$conn->query($query)) {
        throw new RuntimeException($conn->error);
    }
}
echo "Chat delivery columns ready.\n";