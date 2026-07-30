<?php
// File: modules/jobs/controller.php

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../services/OpenAIJobParser.php';

class JobController
{
    private $jobModel;

    public function __construct()
    {
        $this->jobModel = new JobModel();
    }

    /**
     * Send JSON Response
     */
    private function jsonResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Read Request Data
     */
    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'multipart/form-data') !== false) {
            return $_POST;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE JOB
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        require_once __DIR__ . '/../../middleware/auth.php';

        $data = $this->getRequestData();

        $authUser = authUser();

        if (!$authUser || !isset($authUser['id'])) {
            $this->jsonResponse(401, [
                "success" => false,
                "message" => "Unauthorized."
            ]);
        }

        $data['created_by'] = (int)$authUser['id'];

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (empty($data['position'])) {
            $this->jsonResponse(400, [
                "success" => false,
                "message" => "Position is required."
            ]);
        }

        if (empty($data['job_description'])) {
            $this->jsonResponse(400, [
                "success" => false,
                "message" => "Job Description is required."
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE CORE JOB FIRST
        |--------------------------------------------------------------------------
        */

        $data['parsed_job_json'] = null;
        $result = $this->jobModel->createJob($data);

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $jobId = $result['job_id'];
        $aiProcessed = false;
        $warning = null;

        /*
        |----------------------------------------------------------------------
        | OPTIONAL AI ENRICHMENT
        |----------------------------------------------------------------------
        */
        try {
            $parser = new OpenAIJobParser();

            $parsedJob = $parser->parseJobDescription(
                $data['job_description']
            );

            if (is_array($parsedJob)) {
                $parsedJob = json_encode(
                    $parsedJob,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );
            }

            $data['parsed_job_json'] = $parsedJob;
            $enrichmentResult = $this->jobModel->updateJob($jobId, $data);

            if ($enrichmentResult['success']) {
                $aiProcessed = true;
            } else {
                error_log(
                    'Parsed AI data could not be saved for job ' . $jobId .
                    ': ' . ($enrichmentResult['error'] ?? $enrichmentResult['message'])
                );
                $warning = 'Job was saved, but its AI-parsed details could not be stored.';
            }
        } catch (Throwable $e) {
            error_log('Job AI parsing failed during create for job ' . $jobId . ': ' . $e->getMessage());
            $warning = 'Job was saved, but AI job parsing is currently unavailable.';
        }

        $this->jsonResponse(201, [
            "success" => true,
            "message" => $warning ?: "Job created successfully.",
            "job_id" => $jobId,
            "ai_processed" => $aiProcessed,
            "warning" => $warning
        ]);
    }

        /*
    |--------------------------------------------------------------------------
    | LIST JOBS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $page = isset($_GET['page'])
            ? (int)$_GET['page']
            : 1;

        $limit = isset($_GET['limit'])
            ? (int)$_GET['limit']
            : 10;

        $search = isset($_GET['search'])
            ? trim($_GET['search'])
            : '';

        $result = $this->jobModel->getJobs(
            $page,
            $limit,
            $search
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE JOB
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $result = $this->jobModel->getJobById($id);

        if (!$result['success']) {

            $statusCode =
                ($result['message'] === 'Job not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

        /*
    |--------------------------------------------------------------------------
    | UPDATE JOB
    |--------------------------------------------------------------------------
    */
    public function update($id)
    {
        require_once __DIR__ . '/../../middleware/auth.php';

        $data = $this->getRequestData();

        $authUser = authUser();

        if (!$authUser || !isset($authUser['id'])) {
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Unauthorized.'
            ]);
        }

        $existing = $this->jobModel->getJobById($id);

        if (!$existing['success']) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Job not found.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Re-Parse Job Description
        |--------------------------------------------------------------------------
        */
        $aiProcessed = null;
        $warning = null;

        if (!empty($data['job_description'])) {

            try {

                $parser = new OpenAIJobParser();

                $parsedJob = $parser->parseJobDescription(
                    $data['job_description']
                );

                if (is_array($parsedJob)) {
                    $parsedJob = json_encode(
                        $parsedJob,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    );
                }

                $data['parsed_job_json'] = $parsedJob;
                $aiProcessed = true;

            } catch (Throwable $e) {
                error_log('Job AI parsing failed during update for job ' . $id . ': ' . $e->getMessage());
                $data['parsed_job_json'] = $existing['data']['parsed_job_json'];
                $aiProcessed = false;
                $warning = 'Job details were updated, but AI parsing is currently unavailable. Previous AI data was preserved.';
            }
        } else {
            // Keep existing parsed JSON if description not changed
            $data['parsed_job_json'] =
                $existing['data']['parsed_job_json'];
        }

        // Keep old values if not sent
        $fields = [
            'position',
            'location',
            'duration',
            'domain',
            'interview_process',
            'rate',
            'visa',
            'job_description',
            'status'
        ];

        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = $existing['data'][$field];
            }
        }

        $result = $this->jobModel->updateJob(
            $id,
            $data
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, [
            'success' => true,
            'message' => $warning ?: 'Job updated successfully.',
            'affected_rows' => $result['affected_rows'] ?? 0,
            'ai_processed' => $aiProcessed,
            'warning' => $warning
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE JOB
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        $result = $this->jobModel->deleteJob($id);

        if (!$result['success']) {

            $statusCode =
                ($result['message'] === 'Job not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }
}
