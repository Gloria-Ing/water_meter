<?php
// client/dashboard.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$conn = getDB();
$userId = $_SESSION['user_id'];

// Get user data
$user = getCurrentUser();

// Get usage statistics
$totalUsage = getUserTotalUsage($userId);
$lastPayment = getUserLastPayment($userId);

// Get usage history
$history = $conn->prepare("
    SELECT * FROM usage_data 
    WHERE user_id = ? 
    ORDER BY date DESC 
    LIMIT 30
");
$history->bind_param("i", $userId);
$history->execute();
$usageHistory = $history->get_result();

// Get payment history
$payments = $conn->prepare("
    SELECT * FROM payments 
    WHERE user_id = ? 
    ORDER BY date DESC 
    LIMIT 10
");
$payments->bind_param("i", $userId);
$payments->execute();
$paymentHistory = $payments->get_result();

// Get daily usage for chart (last 7 days)
$dailyUsage = $conn->prepare("
    SELECT DATE(date) as day, SUM(liters) as total 
    FROM usage_data 
    WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date)
    ORDER BY day ASC
");
$dailyUsage->bind_param("i", $userId);
$dailyUsage->execute();
$dailyData = $dailyUsage->get_result();

$chartData = [];
while ($row = $dailyData->fetch_assoc()) {
    $chartData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Water Meter System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
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
            font-size: 18px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
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
        .nav-links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
        }
        .nav-links a:hover {
            background: #34495e;
            border-radius: 5px;
        }
        .balance-positive {
            color: #27ae60;
        }
        .balance-negative {
            color: #e74c3c;
        }
        canvas {
            max-height: 300px;
        }
        .welcome {
            margin-bottom: 20px;
            background: #e8f4fd;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            table {
                font-size: 12px;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💧 Water Meter Dashboard</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="change_password.php">Change Password</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
            <p>Monitor your water usage and billing information below.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Water Usage</h3>
                <div class="value"><?php echo formatLiters($totalUsage['total_liters'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bill</h3>
                <div class="value"><?php echo formatCurrency($totalUsage['total_bill'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Current Balance</h3>
                <div class="value <?php echo ($user['balance'] ?? 0) <= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    <?php echo formatCurrency($user['balance'] ?? 0); ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Tariff Rate</h3>
                <div class="value"><?php echo formatCurrency($user['tariff'] ?? 100); ?>/1000L</div>
            </div>
        </div>
        
        <?php if (($user['balance'] ?? 0) > 10000): ?>
        <div class="alert-box">
            <p><strong>⚠️ Alert:</strong> Your balance is high (<?php echo formatCurrency($user['balance']); ?>). 
            Please make a payment to avoid service interruption.</p>
        </div>
        <?php endif; ?>
        
        <?php if (($user['balance'] ?? 0) < 0): ?>
        <div class="alert-box" style="background: #d4edda; border-left-color: #27ae60;">
            <p><strong>✅ Good Standing:</strong> You have a credit balance of <?php echo formatCurrency(abs($user['balance'])); ?>.</p>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>📊 Weekly Usage Chart</h2>
            <canvas id="usageChart"></canvas>
        </div>
        
        <div class="section">
            <h2>📋 Recent Usage History</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Usage (Liters)</th>
                            <th>Bill (RWF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usageHistory->num_rows > 0): ?>
                            <?php while($record = $usageHistory->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($record['date'])); ?></td>
                                <td><?php echo number_format($record['liters'], 2); ?> L</td>
                                <td><?php echo number_format($record['bill'], 2); ?> RWF</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">No usage records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>💰 Payment History</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount (RWF)</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($paymentHistory->num_rows > 0): ?>
                            <?php while($payment = $paymentHistory->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($payment['date'])); ?></td>
                                <td><?php echo number_format($payment['amount'], 2); ?> RWF</td>
                                <td><?php echo $payment['reference']; ?></td>
                                <td style="color: <?php echo $payment['status'] == 'completed' ? '#27ae60' : '#e74c3c'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                 </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No payment records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>📞 Make a Payment</h2>
            <p>To make a payment, please use one of the following methods:</p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><strong>Mobile Money:</strong> +250 788 000 000</li>
                <li><strong>Bank Transfer:</strong> Account Name: Water Meter System, Account: 123456789</li>
                <li><strong>Reference:</strong> Use your user ID (<?php echo $userId; ?>) when making payments</li>
            </ul>
            <p style="margin-top: 15px; color: #666;">After payment, please contact support to confirm your transaction.</p>
        </div>
    </div>
    
    <script>
        const ctx = document.getElementById('usageChart').getContext('2d');
        const chartData = <?php echo json_encode($chartData); ?>;
        
        if (chartData.length > 0) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.day),
                    datasets: [{
                        label: 'Water Usage (Liters)',
                        data: chartData.map(item => parseFloat(item.total) || 0),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Daily Water Usage (Last 7 Days)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Liters'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
        } else {
            ctx.fillStyle = '#ccc';
            ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#666';
            ctx.font = '16px Arial';
            ctx.fillText('No data available', ctx.canvas.width/2 - 80, ctx.canvas.height/2);
        }
    </script>
</body>
</html>
<?php
$history->close();
$payments->close();
$dailyUsage->close();
$conn->close();
?>