<?php
/**
 * Thamani High School - Student Live Bank Fees Check Endpoint
 * -----------------------------------------------------------
 * Allows an authenticated student to check their own real-time school fees
 * balance and payment receipts against connected Bank APIs (SchoolPay,
 * Centenary, Stanbic FlexiPay, Equity, MTN MoMo, Airtel Money).
 */

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth_student.php';
require_once __DIR__ . '/bank_api_helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please sign in to your student account first.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submitted = (string)($_POST['csrf_token'] ?? '');
    $expected  = (string)($_SESSION['student_fees_csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Your session expired. Please reload the page and try again.']);
        exit;
    }
}

$studentId = (int)$_SESSION['student_id'];
$pollBanks = (($_POST['poll_banks'] ?? $_GET['poll_banks'] ?? '1') !== '0');

$result = bank_api_check_student_fees($GLOBALS['conn'], $studentId, $pollBanks);
echo json_encode($result);
exit;
