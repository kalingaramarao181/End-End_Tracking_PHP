<?php
require_once __DIR__ . '/../config/db.php';

function getEmployeePosition($employee_id) {
    global $conn;

    $employee_id = (int)$employee_id;

    $query = "SELECT position_id FROM employee WHERE id = $employee_id LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        return (int)$row['position_id'];
    }

    return null;
}
?>