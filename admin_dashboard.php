<?php
require_once 'config.php';

// Get statistics from database
$total_members = getCount('members');

$active_members_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active' AND end_date >= CURDATE()";
$active_members_result = $conn->query($active_members_sql);
$active_members = $active_members_result->fetch_assoc()['total'];

$expiring_soon_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$expiring_soon_result = $conn->query($expiring_soon_sql);
$expiring_soon = $expiring_soon_result->fetch_assoc()['total'];

$expired_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'expired' OR end_date < CURDATE()";
$expired_result = $conn->query($expired_sql);
$expired_count = $expired_result->fetch_assoc()['total'];

$healthy_count = $active_members - $expiring_soon;
$pending_applications = getCount('applications', "status = 'pending'");

$monthly_revenue_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
$monthly_revenue_result = $conn->query($monthly_revenue_sql);
$monthly_revenue = $monthly_revenue_result->fetch_assoc()['total'];

$pending_payments = getCount('payments', "status = 'pending'");

$recent_members_sql = "SELECT * FROM members ORDER BY id DESC LIMIT 10";
$recent_members = $conn->query($recent_members_sql);

$pending_applications_sql = "SELECT * FROM applications WHERE status = 'pending' ORDER BY submitted_at DESC";
$pending_applications_list = $conn->query($pending_applications_sql);

$recent_payments_sql = "SELECT p.*, m.fullname as member_name 
                        FROM payments p 
                        JOIN members m ON p.member_id = m.id 
                        ORDER BY p.created_at DESC LIMIT 5";
$recent_payments = $conn->query($recent_payments_sql);

$plan_distribution_sql = "SELECT membership_type, COUNT(*) as count FROM members WHERE status = 'active' GROUP BY membership_type";
$plan_distribution = $conn->query($plan_distribution_sql);
$total_active = $active_members;

// Handle application actions (Accept/Decline)
$action_message = '';
$action_message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $application_id = intval($_POST['application_id']);
    $action = $_POST['action'];
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    $payment_received = isset($_POST['payment_received']) ? $_POST['payment_received'] : 'no';
    $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitizeInput($_POST['payment_method']) : 'cash';
    
    $app_sql = "SELECT * FROM applications WHERE id = ?";
    $app = getRecord($app_sql, [$application_id], "i");
    
    if ($app) {
        if ($action === 'accept') {
            $member_code = generateMemberCode();
            $start_date = date('Y-m-d');
            
            // Calculate end date based on membership type
            if (strpos($app['interest'], 'Weekly') !== false) {
                $end_date = date('Y-m-d', strtotime('+7 days'));
                $duration_text = '7 days';
            } elseif (strpos($app['interest'], 'Session') !== false) {
                $end_date = $start_date;
                $duration_text = 'single session';
            } else {
                $end_date = date('Y-m-d', strtotime('+30 days'));
                $duration_text = '30 days';
            }
            
            // Insert member
            $insert_member = "INSERT INTO members (application_id, member_code, fullname, email, phone, membership_type, start_date, end_date, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = executeQuery($insert_member, [
                $application_id, $member_code, $app['fullname'], $app['email'], 
                $app['phone'], $app['interest'], $start_date, $end_date
            ], "isssssss");
            
            if ($stmt && $stmt->affected_rows > 0) {
                $member_id = $conn->insert_id;
                
                // Record payment if payment was received
                $payment_status = 'pending';
                $payment_note = '';
                
                if ($payment_received === 'yes' && $payment_amount > 0) {
                    $payment_status = 'completed';
                    $payment_note = "Payment received upon application approval.";
                    $action_message = "Application approved! Member has been added and payment of ₱" . number_format($payment_amount, 2) . " has been recorded.";
                } else {
                    $payment_amount = $app['price'];
                    $payment_note = "Payment pending. Please collect payment from member.";
                    $action_message = "Application approved! Member has been added. Payment is pending collection.";
                }
                
                // Insert payment record
                $insert_payment = "INSERT INTO payments (member_id, amount, payment_date, payment_method, status, notes) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                executeQuery($insert_payment, [
                    $member_id, 
                    $payment_amount, 
                    $start_date, 
                    $payment_method, 
                    $payment_status,
                    $payment_note . " " . $message
                ], "iddsss");
                
                // Update application status
                $update_app = "UPDATE applications SET status = 'approved', processed_at = NOW(), admin_notes = ? WHERE id = ?";
                executeQuery($update_app, [$message, $application_id], "si");
                
                $action_message_type = "success";
            }
        } elseif ($action === 'decline') {
            $update_app = "UPDATE applications SET status = 'rejected', processed_at = NOW(), admin_notes = ? WHERE id = ?";
            executeQuery($update_app, [$message, $application_id], "si");
            $action_message = "Application has been declined.";
            $action_message_type = "warning";
        }
        
        echo '<script>window.location.href = "admin_dashboard.php?message=' . urlencode($action_message) . '&type=' . $action_message_type . '";</script>';
        exit();
    }
}

