<?php
// register.php - User registration with role selection
require_once 'config/config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Check if registration is allowed for admins (only admins can create other admins)
// For security, regular registration should only allow 'client' role
$allowAdminRegistration = false; // Set to true only for initial setup

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? 'client');
    
    // Security: Only allow 'client' role for public registration
    // Admins should be created by existing admins only
    if (!$allowAdminRegistration && $role == 'admin') {
        $role = 'client';
    }
    
    // Validation
    if (empty($name) || empty($phone) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (!validatePhone($phone)) {
        $error = 'Invalid phone number (10-15 digits)';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $conn = getDB();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already registered. Please use a different email or login.';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $tariff = 100; // Default tariff for clients
            $balance = 0;
            
            $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdd", $name, $phone, $email, $hashedPassword, $role, $tariff, $balance);
            
            if ($stmt->execute()) {
                $newUserId = $conn->insert_id;
                
                // Log the registration
                $logStmt = $conn->prepare("INSERT INTO system_logs (log_type, message, user_id, ip_address) VALUES (?, ?, ?, ?)");
                $logType = 'user_registration';
                $logMessage = "New user registered: $email as $role";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $logStmt->bind_param("ssis", $logType, $logMessage, $newUserId, $ipAddress);
                $logStmt->execute();
                $logStmt->close();
                
                $success = "Registration successful! You can now login as " . ucfirst($role) . ".";
                
                // Clear form
                $name = $phone = $email = '';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Water Meter System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
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
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: bold;
        }
        button:hover {
            background: #5a67d8;
        }
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c53030;
        }
        .success {
            background: #c6f6d5;
            color: #22543d;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #22543d;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .role-info {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
        .info-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>
        <div class="subtitle">Join Water Meter System</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label class="required">Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="required">Phone Number</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required placeholder="0788xxxxxx">
                <div class="info-text">Enter 10-15 digit phone number</div>
            </div>
            
            <div class="form-group">
                <label class="required">Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                <div class="info-text">Will be used for login and notifications</div>
            </div>
            
            <div class="form-group">
                <label class="required">Account Type (Role)</label>
                <select name="role" id="role" required>
                    <option value="client" selected>🏠 Client - Water Consumer</option>
                    <?php if ($allowAdminRegistration): ?>
                    <option value="admin">👑 Administrator - System Manager</option>
                    <?php endif; ?>
                </select>
                <div class="role-info">
                    📌 Clients can view their water usage and bills.<br>
                    <?php if (!$allowAdminRegistration): ?>
                    ℹ️ Admin accounts can only be created by existing administrators.
                    <?php else: ?>
                    👑 Admins can manage users, payments, and system settings.
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required">Password</label>
                <input type="password" name="password" id="password" required>
                <div class="password-strength" id="passwordStrength"></div>
                <div class="info-text">Minimum 6 characters</div>
            </div>
            
            <div class="form-group">
                <label class="required">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
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
            
            if (strength <= 2) return { text: 'Weak', class: 'strength-weak' };
            if (strength <= 4) return { text: 'Medium', class: 'strength-medium' };
            return { text: 'Strong', class: 'strength-strong' };
        }
        
        // Real-time password strength check
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthDiv = document.getElementById('passwordStrength');
        const matchDiv = document.getElementById('passwordMatch');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                if (password.length > 0) {
                    const strength = checkPasswordStrength(password);
                    strengthDiv.innerHTML = 'Password Strength: <span class="' + strength.class + '">' + strength.text + '</span>';
                } else {
                    strengthDiv.innerHTML = '';
                }
                
                if (confirmInput.value.length > 0) {
                    checkMatch();
                }
            });
        }
        
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
        }
        
        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
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