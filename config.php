<?php
// config.php - Database configuration file
// Save this file in your project root directory

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Change this to your database username
define('DB_PASS', '');          // Change this to your database password
define('DB_NAME', 'jeffreys_gym_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Asia/Manila');

// Function to execute queries with parameters (prepared statements)
function executeQuery($sql, $params = [], $types = "") {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

// Function to get single record
function getRecord($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get multiple records
function getRecords($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to insert record and return ID
function insertRecord($sql, $params = [], $types = "") {
    global $conn;
    $stmt = executeQuery($sql, $params, $types);
    return $conn->insert_id;
}

// Function to update record
function updateRecord($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    return $stmt->affected_rows;
}

// Function to delete record
function deleteRecord($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    return $stmt->affected_rows;
}

// Function to get total count
function getCount($table, $where = "", $params = [], $types = "") {
    $sql = "SELECT COUNT(*) as total FROM $table";
    if (!empty($where)) {
        $sql .= " WHERE $where";
    }
    $result = getRecord($sql, $params, $types);
    return $result['total'] ?? 0;
}

// Function to escape string for safe output
function escapeString($string) {
    global $conn;
    return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
}

// Function to get current date and time
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

// Function to get current date
function getCurrentDate() {
    return date('Y-m-d');
}

// Start session for admin if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Function to redirect if not logged in
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Function to sanitize input data
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number (Philippine format)
function validatePhone($phone) {
    return preg_match('/^(09|\+639)\d{9}$/', $phone);
}

// Function to generate unique member code
function generateMemberCode() {
    $year = date('Y');
    $prefix = 'JG' . $year;
    
    // Get the last member code for this year
    global $conn;
    $sql = "SELECT member_code FROM members WHERE member_code LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $lastCode = $result->fetch_assoc()['member_code'];
        $lastNumber = intval(substr($lastCode, -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $newNumber;
}

// Function to log admin actions
function logAdminAction($admin_id, $action, $table_name = null, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $sql = "INSERT INTO audit_logs (admin_id, action, table_name, record_id, old_data, new_data, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    executeQuery($sql, [$admin_id, $action, $table_name, $record_id, $old_data, $new_data, $ip_address], "iisssss");
}

// Test database connection function
function testConnection() {
    global $conn;
    if ($conn->ping()) {
        return true;
    } else {
        return false;
    }
}


?>