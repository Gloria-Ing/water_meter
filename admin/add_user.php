<?php
// admin/add_user.php - Add user with automatic device creation
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? 'client');
    $tariff = floatval($_POST['tariff'] ?? 100);
    $balance = floatval($_POST['balance'] ?? 0);
    
    // Device fields
    $create_device = isset($_POST['create_device']) ? $_POST['create_device'] : 'no';
    $device_name = sanitizeInput($_POST['device_name'] ?? '');
    $device_id = sanitizeInput($_POST['device_id'] ?? '');
    
    // Validation
    if (empty($name) || empty($phone) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (!validatePhone($phone)) {
        $error = 'Invalid phone number (10-15 digits)';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif ($tariff <= 0) {
        $error = 'Tariff must be greater than 0';
    } else {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssssdd", $name, $phone, $email, $hashedPassword, $role, $tariff, $balance);
            
            if ($insertStmt->execute()) {
                $newUserId = $conn->insert_id;
                
                // Create device if requested
                $deviceCreated = false;
                $deviceIdGenerated = '';
                
                if ($create_device == 'yes' && $role == 'client') {
                    // Generate device ID if not provided
                    if (empty($device_id)) {
                        $deviceIdGenerated = 'WATER_METER_' . str_pad($newUserId, 3, '0', STR_PAD_LEFT);
                    } else {
                        $deviceIdGenerated = $device_id;
                    }
                    
                    // Use default device name if not provided
                    if (empty($device_name)) {
                        $device_name = $name . "'s Water Meter";
                    }
                    
                    // Check if device ID already exists
                    $checkDevice = $conn->prepare("SELECT id FROM devices WHERE device_id = ?");
                    $checkDevice->bind_param("s", $deviceIdGenerated);
                    $checkDevice->execute();
                    
                    if ($checkDevice->get_result()->num_rows == 0) {
                        $deviceStmt = $conn->prepare("INSERT INTO devices (user_id, device_id, device_name, status) VALUES (?, ?, ?, 'active')");
                        $deviceStmt->bind_param("iss", $newUserId, $deviceIdGenerated, $device_name);
                        
                        if ($deviceStmt->execute()) {
                            $deviceCreated = true;
                        }
                        $deviceStmt->close();
                    }
                    $checkDevice->close();
                }
                
                // Prepare success message
                $message = "✅ User created successfully!<br>";
                $message .= "User ID: <strong>$newUserId</strong><br>";
                $message .= "Name: $name<br>";
                $message .= "Email: $email<br>";
                $message .= "Role: " . ucfirst($role) . "<br>";
                
                if ($deviceCreated) {
                    $message .= "<br>📱 <strong>Device Created:</strong><br>";
                    $message .= "Device ID: <code>$deviceIdGenerated</code><br>";
                    $message .= "Device Name: $device_name<br>";
                    $message .= "<br>📌 <strong>ESP32 Configuration:</strong><br>";
                    $message .= "<code>const char* deviceId = \"$deviceIdGenerated\";</code><br>";
                    $message .= "<code>const int userId = $newUserId;</code>";
                }
                
                // Clear form
                $name = $phone = $email = '';
                $tariff = 100;
                $balance = 0;
                
                // Log the action
                $logStmt = $conn->prepare("INSERT INTO system_logs (log_type, message, user_id) VALUES ('user_creation', ?, ?)");
                $logMessage = "Admin created user: $email with role: $role" . ($deviceCreated ? " and device: $deviceIdGenerated" : "");
                $logStmt->bind_param("si", $logMessage, $newUserId);
                $logStmt->execute();
                $logStmt->close();
            } else {
                $error = 'Failed to create user: ' . $conn->error;
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}

// Get statistics for display
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'")->fetch_assoc();
$totalDevices = $conn->query("SELECT COUNT(*) as count FROM devices")->fetch_assoc();
$totalAdmins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Admin Panel</title>
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
            max-width: 900px;
            margin: 0 auto;
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
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h3 {
            color: #2c3e50;
            margin: 20px 0 15px 0;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        .required:after {
            content: " *";
            color: red;
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
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #229954;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        .btn-back {
            display: inline-block;
            background: #95a5a6;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-back:hover {
            background: #7f8c8d;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .info-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .stats {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #666;
            font-size: 12px;
        }
        .device-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #3498db;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .checkbox-group input {
            width: auto;
        }
        code {
            background: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #ddd;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Add New User</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="devices.php">Devices</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <a href="users.php" class="btn-back">← Back to Users List</a>
        
        <div class="form-card">
            <h2>📝 Create New User Account</h2>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="userForm">
                <!-- Personal Information -->
                <h3>👤 Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                        <div class="info-text">Format: 0788xxxxxx (10-15 digits)</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="required">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    <div class="info-text">Will be used for login</div>
                </div>
                
                <!-- Account Settings -->
                <h3>⚙️ Account Settings</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Role</label>
                        <select name="role" id="role" required>
                            <option value="client" selected>🏠 Client - Water Consumer</option>
                            <option value="admin">👑 Admin - System Administrator</option>
                        </select>
                        <div class="info-text">Admins have full system access</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Tariff (RWF per 1000 Liters)</label>
                        <input type="number" name="tariff" step="0.01" value="<?php echo $tariff ?? 100; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Initial Balance (RWF)</label>
                    <input type="number" name="balance" step="0.01" value="<?php echo $balance ?? 0; ?>">
                    <div class="info-text">Starting balance for the user</div>
                </div>
                
                <!-- Password -->
                <h3>🔒 Login Credentials</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Password</label>
                        <input type="password" name="password" id="password" required>
                        <div class="info-text">Minimum 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
                    </div>
                </div>
                
                <!-- Device Creation Section (only for clients) -->
                <div id="deviceSection" style="display: none;">
                    <h3>📱 IoT Water Meter Device</h3>
                    <div class="device-section">
                        <div class="checkbox-group">
                            <input type="checkbox" name="create_device" value="yes" id="create_device">
                            <label for="create_device">Create IoT device for this user</label>
                        </div>
                        
                        <div id="deviceFields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Device Name</label>
                                    <input type="text" name="device_name" placeholder="e.g., Home Water Meter">
                                    <div class="info-text">Leave empty for auto-generated name</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Device ID (Optional)</label>
                                    <input type="text" name="device_id" placeholder="Leave empty for auto-generate">
                                    <div class="info-text">Auto-format: WATER_METER_XXX</div>
                                </div>
                            </div>
                            
                            <div class="info-text" style="background: #e8f4fd; padding: 10px; border-radius: 5px;">
                                <strong>📌 Note:</strong> The device ID will be used to identify this water meter. 
                                You'll need to program this ID into the ESP32 device.
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <button type="submit">✨ Create User Account</button>
            </form>
        </div>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo $totalUsers['count']; ?></div>
                <div class="stat-label">Total Clients</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $totalAdmins['count']; ?></div>
                <div class="stat-label">Total Admins</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $totalDevices['count']; ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
        </div>
        
        <div style="margin-top: 20px; background: #e8f4fd; padding: 15px; border-radius: 5px;">
            <h4>💡 Quick Tips:</h4>
            <ul style="margin-left: 20px; color: #666;">
                <li>Users will use their email and password to login</li>
                <li>Admins have full system access</li>
                <li>Clients can only view their own water usage and bills</li>
                <li>Checking "Create IoT device" will automatically register a water meter for this user</li>
                <li>The device ID is needed for ESP32 programming</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Show/hide device section based on role
        const roleSelect = document.getElementById('role');
        const deviceSection = document.getElementById('deviceSection');
        const createDeviceCheckbox = document.getElementById('create_device');
        const deviceFields = document.getElementById('deviceFields');
        
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                if (this.value === 'client') {
                    deviceSection.style.display = 'block';
                } else {
                    deviceSection.style.display = 'none';
                    createDeviceCheckbox.checked = false;
                    deviceFields.style.display = 'none';
                }
            });
            
            // Trigger on page load
            if (roleSelect.value === 'client') {
                deviceSection.style.display = 'block';
            }
        }
        
        if (createDeviceCheckbox) {
            createDeviceCheckbox.addEventListener('change', function() {
                deviceFields.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        // Password match validation
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const matchDiv = document.getElementById('passwordMatch');
        
        function checkMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
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
        
        if (confirmInput) {
            confirmInput.addEventListener('input', checkMatch);
            passwordInput.addEventListener('input', checkMatch);
        }
        
        // Form validation
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
        // Phone number formatting
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let phone = this.value.replace(/\D/g, '');
                if (phone.length > 15) {
                    phone = phone.slice(0, 15);
                }
                this.value = phone;
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>