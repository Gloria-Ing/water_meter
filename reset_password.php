<?php
// reset_password.php
require_once 'config/config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters!";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $conn = getDB();
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "✅ Password reset successfully!";
        } else {
            $message = "❌ Email not found!";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        input, button { width: 100%; padding: 10px; margin: 10px 0; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Reset Password</h2>
    <?php if ($message): ?>
        <div class="<?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>