if (isset($_GET['message'])) {
    $action_message = htmlspecialchars($_GET['message']);
    $action_message_type = isset($_GET['type']) ? $_GET['type'] : 'info';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Jeffrey's Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <style>
    /* CSS Styles */
    :root {
      --bg: #0f131a;
      --bg-soft: #161b24;
      --panel: #1a2130;
      --panel-2: #202839;
      --panel-3: #121722;
      --line: rgba(255,255,255,0.08);
      --line-strong: rgba(255,255,255,0.14);
      --text: #f5f7fb;
      --text-soft: #c3cbdb;
      --text-muted: #93a0b6;
      --primary: #0b57d0;
      --primary-light: #8ab4f8;
      --primary-soft: rgba(138,180,248,0.12);
      --success: #47c97e;
      --warning: #ffb648;
      --danger: #ff6b6b;
      --shadow: 0 25px 60px rgba(0,0,0,0.30);
      --radius-xl: 28px;
      --radius-lg: 22px;
      --radius-md: 16px;
      --radius-sm: 12px;
      --ease: cubic-bezier(.22,.61,.36,1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    html, body {
      min-height: 100%;
      background:
        radial-gradient(circle at top left, rgba(11,87,208,0.16), transparent 22%),
        radial-gradient(circle at bottom right, rgba(255,255,255,0.04), transparent 20%),
        linear-gradient(135deg, #0d1016 0%, #111722 100%);
      color: var(--text);
    }

    body {
      display: flex;
      min-height: 100vh;
      overflow: hidden;
    }

    /* Sidebar */
    .sidebar {
      width: 280px;
      background: rgba(18, 23, 34, 0.94);
      border-right: 1px solid var(--line);
      padding: 24px 18px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      backdrop-filter: blur(12px);
      height: 100vh;
      overflow-y: auto;
    }

    .sidebar-top {
      flex: 1;
    }

    .sidebar-bottom {
      padding-top: 20px;
      border-top: 1px solid var(--line);
      margin-top: 20px;
    }

    .sidebar-bottom a {
      color: var(--danger);
    }

    .sidebar-bottom a:hover {
      background: rgba(255,107,107,0.1);
      border-color: rgba(255,107,107,0.2);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 8px 10px 20px;
      border-bottom: 1px solid var(--line);
      margin-bottom: 8px;
    }

    .brand-logo {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(138,180,248,0.4);
      box-shadow: 0 10px 25px rgba(11,87,208,0.35);
      transition: all 0.3s var(--ease);
    }

    .brand-logo:hover {
      transform: scale(1.08) rotate(5deg);
      border-color: rgba(138,180,248,0.8);
      box-shadow: 0 15px 30px rgba(11,87,208,0.5);
    }

    .brand h2 {
      font-size: 22px;
      font-weight: 900;
      letter-spacing: -0.03em;
      line-height: 1.2;
    }

    .brand small {
      display: block;
      color: var(--text-muted);
      margin-top: 4px;
      font-size: 13px;
      font-weight: 500;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 14px;
      color: var(--text-soft);
      text-decoration: none;
      padding: 13px 16px;
      border-radius: 14px;
      transition: all .25s var(--ease);
      font-weight: 600;
      border: 1px solid transparent;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background: linear-gradient(135deg, rgba(11,87,208,.20), rgba(77,144,254,.14));
      color: #fff;
      border-color: rgba(138,180,248,0.14);
      transform: translateX(4px);
    }

    /* Main content */
    .main {
      flex: 1;
      padding: 24px;
      overflow-y: auto;
      height: 100vh;
      scrollbar-width: thin;
      scrollbar-color: var(--primary) var(--panel-2);
    }

    .main::-webkit-scrollbar {
      width: 8px;
    }

    .main::-webkit-scrollbar-track {
      background: var(--panel-2);
      border-radius: 10px;
    }

    .main::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 10px;
    }

    /* Alert message */
    .alert-message {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      padding: 15px 20px;
      border-radius: 12px;
      font-weight: 600;
      animation: slideInRight 0.3s ease;
      max-width: 400px;
    }

    .alert-success {
      background: linear-gradient(135deg, #1e4620, #2e7d32);
      border-left: 4px solid #4caf50;
      color: #fff;
    }

    .alert-warning {
      background: linear-gradient(135deg, #4a3a1e, #b76e00);
      border-left: 4px solid #ff9800;
      color: #fff;
    }

    .alert-info {
      background: linear-gradient(135deg, #1e3a4a, #0288d1);
      border-left: 4px solid #03a9f4;
      color: #fff;
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .alert-close {
      float: right;
      margin-left: 15px;
      cursor: pointer;
      font-weight: bold;
    }

    /* Application action buttons */
    .app-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .btn-accept {
      background: rgba(71,201,126,0.2);
      border: 1px solid rgba(71,201,126,0.3);
      color: #9ef0bd;
      padding: 6px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s ease;
    }

    .btn-accept:hover {
      background: rgba(71,201,126,0.4);
      transform: translateY(-2px);
    }

    .btn-decline {
      background: rgba(255,107,107,0.2);
      border: 1px solid rgba(255,107,107,0.3);
      color: #ffb1b1;
      padding: 6px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s ease;
    }

    .btn-decline:hover {
      background: rgba(255,107,107,0.4);
      transform: translateY(-2px);
    }

    .scroll-top {
      position: fixed;
      bottom: 25px;
      right: 25px;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), #4d90fe);
      color: #fff;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      box-shadow: 0 10px 25px rgba(11,87,208,0.3);
      transition: all 0.3s ease;
      z-index: 100;
      opacity: 0;
      visibility: hidden;
    }

    .scroll-top.show {
      opacity: 1;
      visibility: visible;
    }

    .scroll-top:hover {
      transform: translateY(-5px) scale(1.1);
      box-shadow: 0 15px 35px rgba(11,87,208,0.5);
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 22px;
      flex-wrap: wrap;
    }

    .topbar h1 {
      font-size: clamp(28px, 4vw, 38px);
      letter-spacing: -0.05em;
      font-weight: 900;
      background: linear-gradient(135deg, #fff, var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .topbar p {
      color: var(--text-soft);
      margin-top: 6px;
    }

    .top-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      border: none;
      cursor: pointer;
      border-radius: 999px;
      padding: 12px 18px;
      font-weight: 800;
      font-size: 14px;
      transition: all .25s var(--ease);
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), #4d90fe);
      color: #fff;
      box-shadow: 0 12px 28px rgba(11,87,208,0.24);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      filter: brightness(1.05);
      box-shadow: 0 16px 32px rgba(11,87,208,0.35);
    }

    .btn-secondary {
      background: rgba(255,255,255,0.04);
      color: var(--text-soft);
      border: 1px solid var(--line);
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.08);
      color: #fff;
      border-color: var(--primary-light);
    }

    .summary-grid,
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 20px;
    }

    .card,
    .summary-card,
    .panel,
    .mini-panel {
      background: linear-gradient(180deg, rgba(29, 37, 53, 0.92), rgba(22, 28, 39, 0.96));
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      backdrop-filter: blur(4px);
    }

    .summary-card {
      padding: 22px;
      position: relative;
      overflow: hidden;
      transition: transform 0.3s var(--ease);
    }

    .summary-card:hover {
      transform: translateY(-4px);
    }

    .summary-card::before {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 4px;
      background: linear-gradient(180deg, var(--primary-light), var(--primary));
    }

    .summary-card h3 {
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-muted);
      margin-bottom: 10px;
    }

    .summary-card .value {
      font-size: 32px;
      font-weight: 900;
      letter-spacing: -0.04em;
      margin-bottom: 8px;
      color: #fff;
    }

    .summary-card .sub {
      color: var(--text-soft);
      font-size: 13px;
      line-height: 1.6;
    }

    .summary-card.warning::before { background: linear-gradient(180deg, #ffd18b, var(--warning)); }
    .summary-card.danger::before { background: linear-gradient(180deg, #ffb0b0, var(--danger)); }
    .summary-card.success::before { background: linear-gradient(180deg, #a4f0c4, var(--success)); }

    .card {
      padding: 22px;
      transition: transform 0.3s var(--ease);
    }

    .card:hover {
      transform: translateY(-4px);
    }

    .card h3 {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 14px;
      text-transform: uppercase;
      letter-spacing: .08em;
    }

    .card p {
      font-size: 32px;
      color: var(--primary-light);
      font-weight: 900;
      letter-spacing: -0.04em;
    }

    .main-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .panel {
      padding: 22px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .panel h2 {
      font-size: 22px;
      font-weight: 900;
      letter-spacing: -0.03em;
      color: #fff;
    }

    .panel p.panel-sub {
      color: var(--text-soft);
      font-size: 14px;
      line-height: 1.6;
    }

    .table-wrap {
      overflow-x: auto;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.02);
      scrollbar-width: thin;
      scrollbar-color: var(--primary) var(--panel-2);
    }

    .table-wrap::-webkit-scrollbar {
      height: 8px;
    }

    .table-wrap::-webkit-scrollbar-track {
      background: var(--panel-2);
      border-radius: 10px;
    }

    .table-wrap::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
    }

    table th,
    table td {
      padding: 16px 14px;
      text-align: left;
      border-bottom: 1px solid var(--line);
      font-size: 14px;
      vertical-align: middle;
    }

    table th {
      color: var(--primary-light);
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      background: rgba(255,255,255,0.03);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    tbody tr {
      transition: background .2s ease;
    }

    tbody tr:hover {
      background: rgba(255,255,255,0.04);
    }

    .clickable-row {
      cursor: pointer;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .02em;
      white-space: nowrap;
    }

    .status-active {
      background: rgba(71,201,126,0.14);
      color: #9ef0bd;
      border: 1px solid rgba(71,201,126,0.18);
    }

    .status-expiring {
      background: rgba(255,182,72,0.14);
      color: #ffd08b;
      border: 1px solid rgba(255,182,72,0.18);
    }

    .status-expired {
      background: rgba(255,107,107,0.14);
      color: #ffb1b1;
      border: 1px solid rgba(255,107,107,0.18);
    }

    .status-paid {
      color: #8df0b5;
      font-weight: 800;
    }

    .status-pending {
      color: #ffc36c;
      font-weight: 800;
    }

    .right-stack {
      display: grid;
      gap: 20px;
    }

    .chart-box {
      margin-top: 10px;
    }

    .bar {
      margin-bottom: 18px;
    }

    .bar span {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 8px;
      color: var(--text-soft);
      font-size: 14px;
      font-weight: 600;
    }

    .bar-track {
      background: rgba(255,255,255,0.06);
      border-radius: 999px;
      overflow: hidden;
      height: 14px;
      border: 1px solid rgba(255,255,255,0.04);
    }

    .bar-fill {
      height: 100%;
      background: linear-gradient(135deg, var(--primary-light), var(--primary));
      width: 0;
      transition: width 1s ease;
      border-radius: 999px;
    }

    .expiring-list,
    .application-list {
      display: grid;
      gap: 12px;
      max-height: 400px;
      overflow-y: auto;
      padding-right: 5px;
      scrollbar-width: thin;
      scrollbar-color: var(--primary) var(--panel-2);
    }

    .expiring-list::-webkit-scrollbar,
    .application-list::-webkit-scrollbar {
      width: 6px;
    }

    .expiring-list::-webkit-scrollbar-track,
    .application-list::-webkit-scrollbar-track {
      background: var(--panel-2);
      border-radius: 10px;
    }

    .expiring-list::-webkit-scrollbar-thumb,
    .application-list::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 10px;
    }

    .expire-item,
    .application-item {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 16px;
      transition: all .2s ease;
    }

    .expire-item:hover,
    .application-item:hover {
      background: rgba(255,255,255,0.05);
      transform: translateX(4px);
      border-color: rgba(138,180,248,0.2);
    }

    .expire-item h4,
    .application-item h4 {
      font-size: 15px;
      margin-bottom: 6px;
      font-weight: 800;
    }

    .expire-item p,
    .application-item p {
      color: var(--text-soft);
      font-size: 13px;
      line-height: 1.6;
    }

    .expire-item .tag,
    .application-item .tag {
      display: inline-flex;
      margin-top: 8px;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 800;
      background: rgba(255,255,255,0.05);
      color: var(--text-soft);
      border: 1px solid var(--line);
    }

    .links {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 18px;
    }

    .link-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: rgba(11,87,208,0.14);
      border: 1px solid rgba(138,180,248,0.14);
      color: #dcebff;
      text-decoration: none;
      border-radius: 999px;
      font-weight: 800;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .link-btn:hover {
      background: rgba(11,87,208,0.22);
      transform: translateY(-2px);
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(5,8,13,0.76);
      backdrop-filter: blur(6px);
      z-index: 100;
      align-items: center;
      justify-content: center;
      padding: 18px;
    }

    .modal.show {
      display: flex;
      animation: fadeIn .25s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      width: 100%;
      max-width: 560px;
      background: linear-gradient(180deg, #1b2230, #141b27);
      border: 1px solid var(--line-strong);
      border-radius: 28px;
      box-shadow: 0 30px 80px rgba(0,0,0,.36);
      padding: 28px;
      max-height: 92vh;
      overflow-y: auto;
      animation: modalUp .3s var(--ease);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 22px;
    }

    .modal-header h3 {
      font-size: 26px;
      font-weight: 900;
      letter-spacing: -0.04em;
      background: linear-gradient(135deg, #fff, var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .close-btn {
      width: 40px;
      height: 40px;
      border: 1px solid var(--line);
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      color: #fff;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.2s ease;
    }

    .close-btn:hover {
      background: rgba(255,255,255,0.1);
      transform: rotate(90deg);
    }

    .modal-grid {
      display: grid;
      gap: 16px;
    }

    .modal-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 16px 18px;
    }

    .modal-card strong {
      display: block;
      margin-bottom: 8px;
      color: #fff;
      font-size: 15px;
    }

    .form-grid {
      display: grid;
      gap: 18px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--text-soft);
      font-weight: 600;
      font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 14px 16px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.04);
      color: #fff;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: rgba(138,180,248,0.45);
      box-shadow: 0 0 0 4px rgba(138,180,248,0.12);
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      flex-wrap: wrap;
      margin-top: 22px;
    }

    /* Payment section in modal */
    .payment-section {
      background: rgba(71,201,126,0.1);
      border: 1px solid rgba(71,201,126,0.2);
      border-radius: 16px;
      padding: 16px;
      margin-top: 8px;
    }

    .payment-section h4 {
      color: #9ef0bd;
      margin-bottom: 12px;
      font-size: 14px;
    }

    .radio-group {
      display: flex;
      gap: 20px;
      margin-bottom: 12px;
    }

    .radio-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }

    .payment-details {
      display: none;
      margin-top: 12px;
    }

    .payment-details.show {
      display: block;
    }

    @media (max-width: 1100px) {
      .main-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 920px) {
      body {
        flex-direction: column;
        overflow: auto;
      }

      .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--line);
        height: auto;
      }

      .main {
        overflow: visible;
        height: auto;
      }
    }

    @media (max-width: 640px) {
      .main {
        padding: 16px;
      }

      .summary-grid,
      .cards {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-top">
      <div class="brand">
        <img src="gym_logo.jpg" alt="Jeffrey's Gym Logo" class="brand-logo" onerror="this.src='https://via.placeholder.com/60x60/0b57d0/ffffff?text=JG'">
        <div>
          <h2>Jeffrey's Gym</h2>
          <small>Admin Dashboard</small>
        </div>
      </div>

      <a href="#" class="active">
        <span>🏠</span> Dashboard
      </a>
      <a href="members.php">
        <span>👥</span> Members
      </a>
      <a href="payments.php">
        <span>💰</span> Payments
      </a>
      <a href="prices.php">
        <span>💲</span> Manage Prices
      </a>
      <a href="applications.php">
        <span>📝</span> Applications
      </a>
    </div>

    <div class="sidebar-bottom">
      <a href="logout.php">
        <span>🚪</span> Sign Out
      </a>
    </div>
  </aside>

  <main class="main" id="mainContent">
    <!-- Alert Message -->
    <?php if ($action_message): ?>
    <div class="alert-message alert-<?php echo $action_message_type; ?>" id="alertMessage">
      <span class="alert-close" onclick="this.parentElement.style.display='none'">&times;</span>
      <?php echo $action_message; ?>
    </div>
    <script>
      setTimeout(function() {
        var alert = document.getElementById('alertMessage');
        if (alert) alert.style.display = 'none';
      }, 5000);
    </script>
    <?php endif; ?>

    <div class="topbar">
      <div>
        <h1>Admin Dashboard</h1>
        <p>Welcome, Admin. Monitor members, payments, applications, and expirations here.</p>
      </div>
      <div class="top-actions">
        <button class="btn btn-secondary" onclick="openModal('summaryModal')">📊 View Summary</button>
        <button class="btn btn-primary" onclick="openModal('addMemberModal')">+ Add Member</button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card success">
        <h3>✅ Healthy Accounts</h3>
        <div class="value"><?php echo $healthy_count; ?></div>
        <div class="sub">Members currently active and not close to expiration.</div>
      </div>

      <div class="summary-card warning">
        <h3>⚠️ Expiring Soon</h3>
        <div class="value"><?php echo $expiring_soon; ?></div>
        <div class="sub">Members close to expiration within the next 7 days.</div>
      </div>

      <div class="summary-card danger">
        <h3>❌ Expired Accounts</h3>
        <div class="value"><?php echo $expired_count; ?></div>
        <div class="sub">Members whose plans are already expired.</div>
      </div>

      <div class="summary-card">
        <h3>📝 Pending Applications</h3>
        <div class="value"><?php echo $pending_applications; ?></div>
        <div class="sub">New applicants waiting for review and confirmation.</div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="cards">
      <div class="card">
        <h3>Total Members</h3>
        <p><?php echo $total_members; ?></p>
      </div>
      <div class="card">
        <h3>Active Members</h3>
        <p><?php echo $active_members; ?></p>
      </div>
      <div class="card">
        <h3>Monthly Revenue</h3>
        <p>₱<?php echo number_format($monthly_revenue, 2); ?></p>
      </div>
      <div class="card">
        <h3>Pending Payments</h3>
        <p><?php echo $pending_payments; ?></p>
      </div>
    </div>

    <div class="main-grid">
      <!-- Left Panel - Members Table -->
      <div class="panel">
        <div class="panel-header">
          <div>
            <h2>Recent Member Records</h2>
            <p class="panel-sub">Members who have been approved and registered.</p>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Member Code</th>
                <th>Name</th>
                <th>Plan Type</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>Expiry Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recent_members && $recent_members->num_rows > 0): ?>
                <?php while($member = $recent_members->fetch_assoc()): 
                  $status_class = '';
                  $status_text = '';
                  $days_remaining = (strtotime($member['end_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                  
                  if ($member['status'] == 'expired' || $days_remaining < 0) {
                      $status_class = 'status-expired';
                      $status_text = 'Expired';
                  } elseif ($days_remaining <= 7) {
                      $status_class = 'status-expiring';
                      $status_text = 'Expiring Soon';
                  } else {
                      $status_class = 'status-active';
                      $status_text = 'Active';
                  }
                ?>
                <tr class="clickable-row" data-type="member" data-id="<?php echo $member['id']; ?>">
                  <td><?php echo $member['id']; ?></td>
                  <td><?php echo htmlspecialchars($member['member_code']); ?></td>
                  <td><?php echo htmlspecialchars($member['fullname']); ?></td>
                  <td><?php echo htmlspecialchars($member['membership_type']); ?></td>
                  <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                  <td><?php echo date('Y-m-d', strtotime($member['start_date'])); ?></td>
                  <td><?php echo date('Y-m-d', strtotime($member['end_date'])); ?></td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" style="text-align: center;">No members yet. Approve applications to add members.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="links">
          <button class="link-btn" onclick="openModal('membersHelpModal')">ℹ️ Member Table Info</button>
          <a href="members.php" class="link-btn">View All Members →</a>
        </div>
      </div>

      <!-- Right Stack -->
      <div class="right-stack">
        <div class="panel">
          <div class="panel-header">
            <div>
              <h2>Membership Analytics</h2>
              <p class="panel-sub">Plan distribution overview</p>
            </div>
          </div>

          <div class="chart-box">
            <?php if ($plan_distribution && $plan_distribution->num_rows > 0): ?>
              <?php while($plan = $plan_distribution->fetch_assoc()): 
                $percentage = $total_active > 0 ? ($plan['count'] / $total_active) * 100 : 0;
              ?>
              <div class="bar">
                <span><strong><?php echo htmlspecialchars($plan['membership_type']); ?></strong><strong><?php echo round($percentage); ?>%</strong></span>
                <div class="bar-track"><div class="bar-fill" data-width="<?php echo $percentage; ?>%"></div></div>
              </div>
              <?php endwhile; ?>
            <?php else: ?>
              <p style="text-align: center; color: var(--text-muted);">No active members yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <div>
              <h2>Expiring Members</h2>
              <p class="panel-sub">Members needing attention</p>
            </div>
            <button class="link-btn" onclick="openModal('summaryModal')">View Summary</button>
          </div>

          <div class="expiring-list">
            <?php
            $expiring_members_sql = "SELECT * FROM members WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY end_date ASC LIMIT 10";
            $expiring_members = $conn->query($expiring_members_sql);
            if ($expiring_members && $expiring_members->num_rows > 0):
              while($member = $expiring_members->fetch_assoc()):
            ?>
            <div class="expire-item clickable-row" data-type="member" data-id="<?php echo $member['id']; ?>">
              <h4><?php echo htmlspecialchars($member['fullname']); ?></h4>
              <p><?php echo htmlspecialchars($member['membership_type']); ?> plan • Expiry: <?php echo date('Y-m-d', strtotime($member['end_date'])); ?></p>
              <span class="tag">Expiring Soon</span>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div class="expire-item">
              <h4>✅ No urgent expirations</h4>
              <p>All members are currently in good standing.</p>
              <span class="tag">Clear</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Applications Section -->
    <div class="main-grid">
      <div class="panel">
        <div class="panel-header">
          <div>
            <h2>Pending Applications</h2>
            <p class="panel-sub">Review and process membership applications</p>
          </div>
        </div>

        <div class="application-list">
          <?php if ($pending_applications_list && $pending_applications_list->num_rows > 0): ?>
            <?php while($app = $pending_applications_list->fetch_assoc()): ?>
            <div class="application-item" data-id="<?php echo $app['id']; ?>">
              <h4><?php echo htmlspecialchars($app['fullname']); ?></h4>
              <p>
                <strong>Interest:</strong> <?php echo htmlspecialchars($app['interest']); ?> - ₱<?php echo number_format($app['price'], 2); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?><br>
                <strong>Phone:</strong> <?php echo htmlspecialchars($app['phone']); ?><br>
                <strong>Applied on:</strong> <?php echo date('Y-m-d H:i', strtotime($app['submitted_at'])); ?>
              </p>
              <?php if (!empty($app['message'])): ?>
                <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($app['message'])); ?></p>
              <?php endif; ?>
              <div class="app-actions">
                <button class="btn-accept" onclick="showActionModal(<?php echo $app['id']; ?>, 'accept', '<?php echo htmlspecialchars($app['fullname']); ?>', <?php echo $app['price']; ?>)">
                  <i class="fas fa-check"></i> Accept & Record Payment
                </button>
                <button class="btn-decline" onclick="showActionModal(<?php echo $app['id']; ?>, 'decline', '<?php echo htmlspecialchars($app['fullname']); ?>', 0)">
                  <i class="fas fa-times"></i> Decline
                </button>
              </div>
            </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="application-item">
              <h4>No pending applications</h4>
              <p>All applications have been processed.</p>
              <span class="tag">All Clear</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <div>
            <h2>Quick Reminders</h2>
            <p class="panel-sub">Admin tasks and notes</p>
          </div>
        </div>

        <div class="expiring-list">
          <div class="expire-item">
            <h4>📅 Review expiring plans weekly</h4>
            <p>Use the summary and expiring container to contact members before renewal deadlines.</p>
            <span class="tag">Admin Reminder</span>
          </div>
          <div class="expire-item">
            <h4>👥 Check applications daily</h4>
            <p>Pending applicants are listed above for easier approval and follow-up.</p>
            <span class="tag">Workflow Reminder</span>
          </div>
          <div class="expire-item">
            <h4>💰 Record payments upon approval</h4>
            <p>When accepting applications, you can record the payment received from the member.</p>
            <span class="tag">Payment Alert</span>
          </div>
          <div class="expire-item">
            <h4>💲 Update prices as needed</h4>
            <p>Go to Manage Prices to update membership rates.</p>
            <span class="tag">Pricing Alert</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <div class="panel">
      <div class="panel-header">
        <div>
          <h2>Recent Payment Records</h2>
          <p class="panel-sub">Click any payment row for complete details.</p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Payment ID</th>
              <th>Member Name</th>
              <th>Amount</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
              <?php while($payment = $recent_payments->fetch_assoc()): ?>
              <tr class="clickable-row" data-type="payment" data-id="<?php echo $payment['id']; ?>">
                <td><?php echo $payment['id']; ?></td>
                <td><?php echo htmlspecialchars($payment['member_name']); ?></td>
                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                <td class="<?php echo $payment['status'] == 'completed' ? 'status-paid' : 'status-pending'; ?>">
                  <?php echo ucfirst($payment['status']); ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center;">No payment records yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="links">
        <button class="link-btn" onclick="openModal('paymentInfoModal')">ℹ️ Payment Table Info</button>
        <a href="payments.php" class="link-btn">View All Payments →</a>
      </div>
    </div>
  </main>

  <!-- Scroll to Top Button -->
  <button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">↑</button>

  <!-- Action Modal (for Accept/Decline with payment recording) -->
  <div class="modal" id="actionModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="actionModalTitle">Process Application</h3>
        <button class="close-btn" onclick="closeModal('actionModal')">✕</button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="application_id" id="actionApplicationId">
        <input type="hidden" name="action" id="actionType">
        
        <div class="modal-grid">
          <div class="modal-card">
            <strong id="actionApplicantName"></strong>
            <p id="actionMessageText"></p>
          </div>
          
          <!-- Payment Section (only shown for accept action) -->
          <div id="paymentSection" class="payment-section" style="display: none;">
            <h4><i class="fas fa-credit-card"></i> Payment Information</h4>
            <div class="radio-group">
              <label>
                <input type="radio" name="payment_received" value="yes" onchange="togglePaymentDetails(true)"> 
                <i class="fas fa-check-circle" style="color: #4caf50;"></i> Payment Received
              </label>
              <label>
                <input type="radio" name="payment_received" value="no" checked onchange="togglePaymentDetails(false)"> 
                <i class="fas fa-clock" style="color: #ff9800;"></i> Payment Pending
              </label>
            </div>
            
            <div id="paymentDetails" class="payment-details">
              <div class="form-group">
                <label>Payment Amount (₱)</label>
                <input type="number" name="payment_amount" id="paymentAmount" step="0.01" placeholder="Enter amount received">
              </div>
              <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                  <option value="cash">Cash</option>
                  <option value="gcash">GCash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="credit_card">Credit Card</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="actionMessage">Message (optional):</label>
            <textarea name="message" id="actionMessage" rows="3" placeholder="Add a message to the applicant..."></textarea>
          </div>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeModal('actionModal')">Cancel</button>
          <button type="submit" class="btn btn-primary" id="actionSubmitBtn">Confirm</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Member Modal -->
  <div class="modal" id="addMemberModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Add New Member</h3>
        <button class="close-btn" onclick="closeModal('addMemberModal')">✕</button>
      </div>

      <form method="POST" action="add_member.php">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="fullname" placeholder="Enter member's full name" required>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="member@email.com" required>
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="09xx xxx xxxx" required>
          </div>
          <div class="form-group">
            <label>Plan Type</label>
            <select name="membership_type" required>
              <option value="">Select a plan</option>
              <?php
              $plans_sql = "SELECT interest_name, price FROM prices ORDER BY price";
              $plans = $conn->query($plans_sql);
              while($plan = $plans->fetch_assoc()):
              ?>
              <option value="<?php echo htmlspecialchars($plan['interest_name']); ?>">
                <?php echo htmlspecialchars($plan['interest_name']); ?> (₱<?php echo number_format($plan['price'], 2); ?>)
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label>Notes (Optional)</label>
            <textarea name="notes" rows="3" placeholder="Additional notes about the member"></textarea>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeModal('addMemberModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Member</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Dynamic Info Modal -->
  <div class="modal" id="infoModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="infoModalTitle">Details</h3>
        <button class="close-btn" onclick="closeModal('infoModal')">✕</button>
      </div>
      <div class="modal-grid" id="infoModalBody"></div>
    </div>
  </div>

  <!-- Summary Modal -->
  <div class="modal" id="summaryModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Expiry Summary</h3>
        <button class="close-btn" onclick="closeModal('summaryModal')">✕</button>
      </div>
      <div class="modal-grid">
        <div class="modal-card">
          <strong>⏳ Expiring Soon</strong>
          <span><?php echo $expiring_soon; ?> members</span>
        </div>
        <div class="modal-card">
          <strong>❌ Expired</strong>
          <span><?php echo $expired_count; ?> members</span>
        </div>
        <div class="modal-card">
          <strong>✅ Healthy</strong>
          <span><?php echo $healthy_count; ?> members</span>
        </div>
        <div class="modal-card">
          <strong>📋 Recommended Action</strong>
          <p>Contact expired members first for renewal, then follow up with members expiring within 7 days.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Help Modals -->
  <div class="modal" id="membersHelpModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Member Table Info</h3>
        <button class="close-btn" onclick="closeModal('membersHelpModal')">✕</button>
      </div>
      <div class="modal-grid">
        <div class="modal-card">
          <strong>Click Member Rows</strong>
          <span>Click any row to open the member's complete details including contact information and payment history.</span>
        </div>
        <div class="modal-card">
          <strong>Status Indicators</strong>
          <span>Green = Active, Yellow = Expiring Soon (within 7 days), Red = Expired</span>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="paymentInfoModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Payment Table Info</h3>
        <button class="close-btn" onclick="closeModal('paymentInfoModal')">✕</button>
      </div>
      <div class="modal-grid">
        <div class="modal-card">
          <strong>Click Payment Rows</strong>
          <span>Click any payment row to view full payment details, receipt, and member information.</span>
        </div>
        <div class="modal-card">
          <strong>Payment Status</strong>
          <span>• Paid: Green - Payment completed<br>• Pending: Yellow - Awaiting confirmation</span>
        </div>
      </div>
    </div>
  </div>

  <script>
    let currentApplicationPrice = 0;

    function openModal(id) {
      document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('show');
    }

    function togglePaymentDetails(show) {
      const paymentDetails = document.getElementById('paymentDetails');
      const paymentAmount = document.getElementById('paymentAmount');
      
      if (show) {
        paymentDetails.classList.add('show');
        if (paymentAmount && currentApplicationPrice > 0) {
          paymentAmount.value = currentApplicationPrice;
        }
      } else {
        paymentDetails.classList.remove('show');
        if (paymentAmount) {
          paymentAmount.value = '';
        }
      }
    }

    function showActionModal(applicationId, action, applicantName, price) {
      const modal = document.getElementById('actionModal');
      const title = document.getElementById('actionModalTitle');
      const applicantNameSpan = document.getElementById('actionApplicantName');
      const messageText = document.getElementById('actionMessageText');
      const submitBtn = document.getElementById('actionSubmitBtn');
      const actionType = document.getElementById('actionType');
      const appIdField = document.getElementById('actionApplicationId');
      const paymentSection = document.getElementById('paymentSection');
      
      currentApplicationPrice = price;
      appIdField.value = applicationId;
      actionType.value = action;
      
      if (action === 'accept') {
        title.innerHTML = 'Accept Application';
        applicantNameSpan.innerHTML = `<i class="fas fa-user-check"></i> Applicant: ${applicantName}`;
        messageText.innerHTML = 'You are about to ACCEPT this application. The applicant will be added as a member automatically.';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Accept & Save';
        submitBtn.style.background = 'linear-gradient(135deg, #2e7d32, #4caf50)';
        paymentSection.style.display = 'block';
        
        // Set default payment amount
        const paymentAmount = document.getElementById('paymentAmount');
        if (paymentAmount) {
          paymentAmount.value = price;
        }
      } else {
        title.innerHTML = 'Decline Application';
        applicantNameSpan.innerHTML = `<i class="fas fa-user-times"></i> Applicant: ${applicantName}`;
        messageText.innerHTML = 'You are about to DECLINE this application. The applicant will be notified.';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Decline Application';
        submitBtn.style.background = 'linear-gradient(135deg, #c62828, #ef5350)';
        paymentSection.style.display = 'none';
      }
      
      modal.classList.add('show');
    }

    // AJAX function to get member details
    async function getMemberDetails(id) {
      const response = await fetch(`api/get_data.php?type=member&id=${id}`);
      return await response.json();
    }

    async function getPaymentDetails(id) {
      const response = await fetch(`api/get_data.php?type=payment&id=${id}`);
      return await response.json();
    }

    async function openInfoModal(type, id) {
      let data;
      if (type === 'member') {
        data = await getMemberDetails(id);
      } else if (type === 'payment') {
        data = await getPaymentDetails(id);
      }
      
      if (data && data.success) {
        const titleEl = document.getElementById('infoModalTitle');
        const bodyEl = document.getElementById('infoModalBody');
        
        titleEl.textContent = data.title;
        bodyEl.innerHTML = data.items.map(item => `
          <div class="modal-card">
            <strong>${item.label}</strong>
            <span>${item.value}</span>
          </div>
        `).join('');
        
        openModal('infoModal');
      }
    }

    // Add click handlers to all clickable rows
    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', () => {
        const type = row.dataset.type;
        const id = row.dataset.id;
        if (type && id) {
          openInfoModal(type, id);
        }
      });
    });

    // Animate bars
    const bars = document.querySelectorAll(".bar-fill");
    bars.forEach(bar => {
      const width = bar.getAttribute("data-width");
      setTimeout(() => {
        bar.style.width = width;
      }, 300);
    });

    // Scroll to top functionality
    const mainContent = document.getElementById('mainContent');
    const scrollTopBtn = document.getElementById('scrollTopBtn');

    function scrollToTop() {
      mainContent.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }

    if (mainContent) {
      mainContent.addEventListener('scroll', () => {
        if (mainContent.scrollTop > 300) {
          scrollTopBtn.classList.add('show');
        } else {
          scrollTopBtn.classList.remove('show');
        }
      });
    }

    window.addEventListener('click', (e) => {
      document.querySelectorAll('.modal').forEach(modal => {
        if (e.target === modal) modal.classList.remove('show');
      });
    });
  </script>
</body>
</html>