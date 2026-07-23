<?php
// File: modules/position/controller.php

require_once __DIR__ . '/model.php';

class PositionController
{
    private $positionModel;

    public function __construct()
    {
        $this->positionModel = new PositionModel();
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

    /**
     * POST /api/position/create
     */
    public function create()
    {
        $data = $this->getRequestData();

        if (empty($data['position_name'])) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'position_name is required.'
            ]);
        }

        $result = $this->positionModel->createPosition($data);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Position name already exists.')
                ? 409
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(201, $result);
    }

    /**
     * GET /api/position/list?page=1&limit=10&search=admin
     */
    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $result = $this->positionModel->getPositions(
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
     * GET /api/position/{id}
     */
    public function show($id)
    {
        $result = $this->positionModel->getPositionById($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Position not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * PUT /api/position/update/{id}
     */
    public function update($id)
    {
        $data = $this->getRequestData();

        if (empty($data['position_name'])) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'position_name is required.'
            ]);
        }

        $result = $this->positionModel->updatePosition($id, $data);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Position name already exists.')
                ? 409
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * DELETE /api/position/delete/{id}
     */
    public function delete($id)
    {
        $result = $this->positionModel->deletePosition($id);

        if (!$result['success']) {
            $statusCode = ($result['message'] === 'Position not found.')
                ? 404
                : 500;

            $this->jsonResponse($statusCode, $result);
        }

        $this->jsonResponse(200, $result);
    }
}