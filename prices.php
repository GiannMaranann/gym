<?php
require_once 'config.php';

// Handle price update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prices'])) {
    foreach ($_POST['prices'] as $id => $price) {
        $price = floatval($price);
        $update_sql = "UPDATE prices SET price = ? WHERE id = ?";
        executeQuery($update_sql, [$price, $id], "di");
    }
    $success_message = "Prices updated successfully!";
}

// Get all prices
$prices_sql = "SELECT * FROM prices ORDER BY price";
$prices = $conn->query($prices_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Prices - Jeffrey's Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0d1016 0%, #111722 100%);
            color: #f5f7fb;
            padding: 40px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff, #8ab4f8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: #93a0b6;
            margin-bottom: 30px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        }

        .price-card .description {
            color: #93a0b6;
            font-size: 13px;
            margin-bottom: 20px;
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

        .price-note {
            font-size: 12px;
            color: #ffb648;
            margin-top: 10px;
        }

        .btn {
            border: none;
            cursor: pointer;
            border-radius: 999px;
            padding: 14px 28px;
            font-weight: 800;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0b57d0, #4d90fe);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.04);
            color: #c3cbdb;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .alert {
            background: linear-gradient(135deg, #1e4620, #2e7d32);
            border-left: 4px solid #4caf50;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #8ab4f8;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Manage Membership Prices</h1>
        <p class="subtitle">Update your gym membership rates. Changes will reflect immediately on the website.</p>

        <?php if (isset($success_message)): ?>
        <div class="alert">✅ <?php echo $success_message; ?></div>
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
                            case 'Regular Membership': echo 'Standard monthly membership - full access to all gym facilities'; break;
                            case 'Student/Senior Rate': echo 'Discounted rate for students and senior citizens (valid ID required)'; break;
                            case 'Non-Membership Promo': echo 'Promotional rate for non-membership access'; break;
                            case 'Weekly Pass': echo '7-day gym access pass'; break;
                            case 'Single Session': echo 'Pay per visit - single gym session'; break;
                            default: echo $price['description'];
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
                    <div class="price-note">⚠️ Valid ID required for verification</div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" name="update_prices" class="btn btn-primary">💾 Save All Changes</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </form>

        <a href="admin_dashboard.php" class="back-link">← Return to Admin Dashboard</a>
    </div>
</body>
</html>