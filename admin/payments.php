<?php
// admin/payments.php - Payment Management System
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();
$message = '';
$error = '';

// =====================================================
// PROCESS PAYMENT (Manual Entry)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = generateReference();
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if ($user_id <= 0 || $amount <= 0) {
        $error = 'Please select a valid user and enter amount';
    } else {
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, reference, status, payment_method, notes) VALUES (?, ?, ?, 'completed', ?, ?)");
        $stmt->bind_param("idsss", $user_id, $amount, $reference, $payment_method, $notes);
        
        if ($stmt->execute()) {
            // Update user balance (reduce balance)
            $updateStmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $updateStmt->bind_param("di", $amount, $user_id);
            $updateStmt->execute();
            $updateStmt->close();
            
            $message = "Payment of " . formatCurrency($amount) . " recorded successfully! Reference: $reference";
            
            // Add to SMS queue
            $userStmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            
            if ($user) {
                $smsMessage = "PAYMENT RECEIVED\n";
                $smsMessage .= "Dear " . $user['name'] . ",\n";
                $smsMessage .= "We have received your payment of " . formatCurrency($amount) . ".\n";
                $smsMessage .= "Reference: $reference\n";
                $smsMessage .= "Thank you for your payment!";
                
                addToSMSQueue($user_id, $user['phone'], $smsMessage);
            }
            $userStmt->close();
        } else {
            $error = "Failed to process payment: " . $conn->error;
        }
        $stmt->close();
    }
}

// =====================================================
// DELETE PAYMENT
// =====================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $payment_id = intval($_GET['delete']);
    
    // Get payment details before deleting
    $stmt = $conn->prepare("SELECT user_id, amount FROM payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment) {
        // Reverse the balance update
        $updateStmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $updateStmt->bind_param("di", $payment['amount'], $payment['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Delete payment
        $deleteStmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $deleteStmt->bind_param("i", $payment_id);
        if ($deleteStmt->execute()) {
            $message = "Payment deleted and balance restored!";
        }
        $deleteStmt->close();
    }
    $stmt->close();
}

// =====================================================
// GET FILTERS
// =====================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// =====================================================
// BUILD QUERY
// =====================================================
$query = "SELECT p.*, u.name as user_name, u.email as user_email, u.phone as user_phone 
          FROM payments p 
          JOIN users u ON p.user_id = u.id 
          WHERE 1=1";

if ($status_filter != 'all') {
    $query .= " AND p.status = '$status_filter'";
}
if (!empty($search)) {
    $query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR p.reference LIKE '%$search%')";
}
$query .= " AND DATE(p.date) BETWEEN '$date_from' AND '$date_to'";
$query .= " ORDER BY p.date DESC";

$payments = $conn->query($query);

// =====================================================
// GET STATISTICS
// =====================================================
$statsQuery = "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
              FROM payments 
              WHERE DATE(date) BETWEEN '$date_from' AND '$date_to'";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get users with balance for dropdown
$users_balance = $conn->query("SELECT id, name, balance FROM users WHERE role = 'client' AND balance > 0 ORDER BY balance DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Admin Panel</title>
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
        }
        .nav-links a:hover {
            background: #34495e;
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
        .stat-card .sub {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
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
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #666;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #229954;
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
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
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
        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            font-size: 12px;
            margin-bottom: 3px;
        }
        .btn-delete {
            background: #e74c3c;
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        .btn-print {
            background: #3498db;
        }
        .btn-print:hover {
            background: #2980b9;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .filter-bar {
                flex-direction: column;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💰 Payment Management</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="generate_sms.php">SMS</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Payments</h3>
                <div class="value"><?php echo number_format($stats['total_amount'] ?? 0, 0); ?> RWF</div>
                <div class="sub"><?php echo $stats['completed_count'] ?? 0; ?> transactions</div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?php echo number_format($stats['pending_amount'] ?? 0, 0); ?> RWF</div>
                <div class="sub">Awaiting confirmation</div>
            </div>
            <div class="stat-card">
                <h3>Failed</h3>
                <div class="value"><?php echo number_format($stats['failed_amount'] ?? 0, 0); ?> RWF</div>
                <div class="sub">Payment failed</div>
            </div>
            <div class="stat-card">
                <h3>Period</h3>
                <div class="value"><?php echo date('d M', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?></div>
                <div class="sub">Selected date range</div>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Payment Form -->
            <div class="section">
                <h2>📝 Record Payment</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Select User *</label>
                        <select name="user_id" required>
                            <option value="">-- Select User --</option>
                            <?php while($user = $users_balance->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name']); ?> - Balance: <?php echo formatCurrency($user['balance']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount (RWF) *</label>
                        <input type="number" name="amount" step="0.01" min="0" required placeholder="Enter amount">
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <button type="submit" name="process_payment">💵 Process Payment</button>
                </form>
            </div>
            
            <!-- Filter Section -->
            <div class="section">
                <h2>🔍 Filter Payments</h2>
                <form method="GET" class="filter-bar">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Search</label>
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Name, email, reference..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit">Search</button>
                        </div>
                    </div>
                </form>
                
                <div style="margin-top: 10px;">
                    <button onclick="window.print()" class="btn-print">🖨️ Print Report</button>
                    <button onclick="exportToExcel()" class="btn-print">📊 Export to Excel</button>
                </div>
            </div>
        </div>
        
        <!-- Payments Table -->
        <div class="section">
            <h2>📋 Payment History</h2>
            <div style="overflow-x: auto;">
                <?php if ($payments->num_rows > 0): ?>
                <table id="paymentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount (RWF)</th>
                            <th>Reference</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['user_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($payment['user_email']); ?></small>
                            </td>
                            <td style="color: <?php echo $payment['status'] == 'completed' ? '#27ae60' : '#e74c3c'; ?>;">
                                <strong><?php echo number_format($payment['amount'], 2); ?> RWF</strong>
                            </td>
                            <td><?php echo $payment['reference']; ?></td>
                            <td><?php echo $payment['payment_method'] ?: 'N/A'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $payment['status']; ?>">
                                    <?php echo strtoupper($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($payment['date'])); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if($payment['status'] == 'pending'): ?>
                                    <button onclick="confirmPayment(<?php echo $payment['id']; ?>, <?php echo $payment['amount']; ?>, <?php echo $payment['user_id']; ?>)" class="btn" style="background:#27ae60; font-size:11px;">Confirm</button>
                                    <?php endif; ?>
                                    <button onclick="deletePayment(<?php echo $payment['id']; ?>)" class="btn-delete">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>📭 No payments found for the selected period</p>
                    <p>Try changing the date range or add a new payment</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function deletePayment(id) {
            if (confirm('⚠️ Are you sure you want to delete this payment?\nThis will restore the user\'s balance!')) {
                window.location.href = '?delete=' + id;
            }
        }
        
        function confirmPayment(id, amount, userId) {
            if (confirm('Confirm this payment of ' + amount.toLocaleString() + ' RWF?')) {
                // You can create a separate endpoint for confirming pending payments
                fetch('confirm_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id + '&user_id=' + userId + '&amount=' + amount
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment confirmed!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
        
        function exportToExcel() {
            var table = document.getElementById('paymentsTable');
            var html = table.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'payments_report_' + new Date().toISOString().slice(0,19) + '.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>