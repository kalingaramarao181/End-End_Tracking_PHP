<?php
// File: modules/jobs/model.php

require_once __DIR__ . '/../../config/db.php';

class JobModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE JOB
    |--------------------------------------------------------------------------
    */
    public function createJob($data)
    {
        $sql = "
            INSERT INTO jobs
            (
                position,
                location,
                duration,
                domain,
                interview_process,
                rate,
                visa,
                job_description,
                parsed_job_json,
                status,
                created_by
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                "success" => false,
                "message" => "Prepare failed.",
                "error" => $this->conn->error
            ];
        }

        $position           = $data['position'] ?? null;
        $location           = $data['location'] ?? null;
        $duration           = $data['duration'] ?? null;
        $domain             = $data['domain'] ?? null;
        $interviewProcess   = $data['interview_process'] ?? null;
        $rate               = $data['rate'] ?? null;
        $visa               = $data['visa'] ?? null;
        $jobDescription     = $data['job_description'] ?? null;
        $parsedJobJson      = $data['parsed_job_json'] ?? null;
        $status             = $data['status'] ?? 'Open';
        $createdBy          = (int)($data['created_by'] ?? 0);

        $stmt->bind_param(
            "ssssssssssi",
            $position,
            $location,
            $duration,
            $domain,
            $interviewProcess,
            $rate,
            $visa,
            $jobDescription,
            $parsedJobJson,
            $status,
            $createdBy
        );

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Failed to create job.",
                "error" => $stmt->error
            ];
        }

        return [
            "success" => true,
            "message" => "Job created successfully.",
            "job_id" => $this->conn->insert_id
        ];
    }

        /*
    |--------------------------------------------------------------------------
    | GET JOB BY ID
    |--------------------------------------------------------------------------
    */
    public function getJobById($id)
    {
        $sql = "
            SELECT *
            FROM jobs
            WHERE id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                "success" => false,
                "message" => "Prepare failed.",
                "error" => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                "success" => false,
                "message" => "Job not found."
            ];
        }

        return [
            "success" => true,
            "data" => $result->fetch_assoc()
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET JOBS LIST
    |--------------------------------------------------------------------------
    */
    public function getJobs($page, $limit, $search)
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
                position LIKE ?
                OR location LIKE ?
                OR duration LIKE ?
                OR domain LIKE ?
                OR interview_process LIKE ?
                OR visa LIKE ?
                OR status LIKE ?
            )";

            $searchValue = "%" . $search . "%";

            for ($i = 0; $i < 7; $i++) {
                $params[] = $searchValue;
                $types .= "s";
            }
        }

        $where = "";

        if (!empty($conditions)) {
            $where = "WHERE " . implode(" AND ", $conditions);
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL RECORDS
        |--------------------------------------------------------------------------
        */

        $countSql = "
            SELECT COUNT(*) AS total
            FROM jobs
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
        | GET DATA
        |--------------------------------------------------------------------------
        */

        $dataSql = "
            SELECT *
            FROM jobs
            $where
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($dataSql);

        $dataParams = $params;
        $dataTypes = $types . "ii";

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
            "success" => true,
            "page" => $page,
            "limit" => $limit,
            "search" => $search,
            "total_records" => $totalRecords,
            "total_pages" => ceil($totalRecords / $limit),
            "data" => $rows
        ];
    }

        /*
    |--------------------------------------------------------------------------
    | UPDATE JOB
    |--------------------------------------------------------------------------
    */
    public function updateJob($id, $data)
    {
        $sql = "
            UPDATE jobs
            SET
                position = ?,
                location = ?,
                duration = ?,
                domain = ?,
                interview_process = ?,
                rate = ?,
                visa = ?,
                job_description = ?,
                parsed_job_json = ?,
                status = ?
            WHERE id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                "success" => false,
                "message" => "Prepare failed.",
                "error" => $this->conn->error
            ];
        }

        $position           = $data['position'] ?? null;
        $location           = $data['location'] ?? null;
        $duration           = $data['duration'] ?? null;
        $domain             = $data['domain'] ?? null;
        $interviewProcess   = $data['interview_process'] ?? null;
        $rate               = $data['rate'] ?? null;
        $visa               = $data['visa'] ?? null;
        $jobDescription     = $data['job_description'] ?? null;
        $parsedJobJson      = $data['parsed_job_json'] ?? null;
        $status             = $data['status'] ?? 'Open';

        $stmt->bind_param(
            "ssssssssssi",
            $position,
            $location,
            $duration,
            $domain,
            $interviewProcess,
            $rate,
            $visa,
            $jobDescription,
            $parsedJobJson,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Failed to update job.",
                "error" => $stmt->error
            ];
        }

        return [
            "success" => true,
            "message" => $stmt->affected_rows === 0
                ? "Job details are already up to date."
                : "Job updated successfully.",
            "affected_rows" => $stmt->affected_rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE JOB
    |--------------------------------------------------------------------------
    */
    public function deleteJob($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM jobs
            WHERE id = ?
        ");

        if (!$stmt) {
            return [
                "success" => false,
                "message" => "Prepare failed.",
                "error" => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Failed to delete job.",
                "error" => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                "success" => false,
                "message" => "Job not found."
            ];
        }

        return [
            "success" => true,
            "message" => "Job deleted successfully."
        ];
    }
}
