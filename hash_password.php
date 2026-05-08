<?php
// hash_password.php - Run this file to generate password hashes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h3>Password Hash Generated:</h3>";
    echo "<p>Password: <strong>" . htmlspecialchars($password) . "</strong></p>";
    echo "<p>Hash: <code>" . $hash . "</code></p>";
    echo "<p>Copy this hash to your database.</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        input, button { padding: 10px; margin: 5px; }
        code { background: #f4f4f4; padding: 10px; display: block; }
    </style>
</head>
<body>
    <h2>Password Hash Generator</h2>
    <form method="POST">
        <input type="text" name="password" placeholder="Enter password" required>
        <button type="submit">Generate Hash</button>
    </form>
</body>
</html>