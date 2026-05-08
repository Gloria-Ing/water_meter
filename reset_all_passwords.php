<?php
// reset_all_passwords.php - Complete password reset
require_once 'config/config.php';

$conn = getDB();

// First, clear all users
$conn->query("TRUNCATE TABLE users");

// Insert admin
$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssi", $name, $phone, $email, $adminHash, $role, $tariff);

$name = "Administrator";
$phone = "0788000001";
$email = "admin@watermeter.com";
$role = "admin";
$tariff = 100;
$stmt->execute();

// Insert client
$clientHash = password_hash('client123', PASSWORD_DEFAULT);
$name = "John Doe";
$phone = "0788111111";
$email = "john@example.com";
$role = "client";
$tariff = 100;
$stmt->execute();

// Insert test user
$testHash = password_hash('test123', PASSWORD_DEFAULT);
$name = "Test User";
$phone = "0788222222";
$email = "test@example.com";
$role = "client";
$tariff = 100;
$stmt->execute();

echo "<h2>✅ All passwords reset successfully!</h2>";
echo "<h3>Login Credentials:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Account</th><th>Email</th><th>Password</th></tr>";
echo "<tr><td>Admin</td><td>admin@watermeter.com</td><td>admin123</td></tr>";
echo "<tr><td>Client</td><td>john@example.com</td><td>client123</td></tr>";
echo "<tr><td>Test</td><td>test@example.com</td><td>test123</td></tr>";
echo "</table>";
echo "<br><a href='login.php' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Login →</a>";

$stmt->close();
$conn->close();
?>