<?php
// fix_device_final.php - Final device fix
require_once 'config/config.php';

$conn = getDB();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Device - Final</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
        pre { background: #2c3e50; color: #4ec9b0; padding: 15px; border-radius: 5px; overflow-x: auto; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Final Device Fix</h1>";

// Get device info
$deviceId = 'WATER_METER_006';
$userId = 6;

// Update device to active
$conn->query("UPDATE devices SET status = 'active', last_seen = NOW() WHERE device_id = '$deviceId'");
echo "<div class='success'>✅ Device activated: $deviceId</div>";

// Get user info
$user = $conn->query("SELECT id, name, email FROM users WHERE id = $userId")->fetch_assoc();
echo "<div class='success'>✅ User found: {$user['name']} (ID: {$user['id']})</div>";

// Insert test data directly
$testLiters = 100;
$tariff = 100;
$testBill = ($testLiters / 1000) * $tariff;

$stmt = $conn->prepare("INSERT INTO usage_data (user_id, liters, bill) VALUES (?, ?, ?)");
$stmt->bind_param("idd", $userId, $testLiters, $testBill);
if ($stmt->execute()) {
    echo "<div class='success'>✅ Test data inserted: $testLiters Liters, Bill: $testBill RWF</div>";
} else {
    echo "<div class='error'>❌ Failed to insert test data</div>";
}
$stmt->close();

// Update user balance
$conn->query("UPDATE users SET balance = balance + $testBill WHERE id = $userId");

// Show current data
echo "<h2>📊 Current Usage Data</h2>";
$usage = $conn->query("SELECT * FROM usage_data WHERE user_id = $userId ORDER BY id DESC LIMIT 5");
if ($usage->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Liters</th><th>Bill</th><th>Date</th></tr>";
    while($row = $usage->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['liters']} L</td>";
        echo "<td>{$row['bill']} RWF</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "<table>";
} else {
    echo "<div class='error'>No data yet</div>";
}

// ESP32 Code
echo "<h2>📱 ESP32 Configuration Code</h2>";
echo "<div class='info'>";
echo "<p><strong>Copy these exact values to your ESP32 code:</strong></p>";
echo "<pre>
// ===== ESP32 WATER METER CONFIGURATION =====
#include &lt;ESP8266WiFi.h&gt;
#include &lt;ESP8266HTTPClient.h&gt;

// WiFi Credentials
const char* ssid = \"YOUR_WIFI_SSID\";
const char* password = \"YOUR_WIFI_PASSWORD\";

// Server Configuration
const char* serverHost = \"" . $_SERVER['SERVER_ADDR'] . "\";
const int serverPort = 80;

// Device Configuration (CRITICAL - Use these exact values)
const char* deviceId = \"$deviceId\";
const int userId = $userId;
const char* apiKey = \"BLISS_001\";

void sendWaterData(float liters) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        String url = \"http://\" + String(serverHost) + \":\" + String(serverPort) + \"/water_meter/api/record_usage.php\";
        
        http.begin(url);
        http.addHeader(\"Content-Type\", \"application/x-www-form-urlencoded\");
        http.addHeader(\"X-Device-Id\", deviceId);
        http.addHeader(\"X-API-Key\", apiKey);
        
        String postData = \"user_id=\" + String(userId) + \"&liters=\" + String(liters);
        
        int httpCode = http.POST(postData);
        
        if (httpCode == 200) {
            Serial.println(\"✅ Data sent successfully!\");
            Serial.println(http.getString());
        } else {
            Serial.print(\"❌ Error: HTTP \");
            Serial.println(httpCode);
        }
        
        http.end();
    } else {
        Serial.println(\"WiFi not connected\");
    }
}

void setup() {
    Serial.begin(115200);
    WiFi.begin(ssid, password);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(\".\");
    }
    Serial.println(\"\\nWiFi connected!\");
    
    // Send test data
    sendWaterData(100);
}

void loop() {
    // Send data every 30 seconds
    static unsigned long lastSend = 0;
    if (millis() - lastSend > 30000) {
        // Replace with actual flow sensor reading
        sendWaterData(50);
        lastSend = millis();
    }
}
</pre>";
echo "</div>";

// Test API with cURL
echo "<h2>🌐 Test API Manually</h2>";
$apiUrl = "http://" . $_SERVER['HTTP_HOST'] . "/water_meter/api/record_usage.php";
$postData = "user_id=$userId&liters=150";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Device-Id: ' . $deviceId,
    'X-API-Key: BLISS_001',
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div class='info'>";
echo "<p><strong>Testing API:</strong></p>";
echo "<p>URL: $apiUrl</p>";
echo "<p>Data: $postData</p>";
echo "<p>Device ID: $deviceId</p>";
echo "<p>HTTP Code: $httpCode</p>";
echo "<p>Response:</p>";
echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

if (strpos($response, 'success') !== false) {
    echo "<div class='success'>✅✅✅ API IS WORKING! Data is being sent successfully!</div>";
} else {
    echo "<div class='error'>❌ API still failing. Check the error message above.</div>";
}

echo "<hr>";
echo "<button onclick=\"location.href='check_database.php'\">Refresh Database Check</button> ";
echo "<button onclick=\"location.href='admin/dashboard.php'\">Go to Admin Dashboard</button> ";
echo "<button onclick=\"location.href='test_api.html'\">Open API Tester</button>";

echo "</div></body></html>";
$conn->close();
?>