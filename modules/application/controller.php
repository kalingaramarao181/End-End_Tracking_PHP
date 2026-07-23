<?php
// File: modules/application/controller.php

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../middleware/upload.php';

class ApplicationController
{
    private $applicationModel;

    public function __construct()
    {
        $this->applicationModel = new ApplicationModel();
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
     * Read JSON request body
     */
    private function getRequestData()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    public function submissionDashboard()
    {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $category = $_GET['category'] ?? null;

        $model = new ApplicationModel();

        $result = $model->getSubmissionDashboard(
            $startDate,
            $endDate,
            $category
        );

        echo json_encode($result);
    }


    public function dashboardSummary()
    {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $category = $_GET['category'] ?? null;

        $model = new ApplicationModel();

        $result = $model->getDashboardSummary(
            $startDate,
            $endDate,
            $category
        );

        echo json_encode($result);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE APPLICATION
    | POST /api/application/create
    |--------------------------------------------------------------------------
    | - employee_id is automatically taken from JWT.
    | - Recruiters and Bench Sales both use the same endpoint.
    | - Frontend should send candidate_id for new records.
    */


    public function create()
    {
        require_once __DIR__ . '/../../middleware/auth.php';

        // Since files are uploaded, use $_POST
        $data = $_POST;

        // Logged-in user
        $authUser = authUser();

        if (!$authUser || !isset($authUser['id'])) {
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Unauthorized.'
            ]);
            return;
        }

        // Store submitter
        $data['employee_id'] = (int)$authUser['id'];

        // Upload files
        $data['resume_path'] = uploadFile(
            $_FILES['resume_file'] ?? null,
            'applications/resumes'
        );

        $data['r2r_path'] = uploadFile(
            $_FILES['r2r_file'] ?? null,
            'applications/r2r'
        );

        $data['driving_path'] = uploadFile(
            $_FILES['driving_file'] ?? null,
            'applications/driving'
        );

        $data['visa_path'] = uploadFile(
            $_FILES['visa_file'] ?? null,
            'applications/visa'
        );

        $data['msc_path'] = uploadFile(
            $_FILES['msc_file'] ?? null,
            'applications/msc'
        );

        // Validation
        if (
            empty($data['candidate_id']) &&
            empty($data['candidate_name'])
        ) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'candidate_id or candidate_name is required.'
            ]);
            return;
        }

        $result = $this->applicationModel->createApplication($data);

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
            return;
        }

        $this->jsonResponse(201, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST APPLICATIONS
    | GET /api/application/list?page=1&limit=10&search=John&position_id=3
    |--------------------------------------------------------------------------
    |
    | Admin / Super Admin:
    |   - Can view all applications.
    |   - Can optionally filter by position_id.
    |
    | Bench Sales / Recruiters / Others:
    |   - Can view only their own applications.
    */
    public function index($forcedPositionId = null)
    {
        require_once __DIR__ . '/../../middleware/auth.php';
        require_once __DIR__ . '/../../config/db.php';

        $authUser = authUser();

        if (!$authUser || !isset($authUser['id'])) {
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Unauthorized.'
            ]);
        }

        $loggedInUserId = (int) $authUser['id'];

        // Pagination 
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Optional filter for Admin
        $positionId = isset($_GET['position_id']) && $_GET['position_id'] !== ''
            ? (int) $_GET['position_id']
            : null;

        /*
        |--------------------------------------------------------------------------
        | Get Logged-In User Position
        |--------------------------------------------------------------------------
        */
        global $conn;

        $stmt = $conn->prepare("
            SELECT
                u.position_id,
                p.position_name
            FROM users u
            LEFT JOIN positions p
                ON u.position_id = p.id
            WHERE u.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $this->jsonResponse(500, [
                'success' => false,
                'message' => 'Failed to determine user position.',
                'error' => $conn->error
            ]);
        }

        $stmt->bind_param("i", $loggedInUserId);
        $stmt->execute();

        $userResult = $stmt->get_result();

        if ($userResult->num_rows === 0) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'User not found.'
            ]);
        }

        $user = $userResult->fetch_assoc();
        $positionName = strtolower(trim($user['position_name'] ?? ''));

        $isAdmin =
            $positionName === 'admin' ||
            $positionName === 'super admin';

        /*
        |--------------------------------------------------------------------------
        | Determine Filters
        |--------------------------------------------------------------------------
        */
        $employeeIdFilter = null;
        $positionIdFilter = null;

        if ($forcedPositionId !== null) {
            $positionIdFilter = (int)$forcedPositionId;
        } elseif ($isAdmin) {
            // Admin can optionally filter by position
            if ($positionId) {
                $positionIdFilter = $positionId;
            }
        }
        // else {
        //     // Non-admin users only see their own applications
        //     $employeeIdFilter = $loggedInUserId;
        // }

        /*
        |--------------------------------------------------------------------------
        | Fetch Data
        |--------------------------------------------------------------------------
        */
        $result = $this->applicationModel->getApplications(
            $page,
            $limit,
            $search,
            $employeeIdFilter,
            $positionIdFilter
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE APPLICATION
    | GET /api/application/{id}
    |--------------------------------------------------------------------------
    */
    public function show($id, $expectedPositionId = null)
    {
        $result = $this->applicationModel->getApplicationById($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Application not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        if ($expectedPositionId !== null && (int)($result['data']['position_id'] ?? 0) !== (int)$expectedPositionId) {
            $this->jsonResponse(404, ['success' => false, 'message' => 'Application not found in this module.']);
        }

        $this->jsonResponse(200, $result);
    }

    /*
|--------------------------------------------------------------------------
| UPDATE APPLICATION
| POST /api/application/update/{id}
|--------------------------------------------------------------------------
| Accepts multipart/form-data
| Supports file uploads
*/
    public function update($id, $expectedPositionId = null)

    {


        require_once __DIR__ . '/../../middleware/auth.php';

        // Get form-data
        $data = $_SERVER['REQUEST_METHOD'] === 'PUT'
            ? ($GLOBALS['_PUT'] ?? [])
            : $_POST;

        // Check application exists
        $existing = $this->applicationModel->getApplicationById($id);

        if (!$existing['success']) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Application not found.'
            ]);
        }

        $existingData = $existing['data'];

        if ($expectedPositionId !== null && (int)($existingData['position_id'] ?? 0) !== (int)$expectedPositionId) {
            $this->jsonResponse(404, ['success' => false, 'message' => 'Application not found in this module.']);
        }

        // Preserve employee_id
        $data['employee_id'] = $existingData['employee_id'];

        // Module-specific forms submit only their own fields. Preserve every
        // omitted application value instead of clearing unrelated data.
        foreach (['date_created','candidate_id','candidate_name','vendor','poc','feedback','client','emp_loc','rate','role','candidate_loc','remarks'] as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $existingData[$field] ?? null;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Upload Files
    |--------------------------------------------------------------------------
    | If new file uploaded -> replace path
    | Else -> keep existing path
    */

        $files = $_SERVER['REQUEST_METHOD'] === 'PUT'
            ? ($GLOBALS['_PUT_FILES'] ?? [])
            : $_FILES;

        $data['resume_path'] = uploadFile(
            $files['resume_file'] ?? null,
            'applications/resumes'
        ) ?: $existingData['resume_path'];

        $data['r2r_path'] = uploadFile(
            $files['r2r_file'] ?? null,
            'applications/r2r'
        ) ?: $existingData['r2r_path'];

        $data['driving_path'] = uploadFile(
            $files['driving_file'] ?? null,
            'applications/driving'
        ) ?: $existingData['driving_path'];

        $data['visa_path'] = uploadFile(
            $files['visa_file'] ?? null,
            'applications/visa'
        ) ?: $existingData['visa_path'];

        $data['msc_path'] = uploadFile(
            $files['msc_file'] ?? null,
            'applications/msc'
        ) ?: $existingData['msc_path'];

        /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

        if (
            empty($data['candidate_id']) &&
            empty($data['candidate_name'])
        ) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'candidate_id or candidate_name is required.'
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

        $result = $this->applicationModel->updateApplication($id, $data);

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE APPLICATION
    | DELETE /api/application/delete/{id}
    |--------------------------------------------------------------------------
    */
    public function delete($id, $expectedPositionId = null)
    {
        if ($expectedPositionId !== null) {
            $existing = $this->applicationModel->getApplicationById($id);
            if (!$existing['success'] || (int)($existing['data']['position_id'] ?? 0) !== (int)$expectedPositionId) {
                $this->jsonResponse(404, ['success' => false, 'message' => 'Application not found in this module.']);
            }
        }
        $result = $this->applicationModel->deleteApplication($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Application not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }


    public function performanceDashboard()
    {
        $result = $this->applicationModel->getPerformanceDashboard();

        echo json_encode($result);
    }

    /*
|--------------------------------------------------------------------------
| UPDATE PROCESS
|--------------------------------------------------------------------------
*/

    public function updateProcess($id, $expectedPositionId = null)
    {
        if ($expectedPositionId !== null) {
            $existing = $this->applicationModel->getApplicationById($id);
            if (!$existing['success'] || (int)($existing['data']['position_id'] ?? 0) !== (int)$expectedPositionId) {
                $this->jsonResponse(404, ['success' => false, 'message' => 'Application not found in this module.']);
            }
        }
        $data = $this->getRequestData();

        if (!isset($data['process_id'])) {
            $this->jsonResponse(400, [
                "success" => false,
                "message" => "process_id is required."
            ]);
        }

        $processId = (int)$data['process_id'];

        if (!in_array($processId, [1, 2, 3])) {
            $this->jsonResponse(400, [
                "success" => false,
                "message" => "Invalid process_id."
            ]);
        }

        $result = $this->applicationModel->updateProcess(
            $id,
            $processId
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    public function recentActivities()
    {
        $result = $this->applicationModel->getRecentActivities();

        $this->jsonResponse(200, $result);
    }
}
