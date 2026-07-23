<?php
// File: modules/permission/controller.php

require_once __DIR__ . '/model.php';

class PermissionController
{
    private $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new PermissionModel();
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
     * Get request body as JSON
     */
    private function getRequestData()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    /**
     * GET /api/permission/position/{position_id}
     */
    public function getByPosition($positionId)
    {
        $result = $this->permissionModel->getPermissionsByPosition($positionId);

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * PUT /api/permission/position/{position_id}
     *
     * Body:
     * [
     *   {
     *     "resource_id": 1,
     *     "can_view": 1,
     *     "can_create": 1,
     *     "can_edit": 1,
     *     "can_delete": 1,
     *     "can_export": 1,
     *     "can_approve": 0
     *   }
     * ]
     */
    public function saveByPosition($positionId)
    {
        $data = $this->getRequestData();

        if (empty($data) || !is_array($data)) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Permissions array is required.'
            ]);
        }

        foreach ($data as $index => $row) {
            if (!isset($row['resource_id'])) {
                $this->jsonResponse(400, [
                    'success' => false,
                    'message' => "resource_id is required at index {$index}."
                ]);
            }
        }

        $result = $this->permissionModel->savePermissionsByPosition(
            $positionId,
            $data
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * DELETE /api/permission/position/{position_id}
     *
     * Deletes all permission rows for the given position.
     */
    public function deleteByPosition($positionId)
    {
        $result = $this->permissionModel->deletePermissionsByPosition(
            $positionId
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }

    /**
     * GET /api/permission/user/{user_id}
     *
     * Returns effective permissions:
     * Role permissions + user overrides.
     */
    public function getByUser($userId)
    {
        $result = $this->permissionModel->getEffectivePermissionsByUser(
            $userId
        );

        if (!$result['success']) {
            $this->jsonResponse(500, $result);
        }

        $this->jsonResponse(200, $result);
    }
}