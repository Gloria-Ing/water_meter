<?php
// test_data.php - Generate test data for dashboard testing
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDB();

echo "<h1>Water Meter System - Data Testing Tool</h1>";
echo "<hr>";

// Test 1: Check Database Connection
echo "<h3>✓ Test 1: Database Connection</h3>";
if ($conn->ping()) {
    echo "<p style='color: green'>✅ Database connected successfully</p>";
} else {
    echo "<p style='color: red'>❌ Database connection failed</p>";
}

// Test 2: Check Users
echo "<h3>✓ Test 2: Users in System</h3>";
$users = $conn->query("SELECT id, name, email, role, balance FROM users");
if ($users->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Balance</th></tr>";
    while($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . formatCurrency($user['balance']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red'>❌ No users found. Please add users first.</p>";
}

// Test 3: Insert Test Usage Data
echo "<h3>✓ Test 3: Insert Test Usage Data</h3>";
$testData = [
    ['user_id' => 2, 'liters' => 1500, 'bill' => 150],
    ['user_id' => 2, 'liters' => 2500, 'bill' => 250],
    ['user_id' => 2, 'liters' => 3000, 'bill' => 300],
    ['user_id' => 3, 'liters' => 1000, 'bill' => 100],
    ['user_id' => 3, 'liters' => 2000, 'bill' => 200],
];

foreach ($testData as $data) {
    $stmt = $conn->prepare("INSERT INTO usage_data (user_id, liters, bill) VALUES (?, ?, ?)");
    $stmt->bind_param("idd", $data['user_id'], $data['liters'], $data['bill']);
    if ($stmt->execute()) {
        echo "<p style='color: green'>✅ Inserted: User {$data['user_id']} - {$data['liters']}L - {$data['bill']} RWF</p>";
    }
    $stmt->close();
}

// Test 4: Insert Test Payment Data
echo "<h3>✓ Test 4: Insert Test Payment Data</h3>";
$payments = [
    ['user_id' => 2, 'amount' => 500, 'reference' => 'PAY_TEST_001', 'status' => 'completed'],
    ['user_id' => 2, 'amount' => 300, 'reference' => 'PAY_TEST_002', 'status' => 'completed'],
    ['user_id' => 3, 'amount' => 200, 'reference' => 'PAY_TEST_003', 'status' => 'pending'],
];

foreach ($payments as $payment) {
    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, reference, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $payment['user_id'], $payment['amount'], $payment['reference'], $payment['status']);
    if ($stmt->execute()) {
        echo "<p style='color: green'>✅ Inserted Payment: User {$payment['user_id']} - {$payment['amount']} RWF</p>";
    }
    $stmt->close();
}

// Test 5: Display Current Usage Data
echo "<h3>✓ Test 5: Current Usage Data in Database</h3>";
$usageData = $conn->query("
    SELECT ud.*, u.name 
    FROM usage_data ud 
    JOIN users u ON ud.user_id = u.id 
    ORDER BY ud.date DESC 
    LIMIT 20
");

if ($usageData->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User</th><th>Liters</th><th>Bill (RWF)</th><th>Date</th></tr>";
    while($row = $usageData->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>" . number_format($row['liters'], 2) . "</td>";
        echo "<td>" . number_format($row['bill'], 2) . "</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange'>⚠️ No usage data found</p>";
}

// Test 6: Display Current Payment Data
echo "<h3>✓ Test 6: Current Payment Data in Database</h3>";
$paymentData = $conn->query("
    SELECT p.*, u.name 
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.date DESC 
    LIMIT 20
");

if ($paymentData->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User</th><th>Amount (RWF)</th><th>Reference</th><th>Status</th><th>Date</th></tr>";
    while($row = $paymentData->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>{$row['reference']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange'>⚠️ No payment data found</p>";
}

// Test 7: Dashboard Statistics
echo "<h3>✓ Test 7: Dashboard Statistics</h3>";
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
$stats['total_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT SUM(liters) as total FROM usage_data");
$stats['total_usage'] = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(balance) as total FROM users WHERE role = 'client'");
$stats['pending_payments'] = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT SUM(liters) as total FROM usage_data WHERE DATE(date) = CURDATE()");
$stats['today_usage'] = $result->fetch_assoc()['total'] ?? 0;

echo "<ul>";
echo "<li>Total Users: <strong>{$stats['total_users']}</strong></li>";
echo "<li>Total Water Usage: <strong>" . formatLiters($stats['total_usage']) . "</strong></li>";
echo "<li>Total Revenue: <strong>" . formatCurrency($stats['total_revenue']) . "</strong></li>";
echo "<li>Pending Payments: <strong>" . formatCurrency($stats['pending_payments']) . "</strong></li>";
echo "<li>Today's Usage: <strong>" . formatLiters($stats['today_usage']) . "</strong></li>";
echo "</ul>";

// Test 8: API Endpoint Test
echo "<h3>✓ Test 8: API Endpoint Test</h3>";
echo "<p>Test the API endpoint directly:</p>";
echo "<code>http://" . $_SERVER['HTTP_HOST'] . "/water_meter/api/get_stats.php</code>";
echo "<br><br>";
echo "<a href='api/get_stats.php' target='_blank' style='background: #3498db; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Test API →</a>";

$conn->close();
echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li><a href='admin/dashboard.php'>Go to Admin Dashboard</a></li>";
echo "<li><a href='client/dashboard.php'>Go to Client Dashboard</a></li>";
echo "<li><a href='api/get_stats.php'>View API Statistics</a></li>";
echo "</ul>";
?>