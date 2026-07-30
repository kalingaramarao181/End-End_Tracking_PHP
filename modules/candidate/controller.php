<?php
// File: modules/candidate/controller.php

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../middleware/upload.php';
require_once "services/OpenAIResumeParser.php";

class CandidateController
{
    private $candidateModel;

    public function __construct()
    {
        $this->candidateModel = new CandidateModel();
    }

    /**
     * Send JSON response
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
    | CREATE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function create()
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

    $data['created_by'] = (int)$authUser['id'];

    // Upload Resume
    $data['resume_path'] = uploadFile(
        $_FILES['resume_file'] ?? null,
        'candidates/resumes'
    );

    // Validate Upload
    if (empty($data['resume_path'])) {
        $this->jsonResponse(400, [
            'success' => false,
            'message' => 'Resume upload failed.'
        ]);
    }

    // Validation
    if (empty($data['name'])) {
        $this->jsonResponse(400, [
            'success' => false,
            'message' => 'Candidate name is required.'
        ]);
    }

    /**
     * Save the candidate before optional AI enrichment. An unavailable or
     * expired AI credential must never prevent the core record from existing.
     */
    $result = $this->candidateModel->createCandidate($data);

    if (!$result['success']) {

        $this->jsonResponse(500, $result);

    }

    $candidateId = $result['candidate_id'];

    $parsedResume = null;
    $aiProcessed = false;
    $warning = null;

    try {
        $parser = new OpenAIResumeParser();
        $parsedResume = $parser->parseResume($data['resume_path']);
        $aiProcessed = true;
    } catch (Throwable $e) {
        error_log(
            'Candidate resume AI parsing failed for candidate ' .
            $candidateId . ': ' . $e->getMessage()
        );
        $warning = 'Candidate and resume were saved, but AI resume parsing is currently unavailable.';
    }

    /**
     * Save Resume Details
     */
    $resumeResult = $this->candidateModel->saveCandidateResume(
        $candidateId,
        $data['resume_path'],
        $parsedResume
    );

    if (!$resumeResult['success']) {
        error_log(
            'Candidate resume metadata could not be saved for candidate ' .
            $candidateId . ': ' . ($resumeResult['error'] ?? $resumeResult['message'])
        );
        $warning = 'Candidate was saved, but resume processing metadata could not be saved.';
    }

    /**
     * Success
     */
    $this->jsonResponse(201, [
        'success' => true,
        'message' => $warning ?: 'Candidate created successfully.',
        'candidate_id' => $candidateId,
        'ai_processed' => $aiProcessed,
        'warning' => $warning
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | LIST CANDIDATES
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

        $result = $this->candidateModel->getCandidates(
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
    | GET SINGLE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $result = $this->candidateModel->getCandidateById($id);

        if (!$result['success']) {

            $statusCode =
                ($result['message'] === 'Candidate not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CANDIDATE
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

        $existing = $this->candidateModel->getCandidateById($id);

        if (!$existing['success']) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Candidate not found.'
            ]);
        }

        $data['updated_by'] = (int)$authUser['id'];

        // Keep old resume if no new resume uploaded
        $data['resume_path'] =
            $existing['data']['resume_path'];

        if (
            isset($_FILES['resume_file']) &&
            $_FILES['resume_file']['error'] === UPLOAD_ERR_OK
        ) {
            $uploadedResume = uploadFile(
                $_FILES['resume_file'],
                'candidates/resumes'
            );

            if ($uploadedResume) {
                $data['resume_path'] = $uploadedResume;
            }
        }

        $result = $this->candidateModel->updateCandidate(
            $id,
            $data
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE CANDIDATE
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        $result = $this->candidateModel->deleteCandidate($id);

        if (!$result['success']) {

            $statusCode =
                ($result['message'] === 'Candidate not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }
}
