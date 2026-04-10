<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login_register.php');
    exit();
}

// Check if user has admin role (only admin can manage prices)
$user_role = $_SESSION['admin_role'] ?? 'staff';
if ($user_role !== 'admin') {
    header('Location: admin_dashboard.php?error=unauthorized');
    exit();
}

$user_name = $_SESSION['admin_fullname'] ?? 'Admin';
$success_message = '';
$error_message = '';

// Handle price update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prices'])) {
    $success_count = 0;
    $error_count = 0;
    
    foreach ($_POST['prices'] as $id => $price) {
        $id = intval($id);
        $price = floatval($price);
        
        if ($price >= 0) {
            $update_sql = "UPDATE prices SET price = ? WHERE id = ?";
            $stmt = executeQuery($update_sql, [$price, $id], "di");
            if ($stmt && $stmt->affected_rows >= 0) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }
    
    if ($success_count > 0) {
        $success_message = "Prices updated successfully! ($success_count record(s) updated)";
    }
    if ($error_count > 0) {
        $error_message = "Failed to update $error_count record(s).";
    }
}

// Get all prices
$prices_sql = "SELECT * FROM prices ORDER BY 
                CASE 
                    WHEN interest_name = 'Regular Membership' THEN 1
                    WHEN interest_name = 'Student/Senior Rate' THEN 2
                    WHEN interest_name = 'Non-Membership Promo' THEN 3
                    WHEN interest_name = 'Weekly Pass' THEN 4
                    WHEN interest_name = 'Single Session' THEN 5
                    ELSE 6
                END";
$prices = $conn->query($prices_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Prices - Jeffrey's Gym</title>
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

        /* Alert messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #1e4620, #2e7d32);
            border-left: 4px solid #4caf50;
            color: #fff;
        }

        .alert-error {
            background: linear-gradient(135deg, #461e1e, #c62828);
            border-left: 4px solid #ef5350;
            color: #fff;
        }

        @keyframes slideIn {
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

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .price-card {
            background: linear-gradient(180deg, rgba(29, 37, 53, 0.92), rgba(22, 28, 39, 0.96));
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 22px;
            padding: 24px;
            transition: transform 0.3s ease;
        }

        .price-card:hover {
            transform: translateY(-4px);
        }

        .price-card h3 {
            font-size: 20px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .price-card .description {
            color: #93a0b6;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .price-input {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .price-input label {
            font-weight: 600;
            color: #8ab4f8;
            font-size: 18px;
        }

        .price-input input {
            flex: 1;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
        }

        .price-input input:focus {
            outline: none;
            border-color: #8ab4f8;
        }

        .price-input span {
            color: var(--text-muted);
            font-size: 14px;
        }

        .price-note {
            font-size: 12px;
            color: #ffb648;
            margin-top: 10px;
            padding: 8px;
            background: rgba(255,182,72,0.1);
            border-radius: 8px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
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

            .sidebar {
                padding: 16px 12px;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
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
            <a href="#" class="active">
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
                <h1>💰 Manage Membership Prices</h1>
                <p>Update your gym membership rates. Changes will reflect immediately on the website.</p>
            </div>
            <div class="top-actions">
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert">
            <span class="alert-close" onclick="this.parentElement.style.display='none'">&times;</span>
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <script>
            setTimeout(function() {
                var alert = document.getElementById('successAlert');
                if (alert) alert.style.display = 'none';
            }, 5000);
        </script>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error" id="errorAlert">
            <span class="alert-close" onclick="this.parentElement.style.display='none'">&times;</span>
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <script>
            setTimeout(function() {
                var alert = document.getElementById('errorAlert');
                if (alert) alert.style.display = 'none';
            }, 5000);
        </script>
        <?php endif; ?>

        <form method="POST">
            <div class="pricing-grid">
                <?php while($price = $prices->fetch_assoc()): ?>
                <div class="price-card">
                    <h3>
                        <?php 
                        $icon = '';
                        switch($price['interest_name']) {
                            case 'Regular Membership': $icon = '🏋️'; break;
                            case 'Student/Senior Rate': $icon = '🎓'; break;
                            case 'Non-Membership Promo': $icon = '🎉'; break;
                            case 'Weekly Pass': $icon = '📅'; break;
                            case 'Single Session': $icon = '💪'; break;
                            default: $icon = '💰';
                        }
                        echo $icon . ' ' . htmlspecialchars($price['interest_name']);
                        ?>
                    </h3>
                    <div class="description">
                        <?php 
                        switch($price['interest_name']) {
                            case 'Regular Membership': 
                                echo 'Standard monthly membership - full access to all gym facilities';
                                break;
                            case 'Student/Senior Rate': 
                                echo 'Discounted rate for students and senior citizens (valid ID required)';
                                break;
                            case 'Non-Membership Promo': 
                                echo 'Promotional rate for non-membership access';
                                break;
                            case 'Weekly Pass': 
                                echo '7-day gym access pass';
                                break;
                            case 'Single Session': 
                                echo 'Pay per visit - single gym session';
                                break;
                            default: 
                                echo $price['description'];
                        }
                        ?>
                    </div>
                    <div class="price-input">
                        <label>₱</label>
                        <input type="number" name="prices[<?php echo $price['id']; ?>]" value="<?php echo $price['price']; ?>" step="10" min="0" required>
                        <span>/ 
                            <?php 
                            if($price['interest_name'] == 'Weekly Pass') echo 'week';
                            elseif($price['interest_name'] == 'Single Session') echo 'session';
                            else echo 'month';
                            ?>
                        </span>
                    </div>
                    <?php if($price['interest_name'] == 'Student/Senior Rate'): ?>
                    <div class="price-note">
                        <i class="fas fa-id-card"></i> Valid ID required for verification
                    </div>
                    <?php endif; ?>
                    <?php if($price['interest_name'] == 'Weekly Pass'): ?>
                    <div class="price-note">
                        <i class="fas fa-calendar-week"></i> Valid for 7 days from start date
                    </div>
                    <?php endif; ?>
                    <?php if($price['interest_name'] == 'Single Session'): ?>
                    <div class="price-note">
                        <i class="fas fa-clock"></i> Valid for one-time use only
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="form-actions">
                <button type="submit" name="update_prices" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save All Changes
                </button>
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </main>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">↑</button>

    <script>
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