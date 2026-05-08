<?php
// test_api_direct.php - Test API without ESP32
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test API</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>API Test Tool</h1>
        
        <div class="card">
            <h2>Test 1: Send Water Usage Data</h2>
            <button onclick="sendData(100)">Send 100 Liters</button>
            <button onclick="sendData(500)">Send 500 Liters</button>
            <button onclick="sendData(1000)">Send 1000 Liters</button>
            <pre id="result1"></pre>
        </div>
        
        <div class="card">
            <h2>Test 2: Check Database</h2>
            <button onclick="checkDatabase()">Check Latest Records</button>
            <pre id="result2"></pre>
        </div>
        
        <div class="card">
            <h2>Test 3: Check Device Status</h2>
            <button onclick="checkDevice()">Check Device</button>
            <pre id="result3"></pre>
        </div>
    </div>
    
    <script>
        function sendData(liters) {
            fetch('api/record_usage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Device-Id': 'WATER_METER_001',
                    'X-API-Key': 'BLISS_001'
                },
                body: 'user_id=2&liters=' + liters
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('result1').innerHTML = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                document.getElementById('result1').innerHTML = 'Error: ' + error;
            });
        }
        
        function checkDatabase() {
            fetch('check_database.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('result2').innerHTML = data;
            });
        }
        
        function checkDevice() {
            fetch('api/check_balance.php', {
                headers: {
                    'X-Device-Id': 'WATER_METER_001'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('result3').innerHTML = JSON.stringify(data, null, 2);
            });
        }
    </script>
</body>
</html>