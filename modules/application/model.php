<?php
// File: modules/application/model.php

require_once __DIR__ . '/../../config/db.php';

class ApplicationModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /*
    |-------------------------------------------------------------------------- 
    | GET Submissions on Dashboard
    |--------------------------------------------------------------------------
    */



    public function getSubmissionDashboard(
        $startDate = null,
        $endDate = null,
        $category = null
    ) {
        $filters = $this->buildDashboardFilters(
            'a.date_created',
            $startDate,
            $endDate,
            $category
        );

        $where = "";

        if ($filters['conditions']) {
            $where = 'WHERE ' . implode(' AND ', $filters['conditions']);
        }

        $sql = "
        SELECT
            u.id AS user_id,
            u.nick_name,
            COUNT(a.id) AS total_submissions
        FROM application a
        LEFT JOIN users u
            ON a.employee_id = u.id
        LEFT JOIN positions p
            ON u.position_id = p.id
        $where
        GROUP BY u.id, u.nick_name
        ORDER BY total_submissions DESC
    ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => $this->conn->error
            ];
        }

        if ($filters['params']) {
            $stmt->bind_param($filters['types'], ...$filters['params']);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        $grandTotal = 0;

        while ($row = $result->fetch_assoc()) {

            $grandTotal += (int) $row['total_submissions'];

            $data[] = [
                "user_id" => (int) $row['user_id'],
                "label" => $row['nick_name'] ?? 'Unknown',
                "value" => (int) $row['total_submissions']
            ];
        }

        return [
            "success" => true,
            "title" => "Submissions Overview",
            "total" => $grandTotal,
            "data" => $data
        ];
    }



    public function getDashboardSummary(
        $startDate = null,
        $endDate = null,
        $category = null
    ) {
        $applicationFilters = $this->buildDashboardFilters(
            'a.date_created',
            $startDate,
            $endDate,
            $category
        );

        $applicationWhere = $applicationFilters['conditions']
            ? 'WHERE ' . implode(' AND ', $applicationFilters['conditions'])
            : '';

        $sql = "
            SELECT
                COUNT(a.id) AS submissions,
                COUNT(CASE WHEN a.process_id = 2 THEN 1 END) AS interviews,
                COUNT(CASE WHEN a.process_id = 3 THEN 1 END) AS placements
            FROM application a
            LEFT JOIN users u
                ON a.employee_id = u.id
            LEFT JOIN positions p
                ON u.position_id = p.id
            $applicationWhere
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare dashboard summary query.',
                'error' => $this->conn->error
            ];
        }

        if ($applicationFilters['params']) {
            $stmt->bind_param(
                $applicationFilters['types'],
                ...$applicationFilters['params']
            );
        }

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to load dashboard summary.',
                'error' => $stmt->error
            ];
        }

        $summary = $stmt->get_result()->fetch_assoc();

        $candidateFilters = $this->buildDashboardFilters(
            'c.created_at',
            $startDate,
            $endDate,
            $category
        );
        array_unshift($candidateFilters['conditions'], "c.status = 'Active'");
        $candidateWhere = 'WHERE ' . implode(' AND ', $candidateFilters['conditions']);

        $candidateSql = "
            SELECT COUNT(*) AS active_candidates
            FROM candidate c
            LEFT JOIN users u
                ON c.created_by = u.id
            LEFT JOIN positions p
                ON u.position_id = p.id
            $candidateWhere
        ";

        $candidateStmt = $this->conn->prepare($candidateSql);

        if (!$candidateStmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare active candidates query.',
                'error' => $this->conn->error
            ];
        }

        if ($candidateFilters['params']) {
            $candidateStmt->bind_param(
                $candidateFilters['types'],
                ...$candidateFilters['params']
            );
        }

        if (!$candidateStmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to load active candidates.',
                'error' => $candidateStmt->error
            ];
        }

        $candidateSummary = $candidateStmt->get_result()->fetch_assoc();

        return [
            'success' => true,
            'data' => [
                'submissions' => (int)$summary['submissions'],
                'interviews' => (int)$summary['interviews'],
                'placements' => (int)$summary['placements'],
                'active_candidates' => (int)$candidateSummary['active_candidates']
            ]
        ];
    }

    private function buildDashboardFilters(
        $dateColumn,
        $startDate,
        $endDate,
        $category
    ) {
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($startDate)) {
            $conditions[] = "DATE($dateColumn) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }

        if (!empty($endDate)) {
            $conditions[] = "DATE($dateColumn) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }

        $category = strtolower(trim($category ?? ''));

        if ($category === 'recruiters') {
            $conditions[] = "LOWER(p.position_name) LIKE '%recruiter%'";
        } elseif ($category === 'benchsales') {
            $conditions[] = "LOWER(p.position_name) LIKE '%bench%'";
        }

        return [
            'conditions' => $conditions,
            'params' => $params,
            'types' => $types
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Create Application
    |--------------------------------------------------------------------------
    | Notes:
    | - employee_id is users.id
    | - candidate_id is used for new records
    | - candidate_name is optional (legacy support)
    */
    public function createApplication($data)
    {
        $sql = "
        INSERT INTO application (
            date_created,
            employee_id,
            candidate_id,
            candidate_name,
            vendor,
            poc,
            feedback,
            client,
            emp_loc,
            rate,
            role,
            candidate_loc,
            remarks,
            resume_path,
            r2r_path,
            driving_path,
            visa_path,
            msc_path
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $dateCreated = $data['date_created'] ?? date('Y-m-d');
        $employeeId = (int)$data['employee_id'];

        $candidateId = !empty($data['candidate_id'])
            ? (int)$data['candidate_id']
            : null;

        $candidateName = $data['candidate_name'] ?? null;
        $vendor = $data['vendor'] ?? null;
        $poc = $data['poc'] ?? null;
        $feedback = $data['feedback'] ?? null;
        $client = $data['client'] ?? null;
        $empLoc = $data['emp_loc'] ?? null;
        $rate = $data['rate'] ?? null;
        $role = $data['role'] ?? null;
        $candidateLoc = $data['candidate_loc'] ?? null;
        $remarks = $data['remarks'] ?? null;

        $resumePath = $data['resume_path'] ?? null;
        $r2rPath = $data['r2r_path'] ?? null;
        $drivingPath = $data['driving_path'] ?? null;
        $visaPath = $data['visa_path'] ?? null;
        $mscPath = $data['msc_path'] ?? null;

        $stmt->bind_param(
            "siisssssssssssssss",
            $dateCreated,
            $employeeId,
            $candidateId,
            $candidateName,
            $vendor,
            $poc,
            $feedback,
            $client,
            $empLoc,
            $rate,
            $role,
            $candidateLoc,
            $remarks,
            $resumePath,
            $r2rPath,
            $drivingPath,
            $visaPath,
            $mscPath
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to create application.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Application created successfully.',
            'application_id' => $this->conn->insert_id
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Get Application By ID
    |--------------------------------------------------------------------------
    */
    public function getApplicationById($id)
    {
        $sql = "
            SELECT
                a.*,
                COALESCE(c.name, a.candidate_name) AS candidate_name,
                u.nick_name AS employee_name,
                u.position_id,
                p.position_name
            FROM application a
            LEFT JOIN candidate c
                ON a.candidate_id = c.id
            LEFT JOIN users u
                ON a.employee_id = u.id
            LEFT JOIN positions p
                ON u.position_id = p.id
            WHERE a.id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Application not found.'
            ];
        }

        return [
            'success' => true,
            'data' => $result->fetch_assoc()
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Get Applications
    |--------------------------------------------------------------------------
    | Filters:
    | - employeeId => show only applications created by a specific user
    | - positionId => show applications created by users in a specific position
    |
    | Admin:
    |   employeeId = null
    |   positionId = optional
    |
    | Recruiter / Bench Sales:
    |   employeeId = logged-in user id
    |   positionId = null
    */
    public function getApplications(
        $page,
        $limit,
        $search,

        $employeeId = null,
        $positionId = null
    ) {
        $page = max(1, (int) $page);
        $limit = max(1, (int) $limit);
        $offset = ($page - 1) * $limit;
        $search = trim($search ?? '');

        $conditions = [];
        $params = [];
        $types = '';

        /*
        |--------------------------------------------------------------------------
        | Search Filter
        |--------------------------------------------------------------------------
        */
        if ($search !== '') {
            $conditions[] = "(
        COALESCE(c.name, a.candidate_name) LIKE ?
        OR a.client LIKE ?
        OR a.vendor LIKE ?
        OR a.role LIKE ?
        OR a.feedback LIKE ?
        OR a.poc LIKE ?
    )";

            $searchValue = '%' . $search . '%';

            for ($i = 0; $i < 6; $i++) {
                $params[] = $searchValue;
                $types .= 's';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by Logged-In User
        |--------------------------------------------------------------------------
        */
        if ($employeeId !== null) {
            $conditions[] = "a.employee_id = ?";
            $params[] = (int) $employeeId;
            $types .= 'i';
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by Position
        |--------------------------------------------------------------------------
        */
        if ($positionId !== null) {
            $conditions[] = "u.position_id = ?";
            $params[] = (int) $positionId;
            $types .= 'i';
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        /*
        |--------------------------------------------------------------------------
        | Count Query
        |--------------------------------------------------------------------------
        */
        $countSql = "
            SELECT COUNT(*) AS total
            FROM application a
            LEFT JOIN candidate c
                ON a.candidate_id = c.id
            LEFT JOIN users u
                ON a.employee_id = u.id
            $where
        ";

        $stmt = $this->conn->prepare($countSql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare count query.',
                'error' => $this->conn->error
            ];
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $countResult = $stmt->get_result();
        $totalRecords = (int) $countResult->fetch_assoc()['total'];

        /*
        |--------------------------------------------------------------------------
        | Data Query
        |--------------------------------------------------------------------------
        */
        $dataSql = "
            SELECT
                a.*,
                COALESCE(c.name, a.candidate_name) AS candidate_name,
                u.nick_name AS employee_name,
                u.position_id,
                p.position_name
            FROM application a
            LEFT JOIN candidate c
                ON a.candidate_id = c.id
            LEFT JOIN users u
                ON a.employee_id = u.id
            LEFT JOIN positions p
                ON u.position_id = p.id
            $where
            ORDER BY a.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($dataSql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare data query.',
                'error' => $this->conn->error
            ];
        }

        $dataParams = $params;
        $dataTypes = $types . 'ii';

        $dataParams[] = $limit;
        $dataParams[] = $offset;

        $stmt->bind_param($dataTypes, ...$dataParams);

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return [
            'success' => true,
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'employee_id_filter' => $employeeId,
            'position_id_filter' => $positionId,
            'total_records' => $totalRecords,
            'total_pages' => (int) ceil($totalRecords / $limit),
            'data' => $rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Update Application
    |--------------------------------------------------------------------------
    */
    public function updateApplication($id, $data)
    {
        $sql = "
            UPDATE application
            SET
                date_created = ?,
                employee_id = ?,
                candidate_id = ?,
                candidate_name = ?,
                vendor = ?,
                poc = ?,
                feedback = ?,
                client = ?,
                emp_loc = ?,
                rate = ?,
                role = ?,
                candidate_loc = ?,
                remarks = ?,
                resume_path = ?,
                r2r_path = ?,
                driving_path = ?,
                visa_path = ?,
                msc_path = ?
            WHERE id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $dateCreated = $data['date_created'] ?? date('Y-m-d');
        $employeeId = (int) $data['employee_id'];

        $candidateId = isset($data['candidate_id']) && $data['candidate_id'] !== ''
            ? (int) $data['candidate_id']
            : null;

        $candidateName = $data['candidate_name'] ?? null;
        $vendor = $data['vendor'] ?? null;
        $poc = $data['poc'] ?? null;
        $feedback = $data['feedback'] ?? null;
        $client = $data['client'] ?? null;
        $empLoc = $data['emp_loc'] ?? null;
        $rate = $data['rate'] ?? null;
        $role = $data['role'] ?? null;
        $candidateLoc = $data['candidate_loc'] ?? null;
        $remarks = $data['remarks'] ?? null;
        $resumePath = $data['resume_path'] ?? null;
        $r2rPath = $data['r2r_path'] ?? null;
        $drivingPath = $data['driving_path'] ?? null;
        $visaPath = $data['visa_path'] ?? null;
        $mscPath = $data['msc_path'] ?? null;

        $stmt->bind_param(
            "siisssssssssssssssi",
            $dateCreated,
            $employeeId,
            $candidateId,
            $candidateName,
            $vendor,
            $poc,
            $feedback,
            $client,
            $empLoc,
            $rate,
            $role,
            $candidateLoc,
            $remarks,
            $resumePath,
            $r2rPath,
            $drivingPath,
            $visaPath,
            $mscPath,
            $id
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to update application.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Application updated successfully.',
            'affected_rows' => $stmt->affected_rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Application
    |--------------------------------------------------------------------------
    */
    public function deleteApplication($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM application
            WHERE id = ?
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to delete application.',
                'error' => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                'success' => false,
                'message' => 'Application not found.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Application deleted successfully.'
        ];
    }


    public function getPerformanceDashboard()
{
    $sql = "
    SELECT
        u.id AS user_id,
        u.nick_name AS employee_name,

        /* Today's Submissions (process_id = 1) */
        SUM(
            CASE
                WHEN a.process_id = 1
                AND DATE(a.date_created) =
                    DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', 'America/New_York'))
                THEN 1
                ELSE 0
            END
        ) AS today_submissions,

        /* Weekly Submissions (process_id = 1) */
        SUM(
            CASE
                WHEN a.process_id = 1
                AND YEARWEEK(a.date_created, 1) =
                    YEARWEEK(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', 'America/New_York'), 1)
                THEN 1
                ELSE 0
            END
        ) AS weekly_submissions,

        /* Total Interviews (process_id = 2) */
        SUM(
            CASE
                WHEN a.process_id = 2
                THEN 1
                ELSE 0
            END
        ) AS interviews,

        /* Total Placements (process_id = 3) */
        SUM(
            CASE
                WHEN a.process_id = 3
                THEN 1
                ELSE 0
            END
        ) AS placements

    FROM users u

    INNER JOIN application a
        ON a.employee_id = u.id

    WHERE u.status = 'active'

    GROUP BY
        u.id,
        u.nick_name

    HAVING
        today_submissions > 0
        OR weekly_submissions > 0
        OR interviews > 0
        OR placements > 0

    ORDER BY
        weekly_submissions DESC,
        today_submissions DESC,
        interviews DESC,
        placements DESC,
        employee_name ASC
    ";

    $result = $this->conn->query($sql);

    if (!$result) {
        return [
            "success" => false,
            "message" => $this->conn->error
        ];
    }

    $data = [];

    while ($row = $result->fetch_assoc()) {

        $data[] = [
            "user_id" => (int)$row["user_id"],
            "employee_name" => $row["employee_name"],

            "today_submissions" => (int)$row["today_submissions"],
            "weekly_submissions" => (int)$row["weekly_submissions"],

            "interviews" => (int)$row["interviews"],
            "placements" => (int)$row["placements"]
        ];
    }

    return [
        "success" => true,
        "data" => $data
    ];
}



    /*
|--------------------------------------------------------------------------
| UPDATE APPLICATION PROCESS
|--------------------------------------------------------------------------
*/

public function updateProcess($id, $processId)
{
    $stmt = $this->conn->prepare("
        UPDATE application
        SET
            process_id = ?,
            date = CONVERT_TZ(NOW(), '+00:00', '-04:00')
        WHERE id = ?
    ");

    if (!$stmt) {
        return [
            "success" => false,
            "message" => "Prepare failed.",
            "error" => $this->conn->error
        ];
    }

    $stmt->bind_param("ii", $processId, $id);

    if (!$stmt->execute()) {
        return [
            "success" => false,
            "message" => "Failed to update process.",
            "error" => $stmt->error
        ];
    }

    if ($stmt->affected_rows === 0) {
        return [
            "success" => false,
            "message" => "Application not found."
        ];
    }

    return [
        "success" => true,
        "message" => "Application process updated successfully.",
        "process_id" => $processId
    ];
}

/*
|--------------------------------------------------------------------------
| RECENT ACTIVITIES
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| RECENT ACTIVITIES
|--------------------------------------------------------------------------
*/

public function getRecentActivities()
{
    $sql = "
    SELECT
        u.id AS employee_id,
        u.nick_name,
        a.process_id,

        COUNT(*) AS total,

        GROUP_CONCAT(
            DISTINCT a.candidate_name
            ORDER BY a.candidate_name
            SEPARATOR ', '
        ) AS candidate_names,

        MAX(a.date) AS activity_date

    FROM application a

    INNER JOIN users u
        ON u.id = a.employee_id

    WHERE
        a.process_id IN (2,3)
        AND a.date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)

    GROUP BY
        u.id,
        a.process_id

    ORDER BY
        activity_date DESC

    LIMIT 20
    ";

    $result = $this->conn->query($sql);

    if (!$result) {
        return [
            "success" => false,
            "message" => $this->conn->error
        ];
    }

    $activities = [];

    while ($row = $result->fetch_assoc()) {

        $candidateNames = $row["candidate_names"];

        if ($row["process_id"] == 2) {

            $message =
                $row["nick_name"] .
                " scheduled " .
                $row["total"] .
                " interview" .
                ($row["total"] > 1 ? "s" : "") .
                " for " .
                $candidateNames .
                ".";

            $type = "interview";

        } else {

            $message =
                $row["nick_name"] .
                " placed " .
                $row["total"] .
                " candidate" .
                ($row["total"] > 1 ? "s" : "") .
                " (" .
                $candidateNames .
                ").";

            $type = "placement";
        }

        $activities[] = [
            "employee_id" => (int)$row["employee_id"],
            "employee_name" => $row["nick_name"],
            "type" => $type,
            "message" => $message,
            "count" => (int)$row["total"],
            "candidate_names" => explode(", ", $candidateNames),
            "date" => $row["activity_date"]
        ];
    }

    return [
        "success" => true,
        "data" => $activities
    ];
}



}
