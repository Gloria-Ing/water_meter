<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, name, email, role, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: client/dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Water Meter System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
        }
        h2 { text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #666; }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .error { background: #fed7d7; color: #c53030; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Water Meter System</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="register.php">Register</a>
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html>