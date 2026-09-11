<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$db   = 'themani'; // Change this if your MySQL database has a different name
$user = 'root';
$pass = '';

try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    $mysqli->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
?>