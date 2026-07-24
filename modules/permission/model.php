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
                COALESCE(p.can_import, 0) AS can_import,
                COALESCE(p.can_upload, 0) AS can_upload,
                COALESCE(p.can_download, 0) AS can_download,
                COALESCE(p.can_approve, 0) AS can_approve,
                COALESCE(p.can_reject, 0) AS can_reject,
                COALESCE(p.can_assign, 0) AS can_assign,
                COALESCE(p.can_manage, 0) AS can_manage,
                COALESCE(p.can_print, 0) AS can_print,
                COALESCE(p.can_share, 0) AS can_share,
                COALESCE(p.data_scope, 'OWN') AS data_scope

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
            $actions=['view','create','edit','delete','export','import','upload','download','approve','reject','assign','manage','print','share'];
            $actionColumns=array_map(fn($a)=>"can_$a",$actions);
            $sql="INSERT INTO permissions(position_id,resource_id,".implode(',',$actionColumns).",data_scope)
                VALUES (".implode(',',array_fill(0,17,'?')).")
                ON DUPLICATE KEY UPDATE ".implode(',',array_map(fn($c)=>"$c=VALUES($c)",$actionColumns)).",
                data_scope=VALUES(data_scope)";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                throw new Exception($this->conn->error);
            }

            foreach ($permissions as $row) {
                $resourceId = (int)$row['resource_id'];
                $values=[$positionId,$resourceId];
                foreach($actions as $action)$values[]=(int)($row["can_$action"]??0);
                $scope=in_array(($row['data_scope']??'OWN'),['OWN','TEAM','DEPARTMENT','OFFICE','ALL'],true)?$row['data_scope']:'OWN';
                $values[]=$scope;
                $stmt->bind_param(str_repeat('i',16).'s',...$values);

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
