<?php
// config/config.php

session_start();

// App Settings
define('APP_NAME', 'Water Meter Billing System');
define('TARIFF_RATE', 100);
define('SMS_BALANCE_THRESHOLD', 10000);

// Database - Read from Render environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 4000;
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'water_meter_db';

// Create connection with SSL (required for TiDB Cloud)
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!$conn->real_connect($host, $user, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Connection failed: " . mysqli_connect_error());
}

// Helper function
function getDB() {
    global $conn;
    return $conn;
}
?>