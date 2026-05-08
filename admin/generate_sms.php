<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDB();
    
    // Get all clients
    $clients = $conn->query("SELECT id, name, phone, balance FROM users WHERE role = 'client'");
    
    while ($client = $clients->fetch_assoc()) {
        $usage = getUserTotalUsage($client['id']);
        $totalLiters = $usage['total_liters'] ?? 0;
        $totalBill = $usage['total_bill'] ?? 0;
        
        $message = "Dear " . $client['name'] . ", ";
        $message .= "Total Usage: " . formatLiters($totalLiters) . ", ";
        $message .= "Bill: " . formatCurrency($totalBill) . ", ";
        $message .= "Balance: " . formatCurrency($client['balance']);
        
        addToSMSQueue($client['id'], $client['phone'], $message);
    }
    
    $_SESSION['message'] = "SMS messages generated successfully";
    $conn->close();
}

header('Location: dashboard.php');
exit();
?>