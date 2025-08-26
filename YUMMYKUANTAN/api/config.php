<?php
// api/config.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'yummykuantan_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit();
    }
}

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Helper function to validate required fields
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(['error' => "Field '$field' is required"], 400);
        }
    }
}
?>