<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();
$message = '';
$error = '';

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId == 0) {
    header('Location: users.php');
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $tariff = floatval($_POST['tariff'] ?? 100);
    $balance = floatval($_POST['balance'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($phone) || empty($email)) {
        $error = 'Please fill in all required fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (!validatePhone($phone)) {
        $error = 'Invalid phone number (10-15 digits)';
    } elseif ($tariff <= 0) {
        $error = 'Tariff must be greater than 0';
    } else {
        // Check if email exists for other users
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $email, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Email already exists for another user';
        } else {
            // Update user information
            if (!empty($newPassword)) {
                // Update with new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, email = ?, tariff = ?, balance = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("sssddsi", $name, $phone, $email, $tariff, $balance, $hashedPassword, $userId);
            } else {
                // Update without changing password
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, email = ?, tariff = ?, balance = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("sssddi", $name, $phone, $email, $tariff, $balance, $userId);
            }
            
            if ($updateStmt->execute()) {
                $message = "User updated successfully!";
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update user: " . $conn->error;
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

// Get usage statistics for this user
$usageStats = getUserTotalUsage($userId);
$lastPayment = getUserLastPayment($userId);

// Get recent usage records
$recentUsage = $conn->prepare("
    SELECT * FROM usage_data 
    WHERE user_id = ? 
    ORDER BY date DESC 
    LIMIT 10
");
$recentUsage->bind_param("i", $userId);
$recentUsage->execute();
$usageHistory = $recentUsage->get_result();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Panel</title>
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
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3498db;
        }
        button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #2980b9;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .stats-card p {
            margin: 5px 0;
        }
        .stats-label {
            font-weight: bold;
            color: #666;
        }
        .stats-value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        .btn-back {
            display: inline-block;
            background: #95a5a6;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .btn-back:hover {
            background: #7f8c8d;
        }
        .btn-danger {
            background: #e74c3c;
            margin-top: 10px;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .password-note {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .badge {
            display: inline-block;
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
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Edit User - Admin Panel</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="change_password.php">Change Password</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <a href="users.php" class="btn-back">← Back to Users List</a>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="grid-2">
            <!-- Edit User Form -->
            <div>
                <div class="section">
                    <h2>Edit User Information</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tariff (RWF per 1000 Liters)</label>
                            <input type="number" name="tariff" step="0.01" value="<?php echo $user['tariff']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Balance (RWF)</label>
                            <input type="number" name="balance" step="0.01" value="<?php echo $user['balance']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>New Password (leave empty to keep current)</label>
                            <input type="password" name="new_password" id="new_password">
                            <div class="password-note">Minimum 6 characters. Leave blank to keep current password.</div>
                            <div id="passwordStrength" style="font-size: 12px; margin-top: 5px;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password">
                            <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
                        </div>
                        
                        <button type="submit">Update User</button>
                    </form>
                </div>
                
                <!-- Danger Zone -->
                <div class="section">
                    <h2 style="color: #e74c3c;">Danger Zone</h2>
                    <p style="margin-bottom: 15px;">Once you delete a user, all their data including usage history and payments will be permanently removed.</p>
                    <button class="btn-danger" onclick="confirmDelete(<?php echo $userId; ?>)">Delete User Account</button>
                </div>
            </div>
            
            <!-- User Statistics -->
            <div>
                <div class="section">
                    <h2>User Statistics</h2>
                    <div class="stats-card">
                        <p><span class="stats-label">User ID:</span> <span class="stats-value"><?php echo $user['id']; ?></span></p>
                        <p><span class="stats-label">Role:</span> <span class="stats-value"><?php echo ucfirst($user['role']); ?></span></p>
                        <p><span class="stats-label">Member Since:</span> <span class="stats-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span></p>
                        <p><span class="stats-label">Last Updated:</span> <span class="stats-value"><?php echo date('F d, Y', strtotime($user['updated_at'])); ?></span></p>
                    </div>
                    
                    <div class="stats-card">
                        <p><span class="stats-label">Total Water Usage:</span> <span class="stats-value"><?php echo formatLiters($usageStats['total_liters'] ?? 0); ?></span></p>
                        <p><span class="stats-label">Total Bill Amount:</span> <span class="stats-value"><?php echo formatCurrency($usageStats['total_bill'] ?? 0); ?></span></p>
                        <p><span class="stats-label">Current Balance:</span> <span class="stats-value" style="color: <?php echo $user['balance'] > 0 ? '#e74c3c' : '#27ae60'; ?>">
                            <?php echo formatCurrency($user['balance']); ?>
                        </span></p>
                        <?php if ($lastPayment): ?>
                        <p><span class="stats-label">Last Payment:</span> <span class="stats-value"><?php echo formatCurrency($lastPayment['amount']); ?> on <?php echo date('Y-m-d', strtotime($lastPayment['date'])); ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Usage -->
                <div class="section">
                    <h2>Recent Usage Records</h2>
                    <div style="overflow-x: auto; max-height: 300px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Liters</th>
                                    <th>Bill (RWF)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($usageHistory->num_rows > 0): ?>
                                    <?php while($record = $usageHistory->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($record['date'])); ?></td>
                                        <td><?php echo number_format($record['liters'], 2); ?></td>
                                        <td><?php echo number_format($record['bill'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center;">No usage records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Payment History -->
                <div class="section">
                    <h2>Payment History</h2>
                    <div style="overflow-x: auto; max-height: 300px;">
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
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo $payment['reference']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No payment records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            if (strength <= 2) return { text: 'Weak', class: 'strength-weak', color: '#e74c3c' };
            if (strength <= 4) return { text: 'Medium', class: 'strength-medium', color: '#f39c12' };
            return { text: 'Strong', class: 'strength-strong', color: '#27ae60' };
        }
        
        // Real-time password strength
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthDiv = document.getElementById('passwordStrength');
        const matchDiv = document.getElementById('passwordMatch');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                if (password.length > 0) {
                    const strength = checkPasswordStrength(password);
                    strengthDiv.innerHTML = 'Password Strength: <span style="color: ' + strength.color + ';">' + strength.text + '</span>';
                } else {
                    strengthDiv.innerHTML = '';
                }
                
                // Check match if confirm has value
                if (confirmPasswordInput.value.length > 0) {
                    checkMatch();
                }
            });
        }
        
        function checkMatch() {
            const newPass = newPasswordInput.value;
            const confirmPass = confirmPasswordInput.value;
            
            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    matchDiv.innerHTML = '✓ Passwords match';
                    matchDiv.style.color = '#27ae60';
                } else {
                    matchDiv.innerHTML = '✗ Passwords do not match';
                    matchDiv.style.color = '#e74c3c';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkMatch);
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length > 0 || confirmPassword.length > 0) {
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
            }
        });
        
        // Confirm delete
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone and will delete all associated data including usage history and payments.')) {
                window.location.href = 'users.php?delete=' + userId;
            }
        }
    </script>
</body>
</html>
<?php
$recentUsage->close();
$payments->close();
$conn->close();
?>