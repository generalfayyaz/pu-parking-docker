<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_auth();
$user = current_user();
$history = getTransactionHistory(100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parking History — PU Parking</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app-container">
      <aside class="sidebar">
      <div class="brand" style="padding:.5rem .75rem 1.2rem;">
        <img src="../images/logo.svg" alt="PU Parking" style="width:100%;max-width:180px;display:block;margin:0 auto">
      </div>
      <nav>
        <a href="../index.php"> Dashboard</a>
        <a href="vehicles.php" class=""> Vehicles</a>
        <a href="parking.php" class="active"> Parking</a>
        <a href="../reports/report.php"> Reports</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="users.php"> Users</a>
        <?php endif; ?>
      </nav>
      <div style="margin-top:auto;padding:.75rem;border-top:1px solid var(--border)">
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:.5rem">
          Logged in as<br>
          <strong style="color:var(--text)"><?= htmlspecialchars($user['name']) ?></strong>
          <span style="color:var(--accent);font-size:.7rem;margin-left:.3rem"><?= $user['role'] ?></span>
        </div>
        <a href="../auth/logout.php" class="btn btn-outline btn-sm w-full" style="text-align:center">Logout</a>
      </div>
    </aside>
  <main class="main-content">
    <div class="page-header flex-between">
      <div><h1>Parking History</h1><p class="text-muted">Recent completed transactions</p></div>
      <a href="../reports/report.php" class="btn btn-outline"> Full Report</a>
    </div>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Plate</th><th>Type</th><th>Slot</th><th>Check-In</th><th>Check-Out</th><th>Duration</th><th>Fee</th><th>Payment</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if (empty($history)): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem">No completed transactions yet</td></tr>
            <?php else: foreach ($history as $r): ?>
            <tr>
              <td class="text-mono text-muted"><?= $r['transaction_id'] ?></td>
              <td><span class="plate"><?= htmlspecialchars($r['plate_number']) ?></span></td>
              <td><span class="badge badge-blue"><?= htmlspecialchars($r['type_name']) ?></span></td>
              <td><?= htmlspecialchars($r['slot_code']) ?></td>
              <td class="text-mono" style="font-size:.8rem"><?= date('d/m H:i', strtotime($r['check_in_time'])) ?></td>
              <td class="text-mono" style="font-size:.8rem"><?= date('d/m H:i', strtotime($r['check_out_time'])) ?></td>
              <td class="text-warning"><?= $r['duration_hours'] ?>h</td>
              <td class="text-accent"><strong><?= formatRupiah($r['fee_total']) ?></strong></td>
              <td class="text-muted"><?= htmlspecialchars($r['payment_method'] ?? '—') ?></td>
              <td><span class="badge badge-green">Paid</span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
