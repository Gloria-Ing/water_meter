<?php
// fix_login.php - Complete login fix
require_once 'config/config.php';

$conn = getDB();

echo "<h1>Login Fix Tool</h1>";
echo "<hr>";

// Step 1: Check if users exist
echo "<h2>Step 1: Checking Users</h2>";
$result = $conn->query("SELECT id, name, email, role, password FROM users");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Password Hash</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td><code>" . substr($row['password'], 0, 30) . "...</code></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ No users found! Creating default users...</p>";
    
    // Insert default admin
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff) VALUES (?, ?, ?, ?, ?, ?)");
    $name = "Administrator";
    $phone = "0788000001";
    $email = "admin@watermeter.com";
    $role = "admin";
    $tariff = 100;
    $stmt->bind_param("sssssi", $name, $phone, $email, $adminHash, $role, $tariff);
    $stmt->execute();
    
    // Insert default client
    $clientHash = password_hash('client123', PASSWORD_DEFAULT);
    $name = "John Doe";
    $phone = "0788111111";
    $email = "john@example.com";
    $role = "client";
    $stmt->bind_param("sssssi", $name, $phone, $email, $clientHash, $role, $tariff);
    $stmt->execute();
    
    echo "<p style='color:green'>✅ Default users created!</p>";
}

// Step 2: Test password verification
echo "<h2>Step 2: Testing Password Verification</h2>";

$testEmails = ['admin@watermeter.com', 'john@example.com'];
$testPasswords = ['admin123', 'client123'];

foreach ($testEmails as $index => $email) {
    $password = $testPasswords[$index];
    
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        echo "<h3>Testing: $email</h3>";
        echo "Password entered: <strong>$password</strong><br>";
        echo "Stored hash: <code>" . substr($user['password'], 0, 30) . "...</code><br>";
        
        if (password_verify($password, $user['password'])) {
            echo "<p style='color:green'>✅ SUCCESS! Password matches!</p>";
        } else {
            echo "<p style='color:red'>❌ FAILED! Password does not match!</p>";
            
            // Fix the password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $newHash, $user['id']);
            $update->execute();
            echo "<p style='color:green'>✅ Password has been reset! Try again.</p>";
        }
    } else {
        echo "<p style='color:red'>❌ User not found: $email</p>";
    }
}

// Step 3: Show correct login form
echo "<hr>";
echo "<h2>Step 3: Login Form</h2>";
echo '<form method="POST" action="login.php">';
echo '<input type="email" name="email" placeholder="Email" style="padding:10px; margin:5px; width:200px;" required><br>';
echo '<input type="password" name="password" placeholder="Password" style="padding:10px; margin:5px; width:200px;" required><br>';
echo '<button type="submit" style="padding:10px 20px; background:#4CAF50; color:white; border:none; cursor:pointer;">Login</button>';
echo '</form>';

echo "<hr>";
echo "<h3>Default Credentials:</h3>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin@watermeter.com / admin123</li>";
echo "<li><strong>Client:</strong> john@example.com / client123</li>";
echo "</ul>";

$conn->close();
?>