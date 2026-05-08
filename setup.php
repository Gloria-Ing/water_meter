<?php
// setup.php - Complete setup script
require_once 'config/config.php';

$conn = getDB();

// Drop and recreate tables
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DROP TABLE IF EXISTS alerts");
$conn->query("DROP TABLE IF EXISTS sms_queue");
$conn->query("DROP TABLE IF EXISTS payments");
$conn->query("DROP TABLE IF EXISTS usage_data");
$conn->query("DROP TABLE IF EXISTS devices");
$conn->query("DROP TABLE IF EXISTS users");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Create tables
$sql = "
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') DEFAULT 'client',
    tariff DECIMAL(10,2) DEFAULT 100.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE usage_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    liters DECIMAL(10,2) DEFAULT 0,
    bill DECIMAL(10,2) DEFAULT 0,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date)
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(50) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
);

CREATE TABLE sms_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
);

CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(50) UNIQUE NOT NULL,
    device_name VARCHAR(100),
    last_seen TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_type VARCHAR(50),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
";

// Execute SQL
if ($conn->multi_query($sql)) {
    do {
        $conn->next_result();
    } while ($conn->more_results());
    echo "✅ Tables created successfully<br>";
} else {
    echo "❌ Error creating tables: " . $conn->error . "<br>";
}

// Insert default users with correct password hashes
$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
$clientHash = password_hash('client123', PASSWORD_DEFAULT);

$users = [
    ['Administrator', '0788000001', 'admin@watermeter.com', $adminHash, 'admin', 100, 0],
    ['John Doe', '0788111111', 'john@example.com', $clientHash, 'client', 100, 0],
    ['Test User', '0788222222', 'test@example.com', password_hash('test123', PASSWORD_DEFAULT), 'client', 100, 0]
];

foreach ($users as $user) {
    $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssdd", $user[0], $user[1], $user[2], $user[3], $user[4], $user[5], $user[6]);
    if ($stmt->execute()) {
        echo "✅ User created: " . $user[2] . "<br>";
    }
    $stmt->close();
}

// Insert devices
$devices = [
    [2, 'WATER_METER_001', 'Home Water Meter', 'active'],
    [3, 'WATER_METER_002', 'Test Device', 'active']
];

foreach ($devices as $device) {
    $stmt = $conn->prepare("INSERT INTO devices (user_id, device_id, device_name, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $device[0], $device[1], $device[2], $device[3]);
    $stmt->execute();
    $stmt->close();
}

echo "<hr>";
echo "<h2>✅ Setup Complete!</h2>";
echo "<h3>Login Credentials:</h3>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin@watermeter.com / admin123</li>";
echo "<li><strong>Client:</strong> john@example.com / client123</li>";
echo "<li><strong>Test:</strong> test@example.com / test123</li>";
echo "</ul>";
echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page →</a>";

$conn->close();
?>