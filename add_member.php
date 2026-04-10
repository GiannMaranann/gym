<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $membership_type = sanitizeInput($_POST['membership_type'] ?? '');
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Get price for membership type
    $price_sql = "SELECT price FROM prices WHERE interest_name = ?";
    $price_result = getRecord($price_sql, [$membership_type], "s");
    $price = $price_result['price'] ?? 0;
    
    // Calculate end date (30 days from start date for monthly)
    $end_date = date('Y-m-d', strtotime($start_date . ' + 30 days'));
    
    // Generate member code
    $member_code = generateMemberCode();
    
    // Insert member
    $sql = "INSERT INTO members (member_code, fullname, email, phone, membership_type, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
    
    $stmt = executeQuery($sql, [$member_code, $fullname, $email, $phone, $membership_type, $start_date, $end_date], "sssssss");
    
    if ($stmt && $stmt->affected_rows > 0) {
        $member_id = $conn->insert_id;
        
        // Create initial payment record
        $payment_sql = "INSERT INTO payments (member_id, amount, payment_date, payment_method, status) 
                        VALUES (?, ?, ?, 'cash', 'completed')";
        executeQuery($payment_sql, [$member_id, $price, $start_date], "ids");
        
        // Log admin action (if logged in)
        if (isset($_SESSION['admin_id'])) {
            logAdminAction($_SESSION['admin_id'], 'ADD_MEMBER', 'members', $member_id, null, json_encode($_POST));
        }
        
        header('Location: admin_dashboard.php?success=member_added');
    } else {
        header('Location: admin_dashboard.php?error=add_failed');
    }
    exit();
} else {
    header('Location: admin_dashboard.php');
    exit();
}
?>