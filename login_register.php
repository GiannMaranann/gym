<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$message_type = '';

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $fullname = sanitizeInput($_POST['fullname']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);
    
    // Validation
    $errors = [];
    
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if (empty($role)) {
        $errors[] = "Please select a role";
    }
    
    // Check if email already exists
    $check_sql = "SELECT id FROM admin_users WHERE email = ?";
    $check_stmt = executeQuery($check_sql, [$email], "s");
    if ($check_stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new admin user
        $insert_sql = "INSERT INTO admin_users (username, password, email, fullname, role, created_at, is_active) 
                       VALUES (?, ?, ?, ?, ?, NOW(), 1)";
        $username = strtolower(explode('@', $email)[0]);
        $stmt = executeQuery($insert_sql, [$username, $hashed_password, $email, $fullname, $role], "sssss");
        
        if ($stmt && $stmt->affected_rows > 0) {
            $message = "Registration successful! You can now login.";
            $message_type = "success";
            
            // Log the action
            $new_id = $conn->insert_id;
            logAdminAction($new_id, 'REGISTER', 'admin_users', $new_id, null, json_encode(['role' => $role]));
        } else {
            $message = "Registration failed. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $sql = "SELECT * FROM admin_users WHERE email = ? AND is_active = 1";
        $stmt = executeQuery($sql, [$email], "s");
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_fullname'] = $user['fullname'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['admin_logged_in'] = true;
                
                // Update last login
                $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
                executeQuery($update_sql, [$user['id']], "i");
                
                // Log the action
                logAdminAction($user['id'], 'LOGIN', 'admin_users', $user['id'], null, json_encode(['ip' => $_SERVER['REMOTE_ADDR']]));
                
                // Redirect based on role
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $message = "Invalid email or password";
                $message_type = "error";
            }
        } else {
            $message = "Invalid email or password";
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Jeffrey's Gym | Login & Register</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

  <style>
    :root {
      --bg: #0f131a;
      --bg-soft: #151b24;
      --card: rgba(20, 25, 34, 0.88);
      --card-2: rgba(17, 22, 30, 0.94);
      --line: rgba(255, 255, 255, 0.08);
      --line-strong: rgba(255, 255, 255, 0.14);
      --text: #f4f7fb;
      --text-soft: #c5cedd;
      --text-muted: #93a0b6;
      --primary: #0b57d0;
      --primary-light: #8ab4f8;
      --primary-soft: rgba(138, 180, 248, 0.12);
      --success-bg: rgba(28, 108, 63, 0.22);
      --success-text: #d1ffe0;
      --error-bg: rgba(220, 53, 69, 0.22);
      --error-text: #ffb1b1;
      --shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
      --radius-xl: 30px;
      --radius-lg: 22px;
      --radius-md: 16px;
      --radius-sm: 12px;
      --ease: cubic-bezier(.22,.61,.36,1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Inter", sans-serif;
    }

    body {
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(11, 87, 208, 0.18), transparent 24%),
        radial-gradient(circle at bottom right, rgba(255, 255, 255, 0.04), transparent 22%),
        linear-gradient(135deg, #0d1016 0%, #12161d 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      color: var(--text);
      overflow-x: hidden;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 28px 28px;
      mask-image: linear-gradient(to bottom, rgba(0,0,0,0.22), rgba(0,0,0,0));
    }

    .auth-wrap {
      width: 100%;
      max-width: 550px;
      position: relative;
      z-index: 1;
    }

    .auth-card {
      background: linear-gradient(180deg, var(--card), var(--card-2));
      border: 1px solid var(--line);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      overflow: hidden;
      padding: 34px;
    }

    .brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      margin-bottom: 26px;
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

    .brand-name {
      font-size: 1.5rem;
      font-weight: 900;
      letter-spacing: -0.04em;
      color: #fff;
      background: linear-gradient(135deg, #fff, var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .form-header {
      text-align: center;
      margin-bottom: 24px;
    }

    .form-header h1 {
      font-size: 2.2rem;
      font-weight: 900;
      letter-spacing: -0.05em;
      margin-bottom: 8px;
      color: #fff;
    }

    .form-header p {
      color: var(--text-soft);
      font-size: 0.97rem;
      line-height: 1.7;
    }

    .tabs {
      display: flex;
      margin-bottom: 24px;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid var(--line);
      background: rgba(255,255,255,0.03);
      padding: 6px;
      gap: 6px;
    }

    .tab-btn {
      flex: 1;
      padding: 14px 16px;
      background: transparent;
      border: none;
      color: var(--text-soft);
      cursor: pointer;
      font-size: 0.98rem;
      font-weight: 800;
      border-radius: 12px;
      transition: all .25s var(--ease);
    }

    .tab-btn.active {
      background: linear-gradient(135deg, var(--primary), #4d90fe);
      color: #fff;
      box-shadow: 0 10px 22px rgba(11, 87, 208, 0.22);
    }

    form {
      display: none;
      animation: fadeUp .35s var(--ease);
    }

    form.active {
      display: block;
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(12px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .input-group {
      margin-bottom: 16px;
    }

    .input-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--text-soft);
      font-size: 0.94rem;
      font-weight: 600;
    }

    .input-group label i {
      margin-right: 8px;
      width: 20px;
    }

    .input-group input,
    .input-group select {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: rgba(255,255,255,0.04);
      color: #fff;
      font-size: 0.97rem;
      outline: none;
      transition: all .25s var(--ease);
    }

    .input-group input::placeholder {
      color: #8f9aab;
    }

    .input-group select option {
      color: #111;
      background: var(--card-2);
    }

    .input-group input:focus,
    .input-group select:focus {
      border-color: rgba(138, 180, 248, 0.45);
      box-shadow: 0 0 0 4px rgba(138, 180, 248, 0.12);
      background: rgba(255,255,255,0.06);
    }

    .btn {
      width: 100%;
      padding: 15px 18px;
      background: linear-gradient(135deg, var(--primary), #4d90fe);
      border: none;
      border-radius: 14px;
      font-size: 1rem;
      font-weight: 800;
      cursor: pointer;
      color: #fff;
      margin-top: 8px;
      box-shadow: 0 14px 28px rgba(11, 87, 208, 0.24);
      transition: all .25s var(--ease);
    }

    .btn:hover {
      transform: translateY(-2px);
      filter: brightness(1.05);
    }

    .message {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 14px;
      display: none;
      text-align: center;
      font-weight: 700;
      line-height: 1.6;
      font-size: 14px;
    }

    .message.success {
      background: var(--success-bg);
      color: var(--success-text);
      border: 1px solid rgba(74, 170, 111, 0.18);
    }

    .message.error {
      background: var(--error-bg);
      color: var(--error-text);
      border: 1px solid rgba(220, 53, 69, 0.18);
    }

    .message.show {
      display: block;
    }

    .role-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 700;
      margin-left: 8px;
    }

    .role-admin {
      background: rgba(11,87,208,0.2);
      color: #8ab4f8;
    }

    .role-frontdesk {
      background: rgba(71,201,126,0.2);
      color: #9ef0bd;
    }

    .role-coach {
      background: rgba(255,182,72,0.2);
      color: #ffd08b;
    }

    .note {
      text-align: center;
      margin-top: 16px;
      color: var(--text-muted);
      font-size: 0.85rem;
      line-height: 1.7;
    }

    @media (max-width: 640px) {
      body {
        padding: 14px;
      }

      .auth-card {
        padding: 24px 18px;
      }

      .form-header h1 {
        font-size: 1.8rem;
      }

      .tabs {
        flex-direction: column;
      }

      .tab-btn {
        width: 100%;
      }

      .brand-name {
        font-size: 1.3rem;
      }

      .brand-logo {
        width: 50px;
        height: 50px;
      }
    }
  </style>
</head>
<body>

  <div class="auth-wrap">
    <div class="auth-card">
      <div class="brand">
        <img src="gym_logo.jpg" alt="Jeffrey's Gym Logo" class="brand-logo" onerror="this.src='https://via.placeholder.com/60x60/0b57d0/ffffff?text=JG'">
        <div class="brand-name">Jeffrey's Gym</div>
      </div>

      <div class="form-header">
        <h1>Account Access</h1>
        <p>Login or register to access the admin dashboard.</p>
      </div>

      <div class="tabs">
        <button class="tab-btn active" type="button" onclick="showForm('login')">Login</button>
        <button class="tab-btn" type="button" onclick="showForm('register')">Register</button>
      </div>

      <!-- Login Form -->
      <form id="loginForm" method="POST" action="" class="active">
        <div class="input-group">
          <label for="loginEmail"><i class="fas fa-envelope"></i> Email</label>
          <input id="loginEmail" name="email" type="email" placeholder="Enter your email" required />
        </div>

        <div class="input-group">
          <label for="loginPassword"><i class="fas fa-lock"></i> Password</label>
          <input id="loginPassword" name="password" type="password" placeholder="Enter your password" required />
        </div>

        <input type="hidden" name="login" value="1">
        <button type="submit" class="btn">Login</button>
      </form>

      <!-- Register Form -->
      <form id="registerForm" method="POST" action="">
        <div class="input-group">
          <label for="registerName"><i class="fas fa-user"></i> Full Name</label>
          <input id="registerName" name="fullname" type="text" placeholder="Enter full name" required />
        </div>

        <div class="input-group">
          <label for="registerEmail"><i class="fas fa-envelope"></i> Email</label>
          <input id="registerEmail" name="email" type="email" placeholder="Enter your email" required />
        </div>

        <div class="input-group">
          <label for="registerPassword"><i class="fas fa-key"></i> Password</label>
          <input id="registerPassword" name="password" type="password" placeholder="Create password (min. 6 characters)" required />
        </div>

        <div class="input-group">
          <label for="registerRole"><i class="fas fa-user-tag"></i> Role</label>
          <select id="registerRole" name="role" required>
            <option value="">Select Role</option>
            <option value="admin">👑 Admin - Full access</option>
            <option value="frontdesk">🏪 Front Desk - Manage members & payments</option>
            <option value="coach">🏋️ Coach - View members & schedules</option>
          </select>
        </div>

        <input type="hidden" name="register" value="1">
        <button type="submit" class="btn">Register</button>
      </form>

      <!-- Message Box -->
      <?php if ($message): ?>
      <div id="messageBox" class="message <?php echo $message_type; ?> show">
        <?php echo $message; ?>
      </div>
      <?php else: ?>
      <div id="messageBox" class="message"></div>
      <?php endif; ?>
      
      <p class="note">
        <i class="fas fa-info-circle"></i> 
        <strong>Role Access:</strong>
        <span class="role-badge role-admin">Admin</span> - Full access
        <span class="role-badge role-frontdesk">Front Desk</span> - Manage members & payments
        <span class="role-badge role-coach">Coach</span> - View members & schedules
      </p>
    </div>
  </div>

  <script>
    const loginForm = document.getElementById("loginForm");
    const registerForm = document.getElementById("registerForm");
    const tabButtons = document.querySelectorAll(".tab-btn");
    const messageBox = document.getElementById("messageBox");

    function showForm(type) {
      if (messageBox) {
        messageBox.style.display = "none";
        messageBox.className = "message";
      }

      if (type === "login") {
        loginForm.classList.add("active");
        registerForm.classList.remove("active");
        tabButtons[0].classList.add("active");
        tabButtons[1].classList.remove("active");
      } else {
        registerForm.classList.add("active");
        loginForm.classList.remove("active");
        tabButtons[1].classList.add("active");
        tabButtons[0].classList.remove("active");
      }
    }

    // Clear message when switching tabs
    function clearMessage() {
      if (messageBox) {
        messageBox.style.display = "none";
        messageBox.className = "message";
      }
    }

    // Auto-hide message after 5 seconds
    <?php if ($message): ?>
    setTimeout(function() {
      var msgBox = document.getElementById('messageBox');
      if (msgBox) {
        msgBox.style.display = 'none';
      }
    }, 5000);
    <?php endif; ?>
  </script>

</body>
</html>