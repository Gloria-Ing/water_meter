<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'usage';
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Get all clients for filter
$clients = $conn->query("SELECT id, name FROM users WHERE role = 'client' ORDER BY name");

// Build where clause
$whereClause = "WHERE date BETWEEN '$startDate' AND '$endDate 23:59:59'";
if ($userId > 0) {
    $whereClause .= " AND user_id = $userId";
}

// Get report data based on type
$reportData = [];
$chartLabels = [];
$chartValues = [];

switch($reportType) {
    case 'usage':
        // Water usage report
        $query = "SELECT 
                    DATE(date) as report_date,
                    SUM(liters) as total_liters,
                    SUM(bill) as total_bill,
                    COUNT(DISTINCT user_id) as active_users
                  FROM usage_data 
                  $whereClause
                  GROUP BY DATE(date)
                  ORDER BY report_date DESC";
        $result = $conn->query($query);
        while($row = $result->fetch_assoc()) {
            $reportData[] = $row;
            $chartLabels[] = $row['report_date'];
            $chartValues[] = $row['total_liters'];
        }
        break;
        
    case 'revenue':
        // Revenue report
        $query = "SELECT 
                    DATE(date) as report_date,
                    SUM(amount) as total_amount,
                    COUNT(*) as payment_count
                  FROM payments 
                  $whereClause AND status = 'completed'
                  GROUP BY DATE(date)
                  ORDER BY report_date DESC";
        $result = $conn->query($query);
        while($row = $result->fetch_assoc()) {
            $reportData[] = $row;
            $chartLabels[] = $row['report_date'];
            $chartValues[] = $row['total_amount'];
        }
        break;
        
    case 'users':
        // User performance report
        $query = "SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.phone,
                    u.tariff,
                    COALESCE(SUM(ud.liters), 0) as total_liters,
                    COALESCE(SUM(ud.bill), 0) as total_bill,
                    COALESCE(MAX(ud.date), 'No usage') as last_usage,
                    (SELECT SUM(amount) FROM payments WHERE user_id = u.id AND status = 'completed' AND date BETWEEN '$startDate' AND '$endDate 23:59:59') as paid_amount
                  FROM users u
                  LEFT JOIN usage_data ud ON u.id = ud.user_id AND ud.date BETWEEN '$startDate' AND '$endDate 23:59:59'
                  WHERE u.role = 'client'
                  GROUP BY u.id
                  ORDER BY total_liters DESC";
        $result = $conn->query($query);
        while($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;
        
    case 'payments':
        // Payment report
        $query = "SELECT 
                    p.*,
                    u.name as user_name,
                    u.email as user_email
                  FROM payments p
                  JOIN users u ON p.user_id = u.id
                  WHERE p.date BETWEEN '$startDate' AND '$endDate 23:59:59'
                  ORDER BY p.date DESC";
        $result = $conn->query($query);
        while($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;
}

// Get summary statistics
$summaryQuery = "SELECT 
                    COALESCE(SUM(liters), 0) as total_usage,
                    COALESCE(SUM(bill), 0) as total_bill,
                    COUNT(DISTINCT user_id) as total_users
                  FROM usage_data 
                  WHERE date BETWEEN '$startDate' AND '$endDate 23:59:59'";
$summary = $conn->query($summaryQuery)->fetch_assoc();

$paymentSummary = "SELECT 
                    COALESCE(SUM(amount), 0) as total_paid,
                    COUNT(*) as total_payments
                  FROM payments 
                  WHERE status = 'completed' 
                  AND date BETWEEN '$startDate' AND '$endDate 23:59:59'";
$paymentSum = $conn->query($paymentSummary)->fetch_assoc();

// Calculate outstanding balance
$outstanding = $summary['total_bill'] - $paymentSum['total_paid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Water Meter System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
        }
        
        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
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
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        button {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        button:hover {
            background: #2980b9;
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
        
        .chart-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            max-height: 400px;
            margin-top: 20px;
        }
        
        .report-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
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
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .report-actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media print {
            .header, .filter-section, .report-actions, .nav-links {
                display: none;
            }
            .container {
                padding: 0;
            }
            .stat-card, .chart-section, .report-section {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reports & Analytics</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Report Type</label>
                    <select name="report_type">
                        <option value="usage" <?php echo $reportType == 'usage' ? 'selected' : ''; ?>>Usage Report</option>
                        <option value="revenue" <?php echo $reportType == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                        <option value="users" <?php echo $reportType == 'users' ? 'selected' : ''; ?>>User Performance</option>
                        <option value="payments" <?php echo $reportType == 'payments' ? 'selected' : ''; ?>>Payment Report</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                
                <div class="form-group">
                    <label>Select User</label>
                    <select name="user_id">
                        <option value="0">All Users</option>
                        <?php while($client = $clients->fetch_assoc()): ?>
                        <option value="<?php echo $client['id']; ?>" <?php echo $userId == $client['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit">Generate Report</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Water Usage</h3>
                <div class="value"><?php echo formatLiters($summary['total_usage']); ?></div>
                <div class="sub">Period: <?php echo date('d M Y', strtotime($startDate)); ?> - <?php echo date('d M Y', strtotime($endDate)); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Bill Amount</h3>
                <div class="value"><?php echo formatCurrency($summary['total_bill']); ?></div>
                <div class="sub">Based on current tariff</div>
            </div>
            
            <div class="stat-card">
                <h3>Total Payments Received</h3>
                <div class="value"><?php echo formatCurrency($paymentSum['total_paid']); ?></div>
                <div class="sub"><?php echo $paymentSum['total_payments']; ?> transactions</div>
            </div>
            
            <div class="stat-card">
                <h3>Outstanding Balance</h3>
                <div class="value" style="color: <?php echo $outstanding > 0 ? '#e74c3c' : '#27ae60'; ?>">
                    <?php echo formatCurrency($outstanding); ?>
                </div>
                <div class="sub">Pending collection</div>
            </div>
        </div>
        
        <!-- Chart Section (for usage and revenue reports) -->
        <?php if (($reportType == 'usage' || $reportType == 'revenue') && !empty($chartLabels)): ?>
        <div class="chart-section">
            <h2><?php echo $reportType == 'usage' ? 'Water Usage Trend' : 'Revenue Trend'; ?></h2>
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Report Actions -->
        <div class="report-actions">
            <button class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
            <button class="btn btn-success" id="exportPdf">📄 Export as PDF</button>
            <button class="btn btn-primary" id="exportExcel">📊 Export as Excel</button>
        </div>
        
        <!-- Report Data Table -->
        <div class="report-section" id="reportContent">
            <h2>
                <?php 
                switch($reportType) {
                    case 'usage': echo 'Water Usage Report'; break;
                    case 'revenue': echo 'Revenue Report'; break;
                    case 'users': echo 'User Performance Report'; break;
                    case 'payments': echo 'Payment Transaction Report'; break;
                }
                ?>
            </h2>
            <p style="margin-bottom: 20px; color: #666;">
                Period: <?php echo date('F d, Y', strtotime($startDate)); ?> - <?php echo date('F d, Y', strtotime($endDate)); ?>
                <?php if($userId > 0): ?> | Filtered by selected user<?php endif; ?>
            </p>
            
            <?php if($reportType == 'usage'): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Usage (Liters)</th>
                        <th>Total Bill (RWF)</th>
                        <th>Active Users</th>
                        <th>Avg Usage per User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($reportData) > 0): ?>
                        <?php foreach($reportData as $row): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($row['report_date'])); ?></td>
                            <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                            <td><?php echo number_format($row['total_bill'], 2); ?> RWF</td>
                            <td><?php echo $row['active_users']; ?> users</td>
                            <td><?php echo number_format($row['total_liters'] / max($row['active_users'], 1), 2); ?> L</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if($reportType == 'revenue'): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Amount (RWF)</th>
                        <th>Number of Payments</th>
                        <th>Average Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($reportData) > 0): ?>
                        <?php foreach($reportData as $row): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($row['report_date'])); ?></td>
                            <td><?php echo number_format($row['total_amount'], 2); ?> RWF</td>
                            <td><?php echo $row['payment_count']; ?> payments</td>
                            <td><?php echo number_format($row['total_amount'] / max($row['payment_count'], 1), 2); ?> RWF</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if($reportType == 'users'): ?>
            <table>
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Contact</th>
                        <th>Total Usage</th>
                        <th>Total Bill</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Last Usage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($reportData) > 0): ?>
                        <?php foreach($reportData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['email']); ?><br>
                                <small><?php echo htmlspecialchars($row['phone']); ?></small>
                            </td>
                            <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                            <td><?php echo number_format($row['total_bill'], 2); ?> RWF</td>
                            <td><?php echo number_format($row['paid_amount'] ?? 0, 2); ?> RWF</td>
                            <td style="color: <?php echo ($row['total_bill'] - ($row['paid_amount'] ?? 0)) > 0 ? '#e74c3c' : '#27ae60'; ?>">
                                <?php echo number_format($row['total_bill'] - ($row['paid_amount'] ?? 0), 2); ?> RWF
                            </td>
                            <td><?php echo $row['last_usage'] != 'No usage' ? date('d M Y', strtotime($row['last_usage'])) : 'No usage'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if($reportType == 'payments'): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Amount (RWF)</th>
                        <th>Reference</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($reportData) > 0): ?>
                        <?php foreach($reportData as $row): ?>
                        <tr>
                            <td><?php echo date('d M Y H:i', strtotime($row['date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['user_name']); ?><br>
                                <small><?php echo htmlspecialchars($row['user_email']); ?></small>
                            </td>
                            <td><?php echo number_format($row['amount'], 2); ?> RWF</td>
                            <td><?php echo $row['reference']; ?></td>
                            <td><?php echo $row['payment_method'] ?: 'N/A'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Chart rendering
        <?php if (($reportType == 'usage' || $reportType == 'revenue') && !empty($chartLabels)): ?>
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: '<?php echo $reportType == 'usage' ? 'Water Usage (Liters)' : 'Revenue (RWF)'; ?>',
                    data: <?php echo json_encode($chartValues); ?>,
                    borderColor: '<?php echo $reportType == 'usage' ? "#3498db" : "#27ae60"; ?>',
                    backgroundColor: '<?php echo $reportType == 'usage' ? "rgba(52, 152, 219, 0.1)" : "rgba(39, 174, 96, 0.1)"; ?>',
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
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '<?php echo $reportType == 'usage' ? 'Liters' : 'RWF'; ?>'
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
        <?php endif; ?>
        
        // Export to PDF
        document.getElementById('exportPdf').addEventListener('click', function() {
            const element = document.getElementById('reportContent');
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'water_meter_report_<?php echo date('Y-m-d'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });
        
        // Export to Excel
        document.getElementById('exportExcel').addEventListener('click', function() {
            const table = document.querySelector('.report-section table');
            const html = table.outerHTML;
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.download = 'water_meter_report_<?php echo date('Y-m-d'); ?>.xls';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>