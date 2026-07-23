<?php

header('Content-Type: application/json');

echo json_encode([
    "success" => true,
    "message" => "Server Working Correctly",
    "request_uri" => $_SERVER['REQUEST_URI']
]);