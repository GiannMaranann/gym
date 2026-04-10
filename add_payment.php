<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = intval($_POST['member_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference_number = sanitizeInput($_POST['reference_number'] ?? '');
    $status = sanitizeInput($_POST['status']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $sql = "INSERT INTO payments (member_id, amount, payment_date, payment_method, reference_number, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = executeQuery($sql, [$member_id, $amount, $payment_date, $payment_method, $reference_number, $status, $notes], "i d s s s s s");
    
    if ($stmt && $stmt->affected_rows > 0) {
        header('Location: payments.php?success=1');
    } else {
        header('Location: payments.php?error=1');
    }
    exit();
}
?>