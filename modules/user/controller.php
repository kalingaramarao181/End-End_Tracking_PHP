<?php
require_once __DIR__ . '/model.php';

class UserController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
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
     * Get request body JSON
     */
    private function getRequestData()
    {
        $input = file_get_contents("php://input");
        return json_decode($input, true);
    }

    /**
     * POST /api/user/register
     * Permission required: users.can_create = 1
     */
    public function register()
    {
        $data = $this->getRequestData();

        // Required fields validation
        $requiredFields = [
            'employee_id',
            'nick_name',
            'email',
            'password',
            'position_id'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $this->jsonResponse(400, [
                    'success' => false,
                    'message' => "$field is required."
                ]);
            }
        }

        // Email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Invalid email address.'
            ]);
        }

        // Password length validation
        if (strlen($data['password']) < 6) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Password must be at least 6 characters.'
            ]);
        }

        $result = $this->userModel->createUser($data);

        if (!$result['success']) {
            $this->jsonResponse(400, $result);
        }

        $this->jsonResponse(201, $result);
    }

    /**
     * PUT /api/user/update/{id}
     * Permission required: users.can_edit = 1
     */
    public function update($id)
    {
        $data = $this->getRequestData();

        // Check if user exists
        $existingUser = $this->userModel->getUserById($id);

        if (!$existingUser) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'User not found.'
            ]);
        }

        // Required fields validation
        $requiredFields = [
            'employee_id',
            'nick_name',
            'email',
            'position_id'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $this->jsonResponse(400, [
                    'success' => false,
                    'message' => "$field is required."
                ]);
            }
        }

        // Email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Invalid email address.'
            ]);
        }

        // Optional password validation
        if (isset($data['password']) && $data['password'] !== '') {
            if (strlen($data['password']) < 6) {
                $this->jsonResponse(400, [
                    'success' => false,
                    'message' => 'Password must be at least 6 characters.'
                ]);
            }
        }

        $result = $this->userModel->updateUser($id, $data);

        if (!$result['success']) {
            $this->jsonResponse(400, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * GET /api/user/{id}
     * Permission required: users.can_view = 1
     */
    public function show($id)
    {
        $user = $this->userModel->getUserById($id);

        if (!$user) {
            $this->jsonResponse(404, [
                'success' => false,
                'message' => 'User not found.'
            ]);
        }

        $this->jsonResponse(200, [
            'success' => true,
            'data' => $user
        ]);
    }



    /**
     * GET /api/user/list
     * Permission required: users.can_view = 1
     */
    public function paginatedList()
{
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $result = $this->userModel->getUsers($page, $limit, $search);

    if (!$result['success']) {
        $this->jsonResponse(500, $result);
    }

    $this->jsonResponse(200, $result);
}


public function delete($id)
{
    $result = $this->userModel->deleteUser($id);

    if (!$result['success']) {
        $statusCode = ($result['message'] === 'User not found.') ? 404 : 400;
        $this->jsonResponse($statusCode, $result);
    }

    $this->jsonResponse(200, $result);
}

}