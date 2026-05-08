<?php
// check_database.php - Check if data exists (PHP 7.0+ compatible)
require_once 'config/config.php';

$conn = getDB();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { border-collapse: collapse; margin: 10px 0; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #2c3e50; color: white; }
        h1, h2, h3 { margin-top: 20px; }
        .box { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📊 Database Status Check</h1>";

// Check Users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$users = $result->fetch_assoc()['count'];
echo "<div class='box'>";
echo "<h3>👥 Users: " . ($users > 0 ? "<span class='success'>$users found</span>" : "<span class='error'>0 users!</span>") . "</h3>";

// Show users
if ($users > 0) {
    $userList = $conn->query("SELECT id, name, email, role, balance FROM users LIMIT 5");
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Balance</th></tr>";
    while($row = $userList->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>" . number_format($row['balance'], 2) . " RWF</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Check Devices
$result = $conn->query("SELECT COUNT(*) as count FROM devices");
$devices = $result->fetch_assoc()['count'];
echo "<div class='box'>";
echo "<h3>📱 Devices: " . ($devices > 0 ? "<span class='success'>$devices found</span>" : "<span class='error'>0 devices!</span>") . "</h3>";

// Show devices
if ($devices > 0) {
    $deviceList = $conn->query("
        SELECT d.*, u.name as user_name 
        FROM devices d 
        LEFT JOIN users u ON d.user_id = u.id 
        LIMIT 5
    ");
    echo "<table>";
    echo "<tr><th>Device ID</th><th>Device Name</th><th>User</th><th>Status</th><th>Last Seen</th></tr>";
    while($row = $deviceList->fetch_assoc()) {
        $lastSeen = isset($row['last_seen']) ? $row['last_seen'] : 'Never';
        $isOnline = ($lastSeen != 'Never' && (strtotime($lastSeen) > (time() - 300)));
        $statusText = $isOnline ? '🟢 ONLINE' : '🔴 OFFLINE';
        $statusClass = $isOnline ? 'success' : 'error';
        echo "<tr>";
        echo "<td>{$row['device_id']}</td>";
        echo "<td>{$row['device_name']}</td>";
        echo "<td>{$row['user_name']}</td>";
        echo "<td class='$statusClass'>$statusText</td>";
        echo "<td>$lastSeen</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No devices found! Add a device when creating a user.</p>";
}
echo "</div>";

// Check Usage Data
$result = $conn->query("SELECT COUNT(*) as count FROM usage_data");
$usage = $result->fetch_assoc()['count'];
echo "<div class='box'>";
echo "<h3>💧 Usage Records: " . ($usage > 0 ? "<span class='success'>$usage records</span>" : "<span class='error'>0 records!</span>") . "</h3>";

// Show recent usage
if ($usage > 0) {
    $recent = $conn->query("
        SELECT ud.*, u.name as user_name 
        FROM usage_data ud 
        LEFT JOIN users u ON ud.user_id = u.id 
        ORDER BY ud.id DESC 
        LIMIT 10
    ");
    echo "<table>";
    echo "<tr><th>ID</th><th>User</th><th>Liters</th><th>Bill (RWF)</th><th>Date</th></tr>";
    while($row = $recent->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_name']}</td>";
        echo "<td>" . number_format($row['liters'], 2) . "</td>";
        echo "<td>" . number_format($row['bill'], 2) . "</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No usage data found!</p>";
    echo "<p>To add test data, <a href='fix_device.php'>click here to run fix_device.php</a></p>";
}
echo "</div>";

// Check Payments
$result = $conn->query("SELECT COUNT(*) as count FROM payments");
$payments = $result->fetch_assoc()['count'];
echo "<div class='box'>";
echo "<h3>💰 Payments: " . ($payments > 0 ? "<span class='success'>$payments records</span>" : "<span class='warning'>$payments records</span>") . "</h3>";

if ($payments > 0) {
    $paymentList = $conn->query("
        SELECT p.*, u.name as user_name 
        FROM payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.date DESC 
        LIMIT 5
    ");
    echo "<table>";
    echo "<tr><th>User</th><th>Amount (RWF)</th><th>Reference</th><th>Status</th><th>Date</th></tr>";
    while($row = $paymentList->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['user_name']}</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>{$row['reference']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Summary
echo "<div class='box' style='background: #e8f4fd;'>";
echo "<h2>📋 Summary</h2>";
echo "<ul>";
echo "<li>Total Users: <strong>$users</strong></li>";
echo "<li>Total Devices: <strong>$devices</strong></li>";
echo "<li>Total Usage Records: <strong>$usage</strong></li>";
echo "<li>Total Payments: <strong>$payments</strong></li>";
echo "</ul>";

if ($devices == 0) {
    echo "<p style='color: orange;'>⚠️ No devices registered. <a href='admin/add_user.php'>Add a user with device</a></p>";
}
if ($usage == 0 && $devices > 0) {
    echo "<p style='color: orange;'>⚠️ Devices registered but no data. Make sure ESP32 is sending data.</p>";
    echo "<p>Test API manually: <a href='test_api.html'>Open API Tester</a></p>";
}
if ($users == 0) {
    echo "<p style='color: red;'>❌ No users found! <a href='register.php'>Register a user</a></p>";
}

echo "</div>";

echo "<div class='box'>";
echo "<h2>🔧 Quick Actions</h2>";
echo "<p><a href='fix_device.php'>🔧 Run Device Fix</a></p>";
echo "<p><a href='test_api.html'>📡 Test API</a></p>";
echo "<p><a href='admin/dashboard.php'>📊 Go to Admin Dashboard</a></p>";
echo "<p><a href='login.php'>🔐 Go to Login</a></p>";
echo "</div>";

echo "</div></body></html>";
$conn->close();
?>