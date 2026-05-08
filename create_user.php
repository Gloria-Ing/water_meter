<?php
// create_user.php - Run from command line: php create_user.php
require_once 'config/config.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

echo "Create New User\n";
echo "===============\n";

echo "Enter name: ";
$name = trim(fgets(STDIN));

echo "Enter phone: ";
$phone = trim(fgets(STDIN));

echo "Enter email: ";
$email = trim(fgets(STDIN));

echo "Enter password: ";
system('stty -echo');
$password = trim(fgets(STDIN));
system('stty echo');
echo "\n";

echo "Enter role (admin/client): ";
$role = trim(fgets(STDIN));

echo "Enter tariff (default 100): ";
$tariff = trim(fgets(STDIN));
if (empty($tariff)) $tariff = 100;

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$conn = getDB();
$stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, role, tariff) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssd", $name, $phone, $email, $hashedPassword, $role, $tariff);

if ($stmt->execute()) {
    echo "\n✅ User created successfully!\n";
    echo "User ID: " . $conn->insert_id . "\n";
} else {
    echo "\n❌ Error: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
?>