<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/auth.php';

requireAdmin();

$conn = getDB();

// Get total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
$totalUsers = $result->fetch_assoc()['total'];

// Get total usage
$result = $conn->query("SELECT SUM(liters) as total FROM usage_data");
$totalUsage = $result->fetch_assoc()['total'] ?? 0;

// Get total revenue
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$totalRevenue = $result->fetch_assoc()['total'] ?? 0;

// Get pending payments
$result = $conn->query("SELECT SUM(balance) as total FROM users WHERE role = 'client'");
$pendingPayments = $result->fetch_assoc()['total'] ?? 0;

// Get today's usage
$result = $conn->query("SELECT SUM(liters) as total FROM usage_data WHERE DATE(date) = CURDATE()");
$todayUsage = $result->fetch_assoc()['total'] ?? 0;

echo json_encode([
    'total_users' => $totalUsers,
    'total_usage' => $totalUsage,
    'total_revenue' => $totalRevenue,
    'pending_payments' => $pendingPayments,
    'today_usage' => $todayUsage
]);

$conn->close();
?>