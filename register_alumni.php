<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests are allowed']);
    exit;
}

require_once __DIR__ . '/conn.php';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$name = trim($input['name'] ?? '');
$year = trim($input['year'] ?? '');
$profession = trim($input['profession'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');

if ($name === '' || $profession === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Name, profession, and phone are required']);
    exit;
}

try {
    $stmt = thamani_db_prepare($conn, "INSERT INTO alumni (name, year, profession, phone, email) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        thamani_db_stmt_bind_param($stmt, "sssss", $name, $year, $profession, $phone, $email);
        if (thamani_db_stmt_execute($stmt)) {
            $insertId = thamani_db_insert_id($conn);
            thamani_db_stmt_close($stmt);
            echo json_encode(['success' => true, 'id' => $insertId]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not save alumni record']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database prepare failed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not save alumni record']);
}
?>