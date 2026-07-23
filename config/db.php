<?php
// Local XAMPP defaults are used when DB_* environment variables are absent.
// Set DB_HOST, DB_USER, DB_PASSWORD and DB_NAME on the production server.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db = getenv('DB_NAME') ?: 'e2e_tracking';

$conn = new mysqli($host, $db_user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["error" => "DB Connection Failed"]));
}
?>
