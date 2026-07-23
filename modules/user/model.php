<?php
require_once __DIR__ . '/../../config/db.php';

class UserModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Create a new user
     */
    public function createUser($data)
    {
        // Check if email already exists
        $stmt = $this->conn->prepare("
            SELECT id
            FROM users
            WHERE email = ? OR nick_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $data['email'], $data['nick_name']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'Email or Nick Name already exists.'
            ];
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        // Insert user
        $stmt = $this->conn->prepare("
            INSERT INTO users (
                employee_id,
                nick_name,
                email,
                password,
                position_id,
                profile_image,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $profileImage = $data['profile_image'] ?? null;
        $status = $data['status'] ?? 'Active';

        $stmt->bind_param(
            "isssiss",
            $data['employee_id'],
            $data['nick_name'],
            $data['email'],
            $hashedPassword,
            $data['position_id'],
            $profileImage,
            $status
        );

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to create user.',
                'error'   => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'User created successfully.',
            'user_id' => $this->conn->insert_id
        ];
    }

    /**
     * Update an existing user
     */
    public function updateUser($id, $data)
    {
        // Check if email or nick_name already used by another user
        $stmt = $this->conn->prepare("
            SELECT id
            FROM users
            WHERE (email = ? OR nick_name = ?)
              AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "ssi",
            $data['email'],
            $data['nick_name'],
            $id
        );
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'Email or Nick Name already exists.'
            ];
        }

        // If password is provided, update it
        if (!empty($data['password'])) {
            $hashedPassword = password_hash(
                $data['password'],
                PASSWORD_BCRYPT
            );

            $stmt = $this->conn->prepare("
                UPDATE users
                SET
                    employee_id = ?,
                    nick_name = ?,
                    email = ?,
                    password = ?,
                    position_id = ?,
                    profile_image = ?,
                    status = ?
                WHERE id = ?
            ");

            $profileImage = $data['profile_image'] ?? null;
            $status = $data['status'] ?? 'Active';

            $stmt->bind_param(
                "isssissi",
                $data['employee_id'],
                $data['nick_name'],
                $data['email'],
                $hashedPassword,
                $data['position_id'],
                $profileImage,
                $status,
                $id
            );
        } else {
            // Update without changing password
            $stmt = $this->conn->prepare("
                UPDATE users
                SET
                    employee_id = ?,
                    nick_name = ?,
                    email = ?,
                    position_id = ?,
                    profile_image = ?,
                    status = ?
                WHERE id = ?
            ");

            $profileImage = $data['profile_image'] ?? null;
            $status = $data['status'] ?? 'Active';

            $stmt->bind_param(
                "ississi",
                $data['employee_id'],
                $data['nick_name'],
                $data['email'],
                $data['position_id'],
                $profileImage,
                $status,
                $id
            );
        }

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Failed to update user.',
                'error'   => $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'User updated successfully.'
        ];
    }

    /**
     * Get one user by ID
     */
    public function getUserById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT
                u.id,
                u.employee_id,
                u.nick_name,
                u.email,
                u.position_id,
                u.profile_image,
                u.last_login,
                u.status,
                u.created_at,
                u.updated_at,
                p.position_name
            FROM users u
            LEFT JOIN positions p
                ON p.id = u.position_id
            WHERE u.id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }


    public function getUsers($page, $limit, $search)
{
    // Sanitize pagination values
    $page = max(1, (int)$page);
    $limit = max(1, (int)$limit);
    $offset = ($page - 1) * $limit;

    $search = trim($search ?? '');

    /*
    |--------------------------------------------------------------------------
    | Build WHERE clause
    |--------------------------------------------------------------------------
    | Search in:
    | - nick_name
    | - email
    | - employee_id
    | - position_name
    */
    $where = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $where = "
            WHERE (
                u.nick_name LIKE ?
                OR u.email LIKE ?
                OR CAST(u.employee_id AS CHAR) LIKE ?
                OR p.position_name LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params = [
            $searchValue,
            $searchValue,
            $searchValue,
            $searchValue
        ];

        $types = 'ssss';
    }

    /*
    |--------------------------------------------------------------------------
    | Get Total Records Count
    |--------------------------------------------------------------------------
    */
    $countSql = "
        SELECT COUNT(*) AS total
        FROM users u
        LEFT JOIN positions p ON p.id = u.position_id
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

    /*
    |--------------------------------------------------------------------------
    | Get Paginated Data
    |--------------------------------------------------------------------------
    */
    $dataSql = "
        SELECT
            u.id,
            u.employee_id,
            u.nick_name,
            u.email,
            u.position_id,
            p.position_name,
            u.profile_image,
            u.last_login,
            u.status,
            u.created_at,
            u.updated_at
        FROM users u
        LEFT JOIN positions p ON p.id = u.position_id
        $where
        ORDER BY u.id DESC
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

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Return Paginated Response Data
    |--------------------------------------------------------------------------
    */
    return [
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'search' => $search,
        'total_records' => $totalRecords,
        'total_pages' => (int) ceil($totalRecords / $limit),
        'data' => $users
    ];
}

    /**
     * Get all users
     */
    public function getAllUsers()
    {
        $sql = "
            SELECT
                u.id,
                u.employee_id,
                u.nick_name,
                u.email,
                u.position_id,
                u.profile_image,
                u.last_login,
                u.status,
                u.created_at,
                u.updated_at,
                p.position_name
            FROM users u
            LEFT JOIN positions p
                ON p.id = u.position_id
            ORDER BY u.id DESC
        ";

        $result = $this->conn->query($sql);

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        return $users;
    }


    public function deleteUser($id)
{
    // Check if user exists
    $stmt = $this->conn->prepare("
        SELECT id, nick_name
        FROM users
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
            'message' => 'User not found.'
        ];
    }

    $user = $result->fetch_assoc();

    // Delete user
    $stmt = $this->conn->prepare("
        DELETE FROM users
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
            'message' => 'Failed to delete user.',
            'error' => $stmt->error
        ];
    }

    return [
        'success' => true,
        'message' => 'User deleted successfully.',
        'deleted_user' => [
            'id' => $id,
            'nick_name' => $user['nick_name']
        ]
    ];
}


}