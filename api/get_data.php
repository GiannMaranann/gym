<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if type and id are provided
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$type = $_GET['type'];
$id = intval($_GET['id']);

$response = ['success' => false];

switch ($type) {
    case 'member':
        $sql = "SELECT m.*, 
                CASE 
                    WHEN m.status = 'expired' OR m.end_date < CURDATE() THEN 'Expired'
                    WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
                    ELSE 'Healthy'
                END as health_status,
                DATEDIFF(m.end_date, CURDATE()) as days_remaining
                FROM members m WHERE m.id = ?";
        $member = getRecord($sql, [$id], "i");
        
        if ($member) {
            $response['success'] = true;
            $response['title'] = "Member Details - " . htmlspecialchars($member['fullname']);
            $days_left = $member['days_remaining'];
            $days_display = $days_left < 0 ? 'Expired' : $days_left . ' days remaining';
            
            $response['items'] = [
                ['label' => 'Member Code', 'value' => $member['member_code']],
                ['label' => 'Full Name', 'value' => htmlspecialchars($member['fullname'])],
                ['label' => 'Email', 'value' => htmlspecialchars($member['email'])],
                ['label' => 'Phone', 'value' => htmlspecialchars($member['phone'])],
                ['label' => 'Membership Type', 'value' => htmlspecialchars($member['membership_type'])],
                ['label' => 'Health Status', 'value' => $member['health_status']],
                ['label' => 'Start Date', 'value' => date('Y-m-d', strtotime($member['start_date']))],
                ['label' => 'Expiry Date', 'value' => date('Y-m-d', strtotime($member['end_date']))],
                ['label' => 'Days Remaining', 'value' => $days_display],
                ['label' => 'Status', 'value' => ucfirst($member['status'])]
            ];
        }
    break;
        
    case 'payment':
        $sql = "SELECT p.*, m.fullname as member_name, m.member_code, m.membership_type 
                FROM payments p 
                JOIN members m ON p.member_id = m.id 
                WHERE p.id = ?";
        $payment = getRecord($sql, [$id], "i");
        
        if ($payment) {
            $response['success'] = true;
            $response['title'] = "Payment Details - " . $payment['member_code'];
            
            $response['items'] = [
                ['label' => 'Payment ID', 'value' => $payment['id']],
                ['label' => 'Member Code', 'value' => $payment['member_code']],
                ['label' => 'Member Name', 'value' => htmlspecialchars($payment['member_name'])],
                ['label' => 'Membership Type', 'value' => htmlspecialchars($payment['membership_type'])],
                ['label' => 'Amount', 'value' => '₱' . number_format($payment['amount'], 2)],
                ['label' => 'Payment Date', 'value' => date('Y-m-d', strtotime($payment['payment_date']))],
                ['label' => 'Payment Method', 'value' => ucfirst(str_replace('_', ' ', $payment['payment_method']))],
                ['label' => 'Reference Number', 'value' => $payment['reference_number'] ?? 'N/A'],
                ['label' => 'Status', 'value' => ucfirst($payment['status'])],
                ['label' => 'Notes', 'value' => nl2br(htmlspecialchars($payment['notes'] ?? 'No notes'))]
            ];
        }
    break;
        
    case 'application':
        $sql = "SELECT * FROM applications WHERE id = ?";
        $app = getRecord($sql, [$id], "i");
        
        if ($app) {
            $response['success'] = true;
            $response['title'] = "Application Details - " . htmlspecialchars($app['fullname']);
            $response['items'] = [
                ['label' => 'Full Name', 'value' => htmlspecialchars($app['fullname'])],
                ['label' => 'Email', 'value' => htmlspecialchars($app['email'])],
                ['label' => 'Phone', 'value' => htmlspecialchars($app['phone'])],
                ['label' => 'Interest', 'value' => htmlspecialchars($app['interest'])],
                ['label' => 'Price', 'value' => '₱' . number_format($app['price'], 2)],
                ['label' => 'Message', 'value' => nl2br(htmlspecialchars($app['message'] ?? 'No message'))],
                ['label' => 'Status', 'value' => ucfirst($app['status'])],
                ['label' => 'Submitted', 'value' => date('Y-m-d H:i:s', strtotime($app['submitted_at']))],
                ['label' => 'Admin Notes', 'value' => nl2br(htmlspecialchars($app['admin_notes'] ?? 'No notes'))]
            ];
        }
    break;
        
    default:
        $response['error'] = 'Invalid type';
}

echo json_encode($response);
?>