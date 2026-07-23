<?php

require_once __DIR__ . '/../../config/db.php';

class AIMatchingModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /*
    |--------------------------------------------------------------------------
    | GET JOB JSON
    |--------------------------------------------------------------------------
    */
    public function getJob($jobId)
    {
        $sql = "
            SELECT
                id,
                position,
                parsed_job_json
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

        $stmt->bind_param("i", $jobId);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
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
    | GET ALL CANDIDATES
    |--------------------------------------------------------------------------
    */
    public function getCandidates()
    {
        $sql = "
            SELECT

                c.id,
                c.name,
                c.email,
                c.phone,

                cr.parse_resume

            FROM candidate c

            INNER JOIN candidate_resume cr

                ON c.id = cr.candidate_id

            WHERE c.status='Active'

            ORDER BY c.id DESC
        ";

        $result = $this->conn->query($sql);

        if (!$result) {

            return [

                "success"=>false,

                "message"=>"Database Error",

                "error"=>$this->conn->error

            ];

        }

        $rows=[];

        while($row=$result->fetch_assoc())
        {
            $rows[]=$row;
        }

        return [

            "success"=>true,

            "data"=>$rows

        ];

    }

        /*
    |--------------------------------------------------------------------------
    | SAVE MATCH RESULT
    |--------------------------------------------------------------------------
    */
    public function saveMatch($jobId, $candidateId, $match)
    {
        // Remove old record if already exists
        $delete = $this->conn->prepare("
            DELETE FROM job_candidate_match
            WHERE job_id = ?
            AND candidate_id = ?
        ");

        $delete->bind_param(
            "ii",
            $jobId,
            $candidateId
        );

        $delete->execute();

        $sql = "
            INSERT INTO job_candidate_match
            (
                job_id,
                candidate_id,
                overall_score,
                skill_score,
                experience_score,
                location_score,
                visa_score,
                education_score,
                certification_score,
                recommendation,
                ai_reason
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

        $overallScore = (float)($match["overall_score"] ?? 0);
        $skillScore = (float)($match["skill_score"] ?? 0);
        $experienceScore = (float)($match["experience_score"] ?? 0);
        $locationScore = (float)($match["location_score"] ?? 0);
        $visaScore = (float)($match["visa_score"] ?? 0);
        $educationScore = (float)($match["education_score"] ?? 0);
        $certificationScore = (float)($match["certification_score"] ?? 0);

        $recommendation = $match["recommendation"] ?? "Consider";

        $reason = $match["ai_reason"] ?? "";

        $stmt->bind_param(

            "iidddddddss",

            $jobId,
            $candidateId,

            $overallScore,
            $skillScore,
            $experienceScore,
            $locationScore,
            $visaScore,
            $educationScore,
            $certificationScore,

            $recommendation,
            $reason

        );

        if (!$stmt->execute()) {

            return [

                "success" => false,
                "message" => "Failed to save match.",
                "error" => $stmt->error

            ];

        }

        return [

            "success" => true,
            "message" => "Match saved successfully."

        ];
    }

        /*
    |--------------------------------------------------------------------------
    | DELETE OLD MATCHES
    |--------------------------------------------------------------------------
    */
    public function deleteOldMatches($jobId)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM job_candidate_match
            WHERE job_id = ?
        ");

        if (!$stmt) {
            return [
                "success" => false,
                "message" => "Prepare failed.",
                "error" => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $jobId);

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Failed to delete old matches.",
                "error" => $stmt->error
            ];
        }

        return [
            "success" => true
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET MATCHED CANDIDATES
    |--------------------------------------------------------------------------
    */
    public function getMatchesByJob($jobId)
    {
        $sql = "
            SELECT

                jcm.id,

                jcm.job_id,

                c.id AS candidate_id,

                c.name,
                c.email,
                c.phone,
                c.current_location,
                c.visa_status,

                jcm.overall_score,
                jcm.skill_score,
                jcm.experience_score,
                jcm.location_score,
                jcm.visa_score,
                jcm.education_score,
                jcm.certification_score,

                jcm.recommendation,
                jcm.ai_reason,

                jcm.created_at

            FROM job_candidate_match jcm

            INNER JOIN candidate c

                ON c.id = jcm.candidate_id

            WHERE jcm.job_id = ?

            ORDER BY

                jcm.overall_score DESC,

                jcm.skill_score DESC,

                jcm.experience_score DESC,

                c.name ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {

            return [

                "success" => false,

                "message" => "Prepare failed.",

                "error" => $this->conn->error

            ];

        }

        $stmt->bind_param("i", $jobId);

        $stmt->execute();

        $result = $stmt->get_result();

        $rows = [];

        while ($row = $result->fetch_assoc()) {

            $rows[] = $row;

        }

        return [

            "success" => true,

            "total_matches" => count($rows),

            "data" => $rows

        ];
    }

}