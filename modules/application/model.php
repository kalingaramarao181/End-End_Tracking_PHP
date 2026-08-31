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

    public function candidateExists($candidateId)
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM candidate WHERE id = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $candidateId = (int)$candidateId;
        $stmt->bind_param("i", $candidateId);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
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
                SUM((SELECT COUNT(*) FROM application_process_history h WHERE h.application_id=a.id AND h.event_type='interview')) AS interviews,
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

        $newYorkNow = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
        $dateCreated = !empty($data['date_created'])
            ? $data['date_created']
            : $newYorkNow->format('Y-m-d H:i:s');
        $employeeId = (int)$data['employee_id'];

        $candidateId = !empty($data['candidate_id'])
            ? (int)$data['candidate_id']
            : null;

        // New linked records store the candidate relationship by ID only.
        // candidate_name remains nullable so pre-existing name-only rows still display.
        $candidateName = $candidateId === null
            ? ($data['candidate_name'] ?? null)
            : null;
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

        $application = $result->fetch_assoc();
        $historyStmt = $this->conn->prepare("SELECT h.id,h.event_type,h.round_number,h.interview_slot,h.feedback,h.created_by,h.created_at,u.nick_name created_by_name FROM application_process_history h LEFT JOIN users u ON u.id=h.created_by WHERE h.application_id=? ORDER BY h.created_at,h.id");
        $history = [];
        if ($historyStmt) {
            $historyStmt->bind_param('i', $id);
            $historyStmt->execute();
            $history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $historyStmt->close();
        }
        $application['process_history'] = $history;
        return [
            'success' => true,
            'data' => $application
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

        $newYorkNow = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
        $dateCreated = !empty($data['date_created'])
            ? $data['date_created']
            : $newYorkNow->format('Y-m-d H:i:s');
        $employeeId = (int) $data['employee_id'];

        $candidateId = isset($data['candidate_id']) && $data['candidate_id'] !== ''
            ? (int) $data['candidate_id']
            : null;

        // Clear the legacy snapshot once an application is linked to a candidate.
        $candidateName = $candidateId === null
            ? ($data['candidate_name'] ?? null)
            : null;
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


    public function getPerformanceDashboard(array $query = [], array $user = [])
    {
        $applicationPage = max(1, (int)($query['application_page'] ?? 1));
        $applicationLimit = min(100, max(5, (int)($query['application_limit'] ?? 10)));
        $applicationOffset = ($applicationPage - 1) * $applicationLimit;
        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $activityDate = "CASE WHEN a.process_id = 2 THEN COALESCE(a.interview_updated_at, a.date_created)
            WHEN a.process_id = 3 THEN COALESCE(a.placement_updated_at, a.date_created)
            ELSE a.date_created END";
        $conditions = ['u.position_id = 3'];
        $params = [];
        $types = '';

        // Respect the configured Bench Sales data scope. Users with ALL can
        // review the complete team analytics; every other scope remains user-specific.
        $dataScope = function_exists('permissionScope')
            ? permissionScope('bench_sales')
            : 'OWN';
        if ($dataScope !== 'ALL') {
            $conditions[] = 'a.employee_id = ?';
            $params[] = (int)($user['id'] ?? 0);
            $types .= 'i';
        }

        foreach (['start_date' => '>=', 'end_date' => '<='] as $field => $operator) {
            $value = trim((string)($query[$field] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $dateValue = $this->conn->real_escape_string($value);
                $activityCondition = $operator === '>='
                    ? "$activityDate >= '$dateValue'"
                    : "$activityDate < DATE_ADD('$dateValue', INTERVAL 1 DAY)";
                $historyCondition = $operator === '>='
                    ? "h.created_at >= '$dateValue'"
                    : "h.created_at < DATE_ADD('$dateValue', INTERVAL 1 DAY)";
                $conditions[] = "($activityCondition OR EXISTS (SELECT 1 FROM application_process_history h WHERE h.application_id=a.id AND h.event_type='interview' AND $historyCondition))";
                $interviewHistoryDateConditions[] = $historyCondition;
            }
        }

        $interviewHistoryDateSql = $interviewHistoryDateConditions
            ? ' AND ' . implode(' AND ', $interviewHistoryDateConditions)
            : '';
        $interviewCount = "(SELECT COUNT(*) FROM application_process_history h WHERE h.application_id=a.id AND h.event_type='interview'$interviewHistoryDateSql)";

        $search = trim((string)($query['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = "($candidateName LIKE ? OR u.nick_name LIKE ? OR a.role LIKE ?
                OR a.vendor LIKE ? OR a.client LIKE ? OR a.poc LIKE ?)";
            for ($index = 0; $index < 6; $index++) {
                $params[] = '%' . $search . '%';
                $types .= 's';
            }
        }

        if (!empty($query['employee_id'])) {
            $conditions[] = 'a.employee_id = ?';
            $params[] = (int)$query['employee_id'];
            $types .= 'i';
        }
        if (!empty($query['candidate_id'])) {
            $conditions[] = 'a.candidate_id = ?';
            $params[] = (int)$query['candidate_id'];
            $types .= 'i';
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $from = " FROM application a
            LEFT JOIN candidate c ON c.id = a.candidate_id
            INNER JOIN users u ON u.id = a.employee_id";

        $summarySql = "SELECT COUNT(a.id) total_submissions,
                SUM($interviewCount) interviews,
                SUM(a.process_id = 3) placements,
                COUNT(DISTINCT a.employee_id) active_employees,
                COUNT(DISTINCT COALESCE(a.candidate_id, CONCAT('legacy-', $candidateName))) unique_candidates
            $from $where";
        $summaryStmt = $this->executePerformanceQuery($summarySql, $types, $params);
        if (!$summaryStmt) {
            return ['success' => false, 'message' => 'Unable to load performance summary.'];
        }
        $summary = $summaryStmt->get_result()->fetch_assoc();
        $summaryStmt->close();
        foreach ($summary as $key => $value) {
            $summary[$key] = (int)$value;
        }

        $employeeSql = "SELECT a.employee_id AS user_id, u.nick_name AS employee_name,
                COUNT(a.id) submissions, SUM($interviewCount) interviews,
                SUM(a.process_id = 3) placements,
                ROUND((SUM(a.process_id = 3) / NULLIF(COUNT(a.id), 0)) * 100, 1) placement_rate
            $from $where
            GROUP BY a.employee_id, u.nick_name
            ORDER BY submissions DESC, placements DESC, employee_name";
        $employeeStmt = $this->executePerformanceQuery($employeeSql, $types, $params);
        if (!$employeeStmt) {
            return ['success' => false, 'message' => 'Unable to load employee performance.'];
        }
        $employeeRows = $this->fetchPerformanceRows($employeeStmt);

        $candidateSql = "SELECT a.candidate_id, $candidateName candidate_name,
                COALESCE(NULLIF(c.skills, ''), a.role) technology,
                c.email, c.phone, c.visa_status, c.current_location,
                COUNT(a.id) submissions, SUM($interviewCount) interviews,
                SUM(a.process_id = 3) placements, MAX($activityDate) last_submission
            $from $where
            GROUP BY a.candidate_id, candidate_name, technology, c.email, c.phone,
                c.visa_status, c.current_location
            ORDER BY submissions DESC, last_submission DESC LIMIT 50";
        $candidateStmt = $this->executePerformanceQuery($candidateSql, $types, $params);
        if (!$candidateStmt) {
            return ['success' => false, 'message' => 'Unable to load candidate performance.'];
        }
        $candidateRows = $this->fetchPerformanceRows($candidateStmt);

        $trendSql = "SELECT DATE($activityDate) submission_date, COUNT(a.id) submissions,
                SUM($interviewCount) interviews, SUM(a.process_id = 3) placements
            $from $where GROUP BY DATE($activityDate) ORDER BY submission_date";
        $trendStmt = $this->executePerformanceQuery($trendSql, $types, $params);
        if (!$trendStmt) {
            return ['success' => false, 'message' => 'Unable to load performance trend.'];
        }
        $trendRows = $this->fetchPerformanceRows($trendStmt);

        $applicationSql = "SELECT a.id, a.candidate_id, a.employee_id,
                $candidateName candidate_name, u.nick_name employee_name,
                a.date_created, $activityDate AS activity_date,
                a.interview_updated_at, a.placement_updated_at,
                a.role, a.vendor, a.poc, a.client, a.rate,
                a.candidate_loc, a.feedback, a.process_id,
                CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview'
                    WHEN 3 THEN 'Placed' ELSE 'Unknown' END status
            $from $where ORDER BY activity_date DESC, a.id DESC LIMIT ? OFFSET ?";
        $applicationParams = $params;
        $applicationParams[] = $applicationLimit;
        $applicationParams[] = $applicationOffset;
        $applicationStmt = $this->executePerformanceQuery($applicationSql, $types . 'ii', $applicationParams);

        if (!$applicationStmt) {
            return ['success' => false, 'message' => 'Unable to load submission details.'];
        }
        $applicationRows = $this->fetchPerformanceRows($applicationStmt);

        return [
            'success' => true,
            'data' => [
                'summary' => $summary,
                'employees' => $employeeRows,
                'candidates' => $candidateRows,
                'trend' => $trendRows,
                'applications' => $applicationRows,
                'applications_pagination' => [
                    'page' => $applicationPage,
                    'limit' => $applicationLimit,
                    'total' => $summary['total_submissions'],
                    'total_pages' => max(1, (int)ceil($summary['total_submissions'] / $applicationLimit))
                ],
                'filters' => [
                    'start_date' => $query['start_date'] ?? null,
                    'end_date' => $query['end_date'] ?? null,
                    'search' => $search
                ]
            ]
        ];
    }

    private function executePerformanceQuery($sql, $types, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('Performance query prepare failed: ' . $this->conn->error);
            return null;
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            error_log('Performance query failed: ' . $stmt->error);
            return null;
        }
        return $stmt;
    }

    private function fetchPerformanceRows($stmt)
    {
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            foreach ([
                'id', 'candidate_id', 'employee_id', 'user_id', 'process_id',
                'submissions', 'interviews', 'placements'
            ] as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null) {
                    $row[$field] = (int)$row[$field];
                }
            }
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }



    /*
|--------------------------------------------------------------------------
| UPDATE APPLICATION PROCESS
|--------------------------------------------------------------------------
*/

public function updateProcess($id, $processId, $interviewSlot = null, $feedback = null, $createdBy = null)
{
    $this->conn->begin_transaction();
    try {
        $lock = $this->conn->prepare('SELECT id FROM application WHERE id=? FOR UPDATE');
        $lock->bind_param('i', $id);
        $lock->execute();
        if (!$lock->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Application not found.');
        }

        $roundNumber = null;
        if ($processId === 2) {
            $roundQuery = $this->conn->prepare("SELECT COALESCE(MAX(round_number),0)+1 next_round FROM application_process_history WHERE application_id=? AND event_type='interview'");
            $roundQuery->bind_param('i', $id);
            $roundQuery->execute();
            $roundNumber = (int)$roundQuery->get_result()->fetch_assoc()['next_round'];
            $eventType = 'interview';
            $history = $this->conn->prepare('INSERT INTO application_process_history(application_id,event_type,round_number,interview_slot,feedback,created_by) VALUES(?,?,?,?,?,?)');
            $history->bind_param('isissi', $id, $eventType, $roundNumber, $interviewSlot, $feedback, $createdBy);
            $history->execute();
        } elseif ($processId === 3) {
            $eventType = 'placed';
            $placementRound = 0;
            $history = $this->conn->prepare('INSERT INTO application_process_history(application_id,event_type,round_number,feedback,created_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE feedback=VALUES(feedback),created_by=VALUES(created_by),created_at=NOW()');
            $history->bind_param('isisi', $id, $eventType, $placementRound, $feedback, $createdBy);
            $history->execute();
        }

        $stmt = $this->conn->prepare("UPDATE application SET process_id=?,interview_slot=COALESCE(?,interview_slot),feedback=COALESCE(?,feedback),interview_updated_at=CASE WHEN ?=2 THEN NOW() ELSE interview_updated_at END,placement_updated_at=CASE WHEN ?=3 THEN NOW() ELSE placement_updated_at END,date=NOW() WHERE id=?");
        $stmt->bind_param('issiii', $processId, $interviewSlot, $feedback, $processId, $processId, $id);
        $stmt->execute();
        $this->conn->commit();
        return ['success'=>true,'message'=>$processId===2?"Interview round {$roundNumber} scheduled successfully.":'Candidate marked as placed successfully.','process_id'=>$processId,'round_number'=>$roundNumber];
    } catch (Throwable $error) {
        $this->conn->rollback();
        return ['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Failed to update application process.','error'=>$error->getMessage()];
    }
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
