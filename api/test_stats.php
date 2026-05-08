<?php
// api/test_stats.php - Test API endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/config.php';

$conn = getDB();

// Get statistics
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
$totalUsers = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT SUM(liters) as total FROM usage_data");
$totalUsage = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$totalRevenue = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(balance) as total FROM users WHERE role = 'client'");
$pendingPayments = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(liters) as total FROM usage_data WHERE DATE(date) = CURDATE()");
$todayUsage = $result->fetch_assoc()['total'] ?? 0;

// Get recent usage
$recentUsage = [];
$usageQuery = $conn->query("
    SELECT u.name, ud.liters, ud.bill, ud.date 
    FROM usage_data ud 
    JOIN users u ON ud.user_id = u.id 
    ORDER BY ud.date DESC 
    LIMIT 5
");
while($row = $usageQuery->fetch_assoc()) {
    $recentUsage[] = $row;
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => [
        'total_users' => $totalUsers,
        'total_usage_liters' => $totalUsage,
        'total_usage_formatted' => number_format($totalUsage, 2) . ' L',
        'total_revenue' => $totalRevenue,
        'total_revenue_formatted' => number_format($totalRevenue, 2) . ' RWF',
        'pending_payments' => $pendingPayments,
        'pending_payments_formatted' => number_format($pendingPayments, 2) . ' RWF',
        'today_usage' => $todayUsage,
        'today_usage_formatted' => number_format($todayUsage, 2) . ' L',
        'recent_usage' => $recentUsage
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>