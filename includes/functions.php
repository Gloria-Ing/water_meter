<?php
// includes/functions.php
require_once __DIR__ . '/../config/config.php';

function calculateBill($liters, $tariff) {
    return ($liters / 1000) * $tariff;
}

function formatCurrency($amount) {
    return number_format($amount, 2) . ' RWF';
}

function formatLiters($liters) {
    return number_format($liters, 2) . ' L';
}

// ===== ADD THIS MISSING FUNCTION =====
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
// ====================================

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

function getUserTotalUsage($userId, $startDate = null, $endDate = null) {
    $conn = getDB();
    $sql = "SELECT SUM(liters) as total_liters, SUM(bill) as total_bill FROM usage_data WHERE user_id = ?";
    
    if ($startDate && $endDate) {
        $sql .= " AND date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userId, $startDate, $endDate);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getUserLastPayment($userId) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? AND status = 'completed' ORDER BY date DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function addToSMSQueue($userId, $phone, $message) {
    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO sms_queue (user_id, phone, message, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iss", $userId, $phone, $message);
    return $stmt->execute();
}

function sendSMSViaAPI($phone, $message) {
    if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
        error_log("SMS disabled. Would send to $phone: $message");
        return true;
    }
    
    $url = 'https://api.africastalking.com/version1/messaging';
    $data = array(
        'username' => defined('SMS_USERNAME') ? SMS_USERNAME : 'sandbox',
        'to' => $phone,
        'message' => $message,
        'from' => defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'WATERMETER'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'apiKey: ' . (defined('SMS_API_KEY') ? SMS_API_KEY : '')
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 201;
}

function createAlert($userId, $type, $message) {
    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO alerts (user_id, alert_type, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $type, $message);
    return $stmt->execute();
}

function generateResetToken($userId) {
    $conn = getDB();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expires, $userId);
    $stmt->execute();
    
    return $token;
}

function validateResetToken($token) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
function generateReference() {
    return 'PAY_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -6));
}
?>