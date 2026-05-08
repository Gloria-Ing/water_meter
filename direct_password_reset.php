<?php
// direct_password_reset.php - Force password reset
require_once 'config/config.php';

$conn = getDB();

// Method 1: Using password_hash() directly
$adminPassword = 'admin123';
$newAdminHash = password_hash($adminPassword, PASSWORD_DEFAULT);

// Update admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $newAdminHash, 'admin@watermeter.com');

if ($stmt->execute()) {
    echo "<p style='color:green'>✅ Admin password reset to: <strong>admin123</strong></p>";
    echo "<p>New Hash: <code>" . $newAdminHash . "</code></p>";
} else {
    echo "<p style='color:red'>❌ Admin update failed: " . $conn->error . "</p>";
}

// Update client
$clientPassword = 'client123';
$newClientHash = password_hash($clientPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $newClientHash, 'john@example.com');

if ($stmt->execute()) {
    echo "<p style='color:green'>✅ Client password reset to: <strong>client123</strong></p>";
} else {
    echo "<p style='color:red'>❌ Client update failed: " . $conn->error . "</p>";
}

// If admin doesn't exist, create it
$check = $conn->query("SELECT id FROM users WHERE email = 'admin@watermeter.com'");
if ($check->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff) VALUES (?, ?, ?, ?, ?, ?)");
    $name = "Administrator";
    $phone = "0788000001";
    $email = "admin@watermeter.com";
    $role = "admin";
    $tariff = 100;
    $stmt->bind_param("sssssi", $name, $phone, $email, $newAdminHash, $role, $tariff);
    $stmt->execute();
    echo "<p style='color:green'>✅ Admin user created!</p>";
}

// Verify the passwords work
echo "<hr>";
echo "<h3>Testing Login:</h3>";

// Test admin login
$testStmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
$testStmt->bind_param("s", $adminEmail);
$adminEmail = 'admin@watermeter.com';
$testStmt->execute();
$result = $testStmt->get_result();
if ($user = $result->fetch_assoc()) {
    if (password_verify('admin123', $user['password'])) {
        echo "<p style='color:green'>✅ Admin login works! (admin@watermeter.com / admin123)</p>";
    } else {
        echo "<p style='color:red'>❌ Admin login still failing</p>";
    }
}

echo "<hr>";
echo "<a href='login.php' style='display:inline-block; padding:10px 20px; background:#4CAF50; color:white; text-decoration:none; border-radius:5px;'>Go to Login →</a>";

$conn->close();
?>