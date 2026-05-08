<?php
// api/record_usage.php - Receives water usage from ESP32
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$deviceId) {
    http_response_code(400);
    echo json_encode(['error' => 'Device ID required']);
    exit();
}

if ($apiKey !== 'BLISS_001') {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit();
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$liters = filter_input(INPUT_POST, 'liters', FILTER_VALIDATE_FLOAT);

if (!$userId || !$liters || $liters <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

$conn = getDB();

$stmt = $conn->prepare("SELECT user_id, status FROM devices WHERE device_id = ? AND user_id = ? AND status = 'active'");
$stmt->bind_param("si", $deviceId, $userId);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    http_response_code(403);
    echo json_encode(['error' => 'Device not authorized']);
    exit();
}

$stmt = $conn->prepare("SELECT id, tariff, balance, phone, name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$bill = calculateBill($liters, $user['tariff']);

$stmt = $conn->prepare("INSERT INTO usage_data (user_id, liters, bill) VALUES (?, ?, ?)");
$stmt->bind_param("idd", $userId, $liters, $bill);

if ($stmt->execute()) {
    $stmt = $conn->prepare("UPDATE devices SET last_seen = NOW() WHERE device_id = ?");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    
    $newBalance = $user['balance'] + $bill;
    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param("di", $newBalance, $userId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'bill' => $bill,
        'total_balance' => $newBalance,
        'message' => 'Usage recorded successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

$conn->close();
?>