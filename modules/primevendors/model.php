<?php

require_once __DIR__ . '/../../config/db.php';

class PrimeVendorModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    public function createPrimeVendor($data)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO primevendors (
                vcompany,
                rname,
                phone,
                documents,
                email,
                fax,
                caddress,
                blocation,
                feedback
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return $this->databaseError('Prepare failed.');
        }

        $vcompany = $data['vcompany'] ?? '';
        $rname = $data['rname'] ?? '';
        $phone = $data['phone'] ?? '';
        $documents = $data['documents'] ?? '';
        $email = $data['email'] ?? '';
        $fax = $data['fax'] ?? '';
        $caddress = $data['caddress'] ?? '';
        $blocation = $data['blocation'] ?? '';
        $feedback = $data['feedback'] ?? '';

        $stmt->bind_param(
            'sssssssss',
            $vcompany,
            $rname,
            $phone,
            $documents,
            $email,
            $fax,
            $caddress,
            $blocation,
            $feedback
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to create prime vendor.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Prime vendor created successfully.',
            'primevendor_id' => $this->conn->insert_id
        ];
    }

    public function getPrimeVendorById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM primevendors
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return $this->databaseError('Prepare failed.');
        }

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to get prime vendor.',
                'error' => $stmt->error
            ];
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Prime vendor not found.'
            ];
        }

        return [
            'success' => true,
            'data' => $result->fetch_assoc()
        ];
    }

    public function getPrimeVendors($page, $limit, $search)
    {
        $page = max(1, (int)$page);
        $limit = min(100, max(1, (int)$limit));
        $offset = ($page - 1) * $limit;
        $search = trim($search ?? '');

        $where = '';
        $params = [];
        $types = '';

        if ($search !== '') {
            $where = "WHERE (
                vcompany LIKE ?
                OR rname LIKE ?
                OR phone LIKE ?
                OR email LIKE ?
                OR fax LIKE ?
                OR caddress LIKE ?
                OR blocation LIKE ?
                OR feedback LIKE ?
            )";

            $searchValue = '%' . $search . '%';
            for ($i = 0; $i < 8; $i++) {
                $params[] = $searchValue;
                $types .= 's';
            }
        }

        $countStmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM primevendors
            $where
        ");

        if (!$countStmt) {
            return $this->databaseError('Failed to prepare count query.');
        }

        if ($params) {
            $countStmt->bind_param($types, ...$params);
        }

        if (!$countStmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to count prime vendors.',
                'error' => $countStmt->error
            ];
        }

        $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];

        $dataStmt = $this->conn->prepare("
            SELECT *
            FROM primevendors
            $where
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");

        if (!$dataStmt) {
            return $this->databaseError('Failed to prepare data query.');
        }

        $dataParams = $params;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataStmt->bind_param($types . 'ii', ...$dataParams);

        if (!$dataStmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to get prime vendors.',
                'error' => $dataStmt->error
            ];
        }

        $rows = [];
        $result = $dataStmt->get_result();

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

    public function updatePrimeVendor($id, $data)
    {
        $stmt = $this->conn->prepare("
            UPDATE primevendors
            SET
                vcompany = ?,
                rname = ?,
                phone = ?,
                documents = ?,
                email = ?,
                fax = ?,
                caddress = ?,
                blocation = ?,
                feedback = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            return $this->databaseError('Prepare failed.');
        }

        $vcompany = $data['vcompany'] ?? '';
        $rname = $data['rname'] ?? '';
        $phone = $data['phone'] ?? '';
        $documents = $data['documents'] ?? '';
        $email = $data['email'] ?? '';
        $fax = $data['fax'] ?? '';
        $caddress = $data['caddress'] ?? '';
        $blocation = $data['blocation'] ?? '';
        $feedback = $data['feedback'] ?? '';

        $stmt->bind_param(
            'sssssssssi',
            $vcompany,
            $rname,
            $phone,
            $documents,
            $email,
            $fax,
            $caddress,
            $blocation,
            $feedback,
            $id
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to update prime vendor.',
                'error' => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Prime vendor updated successfully.',
            'affected_rows' => $stmt->affected_rows
        ];
    }

    public function deletePrimeVendor($id)
    {
        $stmt = $this->conn->prepare('DELETE FROM primevendors WHERE id = ?');

        if (!$stmt) {
            return $this->databaseError('Prepare failed.');
        }

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to delete prime vendor.',
                'error' => $stmt->error
            ];
        }

        if ($stmt->affected_rows === 0) {
            return [
                'success' => false,
                'message' => 'Prime vendor not found.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Prime vendor deleted successfully.'
        ];
    }

    private function databaseError($message)
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => $this->conn->error
        ];
    }
}
