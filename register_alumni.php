<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5713');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests are allowed']);
    exit;
}

require_once __DIR__ . '/conn.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request body must be valid JSON']);
    exit;
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
    $stmt = $mysqli->prepare("INSERT INTO alumni (name, year, profession, phone, email) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $year, $profession, $phone, $email);
    $stmt->execute();

    echo json_encode(['success' => true, 'id' => $mysqli->insert_id]);
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not save alumni record']);
}
?>