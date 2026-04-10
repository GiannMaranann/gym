<?php
require_once 'config.php';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$plan_filter = isset($_GET['plan']) ? sanitizeInput($_GET['plan']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 10;
$offset = ($page - 1) * $rows_per_page;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(m.fullname LIKE ? OR m.member_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($plan_filter)) {
    $where_clauses[] = "m.membership_type LIKE ?";
    $params[] = "%$plan_filter%";
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM payments p 
              LEFT JOIN members m ON p.member_id = m.id 
              $where_sql";
$count_result = executeQuery($count_sql, $params, $types);
$total_rows = $count_result->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Get payments with pagination
$sql = "SELECT 
            p.*,
            m.fullname as member_name,
            m.member_code,
            m.membership_type
        FROM payments p 
        LEFT JOIN members m ON p.member_id = m.id 
        $where_sql
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $rows_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = executeQuery($sql, $params, $types);
$payments = $stmt->get_result();

// Get statistics
// Total paid revenue
$total_paid_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'";
$total_paid_result = $conn->query($total_paid_sql);
$total_paid_revenue = $total_paid_result->fetch_assoc()['total'];

// Today's earnings
$today_earnings_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND DATE(payment_date) = CURDATE()";
$today_earnings_result = $conn->query($today_earnings_sql);
$daily_earnings = $today_earnings_result->fetch_assoc()['total'];

// This week's earnings
$weekly_earnings_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND YEARWEEK(payment_date) = YEARWEEK(CURDATE())";
$weekly_earnings_result = $conn->query($weekly_earnings_sql);
$weekly_earnings = $weekly_earnings_result->fetch_assoc()['total'];

// This month's earnings
$monthly_earnings_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
$monthly_earnings_result = $conn->query($monthly_earnings_sql);
$monthly_earnings = $monthly_earnings_result->fetch_assoc()['total'];

// This year's earnings
$yearly_earnings_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE())";
$yearly_earnings_result = $conn->query($yearly_earnings_sql);
$yearly_earnings = $yearly_earnings_result->fetch_assoc()['total'];

// Payment status counts
$paid_count_sql = "SELECT COUNT(*) as total FROM payments WHERE status = 'completed'";
$paid_count_result = $conn->query($paid_count_sql);
$paid_count = $paid_count_result->fetch_assoc()['total'];

$pending_count_sql = "SELECT COUNT(*) as total FROM payments WHERE status = 'pending'";
$pending_count_result = $conn->query($pending_count_sql);
$pending_count = $pending_count_result->fetch_assoc()['total'];

$failed_count_sql = "SELECT COUNT(*) as total FROM payments WHERE status = 'failed'";
$failed_count_result = $conn->query($failed_count_sql);
$failed_count = $failed_count_result->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payments - Jeffrey's Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <style>
    :root {
      --bg: #0f131a;
      --bg-soft: #161b24;
      --panel: #1a2130;
      --panel-2: #202839;
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
      box-sizing: border-box;
      margin: 0;
      padding: 0;
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

    /* Main Content */
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

    .main::-webkit-scrollbar-thumb:hover {
      background: var(--primary-light);
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
      line-height: 1.6;
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
    .status-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 20px;
    }

    .summary-card,
    .panel {
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

    .summary-card.success::before {
      background: linear-gradient(180deg, #a4f0c4, var(--success));
    }

    .summary-card.warning::before {
      background: linear-gradient(180deg, #ffd18b, var(--warning));
    }

    .summary-card.danger::before {
      background: linear-gradient(180deg, #ffb0b0, var(--danger));
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

    .toolbar {
      display: grid;
      grid-template-columns: 1.4fr 1fr 1fr auto;
      gap: 12px;
      margin-bottom: 18px;
    }

    .toolbar input,
    .toolbar select {
      padding: 13px 16px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.04);
      color: #fff;
      outline: none;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .toolbar input::placeholder {
      color: var(--text-muted);
    }

    .toolbar select option {
      color: #111;
      background: var(--panel);
    }

    .toolbar input:focus,
    .toolbar select:focus {
      border-color: rgba(138,180,248,0.45);
      box-shadow: 0 0 0 4px rgba(138,180,248,0.12);
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

    .table-wrap::-webkit-scrollbar-thumb:hover {
      background: var(--primary-light);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }

    th, td {
      padding: 16px 14px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      font-size: 14px;
      vertical-align: middle;
    }

    th {
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
      cursor: pointer;
    }

    tbody tr:hover {
      background: rgba(255,255,255,0.04);
    }

    .status-paid,
    .status-pending,
    .status-failed {
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

    .status-paid {
      background: rgba(71,201,126,0.14);
      color: #9ef0bd;
      border: 1px solid rgba(71,201,126,0.18);
    }

    .status-pending {
      background: rgba(255,182,72,0.14);
      color: #ffd08b;
      border: 1px solid rgba(255,182,72,0.18);
    }

    .status-failed {
      background: rgba(255,107,107,0.14);
      color: #ffb1b1;
      border: 1px solid rgba(255,107,107,0.18);
    }

    .amount {
      font-weight: 800;
      color: #fff;
    }

    .pagination {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-top: 20px;
      flex-wrap: wrap;
    }

    .pagination-info {
      color: var(--text-soft);
      font-size: 14px;
    }

    .pagination-controls {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .page-number {
      min-width: 42px;
      height: 42px;
      border-radius: 50%;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.04);
      color: var(--text-soft);
      cursor: pointer;
      font-weight: 800;
      transition: all .25s var(--ease);
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .page-number.active,
    .page-number:hover {
      background: linear-gradient(135deg, var(--primary), #4d90fe);
      color: #fff;
      border-color: transparent;
      transform: scale(1.1);
    }

    .empty-state {
      text-align: center;
      padding: 40px 24px;
      color: var(--text-soft);
      font-size: 14px;
      line-height: 1.8;
      background: rgba(255,255,255,0.02);
      border-radius: var(--radius-lg);
      border: 1px dashed var(--line);
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

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      flex-wrap: wrap;
      margin-top: 22px;
    }

    /* Scroll to top button */
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

    @media (max-width: 1100px) {
      .toolbar {
        grid-template-columns: 1fr 1fr;
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

      .toolbar {
        grid-template-columns: 1fr;
      }

      .summary-grid,
      .status-grid {
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

      <a href="admin_dashboard.php">
        <span>🏠</span> Dashboard
      </a>
      <a href="members.php">
        <span>👥</span> Members
      </a>
      <a href="#" class="active">
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
    <div class="topbar">
      <div>
        <h1>Payment Information</h1>
        <p>Track all payment records and monitor earnings by day, week, month, and year.</p>
      </div>
      <div class="top-actions">
        <a href="payments.php" class="btn btn-secondary">↺ Reset Filters</a>
        <button class="btn btn-primary" onclick="openAddPaymentModal()">+ Add Payment</button>
      </div>
    </div>

    <!-- Earnings Summary -->
    <div class="summary-grid">
      <div class="summary-card">
        <h3>💰 Earnings Today</h3>
        <div class="value">₱<?php echo number_format($daily_earnings, 2); ?></div>
        <div class="sub">Total paid earnings recorded for today.</div>
      </div>

      <div class="summary-card success">
        <h3>📅 Earnings This Week</h3>
        <div class="value">₱<?php echo number_format($weekly_earnings, 2); ?></div>
        <div class="sub">Total paid earnings recorded within the current week.</div>
      </div>

      <div class="summary-card warning">
        <h3>📆 Earnings This Month</h3>
        <div class="value">₱<?php echo number_format($monthly_earnings, 2); ?></div>
        <div class="sub">Total paid earnings recorded within the current month.</div>
      </div>

      <div class="summary-card">
        <h3>📊 Earnings This Year</h3>
        <div class="value">₱<?php echo number_format($yearly_earnings, 2); ?></div>
        <div class="sub">Total paid earnings recorded within the current year.</div>
      </div>
    </div>

    <!-- Payment Status Summary -->
    <div class="status-grid">
      <div class="summary-card success">
        <h3>✅ Paid Payments</h3>
        <div class="value"><?php echo $paid_count; ?></div>
        <div class="sub">Successfully completed payment records.</div>
      </div>

      <div class="summary-card warning">
        <h3>⏳ Pending Payments</h3>
        <div class="value"><?php echo $pending_count; ?></div>
        <div class="sub">Payments waiting for confirmation or completion.</div>
      </div>

      <div class="summary-card danger">
        <h3>❌ Failed Payments</h3>
        <div class="value"><?php echo $failed_count; ?></div>
        <div class="sub">Payments that were not completed successfully.</div>
      </div>

      <div class="summary-card">
        <h3>💵 Total Paid Revenue</h3>
        <div class="value">₱<?php echo number_format($total_paid_revenue, 2); ?></div>
        <div class="sub">Combined revenue from all paid transactions shown in records.</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div>
          <h2>Payments Table</h2>
          <p class="panel-sub">Use the filters below to quickly find payment records. Click any row to view details.</p>
        </div>
      </div>

      <form method="GET" action="" class="toolbar">
        <input type="text" name="search" placeholder="🔍 Search member name or code..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="status">
          <option value="">⚡ All Status</option>
          <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Paid</option>
          <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
        </select>
        <select name="plan">
          <option value="">📋 All Plans</option>
          <option value="Regular" <?php echo $plan_filter == 'Regular' ? 'selected' : ''; ?>>Regular Membership</option>
          <option value="Student" <?php echo $plan_filter == 'Student' ? 'selected' : ''; ?>>Student/Senior Rate</option>
          <option value="Non-Membership" <?php echo $plan_filter == 'Non-Membership' ? 'selected' : ''; ?>>Non-Membership Promo</option>
          <option value="Weekly" <?php echo $plan_filter == 'Weekly' ? 'selected' : ''; ?>>Weekly Pass</option>
          <option value="Session" <?php echo $plan_filter == 'Session' ? 'selected' : ''; ?>>Single Session</option>
        </select>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Payment ID</th>
              <th>Member Code</th>
              <th>Member Name</th>
              <th>Plan</th>
              <th>Amount</th>
              <th>Payment Date</th>
              <th>Method</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($payments && $payments->num_rows > 0): ?>
              <?php while($payment = $payments->fetch_assoc()): 
                $status_class = '';
                $status_text = '';
                
                if ($payment['status'] == 'completed') {
                    $status_class = 'status-paid';
                    $status_text = 'Paid';
                } elseif ($payment['status'] == 'pending') {
                    $status_class = 'status-pending';
                    $status_text = 'Pending';
                } else {
                    $status_class = 'status-failed';
                    $status_text = 'Failed';
                }
              ?>
              <tr class="clickable-row" data-id="<?php echo $payment['id']; ?>">
                <td><strong><?php echo $payment['id']; ?></strong></td>
                <td><?php echo htmlspecialchars($payment['member_code'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($payment['member_name'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($payment['membership_type'] ?? 'N/A'); ?></td>
                <td class="amount">₱<?php echo number_format($payment['amount'], 2); ?></td>
                <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                  <div class="empty-state">
                    <i class="fas fa-credit-card" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                    <h3>No payments found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <div class="pagination-info">
          Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> total payments)
        </div>
        <div class="pagination-controls">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>" class="btn btn-secondary">← Previous</a>
          <?php endif; ?>
          
          <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="page-number active"><?php echo $i; ?></button>
            <?php else: ?>
              <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>" class="page-number"><?php echo $i; ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>" class="btn btn-secondary">Next →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Scroll to Top Button -->
  <button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">↑</button>

  <!-- Payment Details Modal -->
  <div class="modal" id="paymentModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Payment Details</h3>
        <button class="close-btn" onclick="closeModal('paymentModal')">✕</button>
      </div>
      <div class="modal-grid" id="paymentModalBody">
        <div class="modal-card">
          <strong>Loading...</strong>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closeModal('paymentModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Add Payment Modal -->
  <div class="modal" id="addPaymentModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Add New Payment</h3>
        <button class="close-btn" onclick="closeModal('addPaymentModal')">✕</button>
      </div>
      <form method="POST" action="add_payment.php">
        <div class="form-grid">
          <div class="form-group">
            <label>Select Member</label>
            <select name="member_id" required>
              <option value="">Select a member</option>
              <?php
              $members_sql = "SELECT id, member_code, fullname, membership_type FROM members WHERE status = 'active' ORDER BY fullname";
              $members_list = $conn->query($members_sql);
              while($member = $members_list->fetch_assoc()):
              ?>
              <option value="<?php echo $member['id']; ?>">
                <?php echo htmlspecialchars($member['member_code']); ?> - <?php echo htmlspecialchars($member['fullname']); ?> (<?php echo htmlspecialchars($member['membership_type']); ?>)
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Amount (₱)</label>
            <input type="number" name="amount" step="0.01" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label>Payment Date</label>
            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method" required>
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="credit_card">Credit Card</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reference Number (Optional)</label>
            <input type="text" name="reference_number" placeholder="Reference number if any">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" required>
              <option value="completed">Paid</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
            </select>
          </div>
          <div class="form-group">
            <label>Notes (Optional)</label>
            <textarea name="notes" rows="3" placeholder="Additional notes about this payment"></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeModal('addPaymentModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Payment</button>
        </div>
      </form>
    </div>
  </div>

  <style>
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
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: rgba(138,180,248,0.45);
      box-shadow: 0 0 0 4px rgba(138,180,248,0.12);
    }
  </style>

  <script>
    function openModal(id) {
      document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('show');
    }

    function openAddPaymentModal() {
      openModal('addPaymentModal');
    }

    window.addEventListener('click', (e) => {
      document.querySelectorAll('.modal').forEach(modal => {
        if (e.target === modal) modal.classList.remove('show');
      });
    });

    // Get payment details via AJAX
    async function getPaymentDetails(id) {
      const response = await fetch(`api/get_data.php?type=payment&id=${id}`);
      return await response.json();
    }

    // Handle click on payment rows
    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', async () => {
        const id = row.dataset.id;
        if (id) {
          const data = await getPaymentDetails(id);
          if (data && data.success) {
            const modalBody = document.getElementById('paymentModalBody');
            modalBody.innerHTML = data.items.map(item => `
              <div class="modal-card">
                <strong>${item.label}</strong>
                <span>${item.value}</span>
              </div>
            `).join('');
            openModal('paymentModal');
          }
        }
      });
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
  </script>
</body>
</html>