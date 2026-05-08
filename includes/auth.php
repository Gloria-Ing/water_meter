<?php
// includes/auth.php
require_once __DIR__ . '/../config/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/client/dashboard.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDB();
    $stmt = $conn->prepare("SELECT id, name, email, phone, role, tariff, balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>