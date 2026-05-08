<?php
// api/check_balance.php - Check user balance
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
if (!$deviceId) {
    http_response_code(400);
    echo json_encode(['error' => 'Device ID required']);
    exit();
}

$conn = getDB();

$stmt = $conn->prepare("SELECT u.id, u.name, u.balance, u.tariff FROM users u JOIN devices d ON u.id = d.user_id WHERE d.device_id = ?");
$stmt->bind_param("s", $deviceId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $totalUsage = getUserTotalUsage($user['id']);
    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'name' => $user['name'],
        'balance' => $user['balance'],
        'balance_formatted' => formatCurrency($user['balance']),
        'tariff' => $user['tariff'],
        'total_usage' => $totalUsage['total_liters'] ?? 0
    ]);
} else {
    echo json_encode(['error' => 'Device not found']);
}

$conn->close();
?>