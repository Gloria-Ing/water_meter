<?php
// test_login.php - Test login credentials
require_once 'config/config.php';

$conn = getDB();

echo "<h2>Login Credentials Test</h2>";

// Test admin login
$email = 'admin@watermeter.com';
$password = 'admin123';
$testPassword = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    echo "<h3>Admin Account:</h3>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    
    if (password_verify($password, $user['password'])) {
        echo "<span style='color:green'>✓ Password 'admin123' works!</span><br>";
    } else {
        echo "<span style='color:red'>✗ Password doesn't match. Run: UPDATE users SET password = '$testPassword' WHERE email = 'admin@watermeter.com';</span><br>";
    }
} else {
    echo "<span style='color:red'>✗ Admin user not found! Run INSERT query above.</span><br>";
}

echo "<hr>";

// Test client login
$email = 'john@example.com';
$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    echo "<h3>Client Account:</h3>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    
    if (password_verify('client123', $user['password'])) {
        echo "<span style='color:green'>✓ Password 'client123' works!</span><br>";
    } else {
        echo "<span style='color:red'>✗ Password doesn't match. Run UPDATE query.</span><br>";
    }
} else {
    echo "<span style='color:red'>✗ Client user not found! Run INSERT query above.</span><br>";
}

echo "<hr>";
echo "<a href='login.php'>Go to Login Page →</a>";

$conn->close();
?>