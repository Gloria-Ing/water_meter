<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = sanitizeInput($_POST['email']);
    $conn = getDB();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->bind_param("ssi", $token, $expires, $user['id']);
        $stmt->execute();
        
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/water_meter/forgot_password.php?token=" . $token;
        $message = "Password reset link: <a href='$resetLink'>$resetLink</a><br>Expires in 1 hour.";
    } else {
        $error = "Email not found";
    }
    $conn->close();
}

if (isset($_GET['token'])) {
    $step = 'reset';
    $token = $_GET['token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($newPassword !== $confirm) {
        $error = "Passwords do not match";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user['id']);
            $stmt->execute();
            $message = "Password reset successful! <a href='login.php'>Login here</a>";
            $step = 'complete';
        } else {
            $error = "Invalid or expired token";
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            width: 100%;
            max-width: 450px;
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
        .message { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($step == 'request'): ?>
            <h2>Forgot Password</h2>
            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>
                <button type="submit" name="request_reset">Send Reset Link</button>
            </form>
            <div class="back-link"><a href="login.php">Back to Login</a></div>
        <?php elseif ($step == 'reset'): ?>
            <h2>Reset Password</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password">Reset Password</button>
            </form>
        <?php elseif ($step == 'complete'): ?>
            <h2>Complete</h2>
            <div class="message"><?php echo $message; ?></div>
            <div class="back-link"><a href="login.php">Go to Login</a></div>
        <?php endif; ?>
    </div>
</body>
</html>