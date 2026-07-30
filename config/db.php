<?php
// E2E business time uses US Eastern Time (EST/EDT with daylight-saving support).
date_default_timezone_set('America/New_York');

// Local XAMPP defaults are used when DB_* environment variables are absent.
// Set DB_HOST, DB_USER, DB_PASSWORD and DB_NAME on the production server.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db = getenv('DB_NAME') ?: 'e2e_tracking';

$conn = new mysqli($host, $db_user, $pass, $db);
// Keep MySQL NOW(), CURDATE(), and TIMESTAMP reads aligned with Eastern Time.
$easternOffset = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('P');
$conn->query("SET time_zone = '" . $conn->real_escape_string($easternOffset) . "'");

if ($conn->connect_error) {
    die(json_encode(["error" => "DB Connection Failed"]));
}
?>
