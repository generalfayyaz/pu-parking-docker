<?php
// ============================================================
//  Login Page
//  File: auth/login.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → redirect to dashboard
if (!empty($_SESSION['operator_id'])) {
    header('Location: ' . BASE_URL . 'index.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT operator_id, username, full_name, password, role
                 FROM operators
                 WHERE username = ? AND is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $op = $stmt->fetch();

            if ($op && password_verify($password, $op['password'])) {
                session_regenerate_id(true);
                $_SESSION['operator_id']       = $op['operator_id'];
                $_SESSION['operator_username'] = $op['username'];
                $_SESSION['operator_name']     = $op['full_name'];
                $_SESSION['operator_role']     = $op['role'];

                header('Location: ' . BASE_URL . 'index.php'); exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'Server error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PU Parking — Login</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--bg); }

    .login-wrap {
      width: 100%;
      max-width: 400px;
      padding: 1rem;
    }

    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow);
    }

    .login-logo {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-logo img {
      width: 100%;
      max-width: 160px;
      border-radius: 10px;
    }

    .login-logo h1 {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--accent);
      margin-top: .75rem;
    }

    .login-logo p {
      font-size: .82rem;
      color: var(--muted);
      margin-top: .25rem;
    }

    .error-box {
      background: var(--danger-muted);
      border: 1px solid var(--danger);
      border-radius: 8px;
      padding: .75rem 1rem;
      font-size: .85rem;
      color: var(--danger);
      margin-bottom: 1.25rem;
    }

    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
      font-size: .78rem;
      color: var(--muted);
    }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <img src="../images/logo.svg" alt="ParkSys Logo" onerror="this.style.display='none'">
      <h1>PU Parking</h1>
      <p>Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password">
      </div>
      <button type="submit" class="btn btn-primary w-full mt-2" style="padding:.85rem">
        Sign In →
      </button>
    </form>

    <div class="login-footer">
      PU Parking v1.0 &nbsp;·&nbsp; &copy; <?= date('Y') ?>
    </div>
  </div>
</div>
</body>
</html>
