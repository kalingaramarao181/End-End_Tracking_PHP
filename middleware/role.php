<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Check whether the authenticated user has a specific permission.
 *
 * Example:
 * requirePermission('users', 'can_create');
 * requirePermission('users', 'can_edit');
 * requirePermission('users', 'can_view');
 */
function requirePermission($resourceName, $action)
{
    global $conn;

    $allowedActions = [
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_export',
        'can_approve'
    ];

    if (!in_array($action, $allowedActions, true)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid permission action.'
        ]);
        exit;
    }

    // Ensure user is authenticated
    $user = authUser();

    if (!$user) {
        authenticate();
        $user = authUser();
    }

    // Super Admin bypass (position_id = 1)
    if ((int)$user['position_id'] === 1) {
        return true;
    }

    $userId = (int)$user['id'];
    $positionId = (int)$user['position_id'];

    // -----------------------------------------------------
    // 1. Check user-specific override in user_permissions
    // -----------------------------------------------------
    $sql = "
        SELECT up.$action AS allowed
        FROM user_permissions up
        INNER JOIN resources r ON r.id = up.resource_id
        WHERE up.user_id = ?
          AND r.resource_name = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $resourceName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['allowed'] !== null) {
            if ((int)$row['allowed'] === 1) {
                return true;
            }

            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Permission denied.'
            ]);
            exit;
        }
    }

    // -----------------------------------------------------
    // 2. Check role-based permissions
    // -----------------------------------------------------
    $sql = "
        SELECT p.$action AS allowed
        FROM permissions p
        INNER JOIN resources r ON r.id = p.resource_id
        WHERE p.position_id = ?
          AND r.resource_name = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $positionId, $resourceName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ((int)$row['allowed'] === 1) {
            return true;
        }
    }

    // -----------------------------------------------------
    // 3. Access denied
    // -----------------------------------------------------
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied.'
    ]);
    exit;
}