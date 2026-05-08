<?php
// config/config.php
session_start();

define('APP_NAME', 'Water Meter Billing System');
define('APP_URL', 'http://localhost/water_meter');
define('TARIFF_RATE', 100);
define('SMS_ENABLED', true);
define('DEBUG_MODE', true);
define('SMS_BALANCE_THRESHOLD', 10000);
define('SMS_API_KEY', 'YOUR_AFRICASTALKING_API_KEY');
define('SMS_USERNAME', 'sandbox');
define('SMS_SENDER_ID', 'WATERMETER');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

require_once __DIR__ . '/database.php';

function getDB() {
    return Database::getInstance()->getConnection();
}
?>