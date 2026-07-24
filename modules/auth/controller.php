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
        $identifier=strtolower(trim($data['email']));
        $ip=$_SERVER['REMOTE_ADDR']??'unknown';
        if($this->authModel->isRateLimited($identifier,$ip)){
            $this->jsonResponse(429,['success'=>false,'message'=>'Too many login attempts. Please try again later.']);
        }

        $user = $this->authModel->findByEmail($data['email']);

        if (!$user) {
            $this->authModel->recordLoginAttempt($identifier,$ip,false);
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Invalid credentials.'
            ]);
        }

        if ($user['status'] !== 'Active'||$user['position_status'] !== 'Active') {
            $this->authModel->recordLoginAttempt($identifier,$ip,false);
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Invalid credentials.'
            ]);
        }

        // Verify hashed password
        if (!password_verify($data['password'], $user['password'])) {
            $this->authModel->recordLoginAttempt($identifier,$ip,false);
            $this->jsonResponse(401, [
                'success' => false,
                'message' => 'Invalid credentials.'
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
        $this->authModel->recordLoginAttempt($identifier,$ip,true);

        // Remove password before returning user
        unset($user['password']);

        $profile=$this->authModel->getAuthorizationProfile((int)$user['id']);
        $this->jsonResponse(200, [
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $profile['user'],
            'resources' => $profile['resources'],
            'menus' => array_values(array_filter($profile['resources'],fn($r)=>$r['permissions']['view']&&$r['route'])),
            'routes' => array_values(array_map(fn($r)=>$r['route'],array_filter($profile['resources'],fn($r)=>$r['permissions']['view']&&$r['route'])))
        ]);
    }

    public function me()
    {
        $profile=$this->authModel->getAuthorizationProfile((int)authUser()['id']);
        if(!$profile)$this->jsonResponse(401,['success'=>false,'message'=>'Unauthorized.']);
        $visible=array_values(array_filter($profile['resources'],fn($r)=>$r['permissions']['view']));
        $this->jsonResponse(200,['success'=>true]+$profile+[
            'menus'=>array_values(array_filter($visible,fn($r)=>$r['route'])),
            'routes'=>array_values(array_map(fn($r)=>$r['route'],array_filter($visible,fn($r)=>$r['route'])))
        ]);
    }
}
