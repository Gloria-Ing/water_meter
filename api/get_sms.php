<?php
// api/get_sms.php - ESP32 retrieves SMS messages
header('Content-Type: application/json');
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "NO_SMS";
    exit();
}

$deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
if (!$deviceId) {
    echo "NO_SMS";
    exit();
}

$conn = getDB();

$stmt = $conn->prepare("SELECT user_id FROM devices WHERE device_id = ? AND status = 'active'");
$stmt->bind_param("s", $deviceId);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    echo "NO_SMS";
    exit();
}

$stmt = $conn->prepare("SELECT id, phone, message FROM sms_queue WHERE user_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
$stmt->bind_param("i", $device['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($sms = $result->fetch_assoc()) {
    echo json_encode($sms);
} else {
    echo "NO_SMS";
}

$conn->close();
?>