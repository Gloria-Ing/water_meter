<?php
// test_connection.php
require_once 'config/config.php';

$conn = getDB();
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

echo "Database connected successfully!<br>";
echo "Total users: " . $row['count'] . "<br>";
echo "<a href='login.php'>Go to Login →</a>";
?>