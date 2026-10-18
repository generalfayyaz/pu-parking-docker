<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_auth();
$user = current_user();
$stats  = getDashboardStats();
$active = getActiveParking();
$types  = getVehicleTypes();
$slots  = getAvailableSlots();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PU Parking — Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="toast" class="toast"></div>

<div class="app-container">
  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="brand" style="padding:.5rem .75rem 1.2rem;">
      <img src="images/logo.svg" alt="PU Parking"
           style="width:100%;max-width:180px;display:block;margin:0 auto">
    </div>
    <nav>
      <a href="index.php" class="active"> Dashboard</a>
      <a href="pages/vehicles.php"> Vehicles</a>
      <a href="pages/parking.php"> Parking</a>
      <a href="reports/report.php"> Reports</a>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="pages/users.php"> Users</a>
      <?php endif; ?>
    </nav>
    <div style="margin-top:auto;padding:.75rem;border-top:1px solid var(--border)">
      <div style="font-size:.78rem;color:var(--muted);margin-bottom:.5rem">
        Logged in as<br>
        <strong style="color:var(--text)"><?= htmlspecialchars($user['name']) ?></strong>
        <span style="color:var(--accent);font-size:.7rem;margin-left:.3rem"><?= $user['role'] ?></span>
      </div>
      <a href="auth/logout.php" class="btn btn-outline btn-sm w-full" style="text-align:center">Logout</a>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="main-content">
    <div class="page-header flex-between">
      <div>
        <h1>Dashboard</h1>
        <p class="text-muted"><?= date('l, d F Y — H:i') ?> WIB</p>
      </div>
      <div class="flex gap-1">
        <button class="btn btn-success" onclick="openCheckin()">+ Check-In</button>
        <button class="btn btn-outline" onclick="openCheckout()">⬆ Check-Out</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Currently Parked</div>
        <div class="stat-value"><?= $stats['active'] ?></div>
        <div class="stat-sub">vehicles inside</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Available Slots</div>
        <div class="stat-value"><?= $stats['available'] ?></div>
        <div class="stat-sub">of <?= $stats['total_slots'] ?> total</div>
      </div>
      <div class="stat-card warning">
        <div class="stat-label">Occupancy</div>
        <div class="stat-value"><?= $stats['total_slots'] > 0 ? round(($stats['total_slots'] - $stats['available']) / $stats['total_slots'] * 100) : 0 ?>%</div>
        <div class="stat-sub">occupancy rate</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Revenue Today</div>
        <div class="stat-value" style="font-size:1.2rem"><?= formatRupiah($stats['revenue_today']) ?></div>
        <div class="stat-sub"><?= $stats['txn_today'] ?> transactions</div>
      </div>
    </div>

    <!-- Slot Map -->
    <div class="card">
      <h3>🅿 Parking Slot Map</h3>
      <div id="slot-map">
        <?php
        $db = getDB();
        $allSlots = $db->query("SELECT slot_code, zone, slot_status FROM parking_slots ORDER BY zone, slot_code")->fetchAll();
        $zones = [];
        foreach ($allSlots as $s) $zones[$s['zone']][] = $s;
        foreach ($zones as $zone => $zSlots): ?>
        <div style="margin-bottom:1rem">
          <div style="font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem">Zone <?= $zone ?></div>
          <div class="slot-grid">
            <?php foreach ($zSlots as $sl):
              $cls = $sl['slot_status'];
              $icon = $cls === 'occupied' ? '🚗' : ($cls === 'maintenance' ? '🔧' : '');
            ?>
            <div class="slot-item <?= $cls ?>">
              <?php if ($icon): ?><div class="slot-icon"><?= $icon ?></div><?php endif; ?>
              <?= htmlspecialchars($sl['slot_code']) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:1.5rem;margin-top:1rem;font-size:.8rem">
        <span><span style="color:var(--accent-2)">■</span> Available</span>
        <span><span style="color:var(--danger)">■</span> Occupied</span>
        <span><span style="color:var(--muted)">■</span> Maintenance</span>
      </div>
    </div>

    <!-- Active Vehicles Table -->
    <div class="card">
      <div class="flex-between mb-2">
        <h3>🚗 Currently Parked</h3>
        <span class="badge badge-blue"><?= count($active) ?> active</span>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Plate</th><th>Type</th><th>Slot</th>
              <th>Check-In</th><th>Duration</th><th>Est. Fee</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($active)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No vehicles currently parked</td></tr>
            <?php else: foreach ($active as $v):
              $now     = new DateTime();
              $in      = new DateTime($v['check_in_time']);
              $diffMin = max(1, ceil(($now->getTimestamp() - $in->getTimestamp()) / 60));
              $diffHrs = max(1, ceil($diffMin / 60));
              $estFee  = $diffHrs * $v['rate_per_hour'];
            ?>
            <tr>
              <td><span class="plate"><?= htmlspecialchars($v['plate_number']) ?></span></td>
              <td><?= htmlspecialchars($v['type_name']) ?></td>
              <td><span class="badge badge-green"><?= htmlspecialchars($v['slot_code']) ?></span></td>
              <td class="text-mono" style="font-size:.82rem"><?= date('d/m H:i', strtotime($v['check_in_time'])) ?></td>
              <td class="text-warning"><?= $diffHrs ?>h <?= $diffMin % 60 ?>m</td>
              <td class="text-accent"><?= formatRupiah($estFee) ?></td>
              <td>
                <button class="btn btn-danger btn-sm"
                  onclick="doCheckout(<?= $v['transaction_id'] ?>, '<?= htmlspecialchars($v['plate_number']) ?>')">
                  Check-Out
                </button>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- ── Check-In Modal ── -->
