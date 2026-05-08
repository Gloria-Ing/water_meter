<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: client/dashboard.php');
    }
} else {
    header('Location: login.php');
}
exit();
?>