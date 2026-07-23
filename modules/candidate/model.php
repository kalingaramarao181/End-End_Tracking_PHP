<?php
// File: modules/candidate/model.php

require_once __DIR__ . '/../../config/db.php';

class CandidateModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function createCandidate($data)
    {
        $sql = "
            INSERT INTO candidate (
                name,
                email,
                phone,
                current_location,
                visa_status,
                skills,
                resume_path,
                remarks,
                status,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $currentLocation = $data['current_location'] ?? null;
        $visaStatus = $data['visa_status'] ?? null;
        $skills = $data['skills'] ?? null;
        $resumePath = $data['resume_path'] ?? null;
        $remarks = $data['remarks'] ?? null;
        $status = $data['status'] ?? 'Active';
        $createdBy = (int)($data['created_by'] ?? 0);

        $stmt->bind_param(
            "sssssssssi",
            $name,
            $email,
            $phone,
            $currentLocation,
            $visaStatus,
            $skills,
            $resumePath,
            $remarks,
            $status,
            $createdBy
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to create candidate.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Candidate created successfully.',
            'candidate_id' => $this->conn->insert_id
        ];
    }

public function saveCandidateResume($candidateId, $resume, $json)
{
    $sql = "
        INSERT INTO candidate_resume
        (
            candidate_id,
            resume,
            parse_resume
        )
        VALUES (?, ?, ?)
    ";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "success" => false,
            "message" => "Prepare Failed",
            "error" => $this->conn->error
        ];
    }

    $stmt->bind_param(
        "iss",
        $candidateId,
        $resume,
        $json
    );

    if (!$stmt->execute()) {
        return [
            "success" => false,
            "message" => "Execute Failed",
            "error" => $stmt->error
        ];
    }

    return [
        "success" => true
    ];
}

    /*
    |--------------------------------------------------------------------------
    | GET CANDIDATE BY ID
    |--------------------------------------------------------------------------
    */
    public function getCandidateById($id)
    {
        $sql = "
            SELECT *
            FROM candidate
            WHERE id = ?
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
                'message' => 'Candidate not found.'
            ];
        }

        $candidate = $result->fetch_assoc();

        $documents = [];
        $documentStmt = $this->conn->prepare("
            SELECT cd.*,
                   dr.id AS reminder_id,
                   dr.expiry_date,
                   dr.next_reminder_date,
                   dr.last_sent_date,
                   dr.status AS reminder_status,
                   DATEDIFF(dr.expiry_date, CURDATE()) AS days_left
            FROM candidate_documents cd
            LEFT JOIN document_reminders dr ON dr.document_id = cd.id
            WHERE cd.candidate_id = ?
            ORDER BY cd.id DESC
        ");

        if ($documentStmt) {
            $documentStmt->bind_param('i', $id);
            $documentStmt->execute();
            $documentResult = $documentStmt->get_result();
            while ($document = $documentResult->fetch_assoc()) {
                $document['document_details'] = $document['document_details']
                    ? json_decode($document['document_details'], true)
                    : null;
                $document['expiry_detection_status'] = empty($document['document_details']['expiry_date']) && empty($document['document_details']['reminder_date'])
                    ? 'Reminder Date Not Entered'
                    : 'Entered';
                $documents[] = $document;
            }
        }

        $candidate['documents'] = $documents;

        return [
            'success' => true,
            'data' => $candidate
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET CANDIDATES LIST
    |--------------------------------------------------------------------------
    */
    public function getCandidates($page, $limit, $search)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $offset = ($page - 1) * $limit;
        $search = trim($search ?? '');

        $conditions = [];
        $params = [];
        $types = '';

        if ($search !== '') {

            $conditions[] = "(
                name LIKE ?
                OR email LIKE ?
                OR phone LIKE ?
                OR skills LIKE ?
                OR visa_status LIKE ?
            )";

            $searchValue = '%' . $search . '%';

            for ($i = 0; $i < 5; $i++) {
                $params[] = $searchValue;
                $types .= 's';
            }
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        /*
        |--------------------------------------------------------------------------
        | COUNT QUERY
        |--------------------------------------------------------------------------
        */
        $countSql = "
            SELECT COUNT(*) AS total
            FROM candidate
            $where
        ";

        $stmt = $this->conn->prepare($countSql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $countResult = $stmt->get_result();

        $totalRecords = (int)$countResult
            ->fetch_assoc()['total'];

        /*
        |--------------------------------------------------------------------------
        | DATA QUERY
        |--------------------------------------------------------------------------
        */
        $dataSql = "
            SELECT *
            FROM candidate
            $where
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($dataSql);

        $dataParams = $params;
        $dataTypes = $types . 'ii';

        $dataParams[] = $limit;
        $dataParams[] = $offset;

        $stmt->bind_param(
            $dataTypes,
            ...$dataParams
        );

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
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'data' => $rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function updateCandidate($id, $data)
    {
        $sql = "
            UPDATE candidate
            SET
                name = ?,
                email = ?,
                phone = ?,
                current_location = ?,
                visa_status = ?,
                skills = ?,
                resume_path = ?,
                remarks = ?,
                status = ?,
                updated_by = ?
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

        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $currentLocation = $data['current_location'] ?? null;
        $visaStatus = $data['visa_status'] ?? null;
        $skills = $data['skills'] ?? null;
        $resumePath = $data['resume_path'] ?? null;
        $remarks = $data['remarks'] ?? null;
        $status = $data['status'] ?? 'Active';
        $updatedBy = (int)($data['updated_by'] ?? 0);

        $stmt->bind_param(
            "sssssssssii",
            $name,
            $email,
            $phone,
            $currentLocation,
            $visaStatus,
            $skills,
            $resumePath,
            $remarks,
            $status,
            $updatedBy,
            $id
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to update candidate.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Candidate updated successfully.',
            'affected_rows' => $stmt->affected_rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function deleteCandidate($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM candidate
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
                'message' => 'Failed to delete candidate.',
                'error' => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                'success' => false,
                'message' => 'Candidate not found.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Candidate deleted successfully.'
        ];
    }
}
