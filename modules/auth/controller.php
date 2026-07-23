<?php
require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../config/jwt.php';

class AuthController
{
    private $authModel;

    public function __construct()
    {
        $this->authModel = new AuthModel();
    }

    private function jsonResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function getRequestData()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        $data = $this->getRequestData();

        if (empty($data['email']) || empty($data['password'])) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Email and password are required.'
            ]);
        }

        $user = $this->authModel->findByEmail($data['email']);

        if (!$user) {
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'User not found.'
            ]);
        }

        if ($user['status'] !== 'Active') {
            $this->jsonResponse(403, [
                'success' => false,
                'message' => 'User is not active.'
            ]);
        }

        // Verify hashed password
        if (!password_verify($data['password'], $user['password'])) {
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Invalid password.'
            ]);
        }

        // Generate JWT token
        $payload = [
            'user_id' => $user['id'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24) // 24 hours
        ];

        $token = generateJWT($payload);

        // Update last login
        $this->authModel->updateLastLogin($user['id']);

        // Remove password before returning user
        unset($user['password']);

        $this->jsonResponse(200, [
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user
        ]);
    }
}