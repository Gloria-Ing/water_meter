<?php
// admin/confirm_payment.php - Confirm pending payment
require_once '../config/config.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$conn = getDB();
$payment_id = intval($_POST['id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);

if (!$payment_id || !$user_id || !$amount) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Update payment status
$stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();

// Update user balance (reduce balance)
$updateStmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
$updateStmt->bind_param("di", $amount, $user_id);
$updateStmt->execute();

// Get user info for SMS
$userStmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if ($user) {
    $smsMessage = "PAYMENT CONFIRMED\n";
    $smsMessage .= "Dear " . $user['name'] . ",\n";
    $smsMessage .= "Your payment of " . number_format($amount, 2) . " RWF has been confirmed.\n";
    $smsMessage .= "Thank you!";
    
    addToSMSQueue($user_id, $user['phone'], $smsMessage);
}

echo json_encode(['success' => true]);

$conn->close();
?>