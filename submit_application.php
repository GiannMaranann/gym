<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $interest = sanitizeInput($_POST['interest'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $message = sanitizeInput($_POST['message'] ?? '');

    // Validate required fields
    $errors = [];
    
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($interest)) {
        $errors[] = "Please select an interest";
    }
    
    if ($price <= 0) {
        $errors[] = "Invalid price";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $sql = "INSERT INTO applications (fullname, email, phone, interest, price, message, status, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = executeQuery($sql, [$fullname, $email, $phone, $interest, $price, $message], "ssssds");
        
        if ($stmt && $stmt->affected_rows > 0) {
            header('Location: index.php?success=1');
            exit();
        } else {
            $errors[] = "Failed to submit application. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $error_string = urlencode(implode(', ', $errors));
        header("Location: index.php?error=$error_string");
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>