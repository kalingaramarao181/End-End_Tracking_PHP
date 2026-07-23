<?php
require_once __DIR__ . "/../../config/db.php";

function getEmployeeByUsername($username) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM employee WHERE email = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function fetchEmployees($filters) {
    global $conn;

    $sql = "SELECT e.*, p.name AS role
            FROM employee e
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE 1";

    $params = [];
    $types = "";

    // 🔍 Search
    if (!empty($filters['search'])) {
        $sql .= " AND (e.name LIKE ? OR e.email LIKE ?)";
        $params[] = "%" . $filters['search'] . "%";
        $params[] = "%" . $filters['search'] . "%";
        $types .= "ss";
    }

    // 🎯 Filter by role (position)
    if (!empty($filters['position_id'])) {
        $sql .= " AND e.position_id = ?";
        $params[] = $filters['position_id'];
        $types .= "i";
    }

    // 📄 Pagination
    $sql .= " ORDER BY e.id DESC LIMIT ? OFFSET ?";
    $params[] = $filters['limit'];
    $params[] = $filters['offset'];
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    return $data;
}