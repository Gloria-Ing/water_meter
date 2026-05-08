<?php
// test.php - Place this in your root folder
echo "<h1>Water Meter API Test</h1>";
echo "<p>Your app is running!</p>";

// Show all files in current directory
echo "<h2>Files in root directory:</h2>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    echo "<li>" . $file . "</li>";
}
echo "</ul>";

// Check for api folder
if (is_dir('api')) {
    echo "<h2>Files in /api folder:</h2>";
    $api_files = scandir('api');
    echo "<ul>";
    foreach ($api_files as $file) {
        echo "<li>api/" . $file . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>No 'api' folder found!</p>";
}

// Test database connection
echo "<h2>Database Test:</h2>";
require_once 'config/database.php';
$db = Database::getInstance();
echo "<p style='color:green'>✓ Database connected!</p>";
?>