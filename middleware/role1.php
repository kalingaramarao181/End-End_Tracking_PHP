<?php
function checkRole($roles, $user) {
    if (!in_array($user->user->role, $roles)) {
        http_response_code(403);
        echo json_encode(["error" => "Access denied"]);
        exit;
    }
}