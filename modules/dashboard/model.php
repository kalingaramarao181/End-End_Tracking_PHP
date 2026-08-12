<?php

require_once __DIR__ . '/../../config/db.php';

class DashboardModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    public function getExecutiveSummary(array $user)
    {
        $isAdmin = in_array((int)$user['position_id'], [1, 2], true);
        $metrics = [
            'users' => "SELECT COUNT(*) value FROM users",
            'active_users' => "SELECT COUNT(*) value FROM users WHERE status='Active'",
            'employees' => "SELECT COUNT(*) value FROM employees WHERE user_id IS NOT NULL",
            'former_employees' => "SELECT COUNT(*) value FROM employees WHERE user_id IS NULL",
            'candidates' => "SELECT COUNT(*) value FROM candidate",
            'active_candidates' => "SELECT COUNT(*) value FROM candidate WHERE status='Active'",
            'open_jobs' => "SELECT COUNT(*) value FROM jobs WHERE status='Open'",
            'closed_jobs' => "SELECT COUNT(*) value FROM jobs WHERE status<>'Open'",
            'submissions_today' => "SELECT COUNT(*) value FROM application WHERE DATE(date_created)=CURDATE()",
            'interviews_today' => "SELECT COUNT(*) value FROM application WHERE process_id=2 AND DATE(COALESCE(interview_updated_at,date_created))=CURDATE()",
            'placements' => "SELECT COUNT(*) value FROM application WHERE process_id=3",
            'attendance_today' => "SELECT COUNT(DISTINCT a.employee_id) value FROM attendance a INNER JOIN employees e ON e.id=a.employee_id WHERE e.user_id IS NOT NULL AND a.date=CURDATE()",
            'absent_today' => "SELECT GREATEST((SELECT COUNT(*) FROM employees WHERE user_id IS NOT NULL)-(SELECT COUNT(DISTINCT a.employee_id) FROM attendance a INNER JOIN employees e ON e.id=a.employee_id WHERE e.user_id IS NOT NULL AND a.date=CURDATE()),0) value",
            'pending_leave_requests' => "SELECT COUNT(*) value FROM leave_requests WHERE status='pending'",
            'documents_expiring' => "SELECT COUNT(*) value FROM document_reminders WHERE status='Pending' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)",
        ];
        if ($isAdmin) {
            $metrics['vendors'] = "SELECT COUNT(*) value FROM primevendors";
        }

        $data = [];
        foreach ($metrics as $key => $sql) {
            $result = $this->conn->query($sql);
            // Older installations may not have every optional enterprise table yet.
            $data[$key] = $result ? (int)$result->fetch_assoc()['value'] : null;
        }
        return ['success' => true, 'data' => $data];
    }

    public function getAuditActivities(array $user, $limit = 30)
    {
        $limit = min(100, max(1, (int)$limit));
        $isAdmin = in_array((int)$user['position_id'], [1, 2], true);
        $where = $isAdmin ? '' : 'WHERE al.user_id = ?';
        $sql = "SELECT al.id,al.user_id,al.employee_id,al.resource,LOWER(al.action) action,
                al.record_id,al.status,al.created_at,u.nick_name actor,u.email actor_email
            FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
            $where ORDER BY al.created_at DESC,al.id DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Unable to load activity history.'];
        }
        if ($isAdmin) {
            $stmt->bind_param('i', $limit);
        } else {
            $userId = (int)$user['id'];
            $stmt->bind_param('ii', $userId, $limit);
        }
        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = $row['user_id'] === null ? null : (int)$row['user_id'];
            $row['message'] = trim(($row['actor'] ?: 'System user') . ' '
                . $row['action'] . ' ' . str_replace('_', ' ', $row['resource'])
                . ($row['record_id'] ? ' #' . $row['record_id'] : ''));
            $rows[] = $row;
        }
        return ['success' => true, 'data' => $rows];
    }

    public function getWorkforceAnalytics(array $user, $selectedEmployeeId = null, $period = 'this_week')
    {
        $isAdmin = in_array((int)$user['position_id'], [1, 2], true);
        $scopeSql = $isAdmin ? '' : ' AND a.employee_id = ?';
        $employeeScopeSql = $isAdmin ? '' : ' AND u.id = ?';
        $scopeParams = $isAdmin ? [] : [(int)$user['id']];
        $scopeTypes = $isAdmin ? '' : 'i';
        $period = in_array($period, ['today', 'this_week', 'this_month'], true)
            ? $period
            : 'this_week';

        if ($period === 'today') {
            $currentPeriod = 'DATE(a.date_created) = CURDATE()';
            $previousPeriod = 'DATE(a.date_created) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
            $periodLabel = 'Today';
            $previousPeriodLabel = 'Yesterday';
        } elseif ($period === 'this_month') {
            $currentPeriod = 'YEAR(a.date_created) = YEAR(CURDATE()) AND MONTH(a.date_created) = MONTH(CURDATE())';
            $previousPeriod = 'YEAR(a.date_created) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND MONTH(a.date_created) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))';
            $periodLabel = 'This Month';
            $previousPeriodLabel = 'Last Month';
        } else {
            $currentPeriod = 'YEARWEEK(a.date_created, 1) = YEARWEEK(CURDATE(), 1)';
            $previousPeriod = 'YEARWEEK(a.date_created, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)';
            $periodLabel = 'This Week';
            $previousPeriodLabel = 'Last Week';
        }
        $currentInterviewPeriod = str_replace('a.date_created', 'a.interview_updated_at', $currentPeriod);
        $previousInterviewPeriod = str_replace('a.date_created', 'a.interview_updated_at', $previousPeriod);
        $currentPlacementPeriod = str_replace('a.date_created', 'a.placement_updated_at', $currentPeriod);
        $previousPlacementPeriod = str_replace('a.date_created', 'a.placement_updated_at', $previousPeriod);

        $employeeSql = "SELECT
                u.id AS employee_id,
                COALESCE(NULLIF(u.nick_name, ''), u.email, CONCAT('User #', u.id)) AS employee_name,
                p.position_name AS employee_role,
                SUM(CASE WHEN $currentPeriod THEN 1 ELSE 0 END) AS this_week_submissions,
                SUM(CASE WHEN $previousPeriod THEN 1 ELSE 0 END) AS last_week_submissions,
                SUM(CASE WHEN a.process_id = 2 AND $currentInterviewPeriod THEN 1 ELSE 0 END) AS this_week_interviews,
                SUM(CASE WHEN a.process_id = 2 AND $previousInterviewPeriod THEN 1 ELSE 0 END) AS last_week_interviews,
                SUM(CASE WHEN a.process_id = 3 AND $currentPlacementPeriod THEN 1 ELSE 0 END) AS this_week_placements,
                SUM(CASE WHEN a.process_id = 3 AND $previousPlacementPeriod THEN 1 ELSE 0 END) AS last_week_placements
            FROM users u
            LEFT JOIN application a ON a.employee_id = u.id
                AND (
                    $currentPeriod OR $previousPeriod OR $currentInterviewPeriod OR $previousInterviewPeriod OR $currentPlacementPeriod OR $previousPlacementPeriod
                )
            LEFT JOIN positions p ON p.id = u.position_id
            WHERE u.status = 'Active' $employeeScopeSql
            GROUP BY u.id, u.nick_name, u.email, p.position_name
            HAVING this_week_submissions > 0 OR last_week_submissions > 0
                OR this_week_interviews > 0 OR last_week_interviews > 0
                OR this_week_placements > 0 OR last_week_placements > 0
            ORDER BY this_week_submissions DESC, last_week_submissions DESC, employee_name";
        $employeeStmt = $this->prepareAndExecute($employeeSql, $scopeTypes, $scopeParams);
        if (!$employeeStmt) {
            return ['success' => false, 'message' => 'Unable to load weekly employee analytics.'];
        }

        $employees = [];
        $employeeResult = $employeeStmt->get_result();
        while ($row = $employeeResult->fetch_assoc()) {
            foreach ([
                'employee_id', 'this_week_submissions', 'last_week_submissions',
                'this_week_interviews', 'last_week_interviews',
                'this_week_placements', 'last_week_placements'
            ] as $field) {
                $row[$field] = (int)$row[$field];
            }
            $row['submission_change'] =
                $row['this_week_submissions'] - $row['last_week_submissions'];
            $employees[] = $row;
        }

        $allowedEmployeeIds = array_column($employees, 'employee_id');
        if (!$isAdmin) {
            $selectedEmployeeId = (int)$user['id'];
        } elseif (!$selectedEmployeeId || !in_array($selectedEmployeeId, $allowedEmployeeIds, true)) {
            $selectedEmployeeId = $employees[0]['employee_id'] ?? null;
        }

        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $submissionSelect = "a.id, a.candidate_id, a.employee_id, a.date_created AS submission_date,
            $candidateName AS candidate_name, c.email AS candidate_email, c.phone AS candidate_phone,
            COALESCE(NULLIF(c.skills, ''), a.role) AS technology,
            COALESCE(NULLIF(c.visa_status, ''), a.visastatus) AS visa_status,
            c.current_location, a.candidate_loc, a.vendor, a.poc AS vendor_contact,
            a.email AS vendor_email, a.contact, a.client, a.role, a.rate,
            a.feedback, a.remarks, a.interview_slot, a.interview_updated_at, a.placement_updated_at,
            CASE WHEN a.process_id = 2 THEN COALESCE(a.interview_updated_at, a.date_created)
                WHEN a.process_id = 3 THEN COALESCE(a.placement_updated_at, a.date_created)
                ELSE a.date_created END AS activity_date, a.interview_mode, a.process_id,
            CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview' WHEN 3 THEN 'Placed' ELSE 'Unknown' END AS status,
            COALESCE(NULLIF(u.nick_name, ''), u.email) AS employee_name";
        $submissionFrom = " FROM application a
            LEFT JOIN candidate c ON c.id = a.candidate_id
            INNER JOIN users u ON u.id = a.employee_id";

        $latestSql = "SELECT $submissionSelect $submissionFrom
            WHERE 1=1 $scopeSql
            ORDER BY a.date_created DESC, a.id DESC LIMIT 8";
        $latestStmt = $this->prepareAndExecute($latestSql, $scopeTypes, $scopeParams);
        if (!$latestStmt) {
            return ['success' => false, 'message' => 'Unable to load latest submissions.'];
        }
        $latest = $this->fetchSubmissionRows($latestStmt);

        $selectedSubmissions = [];
        if ($selectedEmployeeId) {
            $selectedSql = "SELECT $submissionSelect $submissionFrom
                WHERE a.employee_id = ?
                AND ($currentPeriod
                    OR (a.process_id = 2 AND $currentInterviewPeriod)
                    OR (a.process_id = 3 AND $currentPlacementPeriod))
                ORDER BY activity_date DESC, a.id DESC LIMIT 50";
            $selectedStmt = $this->prepareAndExecute($selectedSql, 'i', [$selectedEmployeeId]);
            if (!$selectedStmt) {
                return ['success' => false, 'message' => 'Unable to load employee candidate details.'];
            }
            $selectedSubmissions = $this->fetchSubmissionRows($selectedStmt);
        }

        $totals = [
            'this_week_submissions' => array_sum(array_column($employees, 'this_week_submissions')),
            'last_week_submissions' => array_sum(array_column($employees, 'last_week_submissions')),
            'this_week_placements' => array_sum(array_column($employees, 'this_week_placements')),
            'last_week_placements' => array_sum(array_column($employees, 'last_week_placements')),
            'this_week_interviews' => array_sum(array_column($employees, 'this_week_interviews')),
            'last_week_interviews' => array_sum(array_column($employees, 'last_week_interviews')),
        ];

        return [
            'success' => true,
            'data' => [
                'employees' => $employees,
                'totals' => $totals,
                'latest_submissions' => $latest,
                'selected_employee_id' => $selectedEmployeeId,
                'selected_submissions' => $selectedSubmissions,
                'period' => $period,
                'period_label' => $periodLabel,
                'previous_period_label' => $previousPeriodLabel,
                'week_starts_on' => 'Monday'
            ]
        ];
    }

    public function getProfilePerformance(array $user, $candidateId = null, $profileUserId = null, array $query = [])
    {
        $isAdmin = in_array((int)$user['position_id'], [1, 2], true);
        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $conditions = [];
        $params = [];
        $types = '';

        if ($candidateId) {
            $conditions[] = 'a.candidate_id = ?';
            $params[] = (int)$candidateId;
            $types .= 'i';
        } else {
            $conditions[] = 'a.employee_id = ?';
            $params[] = (int)$profileUserId;
            $types .= 'i';
            if (!$isAdmin && (int)$profileUserId !== (int)$user['id']) {
                return ['success' => false, 'message' => 'You do not have access to this user performance profile.'];
            }
        }

        if (!$isAdmin && $candidateId) {
            $conditions[] = 'a.employee_id = ?';
            $params[] = (int)$user['id'];
            $types .= 'i';
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $from = " FROM application a
            LEFT JOIN candidate c ON c.id = a.candidate_id
            INNER JOIN users u ON u.id = a.employee_id
            LEFT JOIN positions p ON p.id = u.position_id";

        $summarySql = "SELECT COUNT(a.id) submissions,
                SUM(a.process_id = 2) interviews, SUM(a.process_id = 3) placements,
                COUNT(DISTINCT a.employee_id) users,
                COUNT(DISTINCT COALESCE(a.candidate_id, CONCAT('legacy-', $candidateName))) candidates,
                MIN(a.date_created) first_submission, MAX(a.date_created) latest_submission
            $from $where";
        $summaryStmt = $this->prepareAndExecute($summarySql, $types, $params);
        if (!$summaryStmt) {
            return ['success' => false, 'message' => 'Unable to load profile performance.'];
        }
        $summary = $summaryStmt->get_result()->fetch_assoc();
        $summaryStmt->close();
        foreach (['submissions', 'interviews', 'placements', 'users', 'candidates'] as $field) {
            $summary[$field] = (int)$summary[$field];
        }

        $groupSelect = $candidateId
            ? "a.employee_id AS related_id, COALESCE(NULLIF(u.nick_name, ''), u.email) AS related_name,
                p.position_name AS related_subtitle"
            : "a.candidate_id AS related_id, $candidateName AS related_name,
                COALESCE(NULLIF(c.skills, ''), a.role) AS related_subtitle";
        $groupBy = $candidateId
            ? 'a.employee_id, u.nick_name, u.email, p.position_name'
            : "a.candidate_id, related_name, related_subtitle";
        $breakdownSql = "SELECT $groupSelect, COUNT(a.id) submissions,
                SUM(a.process_id = 2) interviews, SUM(a.process_id = 3) placements,
                MAX(a.date_created) latest_submission
            $from $where GROUP BY $groupBy
            ORDER BY submissions DESC, latest_submission DESC LIMIT 30";
        $breakdownStmt = $this->prepareAndExecute($breakdownSql, $types, $params);
        if (!$breakdownStmt) {
            return ['success' => false, 'message' => 'Unable to load profile breakdown.'];
        }
        $breakdown = $this->fetchSubmissionRowsGeneric($breakdownStmt);

        $trendSql = "SELECT DATE(a.date_created) activity_date, COUNT(a.id) submissions,
                SUM(a.process_id = 2) interviews, SUM(a.process_id = 3) placements
            $from $where GROUP BY DATE(a.date_created)
            ORDER BY activity_date DESC LIMIT 60";
        $trendStmt = $this->prepareAndExecute($trendSql, $types, $params);
        if (!$trendStmt) {
            return ['success' => false, 'message' => 'Unable to load profile trend.'];
        }
        $trend = array_reverse($this->fetchSubmissionRowsGeneric($trendStmt));

        $page = max(1, (int)($query['page'] ?? 1));
        $requestedLimit = (int)($query['limit'] ?? 10);
        $limit = in_array($requestedLimit, [5, 10, 50, 100], true) ? $requestedLimit : 10;
        $relatedId = !empty($query['related_id']) ? (int)$query['related_id'] : null;
        $applicationWhere = $where;
        $applicationParams = $params;
        $applicationTypes = $types;

        if ($relatedId) {
            $applicationWhere .= $candidateId ? ' AND a.employee_id = ?' : ' AND a.candidate_id = ?';
            $applicationParams[] = $relatedId;
            $applicationTypes .= 'i';
        }

        $applicationCountSql = "SELECT COUNT(a.id) total $from $applicationWhere";
        $applicationCountStmt = $this->prepareAndExecute($applicationCountSql, $applicationTypes, $applicationParams);
        if (!$applicationCountStmt) {
            return ['success' => false, 'message' => 'Unable to count profile submissions.'];
        }
        $totalApplications = (int)$applicationCountStmt->get_result()->fetch_assoc()['total'];
        $applicationCountStmt->close();
        $totalPages = max(1, (int)ceil($totalApplications / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $applicationSql = "SELECT a.id, a.candidate_id, a.employee_id, a.date_created,
                $candidateName candidate_name, COALESCE(NULLIF(u.nick_name, ''), u.email) employee_name,
                p.position_name employee_role, a.role, a.vendor, a.client, a.poc,
                a.rate, a.candidate_loc, a.feedback, a.remarks, a.process_id,
                CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview'
                    WHEN 3 THEN 'Placed' ELSE 'Unknown' END status
            $from $applicationWhere ORDER BY a.date_created DESC, a.id DESC LIMIT ? OFFSET ?";
        $applicationParams[] = $limit;
        $applicationParams[] = $offset;
        $applicationStmt = $this->prepareAndExecute($applicationSql, $applicationTypes . 'ii', $applicationParams);
        if (!$applicationStmt) {
            return ['success' => false, 'message' => 'Unable to load profile submissions.'];
        }
        $applications = $this->fetchSubmissionRowsGeneric($applicationStmt);

        return ['success' => true, 'data' => [
            'profile_type' => $candidateId ? 'candidate' : 'user',
            'summary' => $summary,
            'breakdown' => $breakdown,
            'trend' => $trend,
            'applications' => $applications,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $totalApplications,
                'total_pages' => $totalPages
            ]
        ]];
    }
    public function getTableData($table, array $query, array $user)
    {
        $specs = $this->tableSpecs();
        if (!isset($specs[$table])) {
            return ['success' => false, 'message' => 'Unknown dashboard table.'];
        }

        $spec = $specs[$table];
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim((string)($query['search'] ?? ''));
        $sort = (string)($query['sort'] ?? $spec['default_sort']);
        $order = strtolower((string)($query['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sortColumn = $spec['sorts'][$sort] ?? $spec['sorts'][$spec['default_sort']];

        $conditions = $spec['conditions'];
        $params = [];
        $types = '';

        // Super Admin and Admin see all data. Every other role sees owned data.
        if (!in_array((int)$user['position_id'], [1, 2], true)) {
            $conditions[] = $spec['owner_column'] . ' = ?';
            $params[] = (int)$user['id'];
            $types .= 'i';
        }

        if ($search !== '') {
            $likes = [];
            foreach ($spec['search_columns'] as $column) {
                $likes[] = "$column LIKE ?";
                $params[] = '%' . $search . '%';
                $types .= 's';
            }
            $conditions[] = '(' . implode(' OR ', $likes) . ')';
        }

        $dateColumn = $spec['date_column'];
        if ($this->validDate($query['start_date'] ?? null)) {
            $conditions[] = "$dateColumn >= ?";
            $params[] = $query['start_date'];
            $types .= 's';
        }
        if ($this->validDate($query['end_date'] ?? null)) {
            $conditions[] = "$dateColumn < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $query['end_date'];
            $types .= 's';
        }

        $category = strtolower(trim((string)($query['category'] ?? '')));
        if ($category === 'recruiters') {
            $conditions[] = "LOWER(p.position_name) LIKE '%recruiter%'";
        } elseif ($category === 'benchsales' || $category === 'bench-sales') {
            $conditions[] = "LOWER(p.position_name) LIKE '%bench%'";
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $countSql = 'SELECT COUNT(' . $spec['count_column'] . ') AS total '
            . $spec['from'] . $where;
        $countStmt = $this->prepareAndExecute($countSql, $types, $params);
        if (!$countStmt) {
            return ['success' => false, 'message' => 'Unable to load dashboard data.'];
        }
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];

        $dataSql = 'SELECT ' . $spec['select'] . ' ' . $spec['from'] . $where
            . " ORDER BY $sortColumn $order LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataStmt = $this->prepareAndExecute($dataSql, $types . 'ii', $dataParams);
        if (!$dataStmt) {
            return ['success' => false, 'message' => 'Unable to load dashboard data.'];
        }

        $rows = [];
        $result = $dataStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $rows[] = $row;
        }

        return [
            'success' => true,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'data' => $rows
        ];
    }

    public function getRecord($table, $id, array $user)
    {
        $specs = $this->detailSpecs();
        if (!isset($specs[$table])) {
            return ['success' => false, 'message' => 'Unknown dashboard record type.'];
        }

        $spec = $specs[$table];
        $conditions = array_merge($spec['conditions'], [$spec['id_column'] . ' = ?']);
        $params = [(int)$id];
        $types = 'i';

        if (!in_array((int)$user['position_id'], [1, 2], true)) {
            $conditions[] = $spec['owner_column'] . ' = ?';
            $params[] = (int)$user['id'];
            $types .= 'i';
        }

        $sql = 'SELECT ' . $spec['select'] . ' ' . $spec['from']
            . ' WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1';
        $stmt = $this->prepareAndExecute($sql, $types, $params);

        if (!$stmt) {
            return ['success' => false, 'message' => 'Unable to load dashboard record.'];
        }

        $record = $stmt->get_result()->fetch_assoc();
        if (!$record) {
            return [
                'success' => false,
                'not_found' => true,
                'message' => 'Record not found or you do not have access to it.'
            ];
        }

        $record['id'] = (int)$record['id'];
        return ['success' => true, 'data' => $record];
    }

    private function detailSpecs()
    {
        $applicationFrom = 'FROM application a
            LEFT JOIN candidate c ON c.id = a.candidate_id
            LEFT JOIN users u ON u.id = a.employee_id
            LEFT JOIN positions p ON p.id = u.position_id';
        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $status = "CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview'
            WHEN 3 THEN 'Placed' ELSE 'Unknown' END";
        $applicationSelect = "a.id, a.candidate_id, $candidateName AS candidate,
            a.date_created, u.nick_name AS recruiter, p.position_name AS recruiter_role,
            a.vendor, a.poc AS vendor_contact, a.client, a.role AS technology,
            a.emp_loc AS employment_location, a.candidate_loc AS candidate_location,
            a.rate, $status AS status, a.process_id, a.feedback, a.remarks,
            a.interview_slot, a.interview_mode, a.vendor_position,
            a.bill_rate, a.end_client, a.email, a.contact, a.visastatus AS visa,
            a.resume_path, a.r2r_path, a.driving_path, a.visa_path, a.msc_path";

        return [
            'submissions' => [
                'select' => $applicationSelect,
                'from' => $applicationFrom,
                'conditions' => [],
                'id_column' => 'a.id',
                'owner_column' => 'a.employee_id'
            ],
            'interviews' => [
                'select' => $applicationSelect,
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 2'],
                'id_column' => 'a.id',
                'owner_column' => 'a.employee_id'
            ],
            'placements' => [
                'select' => $applicationSelect,
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 3'],
                'id_column' => 'a.id',
                'owner_column' => 'a.employee_id'
            ],
            'active-candidates' => [
                'select' => "c.id, c.name AS candidate, c.email, c.phone,
                    c.current_location, c.visa_status AS visa, c.skills AS technology,
                    c.resume_path, c.remarks, c.status, c.created_at, c.updated_at,
                    u.nick_name AS recruiter, p.position_name AS recruiter_role",
                'from' => 'FROM candidate c
                    LEFT JOIN users u ON u.id = c.created_by
                    LEFT JOIN positions p ON p.id = u.position_id',
                'conditions' => ["c.status = 'Active'"],
                'id_column' => 'c.id',
                'owner_column' => 'c.created_by'
            ]
        ];
    }

    private function tableSpecs()
    {
        $applicationFrom = 'FROM application a
            LEFT JOIN candidate c ON c.id = a.candidate_id
            LEFT JOIN users u ON u.id = a.employee_id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN positions p ON p.id = u.position_id';
        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $status = "CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview'
            WHEN 3 THEN 'Placed' ELSE 'Unknown' END";

        return [
            'submissions' => [
                'select' => "a.id, a.candidate_id, u.id AS recruiter_user_id,
                    e.id AS recruiter_employee_id, a.date_created AS submission_date, $candidateName AS candidate,
                    u.nick_name AS recruiter, a.vendor, a.client, a.role AS technology,
                    $status AS status, a.rate, a.candidate_loc AS location,
                    a.interview_slot, a.feedback",
                'from' => $applicationFrom,
                'conditions' => [],
                'owner_column' => 'a.employee_id',
                'count_column' => 'a.id',
                'date_column' => 'a.date_created',
                'search_columns' => [$candidateName, 'u.nick_name', 'a.vendor', 'a.client', 'a.role'],
                'sorts' => [
                    'submission_date' => 'a.date_created', 'candidate' => $candidateName,
                    'recruiter' => 'u.nick_name', 'vendor' => 'a.vendor', 'client' => 'a.client',
                    'technology' => 'a.role', 'status' => 'a.process_id', 'rate' => 'a.rate',
                    'location' => 'a.candidate_loc'
                ],
                'default_sort' => 'submission_date'
            ],
            'active-candidates' => [
                'select' => "c.id, c.id AS candidate_id, u.id AS recruiter_user_id,
                    e.id AS recruiter_employee_id, c.name AS candidate, c.skills AS technology,
                    NULL AS experience, c.visa_status AS visa, c.current_location,
                    NULL AS preferred_location, u.nick_name AS recruiter,
                    NULL AS availability, c.status",
                'from' => 'FROM candidate c
                    LEFT JOIN users u ON u.id = c.created_by
                    LEFT JOIN employees e ON e.user_id = u.id
                    LEFT JOIN positions p ON p.id = u.position_id',
                'conditions' => ["c.status = 'Active'"],
                'owner_column' => 'c.created_by',
                'count_column' => 'c.id',
                'date_column' => 'c.created_at',
                'search_columns' => ['c.name', 'c.skills', 'c.visa_status', 'c.current_location', 'u.nick_name'],
                'sorts' => [
                    'candidate' => 'c.name', 'technology' => 'c.skills',
                    'visa' => 'c.visa_status', 'current_location' => 'c.current_location',
                    'recruiter' => 'u.nick_name', 'status' => 'c.status'
                ],
                'default_sort' => 'candidate'
            ],
            'interviews' => [
                'select' => "a.id, a.candidate_id, u.id AS recruiter_user_id,
                    e.id AS recruiter_employee_id, COALESCE(a.interview_updated_at, a.date_created) AS interview_date,
                    $candidateName AS candidate, a.client, a.vendor,
                    a.interview_mode AS interview_type, NULL AS round,
                    u.nick_name AS recruiter, $status AS status, a.feedback",
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 2'],
                'owner_column' => 'a.employee_id',
                'count_column' => 'a.id',
                'date_column' => 'COALESCE(a.interview_updated_at, a.date_created)',
                'search_columns' => [$candidateName, 'u.nick_name', 'a.vendor', 'a.client', 'a.role'],
                'sorts' => [
                    'interview_date' => 'COALESCE(a.interview_updated_at, a.date_created)', 'candidate' => $candidateName,
                    'client' => 'a.client', 'vendor' => 'a.vendor',
                    'interview_type' => 'a.interview_mode', 'recruiter' => 'u.nick_name',
                    'status' => 'a.process_id', 'feedback' => 'a.feedback'
                ],
                'default_sort' => 'interview_date'
            ],
            'placements' => [
                'select' => "a.id, a.candidate_id, u.id AS recruiter_user_id,
                    e.id AS recruiter_employee_id, COALESCE(a.placement_updated_at, a.date_created) AS placement_date, $candidateName AS candidate,
                    a.client, a.vendor, u.nick_name AS recruiter, a.candidate_loc AS location,
                    a.rate, NULL AS start_date, $status AS status",
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 3'],
                'owner_column' => 'a.employee_id',
                'count_column' => 'a.id',
                'date_column' => 'COALESCE(a.placement_updated_at, a.date_created)',
                'search_columns' => [$candidateName, 'u.nick_name', 'a.vendor', 'a.client', 'a.role'],
                'sorts' => [
                    'placement_date' => 'COALESCE(a.placement_updated_at, a.date_created)', 'candidate' => $candidateName,
                    'client' => 'a.client', 'vendor' => 'a.vendor', 'recruiter' => 'u.nick_name',
                    'location' => 'a.candidate_loc', 'rate' => 'a.rate', 'status' => 'a.process_id'
                ],
                'default_sort' => 'placement_date'
            ]
        ];
    }

    private function prepareAndExecute($sql, $types, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('Dashboard query prepare failed: ' . $this->conn->error);
            return null;
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            error_log('Dashboard query failed: ' . $stmt->error);
            return null;
        }
        return $stmt;
    }

    private function fetchSubmissionRows($stmt)
    {
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            foreach (['id', 'employee_id', 'process_id'] as $field) {
                $row[$field] = (int)$row[$field];
            }
            $row['candidate_id'] = $row['candidate_id'] === null
                ? null
                : (int)$row['candidate_id'];
            $rows[] = $row;
        }
        return $rows;
    }

    private function fetchSubmissionRowsGeneric($stmt)
    {
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            foreach ([
                'id', 'candidate_id', 'employee_id', 'related_id', 'process_id',
                'recruiter_user_id', 'recruiter_employee_id',
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

    private function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}
