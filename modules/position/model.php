<?php
// File: modules/position/model.php

require_once __DIR__ . '/../../config/db.php';

class PositionModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Create Position
     */
    public function createPosition($data)
    {
        // Check duplicate position_name
        $stmt = $this->conn->prepare("
            SELECT id
            FROM positions
            WHERE position_name = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param("s", $data['position_name']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'Position name already exists.'
            ];
        }

        $description = $data['description'] ?? null;
        $status = $data['status'] ?? 'Active';

        // Insert position
        $stmt = $this->conn->prepare("
            INSERT INTO positions (
                position_name,
                description,
                status
            )
            VALUES (?, ?, ?)
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Prepare failed.',
                'error' => $this->conn->error
            ];
        }

        $stmt->bind_param(
            "sss",
            $data['position_name'],
            $description,
            $status
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to create position.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Position created successfully.',
            'position_id' => $this->conn->insert_id
        ];
    }

    /**
     * Get Position By ID
     */
    public function getPositionById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM positions
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
                'message' => 'Position not found.'
            ];
        }

        return [
            'success' => true,
            'data' => $result->fetch_assoc()
        ];
    }

    /**
     * Get Positions with Pagination and Search
     */
    public function getPositions($page, $limit, $search)
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
                    position_name LIKE ?
                    OR description LIKE ?
                    OR status LIKE ?
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

        // Count query
        $countSql = "
            SELECT COUNT(*) AS total
            FROM positions
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

        // Data query
        $dataSql = "
            SELECT *
            FROM positions
            $where
            ORDER BY id ASC
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
     * Update Position
     */
    public function updatePosition($id, $data)
    {
        // Check duplicate position_name
        $stmt = $this->conn->prepare("
            SELECT id
            FROM positions
            WHERE position_name = ?
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

        $stmt->bind_param("si", $data['position_name'], $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'Position name already exists.'
            ];
        }

        $description = $data['description'] ?? null;
        $status = $data['status'] ?? 'Active';

        $stmt = $this->conn->prepare("
            UPDATE positions
            SET
                position_name = ?,
                description = ?,
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
            "sssi",
            $data['position_name'],
            $description,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to update position.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Position updated successfully.',
            'affected_rows' => $stmt->affected_rows
        ];
    }

    /**
     * Delete Position
     */
    public function deletePosition($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM positions
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
                'message' => 'Failed to delete position.',
                'error' => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                'success' => false,
                'message' => 'Position not found.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Position deleted successfully.'
        ];
    }
}