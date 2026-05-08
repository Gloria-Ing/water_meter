<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters for admin accounts';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = 'Password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*]/', $newPassword)) {
        $error = 'Password must contain at least one special character (!@#$%^&*)';
    } else {
        $result = changePassword($_SESSION['user_id'], $currentPassword, $newPassword);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Panel</title>
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
            max-width: 500px;
            margin: 50px auto;
        }
        .password-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
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
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #3498db;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
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
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            width: 100%;
            color: #3498db;
            text-decoration: none;
        }
        .requirements {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .requirements ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        .requirements li {
            color: #666;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Panel</h1>
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
        <div class="password-card">
            <h2>Change Password</h2>
            
            <div class="requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>Minimum 8 characters</li>
                    <li>At least one uppercase letter</li>
                    <li>At least one lowercase letter</li>
                    <li>At least one number</li>
                    <li>At least one special character (!@#$%^&*)</li>
                </ul>
            </div>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" required>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <button type="submit">Change Password</button>
            </form>
            
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            let strength = 0;
            let requirements = [];
            
            if (password.length >= 8) strength++;
            else requirements.push('8+ characters');
            
            if (password.match(/[a-z]+/)) strength++;
            else requirements.push('lowercase letter');
            
            if (password.match(/[A-Z]+/)) strength++;
            else requirements.push('uppercase letter');
            
            if (password.match(/[0-9]+/)) strength++;
            else requirements.push('number');
            
            if (password.match(/[!@#$%^&*]+/)) strength++;
            else requirements.push('special character');
            
            if (strength <= 2) return { text: 'Weak - Missing: ' + requirements.join(', '), class: 'strength-weak' };
            if (strength <= 4) return { text: 'Medium - Missing: ' + requirements.join(', '), class: 'strength-medium' };
            return { text: 'Strong - All requirements met!', class: 'strength-strong' };
        }
        
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            const strengthDiv = document.getElementById('passwordStrength');
            strengthDiv.innerHTML = '<span class="' + strength.class + '">' + strength.text + '</span>';
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchDiv.innerHTML = '✓ Passwords match';
                    matchDiv.style.color = '#27ae60';
                } else {
                    matchDiv.innerHTML = '✗ Passwords do not match';
                    matchDiv.style.color = '#e74c3c';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!newPassword.match(/[a-z]/)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter!');
                return false;
            }
            
            if (!newPassword.match(/[A-Z]/)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter!');
                return false;
            }
            
            if (!newPassword.match(/[0-9]/)) {
                e.preventDefault();
                alert('Password must contain at least one number!');
                return false;
            }
            
            if (!newPassword.match(/[!@#$%^&*]/)) {
                e.preventDefault();
                alert('Password must contain at least one special character (!@#$%^&*)!');
                return false;
            }
        });
    </script>
</body>
</html>