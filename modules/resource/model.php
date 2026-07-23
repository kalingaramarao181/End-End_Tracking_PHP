<?php
// File: modules/resource/model.php

require_once __DIR__ . '/../../config/db.php';

class ResourceModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Create Resource
     */
    public function createResource($data)
{
    // Check duplicate resource_name
    $stmt = $this->conn->prepare("
        SELECT id
        FROM resources
        WHERE resource_name = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Prepare failed.',
            'error' => $this->conn->error
        ];
    }

    $stmt->bind_param("s", $data['resource_name']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Resource name already exists.'
        ];
    }

    // Optional fields
    $displayName = $data['display_name'] ?? $data['resource_name'];
    $icon = $data['icon'] ?? null;
    $route = $data['route'] ?? null;
    $parentId = $data['parent_id'] ?? null;
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $status = $data['status'] ?? 'Active';

    // Insert resource
    $stmt = $this->conn->prepare("
        INSERT INTO resources (
            resource_name,
            display_name,
            icon,
            route,
            parent_id,
            sort_order,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Prepare failed.',
            'error' => $this->conn->error
        ];
    }

    $stmt->bind_param(
        "ssssiis",
        $data['resource_name'],
        $displayName,
        $icon,
        $route,
        $parentId,
        $sortOrder,
        $status
    );

    if (!$stmt->execute()) {
        return [
            'success' => false,
            'message' => 'Failed to create resource.',
            'error' => $stmt->error
        ];
    }

    return [
        'success' => true,
        'message' => 'Resource created successfully.',
        'resource_id' => $this->conn->insert_id
    ];
}


    public function getResourcesByUserId($userId)
{
    $sql = "
        SELECT
            r.id,
            r.resource_name,
            r.display_name,
            r.icon,
            r.route,
            r.parent_id,
            r.sort_order,
            r.status,

            COALESCE(up.can_view, p.can_view, 0) AS can_view,
            COALESCE(up.can_create, p.can_create, 0) AS can_create,
            COALESCE(up.can_edit, p.can_edit, 0) AS can_edit,
            COALESCE(up.can_delete, p.can_delete, 0) AS can_delete,
            COALESCE(up.can_export, p.can_export, 0) AS can_export,
            COALESCE(up.can_approve, p.can_approve, 0) AS can_approve

        FROM users u

        INNER JOIN resources r
            ON r.status = 'Active'

        LEFT JOIN permissions p
            ON p.position_id = u.position_id
           AND p.resource_id = r.id

        LEFT JOIN user_permissions up
            ON up.user_id = u.id
           AND up.resource_id = r.id

        WHERE u.id = ?
          AND COALESCE(up.can_view, p.can_view, 0) = 1

        ORDER BY r.sort_order ASC, r.id ASC
    ";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Failed to prepare query.',
            'error' => $this->conn->error
        ];
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    $resources = [];
    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }

    return [
        'success' => true,
        'user_id' => (int)$userId,
        'total_resources' => count($resources),
        'data' => $resources
    ];
}

    /**
     * Get Resource By ID
     */
    public function getResourceById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM resources
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Resource not found.'
            ];
        }

        return [
            'success' => true,
            'data' => $result->fetch_assoc()
        ];
    }

    /**
     * Get Resources with Pagination and Search
     */
    public function getResources($page, $limit, $search)
{
    $page = max(1, (int)$page);
    $limit = max(1, (int)$limit);
    $offset = ($page - 1) * $limit;
    $search = trim($search ?? '');

    $where = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $where = "
            WHERE (
                resource_name LIKE ?
                OR display_name LIKE ?
                OR route LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params = [
            $searchValue,
            $searchValue,
            $searchValue
        ];

        $types = 'sss';
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM resources
        $where
    ";

    $stmt = $this->conn->prepare($countSql);

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Failed to prepare count query.',
            'error' => $this->conn->error
        ];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalRecords = (int)$countResult->fetch_assoc()['total'];

    $dataSql = "
        SELECT *
        FROM resources
        $where
        ORDER BY sort_order ASC, id ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $this->conn->prepare($dataSql);

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Failed to prepare data query.',
            'error' => $this->conn->error
        ];
    }

    if (!empty($params)) {
        $bindTypes = $types . 'ii';
        $bindParams = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return [
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'search' => $search,
        'total_records' => $totalRecords,
        'total_pages' => (int)ceil($totalRecords / $limit),
        'data' => $rows
    ];
}

    /**
     * Update Resource
     */
    public function updateResource($id, $data)
{
    $stmt = $this->conn->prepare("
        SELECT id
        FROM resources
        WHERE resource_name = ?
          AND id != ?
        LIMIT 1
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Prepare failed.',
            'error' => $this->conn->error
        ];
    }

    $stmt->bind_param("si", $data['resource_name'], $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Resource name already exists.'
        ];
    }

    $displayName = $data['display_name'] ?? $data['resource_name'];
    $icon = $data['icon'] ?? null;
    $route = $data['route'] ?? null;
    $parentId = $data['parent_id'] ?? null;
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $status = $data['status'] ?? 'Active';

    $stmt = $this->conn->prepare("
        UPDATE resources
        SET
            resource_name = ?,
            display_name = ?,
            icon = ?,
            route = ?,
            parent_id = ?,
            sort_order = ?,
            status = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Prepare failed.',
            'error' => $this->conn->error
        ];
    }

    $stmt->bind_param(
        "ssssiisi",
        $data['resource_name'],
        $displayName,
        $icon,
        $route,
        $parentId,
        $sortOrder,
        $status,
        $id
    );

    if (!$stmt->execute()) {
        return [
            'success' => false,
            'message' => 'Failed to update resource.',
            'error' => $stmt->error
        ];
    }

    return [
        'success' => true,
        'message' => 'Resource updated successfully.',
        'affected_rows' => $stmt->affected_rows
    ];
}

    /**
     * Delete Resource
     */
    public function deleteResource($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM resources
            WHERE id = ?
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to delete resource.',
                'error' => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                'success' => false,
                'message' => 'Resource not found.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ];
    }
}