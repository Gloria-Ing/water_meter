<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$alertType = sanitizeInput($_POST['alert_type'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');

if (!$userId || empty($alertType)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

$conn = getDB();
$stmt = $conn->prepare("INSERT INTO alerts (user_id, alert_type, message) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $userId, $alertType, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create alert']);
}

$stmt->close();
$conn->close();
?>