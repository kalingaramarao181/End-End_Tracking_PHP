<?php
require_once __DIR__ . '/../config/jwt.php';

/**
 * Get Authorization header
 */
function getAuthorizationHeader()
{
    $headers = null;

    // Apache
    if (isset($_SERVER['Authorization'])) {
        return trim($_SERVER["Authorization"]);
    }

    // Nginx or FastCGI
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER["HTTP_AUTHORIZATION"]);
    }

    // Shared Hosting
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
    }

    // Fallback
    if (function_exists('getallheaders')) {

        $headers = getallheaders();

        foreach ($headers as $key => $value) {

            if (strtolower($key) === 'authorization') {
                return trim($value);
            }
        }
    }

    return null;
}
/**
 * Authenticate user using JWT token
 */
function authenticate()
{
    global $conn;

    $authHeader = getAuthorizationHeader();

    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['error' => 'Token missing']);
        exit;
    }

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Authorization header']);
        exit;
    }

    $token = trim($matches[1]);
    $decoded = verifyJWT($token);

    if (!$decoded || !isset($decoded->user_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    $userId = (int)$decoded->user_id;

    // Load user from database
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.employee_id,
            u.nick_name,
            u.email,
            u.position_id,
            u.status,
            p.status AS position_status,
            p.position_name
        FROM users u
        LEFT JOIN positions p ON p.id = u.position_id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $user = $result->fetch_assoc();

    if ($user['status'] !== 'Active') {
        http_response_code(403);
        echo json_encode(['error' => 'User is inactive']);
        exit;
    }
    if ($user['position_status'] !== 'Active') {
        http_response_code(403);
        echo json_encode(['error' => 'Position is inactive']);
        exit;
    }

    // Store user as array
    $GLOBALS['auth_user'] = $user;

    return $user;
}

/**
 * Get authenticated user
 */
function authUser()
{
    return $GLOBALS['auth_user'] ?? null;
}
