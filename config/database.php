<?php
// config/database.php

// Read database credentials from environment variables (Render)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 4000;
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'water_meter';

// Define constants for backward compatibility
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $password);
define('DB_NAME', $database);
define('DB_PORT', $port);

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $host = DB_HOST;
        $port = DB_PORT;
        $user = DB_USER;
        $password = DB_PASS;
        $database = DB_NAME;
        
        // Create connection with SSL for TiDB Cloud
        $this->connection = mysqli_init();
        mysqli_ssl_set($this->connection, NULL, NULL, NULL, NULL, NULL);
        
        if (!$this->connection->real_connect($host, $user, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL)) {
            die("Connection failed: " . mysqli_connect_error());
        }
        
        $this->connection->set_charset("utf8mb4");
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
}
?>