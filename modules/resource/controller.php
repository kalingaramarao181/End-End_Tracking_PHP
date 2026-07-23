<?php
// File: modules/resource/controller.php

require_once __DIR__ . '/model.php';

class ResourceController
{
    private $resourceModel;

    public function __construct()
    {
        $this->resourceModel = new ResourceModel();
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

    public function myResources()
{
    require_once __DIR__ . '/../../middleware/auth.php';

    $user = authUser();

    if (!$user || !isset($user['id'])) {
        $this->jsonResponse(401, [
            'success' => false,
            'message' => 'Unauthorized.'
        ]);
    }

    $result = $this->resourceModel->getResourcesByUserId($user['id']);

    if (!$result['success']) {
        $this->jsonResponse(500, $result);
    }

    $this->jsonResponse(200, $result);
}

    /**
     * POST /api/resource/create
     */
    public function create()
    {
        $data = $this->getRequestData();

        if (empty($data['resource_name'])) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'resource_name is required.'
            ]);
        }

        $result = $this->resourceModel->createResource($data);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Resource name already exists.')
                ? 409
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(201, $result);
    }

    /**
     * GET /api/resource/list?page=1&limit=10&search=users
     */
    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $result = $this->resourceModel->getResources(
            $page,
            $limit,
            $search
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * GET /api/resource/{id}
     */
    public function show($id)
    {
        $result = $this->resourceModel->getResourceById($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Resource not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * PUT /api/resource/update/{id}
     */
    public function update($id)
    {
        $data = $this->getRequestData();

        if (empty($data['resource_name'])) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'resource_name is required.'
            ]);
        }

        $result = $this->resourceModel->updateResource($id, $data);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Resource name already exists.')
                ? 409
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * DELETE /api/resource/delete/{id}
     */
    public function delete($id)
    {
        $result = $this->resourceModel->deleteResource($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Resource not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }
}