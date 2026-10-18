<?php
// ============================================================
//  Report: Daily & Summary Reports
//  File: reports/report.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_auth();
$user = current_user();
$db = getDB();

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');

// Daily revenue report
$revStmt = $db->prepare(
    "SELECT DATE(check_out_time) as report_date,
            COUNT(*) as total_transactions,
            SUM(fee_total) as total_revenue,
            AVG(duration_hours) as avg_duration
     FROM parking_transactions
     WHERE check_out_time IS NOT NULL
       AND DATE(check_out_time) BETWEEN ? AND ?
     GROUP BY DATE(check_out_time)
     ORDER BY report_date DESC"
);
$revStmt->execute([$dateFrom, $dateTo]);
$dailyReport = $revStmt->fetchAll();

// Vehicle type breakdown
$typeStmt = $db->prepare(
    "SELECT vt.type_name, COUNT(*) as count, SUM(t.fee_total) as revenue
     FROM parking_transactions t
     JOIN vehicles v ON t.vehicle_id = v.vehicle_id
     JOIN vehicle_types vt ON v.type_id = vt.type_id
     WHERE t.check_out_time IS NOT NULL
       AND DATE(t.check_out_time) BETWEEN ? AND ?
     GROUP BY vt.type_id, vt.type_name ORDER BY revenue DESC"
);
$typeStmt->execute([$dateFrom, $dateTo]);
$typeReport = $typeStmt->fetchAll();

// Summary totals
$totalRevenue = array_sum(array_column($dailyReport, 'total_revenue'));
$totalTxn     = array_sum(array_column($dailyReport, 'total_transactions'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports — PU Parking</title>
<link rel="stylesheet" href="../css/style.css">
<style>
  .report-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
  .filter-bar { display:flex; gap:1rem; align-items:flex-end; background:var(--surface); padding:1.5rem; border-radius:12px; margin-bottom:2rem; box-shadow:var(--shadow); }
  .filter-bar label { font-size:.85rem; color:var(--muted); display:block; margin-bottom:.3rem; }
  .filter-bar input { padding:.6rem .8rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); }
  .summary-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.2rem; margin-bottom:2rem; }
  .report-table-wrap { background:var(--surface); border-radius:12px; padding:1.5rem; box-shadow:var(--shadow); margin-bottom:2rem; }
  .report-table-wrap h3 { color:var(--accent); margin-bottom:1rem; font-size:1rem; }
  @media print {
    .no-print { display:none !important; }
    body { background:#fff; color:#000; }
    .report-table-wrap { box-shadow:none; border:1px solid #ccc; }
  }
</style>
</head>
<body>
<div class="app-container">
      <aside class="sidebar no-print">
      <div class="brand" style="padding:.5rem .75rem 1.2rem;">
        <img src="../images/logo.svg" alt="PU Parking" style="width:100%;max-width:180px;display:block;margin:0 auto">
      </div>
      <nav>
        <a href="../index.php"> Dashboard</a>
        <a href="../pages/vehicles.php"> Vehicles</a>
        <a href="../pages/parking.php"> Parking</a>
        <a href="report.php" class="active"> Reports</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="../pages/users.php"> Users</a>
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
    <div class="report-header">
      <h1> Parking Reports</h1>
      <button class="btn btn-outline no-print" onclick="window.print()">🖨 Print Report</button>
    </div>

    <form class="filter-bar no-print" method="GET">
      <div><label>From Date</label><input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
      <div><label>To Date</label><input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>"></div>
      <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="summary-cards">
      <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value"><?= formatRupiah($totalRevenue) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?= number_format($totalTxn) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Period</div>
        <div class="stat-value" style="font-size:1rem"><?= $dateFrom ?> – <?= $dateTo ?></div>
      </div>
    </div>

    <div class="report-table-wrap">
      <h3>📅 Daily Revenue Report</h3>
      <table class="data-table">
        <thead><tr><th>Date</th><th>Transactions</th><th>Avg Duration (hrs)</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php if (empty($dailyReport)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted)">No data for selected period</td></tr>
          <?php else: foreach ($dailyReport as $row): ?>
          <tr>
            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
            <td><?= number_format($row['total_transactions']) ?></td>
            <td><?= number_format($row['avg_duration'],1) ?></td>
            <td><strong><?= formatRupiah($row['total_revenue']) ?></strong></td>
          </tr>
          <?php endforeach; endif; ?>
          <?php if ($totalRevenue > 0): ?>
          <tr style="background:var(--accent-muted);font-weight:600">
            <td>TOTAL</td><td><?= $totalTxn ?></td><td>-</td><td><?= formatRupiah($totalRevenue) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="report-table-wrap">
      <h3>🚗 Vehicle Type Breakdown</h3>
      <table class="data-table">
        <thead><tr><th>Vehicle Type</th><th>Transactions</th><th>Revenue</th><th>% Share</th></tr></thead>
        <tbody>
          <?php foreach ($typeReport as $row): 
            $pct = $totalRevenue > 0 ? round($row['revenue']/$totalRevenue*100,1) : 0;
          ?>
          <tr>
            <td><?= htmlspecialchars($row['type_name']) ?></td>
            <td><?= $row['count'] ?></td>
            <td><?= formatRupiah($row['revenue']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <div style="height:8px;width:<?= $pct ?>px;max-width:100px;background:var(--accent);border-radius:4px;min-width:4px"></div>
                <?= $pct ?>%
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
