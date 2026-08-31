<?php
require_once __DIR__ . '/../config/db.php';
$sql = file_get_contents(__DIR__ . '/20260829_application_process_history.sql');
if (!$conn->multi_query($sql)) {
    throw new RuntimeException($conn->error);
}
do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());
if ($conn->errno) {
    throw new RuntimeException($conn->error);
}
echo "Application process history ready.\n";