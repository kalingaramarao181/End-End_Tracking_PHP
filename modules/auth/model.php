<?php
require_once __DIR__ . '/../../config/db.php';

class AuthModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Find active user by email
     */
    public function findByEmail($email)
    {
        $sql = "
            SELECT
                id,
                employee_id,
                nick_name,
                email,
                password,
                position_id,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId)
    {
        $stmt = $this->conn->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
    }
}