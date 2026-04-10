<?php
require_once 'config.php';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$interest_filter = isset($_GET['interest']) ? sanitizeInput($_GET['interest']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 7;
$offset = ($page - 1) * $rows_per_page;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(m.fullname LIKE ? OR m.email LIKE ? OR m.member_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "health_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($interest_filter)) {
    $where_clauses[] = "m.membership_type = ?";
    $params[] = $interest_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM (
    SELECT 
        m.*,
        CASE 
            WHEN m.status = 'expired' OR m.end_date < CURDATE() THEN 'Expired'
            WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
            ELSE 'Healthy'
        END as health_status
    FROM members m
) AS sub $where_sql";

$count_result = executeQuery($count_sql, $params, $types);
$total_rows = $count_result->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Get members with pagination
$sql = "SELECT 
            m.*,
            CASE 
                WHEN m.status = 'expired' OR m.end_date < CURDATE() THEN 'Expired'
                WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
                ELSE 'Healthy'
            END as health_status,
            DATEDIFF(m.end_date, CURDATE()) as days_remaining
        FROM members m
        $where_sql
        ORDER BY 
            CASE 
                WHEN m.end_date < CURDATE() THEN 3
                WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                ELSE 1
            END,
            m.end_date ASC
        LIMIT ? OFFSET ?";

$params[] = $rows_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = executeQuery($sql, $params, $types);
$members = $stmt->get_result();

// Get statistics
$total_members = getCount('members');

$active_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active' AND end_date >= CURDATE()";
$active_result = $conn->query($active_sql);
$active_members = $active_result->fetch_assoc()['total'];

$expiring_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$expiring_result = $conn->query($expiring_sql);
$expiring_members = $expiring_result->fetch_assoc()['total'];

$expired_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'expired' OR end_date < CURDATE()";
$expired_result = $conn->query($expired_sql);
$expired_members = $expired_result->fetch_assoc()['total'];

$healthy_members = $active_members - $expiring_members;

// Get all interests for filter dropdown
$interests_sql = "SELECT DISTINCT interest_name FROM prices ORDER BY interest_name";
$interests_result = $conn->query($interests_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Members - Jeffrey's Gym</title>
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

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 18px;
      margin-bottom: 20px;
    }

    .summary-card {
      background: linear-gradient(180deg, rgba(29, 37, 53, 0.92), rgba(22, 28, 39, 0.96));
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
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

    .summary-card.healthy::before { background: linear-gradient(180deg, #a4f0c4, var(--success)); }
    .summary-card.warning::before { background: linear-gradient(180deg, #ffd18b, var(--warning)); }
    .summary-card.danger::before { background: linear-gradient(180deg, #ffb0b0, var(--danger)); }

    .panel {
      background: linear-gradient(180deg, rgba(29, 37, 53, 0.92), rgba(22, 28, 39, 0.96));
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
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

    .filter-bar {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 12px;
      margin-bottom: 20px;
    }

    .filter-bar input,
    .filter-bar select {
      padding: 12px 16px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.04);
      color: #fff;
      outline: none;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .filter-bar input::placeholder {
      color: var(--text-muted);
    }

    .filter-bar select option {
      color: #111;
      background: var(--panel);
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
      border-color: rgba(138,180,248,0.45);
      box-shadow: 0 0 0 4px rgba(138,180,248,0.12);
    }

    .table-wrap {
      overflow-x: auto;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.02);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1000px;
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
    }

    tbody tr {
      transition: background .2s ease;
      cursor: pointer;
    }

    tbody tr:hover {
      background: rgba(255,255,255,0.04);
    }

    .badge {
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

    .badge.healthy {
      background: rgba(71,201,126,0.14);
      color: #9ef0bd;
      border: 1px solid rgba(71,201,126,0.18);
    }

    .badge.expiring {
      background: rgba(255,182,72,0.14);
      color: #ffd08b;
      border: 1px solid rgba(255,182,72,0.18);
    }

    .badge.expired {
      background: rgba(255,107,107,0.14);
      color: #ffb1b1;
      border: 1px solid rgba(255,107,107,0.18);
    }

    .badge.membership {
      background: rgba(138,180,248,0.14);
      color: #bdd7ff;
      border: 1px solid rgba(138,180,248,0.18);
    }

    /* Pagination */
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
      min-width: 40px;
      height: 40px;
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
      padding: 60px 24px;
      color: var(--text-soft);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.5;
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

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      flex-wrap: wrap;
      margin-top: 22px;
    }

    @media (max-width: 1100px) {
      .filter-bar {
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

      .filter-bar {
        grid-template-columns: 1fr;
      }

      .summary-grid {
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
      <a href="#" class="active">
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
    <div class="topbar">
      <div>
        <h1>Member Information</h1>
        <p>Manage member records, filter by status and membership type. Showing <?php echo $rows_per_page; ?> members per page.</p>
      </div>
      <div class="top-actions">
        <a href="members.php" class="btn btn-secondary">↺ Reset Filters</a>
        <button class="btn btn-primary" onclick="openAddMemberModal()">+ Add Member</button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card healthy">
        <h3>✅ Healthy Accounts</h3>
        <div class="value"><?php echo $healthy_members; ?></div>
        <div class="sub">Members with more than 7 days until expiry</div>
      </div>

      <div class="summary-card warning">
        <h3>⚠️ Expiring Soon</h3>
        <div class="value"><?php echo $expiring_members; ?></div>
        <div class="sub">Members expiring within 7 days</div>
      </div>

      <div class="summary-card danger">
        <h3>❌ Expired Accounts</h3>
        <div class="value"><?php echo $expired_members; ?></div>
        <div class="sub">Members with expired memberships</div>
      </div>

      <div class="summary-card">
        <h3>📊 Total Members</h3>
        <div class="value"><?php echo $total_members; ?></div>
        <div class="sub">All registered members</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div>
          <h2>Members Table</h2>
          <p class="panel-sub">Click any row to view complete member details.</p>
        </div>
      </div>

      <!-- Filter Bar - No Apply Button, auto-filter on change -->
      <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="🔍 Search by name, email, or member code..." value="<?php echo htmlspecialchars($search); ?>">
        <select id="statusSelect">
          <option value="">📋 All Health Status</option>
          <option value="Healthy" <?php echo $status_filter == 'Healthy' ? 'selected' : ''; ?>>✅ Healthy</option>
          <option value="Expiring Soon" <?php echo $status_filter == 'Expiring Soon' ? 'selected' : ''; ?>>⚠️ Expiring Soon</option>
          <option value="Expired" <?php echo $status_filter == 'Expired' ? 'selected' : ''; ?>>❌ Expired</option>
        </select>
        <select id="interestSelect">
          <option value="">🏋️ All Interests</option>
          <?php 
          $interests_result = $conn->query($interests_sql);
          while($interest = $interests_result->fetch_assoc()): 
          ?>
          <option value="<?php echo htmlspecialchars($interest['interest_name']); ?>" <?php echo $interest_filter == $interest['interest_name'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($interest['interest_name']); ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="table-wrap">
        <table id="membersTable">
          <thead>
            <tr>
              <th>Member Code</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Membership Type</th>
              <th>Health Status</th>
              <th>Start Date</th>
              <th>Expiry Date</th>
              <th>Days Left</th>
            </tr>
          </thead>
          <tbody id="membersTableBody">
            <?php if ($members && $members->num_rows > 0): ?>
              <?php while($member = $members->fetch_assoc()): 
                $health_class = '';
                if ($member['health_status'] == 'Healthy') {
                    $health_class = 'healthy';
                } elseif ($member['health_status'] == 'Expiring Soon') {
                    $health_class = 'expiring';
                } else {
                    $health_class = 'expired';
                }
                
                $days_left = $member['days_remaining'];
                $days_display = $days_left < 0 ? 'Expired' : $days_left . ' days';
              ?>
              <tr class="clickable-row" data-id="<?php echo $member['id']; ?>">
                <td><strong><?php echo htmlspecialchars($member['member_code']); ?></strong></td>
                <td><?php echo htmlspecialchars($member['fullname']); ?></td>
                <td><?php echo htmlspecialchars($member['email']); ?></td>
                <td><span class="badge membership"><?php echo htmlspecialchars($member['membership_type']); ?></span></td>
                <td><span class="badge <?php echo $health_class; ?>"><?php echo $member['health_status']; ?></span></td>
                <td><?php echo date('Y-m-d', strtotime($member['start_date'])); ?></td>
                <td><?php echo date('Y-m-d', strtotime($member['end_date'])); ?></td>
                <td><?php echo $days_display; ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                  <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No members found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" id="paginationContainer">
        <!-- Pagination will be loaded via AJAX -->
      </div>
    </div>
  </main>

  <!-- Scroll to Top Button -->
  <button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">↑</button>

  <!-- Member Details Modal -->
  <div class="modal" id="memberModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Member Details</h3>
        <button class="close-btn" onclick="closeModal('memberModal')">✕</button>
      </div>
      <div class="modal-grid" id="memberModalBody">
        <div class="modal-card">
          <strong>Loading...</strong>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closeModal('memberModal')">Close</button>
      </div>
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
            <label>Membership Type</label>
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

  <script>
    // Debounce function to prevent too many requests
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Function to apply filters via AJAX
    function applyFilters() {
      const search = document.getElementById('searchInput').value;
      const status = document.getElementById('statusSelect').value;
      const interest = document.getElementById('interestSelect').value;
      const page = 1; // Reset to page 1 when filtering
      
      // Build URL with parameters
      const url = `members_ajax.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&interest=${encodeURIComponent(interest)}&page=${page}`;
      
      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update table body
            const tbody = document.getElementById('membersTableBody');
            tbody.innerHTML = data.html;
            
            // Update pagination
            const paginationContainer = document.getElementById('paginationContainer');
            paginationContainer.innerHTML = data.pagination;
            
            // Re-attach click handlers to new rows
            attachRowClickHandlers();
          }
        })
        .catch(error => console.error('Error:', error));
    }

    // Function to load page
    function loadPage(page) {
      const search = document.getElementById('searchInput').value;
      const status = document.getElementById('statusSelect').value;
      const interest = document.getElementById('interestSelect').value;
      
      const url = `members_ajax.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&interest=${encodeURIComponent(interest)}&page=${page}`;
      
      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById('membersTableBody').innerHTML = data.html;
            document.getElementById('paginationContainer').innerHTML = data.pagination;
            attachRowClickHandlers();
          }
        })
        .catch(error => console.error('Error:', error));
    }

    // Attach click handlers to member rows
    function attachRowClickHandlers() {
      document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', async () => {
          const id = row.dataset.id;
          if (id) {
            const data = await getMemberDetails(id);
            if (data && data.success) {
              const modalBody = document.getElementById('memberModalBody');
              modalBody.innerHTML = data.items.map(item => `
                <div class="modal-card">
                  <strong>${item.label}</strong>
                  <span>${item.value}</span>
                </div>
              `).join('');
              openModal('memberModal');
            }
          }
        });
      });
    }

    // Get member details via AJAX
    async function getMemberDetails(id) {
      try {
        const response = await fetch(`api/get_data.php?type=member&id=${id}`);
        if (!response.ok) throw new Error('Network response was not ok');
        return await response.json();
      } catch (error) {
        console.error('Error fetching member details:', error);
        return { success: false, error: 'Failed to load member details' };
      }
    }

    // Modal functions
    function openModal(id) {
      document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('show');
    }

    function openAddMemberModal() {
      openModal('addMemberModal');
    }

    // Auto-filter on input/change events
    const searchInput = document.getElementById('searchInput');
    const statusSelect = document.getElementById('statusSelect');
    const interestSelect = document.getElementById('interestSelect');
    
    searchInput.addEventListener('input', debounce(applyFilters, 500));
    statusSelect.addEventListener('change', applyFilters);
    interestSelect.addEventListener('change', applyFilters);

    // Pagination click handler (event delegation)
    document.getElementById('paginationContainer').addEventListener('click', (e) => {
      const pageLink = e.target.closest('.page-link');
      if (pageLink) {
        e.preventDefault();
        const page = pageLink.dataset.page;
        if (page) {
          loadPage(parseInt(page));
        }
      }
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

    // Initial attachment of click handlers
    attachRowClickHandlers();
  </script>
</body>
</html>