<?php
// api/update_sms.php - Update SMS status after sending
header('Content-Type: application/json');
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$smsId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$smsId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid SMS ID']);
    exit();
}

$conn = getDB();
$stmt = $conn->prepare("UPDATE sms_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $smsId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
}

$conn->close();
?>