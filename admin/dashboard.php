<?php
// admin/dashboard.php - Complete working dashboard
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();

// =====================================================
// GET ALL STATISTICS
// =====================================================

// 1. Total Users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
$totalUsers = $result->fetch_assoc()['total'];

// 2. Total Admins
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$totalAdmins = $result->fetch_assoc()['total'];

// 3. Total Devices
$result = $conn->query("SELECT COUNT(*) as total FROM devices");
$totalDevices = $result->fetch_assoc()['total'];

// 4. Active Devices (seen in last 5 minutes)
$result = $conn->query("SELECT COUNT(*) as total FROM devices WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$onlineDevices = $result->fetch_assoc()['total'];

// 5. Total Water Usage
$result = $conn->query("SELECT SUM(liters) as total FROM usage_data");
$totalUsage = $result->fetch_assoc()['total'] ?? 0;

// 6. Total Revenue (from bills)
$result = $conn->query("SELECT SUM(bill) as total FROM usage_data");
$totalRevenue = $result->fetch_assoc()['total'] ?? 0;

// 7. Total Payments Received
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$totalPaid = $result->fetch_assoc()['total'] ?? 0;

// 8. Outstanding Balance
$outstanding = $totalRevenue - $totalPaid;

// 9. Today's Usage
$result = $conn->query("SELECT SUM(liters) as total FROM usage_data WHERE DATE(date) = CURDATE()");
$todayUsage = $result->fetch_assoc()['total'] ?? 0;

// 10. This Month's Usage
$result = $conn->query("SELECT SUM(liters) as total FROM usage_data WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
$monthUsage = $result->fetch_assoc()['total'] ?? 0;

// 11. Pending SMS
$result = $conn->query("SELECT COUNT(*) as total FROM sms_queue WHERE status = 'pending'");
$pendingSMS = $result->fetch_assoc()['total'];

// 12. Unread Alerts
$result = $conn->query("SELECT COUNT(*) as total FROM alerts WHERE is_read = 0");
$unreadAlerts = $result->fetch_assoc()['total'];

// =====================================================
// GET RECENT DATA
// =====================================================

// Recent Usage (last 10 records)
$recentUsage = $conn->query("
    SELECT ud.*, u.name as user_name 
    FROM usage_data ud 
    JOIN users u ON ud.user_id = u.id 
    ORDER BY ud.date DESC 
    LIMIT 10
");

// Recent Users (last 5)
$recentUsers = $conn->query("
    SELECT * FROM users 
    WHERE role = 'client' 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Recent Payments (last 5)
$recentPayments = $conn->query("
    SELECT p.*, u.name as user_name 
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.status = 'completed'
    ORDER BY p.date DESC 
    LIMIT 5
");

// Devices Status
$devices = $conn->query("
    SELECT d.*, u.name as user_name 
    FROM devices d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.last_seen DESC 
    LIMIT 10
");

// Top Users by Usage (this month)
$topUsers = $conn->query("
    SELECT u.name, SUM(ud.liters) as total_liters, SUM(ud.bill) as total_bill
    FROM users u
    JOIN usage_data ud ON u.id = ud.user_id
    WHERE MONTH(ud.date) = MONTH(CURDATE()) AND YEAR(ud.date) = YEAR(CURDATE())
    GROUP BY u.id
    ORDER BY total_liters DESC
    LIMIT 5
");

// Monthly data for chart (last 12 months)
$monthlyData = [];
for($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(liters), 0) as total FROM usage_data WHERE DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $monthlyData[] = [
        'month' => $monthName,
        'usage' => floatval($result['total'])
    ];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Water Meter System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .nav-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: #34495e;
        }
        .welcome {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .sub {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
        .stat-card .trend-up { color: #27ae60; }
        .stat-card .trend-down { color: #e74c3c; }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-online {
            background: #d4edda;
            color: #155724;
        }
        .badge-offline {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        .chart-container {
            max-height: 300px;
            margin-top: 20px;
        }
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💧 Water Meter Admin Dashboard</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="add_user.php">Add User</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="generate_sms.php">SMS</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
            <p>Here's your water management system overview.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>👥 Total Users</h3>
                <div class="value"><?php echo $totalUsers; ?></div>
                <div class="sub">+ <?php echo $totalAdmins; ?> admins</div>
            </div>
            <div class="stat-card">
                <h3>📱 Devices</h3>
                <div class="value"><?php echo $totalDevices; ?></div>
                <div class="sub"><span class="trend-up">🟢 <?php echo $onlineDevices; ?> online</span></div>
            </div>
            <div class="stat-card">
                <h3>💧 Total Water Usage</h3>
                <div class="value"><?php echo number_format($totalUsage, 0); ?> L</div>
                <div class="sub">Today: <?php echo number_format($todayUsage, 0); ?> L</div>
            </div>
            <div class="stat-card">
                <h3>💰 Total Revenue</h3>
                <div class="value"><?php echo number_format($totalRevenue, 0); ?> RWF</div>
                <div class="sub">Paid: <?php echo number_format($totalPaid, 0); ?> RWF</div>
            </div>
            <div class="stat-card">
                <h3>⚠️ Outstanding</h3>
                <div class="value" style="color: <?php echo $outstanding > 0 ? '#e74c3c' : '#27ae60'; ?>">
                    <?php echo number_format($outstanding, 0); ?> RWF
                </div>
                <div class="sub">Pending collection</div>
            </div>
            <div class="stat-card">
                <h3>📨 Pending SMS</h3>
                <div class="value"><?php echo $pendingSMS; ?></div>
                <div class="sub"><?php echo $unreadAlerts; ?> unread alerts</div>
            </div>
        </div>
        
        <!-- Chart Section -->
        <div class="section">
            <h2>📊 Monthly Water Usage Trend</h2>
            <div class="chart-container">
                <canvas id="usageChart"></canvas>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Recent Usage -->
            <div class="section">
                <h2>📋 Recent Usage Records</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <?php if ($recentUsage->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Liters</th>
                                <th>Bill (RWF)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $recentUsage->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td><?php echo number_format($row['liters'], 2); ?> L</td>
                                <td><?php echo number_format($row['bill'], 2); ?> RWF</td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <p>❌ No usage data yet</p>
                        <p>Add a device and send data from ESP32</p>
                        <a href="add_user.php" style="color: #3498db;">Add User + Device →</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="section">
                <h2>👤 Recent Users</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <?php if ($recentUsers->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $recentUsers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td style="color: <?php echo $row['balance'] > 0 ? '#e74c3c' : '#27ae60'; ?>">
                                    <?php echo number_format($row['balance'], 0); ?> RWF
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <p>❌ No users found</p>
                        <a href="add_user.php" style="color: #3498db;">Add Your First User →</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Devices Status -->
            <div class="section">
                <h2>📡 Device Status</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <?php if ($devices->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Device Name</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $devices->fetch_assoc()): 
                                $isOnline = $row['last_seen'] && (strtotime($row['last_seen']) > (time() - 300));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $isOnline ? 'badge-online' : 'badge-offline'; ?>">
                                        <?php echo $isOnline ? '🟢 ONLINE' : '🔴 OFFLINE'; ?>
                                    </span>
                                 </td>
                                <td><?php echo $row['last_seen'] ? date('Y-m-d H:i', strtotime($row['last_seen'])) : 'Never'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <p>❌ No devices found</p>
                        <p>Add a device when creating a user</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Users -->
            <div class="section">
                <h2>🏆 Top Users (This Month)</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <?php if ($topUsers->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Usage (L)</th>
                                <th>Bill (RWF)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; while($row = $topUsers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo number_format($row['total_liters'], 0); ?> L</td>
                                <td><?php echo number_format($row['total_bill'], 0); ?> RWF</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <p>❌ No usage data this month</p>
                        <p>Data will appear after ESP32 sends readings</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="section">
            <h2>💰 Recent Payments</h2>
            <div style="overflow-x: auto;">
                <?php if ($recentPayments->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount (RWF)</th>
                            <th>Reference</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $recentPayments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                            <td><?php echo number_format($row['amount'], 2); ?> RWF</td>
                            <td><?php echo $row['reference']; ?></td>
                            <td><?php echo $row['payment_method'] ?: 'N/A'; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <p>❌ No payments recorded yet</p>
                    <p>Payments will appear here when users make payments</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Monthly Usage Chart
        const chartData = <?php echo json_encode($monthlyData); ?>;
        
        const ctx = document.getElementById('usageChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.month),
                datasets: [{
                    label: 'Water Usage (Liters)',
                    data: chartData.map(item => item.usage),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' Liters';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Liters'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>