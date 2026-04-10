<?php
require_once 'config.php';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 10;
$offset = ($page - 1) * $rows_per_page;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(fullname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM applications $where_sql";
$count_result = executeQuery($count_sql, $params, $types);
$total_rows = $count_result->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Get applications with pagination
$sql = "SELECT * FROM applications $where_sql ORDER BY 
        CASE 
            WHEN status = 'pending' THEN 1
            WHEN status = 'approved' THEN 2
            WHEN status = 'rejected' THEN 3
            ELSE 4
        END,
        submitted_at DESC
        LIMIT ? OFFSET ?";

$params[] = $rows_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = executeQuery($sql, $params, $types);
$applications = $stmt->get_result();

// Get statistics
$pending_count = getCount('applications', "status = 'pending'");
$approved_count = getCount('applications', "status = 'approved'");
$rejected_count = getCount('applications', "status = 'rejected'");
$total_applications = getCount('applications');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Jeffrey's Gym</title>
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(11,87,208,0.16), transparent 22%),
                radial-gradient(circle at bottom right, rgba(255,255,255,0.04), transparent 20%),
                linear-gradient(135deg, #0d1016 0%, #111722 100%);
            color: var(--text);
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
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

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: linear-gradient(180deg, rgba(29, 37, 53, 0.92), rgba(22, 28, 39, 0.96));
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 20px;
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

        .summary-card.pending::before { background: linear-gradient(180deg, #ffd18b, var(--warning)); }
        .summary-card.approved::before { background: linear-gradient(180deg, #a4f0c4, var(--success)); }
        .summary-card.rejected::before { background: linear-gradient(180deg, #ffb0b0, var(--danger)); }

        .summary-card h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .summary-card .value {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -0.04em;
            margin-bottom: 4px;
            color: #fff;
        }

        .summary-card .sub {
            color: var(--text-soft);
            font-size: 12px;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
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
        }

        .filter-bar input {
            flex: 2;
            min-width: 200px;
        }

        .filter-bar input::placeholder {
            color: var(--text-muted);
        }

        .filter-bar select {
            flex: 1;
            min-width: 150px;
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

        /* Table */
        .table-wrap {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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

        /* Status Badges */
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

        .status-pending {
            background: rgba(255,182,72,0.14);
            color: #ffd08b;
            border: 1px solid rgba(255,182,72,0.18);
        }

        .status-approved {
            background: rgba(71,201,126,0.14);
            color: #9ef0bd;
            border: 1px solid rgba(71,201,126,0.18);
        }

        .status-rejected {
            background: rgba(255,107,107,0.14);
            color: #ffb1b1;
            border: 1px solid rgba(255,107,107,0.18);
        }

        .status-contacted {
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
            gap: 8px;
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

        /* Modal */
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

        /* Empty State */
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

        /* Scroll to top */
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
                flex-direction: column;
            }

            .filter-bar input,
            .filter-bar select {
                width: 100%;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .pagination {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="payments.php">
                <span>💰</span> Payments
            </a>
            <a href="prices.php">
                <span>💲</span> Manage Prices
            </a>
            <a href="#" class="active">
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
                <h1><i class="fas fa-file-alt"></i> Membership Applications</h1>
                <p>View and manage all membership applications from the website.</p>
            </div>
            <div class="top-actions">
                <a href="applications.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
                <a href="admin_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card pending">
                <h3>⏳ Pending</h3>
                <div class="value"><?php echo $pending_count; ?></div>
                <div class="sub">Waiting for review</div>
            </div>
            <div class="summary-card approved">
                <h3>✅ Approved</h3>
                <div class="value"><?php echo $approved_count; ?></div>
                <div class="sub">Applications approved</div>
            </div>
            <div class="summary-card rejected">
                <h3>❌ Rejected</h3>
                <div class="value"><?php echo $rejected_count; ?></div>
                <div class="sub">Applications declined</div>
            </div>
            <div class="summary-card">
                <h3>📊 Total</h3>
                <div class="value"><?php echo $total_applications; ?></div>
                <div class="sub">All applications</div>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
            <select id="statusSelect">
                <option value="">📋 All Status</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
                <option value="contacted" <?php echo $status_filter == 'contacted' ? 'selected' : ''; ?>>📞 Contacted</option>
            </select>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Interest</th>
                        <th>Price</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($applications && $applications->num_rows > 0): ?>
                        <?php while($app = $applications->fetch_assoc()): 
                            $status_class = '';
                            switch($app['status']) {
                                case 'pending': $status_class = 'status-pending'; break;
                                case 'approved': $status_class = 'status-approved'; break;
                                case 'rejected': $status_class = 'status-rejected'; break;
                                case 'contacted': $status_class = 'status-contacted'; break;
                            }
                        ?>
                        <tr class="clickable-row" data-id="<?php echo $app['id']; ?>">
                            <td><?php echo $app['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($app['fullname']); ?></strong></td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo htmlspecialchars($app['interest']); ?></td>
                            <td class="amount">₱<?php echo number_format($app['price'], 2); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($app['submitted_at'])); ?></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($app['status']); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px;">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>No applications found</h3>
                                    <p>No membership applications match your criteria.</p>
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
                Showing <?php echo (($page - 1) * $rows_per_page) + 1; ?> to <?php echo min($page * $rows_per_page, $total_rows); ?> of <?php echo $total_rows; ?> applications
            </div>
            <div class="pagination-controls">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '" class="page-number">1</a>';
                    if ($start_page > 2) echo '<span class="page-number" style="border: none;">...</span>';
                }
                
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <?php if ($i == $page): ?>
                        <button class="page-number active"><?php echo $i; ?></button>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-number"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1) echo '<span class="page-number" style="border: none;">...</span>'; ?>
                    <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-number"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">↑</button>

    <!-- Application Details Modal -->
    <div class="modal" id="appModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Application Details</h3>
                <button class="close-btn" onclick="closeModal('appModal')">✕</button>
            </div>
            <div class="modal-grid" id="appModalBody">
                <div class="modal-card">
                    <strong>Loading...</strong>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('appModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-filter on input/change
        const searchInput = document.getElementById('searchInput');
        const statusSelect = document.getElementById('statusSelect');
        
        function applyFilters() {
            const search = searchInput.value;
            const status = statusSelect.value;
            window.location.href = `?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
        }
        
        // Debounce function for search
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 500);
        });
        
        statusSelect.addEventListener('change', applyFilters);
        
        // Get application details via AJAX
        async function getApplicationDetails(id) {
            try {
                const response = await fetch(`api/get_data.php?type=application&id=${id}`);
                if (!response.ok) throw new Error('Network response was not ok');
                return await response.json();
            } catch (error) {
                console.error('Error fetching application details:', error);
                return { success: false, error: 'Failed to load application details' };
            }
        }
        
        // Handle click on application rows
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', async () => {
                const id = row.dataset.id;
                if (id) {
                    const data = await getApplicationDetails(id);
                    if (data && data.success) {
                        const modalBody = document.getElementById('appModalBody');
                        modalBody.innerHTML = data.items.map(item => `
                            <div class="modal-card">
                                <strong>${item.label}</strong>
                                <span>${item.value}</span>
                            </div>
                        `).join('');
                        openModal('appModal');
                    } else {
                        const modalBody = document.getElementById('appModalBody');
                        modalBody.innerHTML = `
                            <div class="modal-card">
                                <strong>Error</strong>
                                <span>Unable to load application details. Please try again.</span>
                            </div>
                        `;
                        openModal('appModal');
                    }
                }
            });
        });
        
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        window.addEventListener('click', (e) => {
            document.querySelectorAll('.modal').forEach(modal => {
                if (e.target === modal) modal.classList.remove('show');
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