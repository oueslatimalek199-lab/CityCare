<?php
// Database configuration

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'citycare';

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset('utf8mb4');
    
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}
?>