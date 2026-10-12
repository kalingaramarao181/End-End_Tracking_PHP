<?php
/** Employment dates are independent of attendance and login activity. */
function employeeHasLeavingDate($database): bool {
    $result = $database->query("SHOW COLUMNS FROM employees LIKE 'date_of_leaving'");
    return $result && $result->num_rows > 0;
}
function employeeValidateLeavingDate($value, $joiningDate): string {
    if (!is_string($value)) throw new InvalidArgumentException('A valid leaving date is required.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Use YYYY-MM-DD for the leaving date.');
    if ($value > date('Y-m-d')) throw new InvalidArgumentException('The leaving date cannot be in the future.');
    if ($joiningDate && $joiningDate !== '0000-00-00' && $value < $joiningDate) throw new InvalidArgumentException('The leaving date cannot be before the joining date.');
    return $value;
}
// Call inside account deletion transaction, before the foreign key unlinks it.
function employeeRecordUserDeparture($database, int $userId): void {
    if (!employeeHasLeavingDate($database)) return;
    $statement = $database->prepare("UPDATE employees e JOIN users u ON u.id=e.user_id
        SET e.date_of_leaving=COALESCE(e.date_of_leaving, CURDATE()),
            e.position_id=COALESCE(u.position_id,e.position_id) WHERE u.id=?");
    $statement->bind_param('i', $userId);
    if (!$statement->execute()) throw new RuntimeException('Unable to record employee departure.');
}
function employeeSetLeavingDate(int $employeeId, $value): array {
    global $conn;
    if (!employeeHasLeavingDate($conn)) throw new RuntimeException('Apply the employee leaving-date migration first.');
    $conn->begin_transaction();
    try {
        $statement=$conn->prepare('SELECT user_id,date_of_joining FROM employees WHERE id=? FOR UPDATE');
        $statement->bind_param('i',$employeeId);$statement->execute();$row=$statement->get_result()->fetch_assoc();
        if (!$row) throw new InvalidArgumentException('Employee not found.');
        if ($row['user_id'] !== null) throw new InvalidArgumentException('Disconnect the employee account before recording a leaving date.');
        $date=employeeValidateLeavingDate($value,$row['date_of_joining']);
        $statement=$conn->prepare('UPDATE employees SET date_of_leaving=? WHERE id=?');
        $statement->bind_param('si',$date,$employeeId);
        if (!$statement->execute()) throw new RuntimeException('Unable to save leaving date.');
        $conn->commit();return ['success'=>true,'message'=>'Leaving date saved.','date_of_leaving'=>$date];
    } catch (Throwable $error) {$conn->rollback();throw $error;}
}