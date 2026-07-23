<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/applicationController.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getApplications();
} else {
    echo json_encode(["message" => "Method not allowed"]);
}
?>