<div id="checkin-modal" class="modal-overlay">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2>🚗 Vehicle Check-In</h2>
      <button class="btn btn-outline btn-sm" onclick="closeModal('checkin-modal')">✕</button>
    </div>

    <!-- Slot Selection -->
    <div class="mb-2">
      <div style="font-size:.82rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.6rem">
        Select Slot (click to choose)
      </div>
      <div class="slot-grid" id="modal-slots">
        <?php foreach ($slots as $s): ?>
        <div class="slot-item available" data-slot-id="<?= $s['slot_id'] ?>"
          onclick="selectSlot(this, <?= $s['slot_id'] ?>)">
          <?= htmlspecialchars($s['slot_code']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" id="selected_slot_id">
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label>Plate Number *</label>
        <input type="text" id="ci_plate" placeholder="e.g. B 1234 ABC" style="text-transform:uppercase">
      </div>
      <div class="form-group">
        <label>Vehicle Type *</label>
        <select id="ci_type">
          <option value="">— Select type —</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= $t['type_id'] ?>" data-rate="<?= $t['rate_per_hour'] ?>">
            <?= htmlspecialchars($t['type_name']) ?> — <?= formatRupiah($t['rate_per_hour']) ?>/hr
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="ci_rate_info" style="margin:.75rem 0;font-size:.83rem;color:var(--muted)"></div>
    <button class="btn btn-success w-full mt-2" onclick="submitCheckin()">✓ Confirm Check-In</button>
  </div>
</div>

<!-- ── Check-Out Modal ── -->
<div id="checkout-modal" class="modal-overlay">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2>⬆ Vehicle Check-Out</h2>
      <button class="btn btn-outline btn-sm" onclick="closeModal('checkout-modal')">✕</button>
    </div>
    <div class="form-group mb-2">
      <label>Transaction ID or Plate Number</label>
      <input type="text" id="co_input" placeholder="Enter transaction ID (e.g. 12)">
    </div>
    <div class="form-group mb-2">
      <label>Payment Method</label>
      <select id="co_payment">
        <option value="cash">Cash</option>
        <option value="card">Card</option>
        <option value="e-wallet">E-Wallet</option>
      </select>
    </div>
    <button class="btn btn-primary w-full" onclick="submitCheckout()">Process Check-Out</button>
    <div id="receipt-area" style="display:none"></div>
  </div>
</div>

<script>
// ── Modal helpers ──
function openCheckin()  { document.getElementById('checkin-modal').classList.add('open'); }
function openCheckout() { document.getElementById('checkout-modal').classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Slot selection ──
let selectedSlotId = null;
function selectSlot(el, slotId) {
  document.querySelectorAll('#modal-slots .slot-item').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');
  selectedSlotId = slotId;
  document.getElementById('selected_slot_id').value = slotId;
}

// ── Rate preview ──
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('ci_type').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const rate = opt.dataset.rate;
    const info = document.getElementById('ci_rate_info');
    info.textContent = rate ? `Rate: Rp ${Number(rate).toLocaleString('id-ID')}/hour (rounded up to full hours, min 1 hour)` : '';
  });
  document.getElementById('ci_plate').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
  });
});

// ── Toast ──
function showToast(msg, type = 'info') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `toast ${type} show`;
  setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Check-In Submit ──
async function submitCheckin() {
  const plate  = document.getElementById('ci_plate').value.trim();
  const typeId = document.getElementById('ci_type').value;
  const slotId = selectedSlotId;
  if (!plate || !typeId || !slotId) { showToast('Please fill all fields and select a slot', 'error'); return; }

  const fd = new FormData();
  fd.append('plate_number', plate);
  fd.append('type_id', typeId);
  fd.append('slot_id', slotId);

  const res  = await fetch('pages/checkin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(`✓ ${data.message} — Slot ${data.slot_code}`, 'success');
    closeModal('checkin-modal');
    setTimeout(() => location.reload(), 1200);
  } else {
    showToast(data.message, 'error');
  }
}

// ── Check-Out Submit ──
async function submitCheckout() {
  const txnId   = document.getElementById('co_input').value.trim();
  const payment = document.getElementById('co_payment').value;
  if (!txnId) { showToast('Enter a transaction ID', 'error'); return; }

  const fd = new FormData();
  fd.append('transaction_id', txnId);
  fd.append('payment_method', payment);

  const res  = await fetch('pages/checkout.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    document.getElementById('receipt-area').style.display = 'block';
    document.getElementById('receipt-area').innerHTML = `
      <div class="receipt mt-2">
        <div style="text-align:center;margin-bottom:.75rem;font-weight:600;color:var(--accent)">— PARKING RECEIPT —</div>
        <div class="receipt-row"><span>Plate</span><span>${data.plate}</span></div>
        <div class="receipt-row"><span>Slot</span><span>${data.slot_code}</span></div>
        <div class="receipt-row"><span>Check-In</span><span>${data.check_in}</span></div>
        <div class="receipt-row"><span>Check-Out</span><span>${data.check_out}</span></div>
        <div class="receipt-row"><span>Duration</span><span>${data.duration_hours} hour(s)</span></div>
        <div class="receipt-row"><span>Payment</span><span>${data.payment_method}</span></div>
        <div class="receipt-row"><span>TOTAL FEE</span><span>${data.fee_total}</span></div>
      </div>`;
    showToast('Check-out complete! Receipt generated.', 'success');
    setTimeout(() => location.reload(), 3000);
  } else {
    showToast(data.message, 'error');
  }
}

// ── Quick checkout from table ──
function doCheckout(txnId, plate) {
  document.getElementById('co_input').value = txnId;
  openCheckout();
}

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body>
</html>
