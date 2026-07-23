<?php
// File: modules/permission/model.php

require_once __DIR__ . '/../../config/db.php';

class PermissionModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Get all resources with permissions for a specific position.
     *
     * Returns every resource even if no permission row exists yet.
     */
    public function getPermissionsByPosition($positionId)
    {
        $sql = "
            SELECT
                r.id AS resource_id,
                r.resource_name,
                r.display_name,

                COALESCE(p.can_view, 0) AS can_view,
                COALESCE(p.can_create, 0) AS can_create,
                COALESCE(p.can_edit, 0) AS can_edit,
                COALESCE(p.can_delete, 0) AS can_delete,
                COALESCE(p.can_export, 0) AS can_export,
                COALESCE(p.can_approve, 0) AS can_approve

            FROM resources r
            LEFT JOIN permissions p
                ON p.resource_id = r.id
               AND p.position_id = ?

            ORDER BY r.sort_order ASC, r.id ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare query.',
                'error'   => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $positionId);
        $stmt->execute();

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return [
            'success' => true,
            'position_id' => (int)$positionId,
            'data' => $rows
        ];
    }

    /**
     * Save or update permissions for a position.
     *
     * $permissions format:
     * [
     *   {
     *     "resource_id": 1,
     *     "can_view": 1,
     *     "can_create": 1,
     *     "can_edit": 1,
     *     "can_delete": 0,
     *     "can_export": 0,
     *     "can_approve": 0
     *   }
     * ]
     */
    public function savePermissionsByPosition($positionId, $permissions)
    {
        $this->conn->begin_transaction();

        try {
            $sql = "
                INSERT INTO permissions (
                    position_id,
                    resource_id,
                    can_view,
                    can_create,
                    can_edit,
                    can_delete,
                    can_export,
                    can_approve
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    can_view = VALUES(can_view),
                    can_create = VALUES(can_create),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete),
                    can_export = VALUES(can_export),
                    can_approve = VALUES(can_approve)
            ";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                throw new Exception($this->conn->error);
            }

            foreach ($permissions as $row) {
                $resourceId = (int)$row['resource_id'];
                $canView = (int)($row['can_view'] ?? 0);
                $canCreate = (int)($row['can_create'] ?? 0);
                $canEdit = (int)($row['can_edit'] ?? 0);
                $canDelete = (int)($row['can_delete'] ?? 0);
                $canExport = (int)($row['can_export'] ?? 0);
                $canApprove = (int)($row['can_approve'] ?? 0);

                $stmt->bind_param(
                    "iiiiiiii",
                    $positionId,
                    $resourceId,
                    $canView,
                    $canCreate,
                    $canEdit,
                    $canDelete,
                    $canExport,
                    $canApprove
                );

                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Permissions saved successfully.'
            ];
        } catch (Exception $e) {
            $this->conn->rollback();

            return [
                'success' => false,
                'message' => 'Failed to save permissions.',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Delete all permissions for a position.
     */
    public function deletePermissionsByPosition($positionId)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM permissions
            WHERE position_id = ?
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare delete query.',
                'error'   => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $positionId);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to delete permissions.',
                'error'   => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Permissions deleted successfully.',
            'affected_rows' => $stmt->affected_rows
        ];
    }

    /**
     * Get effective permissions for a user.
     * (Role permissions + user overrides if available)
     */
    public function getEffectivePermissionsByUser($userId)
    {
        $sql = "
            SELECT
                r.id AS resource_id,
                r.resource_name,
                r.display_name,

                COALESCE(up.can_view, p.can_view, 0) AS can_view,
                COALESCE(up.can_create, p.can_create, 0) AS can_create,
                COALESCE(up.can_edit, p.can_edit, 0) AS can_edit,
                COALESCE(up.can_delete, p.can_delete, 0) AS can_delete,
                COALESCE(up.can_export, p.can_export, 0) AS can_export,
                COALESCE(up.can_approve, p.can_approve, 0) AS can_approve

            FROM users u

            INNER JOIN resources r

            LEFT JOIN permissions p
                ON p.position_id = u.position_id
               AND p.resource_id = r.id

            LEFT JOIN user_permissions up
                ON up.user_id = u.id
               AND up.resource_id = r.id

            WHERE u.id = ?

            ORDER BY r.sort_order ASC, r.id ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare query.',
                'error'   => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return [
            'success' => true,
            'user_id' => (int)$userId,
            'data' => $rows
        ];
    }
}