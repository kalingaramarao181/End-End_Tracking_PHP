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
            LEFT JOIN positions p ON p.id = u.position_id';
        $candidateName = "COALESCE(c.name, NULLIF(a.candidate_name, ''), NULLIF(a.cname, ''),
            NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.middlename, a.lastname)), ''))";
        $status = "CASE a.process_id WHEN 1 THEN 'Submitted' WHEN 2 THEN 'Interview'
            WHEN 3 THEN 'Placed' ELSE 'Unknown' END";

        return [
            'submissions' => [
                'select' => "a.id, a.date_created AS submission_date, $candidateName AS candidate,
                    u.nick_name AS recruiter, a.vendor, a.client, a.role AS technology,
                    $status AS status, a.rate, a.candidate_loc AS location",
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
                'select' => "c.id, c.name AS candidate, c.skills AS technology,
                    NULL AS experience, c.visa_status AS visa, c.current_location,
                    NULL AS preferred_location, u.nick_name AS recruiter,
                    NULL AS availability, c.status",
                'from' => 'FROM candidate c
                    LEFT JOIN users u ON u.id = c.created_by
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
                'select' => "a.id, COALESCE(NULLIF(a.interview_slot, ''), a.date_created) AS interview_date,
                    $candidateName AS candidate, a.client, a.vendor,
                    a.interview_mode AS interview_type, NULL AS round,
                    u.nick_name AS recruiter, $status AS status, a.feedback",
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 2'],
                'owner_column' => 'a.employee_id',
                'count_column' => 'a.id',
                'date_column' => 'a.date_created',
                'search_columns' => [$candidateName, 'u.nick_name', 'a.vendor', 'a.client', 'a.role'],
                'sorts' => [
                    'interview_date' => 'a.date_created', 'candidate' => $candidateName,
                    'client' => 'a.client', 'vendor' => 'a.vendor',
                    'interview_type' => 'a.interview_mode', 'recruiter' => 'u.nick_name',
                    'status' => 'a.process_id', 'feedback' => 'a.feedback'
                ],
                'default_sort' => 'interview_date'
            ],
            'placements' => [
                'select' => "a.id, a.date_created AS placement_date, $candidateName AS candidate,
                    a.client, a.vendor, u.nick_name AS recruiter, a.candidate_loc AS location,
                    a.rate, NULL AS start_date, $status AS status",
                'from' => $applicationFrom,
                'conditions' => ['a.process_id = 3'],
                'owner_column' => 'a.employee_id',
                'count_column' => 'a.id',
                'date_column' => 'a.date_created',
                'search_columns' => [$candidateName, 'u.nick_name', 'a.vendor', 'a.client', 'a.role'],
                'sorts' => [
                    'placement_date' => 'a.date_created', 'candidate' => $candidateName,
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

    private function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}
