<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_auth();
$user = current_user();
$db = getDB();
$vehicles = $db->query(
  "SELECT v.vehicle_id, v.plate_number, vt.type_name, v.brand, v.model, v.color,
            o.full_name AS owner_name, o.phone,
            (SELECT COUNT(*) FROM parking_transactions t WHERE t.vehicle_id = v.vehicle_id) AS trip_count,
            (SELECT COUNT(*) FROM parking_transactions t WHERE t.vehicle_id = v.vehicle_id AND t.check_out_time IS NULL) AS is_parked,
            (SELECT MAX(check_in_time) FROM parking_transactions WHERE vehicle_id = v.vehicle_id) AS last_seen
     FROM vehicles v
     JOIN vehicle_types vt ON v.type_id = vt.type_id
     LEFT JOIN owners o ON v.owner_id = o.owner_id
     ORDER BY v.created_at DESC"
)->fetchAll();
$types = getVehicleTypes();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Vehicles — PU Parking</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .actions-cell {
      display: flex;
      gap: .4rem;
    }

    /* ── Edit Modal extras ── */
    .modal-wide {
      max-width: 560px;
    }

    .modal-section-title {
      font-size: .72rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .1em;
      margin: 1.2rem 0 .6rem;
      padding-bottom: .4rem;
      border-bottom: 1px solid var(--border);
    }

    /* ── Confirm Delete Dialog ── */
    .confirm-dialog {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 8px 40px rgba(0, 0, 0, .6);
      text-align: center;
    }

    .confirm-dialog .icon {
      font-size: 2.5rem;
      margin-bottom: .75rem;
    }

    .confirm-dialog h3 {
      font-size: 1.1rem;
      color: var(--text);
      margin-bottom: .5rem;
    }

    .confirm-dialog p {
      color: var(--muted);
      font-size: .88rem;
      margin-bottom: 1.5rem;
    }

    .confirm-dialog .plate-tag {
      display: inline-block;
      font-family: var(--mono);
      font-size: .95rem;
      font-weight: 600;
      background: rgba(224, 92, 92, .12);
      color: var(--danger);
      border: 1px solid rgba(224, 92, 92, .25);
      padding: .25rem .75rem;
      border-radius: 6px;
      margin-bottom: 1rem;
    }

    .btn-row {
      display: flex;
      gap: .75rem;
      justify-content: center;
    }

    /* Parked indicator chip */
    .chip-parked {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      background: var(--danger-muted);
      color: var(--danger);
      border: 1px solid rgba(224, 92, 92, .2);
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 600;
      padding: .18rem .55rem;
    }

    .chip-parked::before {
      content: '●';
      font-size: .55rem;
    }

    /* Disabled button look */
    .btn:disabled {
      opacity: .4;
      cursor: not-allowed;
      pointer-events: none;
    }
  </style>
</head>

<body>
  <div id="toast" class="toast"></div>
  <div class="app-container">
        <aside class="sidebar">
      <div class="brand" style="padding:.5rem .75rem 1.2rem;">
        <img src="../images/logo.svg" alt="PU Parking" style="width:100%;max-width:180px;display:block;margin:0 auto">
      </div>
      <nav>
        <a href="../index.php"> Dashboard</a>
        <a href="vehicles.php" class="active"> Vehicles</a>
        <a href="parking.php"> Parking</a>
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
        <div>
          <h1>Vehicle Registry</h1>
          <p class="text-muted">Manage registered vehicles — <?= count($vehicles) ?> total</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">+ Add Vehicle</button>
      </div>

      <div class="card">
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Plate</th>
                <th>Type</th>
                <th>Brand / Model</th>
                <th>Color</th>
                <th>Owner</th>
                <th>Visits</th>
                <th>Last Seen</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vehicles as $v):
                $parked = (int) $v['is_parked'] > 0; ?>
                <tr>
                  <td><span class="plate"><?= htmlspecialchars($v['plate_number']) ?></span></td>
                  <td><span class="badge badge-blue"><?= htmlspecialchars($v['type_name']) ?></span></td>
                  <td><?= htmlspecialchars(trim($v['brand'] . ' ' . $v['model'])) ?: '<span class="text-muted">—</span>' ?>
                  </td>
                  <td><?= htmlspecialchars($v['color'] ?? '—') ?></td>
                  <td><?= $v['owner_name'] ? htmlspecialchars($v['owner_name']) : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td class="text-mono"><?= $v['trip_count'] ?></td>
                  <td class="text-muted" style="font-size:.82rem">
                    <?= $v['last_seen'] ? date('d/m/Y H:i', strtotime($v['last_seen'])) : '—' ?></td>
                  <td>
                    <?php if ($parked): ?>
                      <span class="chip-parked">Parked</span>
                    <?php else: ?>
                      <span class="badge badge-gray">Out</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="actions-cell">
                      <button class="btn btn-outline btn-sm" title="Edit vehicle"
                        onclick="openEditModal(<?= $v['vehicle_id'] ?>)">
                         Edit
                      </button>
                      <button class="btn btn-danger btn-sm"
                        title="<?= $parked ? 'Cannot delete while parked' : 'Delete vehicle' ?>" <?= $parked ? 'disabled' : '' ?>
                        onclick="openDeleteConfirm(<?= $v['vehicle_id'] ?>, '<?= htmlspecialchars($v['plate_number'], ENT_QUOTES) ?>')">
                        🗑 Delete
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($vehicles)): ?>
                <tr>
                  <td colspan="9" style="text-align:center;color:var(--muted);padding:2rem">No vehicles registered yet
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- ══════════════════════════════════════════
     ADD VEHICLE MODAL
═══════════════════════════════════════════ -->
  <div id="add-modal" class="modal-overlay">
    <div class="modal modal-wide">
      <div class="flex-between mb-2">
        <h2>+ Add Vehicle</h2>
        <button class="btn btn-outline btn-sm" onclick="closeModal('add-modal')">✕</button>
      </div>
      <div class="modal-section-title">Vehicle Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Plate Number *</label>
          <input type="text" id="a_plate" placeholder="B 1234 ABC" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label>Vehicle Type *</label>
          <select id="a_type">
            <option value="">— Select —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Brand</label>
          <input type="text" id="a_brand" placeholder="Toyota">
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" id="a_model" placeholder="Avanza">
        </div>
        <div class="form-group">
          <label>Color</label>
          <input type="text" id="a_color" placeholder="Silver">
        </div>
      </div>
      <div class="modal-section-title">Owner Info (optional)</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Owner Name</label>
          <input type="text" id="a_owner" placeholder="Full name">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" id="a_phone" placeholder="08123456789">
        </div>
      </div>
      <button class="btn btn-primary w-full mt-2" onclick="saveVehicle()">💾 Save Vehicle</button>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
     EDIT VEHICLE MODAL
═══════════════════════════════════════════ -->
  <div id="edit-modal" class="modal-overlay">
    <div class="modal modal-wide">
      <div class="flex-between mb-2">
        <h2> Edit Vehicle</h2>
        <button class="btn btn-outline btn-sm" onclick="closeModal('edit-modal')">✕</button>
      </div>
      <input type="hidden" id="e_id">
      <div class="modal-section-title">Vehicle Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Plate Number *</label>
          <input type="text" id="e_plate" placeholder="B 1234 ABC" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label>Vehicle Type *</label>
          <select id="e_type">
            <option value="">— Select —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Brand</label>
          <input type="text" id="e_brand" placeholder="Toyota">
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" id="e_model" placeholder="Avanza">
        </div>
        <div class="form-group">
          <label>Color</label>
          <input type="text" id="e_color" placeholder="Silver">
        </div>
      </div>
      <div class="modal-section-title">Owner Info</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Owner Name</label>
          <input type="text" id="e_owner" placeholder="Full name">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" id="e_phone" placeholder="08123456789">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Email</label>
          <input type="email" id="e_email" placeholder="owner@email.com">
        </div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1.25rem">
        <button class="btn btn-outline w-full" onclick="closeModal('edit-modal')">Cancel</button>
        <button class="btn btn-primary w-full" onclick="saveEdit()">✓ Update Vehicle</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════════ -->
  <div id="delete-modal" class="modal-overlay">
    <div class="confirm-dialog">
      <div class="icon">⚠️</div>
      <h3>Delete Vehicle?</h3>
      <p>This will permanently remove the vehicle and all its parking history. This action cannot be undone.</p>
      <div class="plate-tag" id="del-plate-tag">—</div>
      <br>
      <div class="btn-row">
        <button class="btn btn-outline" onclick="closeModal('delete-modal')" style="min-width:110px">Cancel</button>
        <button class="btn btn-danger" id="confirm-delete-btn" style="min-width:110px">🗑 Delete</button>
      </div>
    </div>
  </div>

  <script>
    // ── Utilities ─────────────────────────────────────────────
    function showToast(msg, type = 'info') {
      const t = document.getElementById('toast');
      t.textContent = msg; t.className = `toast ${type} show`;
      setTimeout(() => t.classList.remove('show'), 3500);
    }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function openModal(id) { document.getElementById(id).classList.add('open'); }

    // Close on backdrop
    document.querySelectorAll('.modal-overlay').forEach(m => {
      m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });

    // Auto-uppercase plate inputs
    ['a_plate', 'e_plate'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
    });

    // ── ADD VEHICLE ───────────────────────────────────────────
    function openAddModal() {
      ['a_plate', 'a_brand', 'a_model', 'a_color', 'a_owner', 'a_phone'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('a_type').value = '';
      openModal('add-modal');
    }

    async function saveVehicle() {
      const fd = new FormData();
      fd.append('plate_number', document.getElementById('a_plate').value.trim());
      fd.append('type_id', document.getElementById('a_type').value);
      fd.append('brand', document.getElementById('a_brand').value.trim());
      fd.append('model', document.getElementById('a_model').value.trim());
      fd.append('color', document.getElementById('a_color').value.trim());
      fd.append('owner_name', document.getElementById('a_owner').value.trim());
      fd.append('owner_phone', document.getElementById('a_phone').value.trim());

      const res = await fetch('add_vehicle.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('Vehicle added!', 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(data.message, 'error');
      }
    }

    // ── EDIT VEHICLE ──────────────────────────────────────────
    async function openEditModal(vehicleId) {
      // Fetch current data
      const res = await fetch(`edit_vehicle.php?id=${vehicleId}`);
      const data = await res.json();
      if (!data.success) { showToast('Could not load vehicle data', 'error'); return; }

      const d = data.data;
      document.getElementById('e_id').value = d.vehicle_id;
      document.getElementById('e_plate').value = d.plate_number;
      document.getElementById('e_type').value = d.type_id;
      document.getElementById('e_brand').value = d.brand || '';
      document.getElementById('e_model').value = d.model || '';
      document.getElementById('e_color').value = d.color || '';
      document.getElementById('e_owner').value = d.owner_name || '';
      document.getElementById('e_phone').value = d.owner_phone || '';
      document.getElementById('e_email').value = d.owner_email || '';

      openModal('edit-modal');
    }

    async function saveEdit() {
      const fd = new FormData();
      fd.append('vehicle_id', document.getElementById('e_id').value);
      fd.append('plate_number', document.getElementById('e_plate').value.trim());
      fd.append('type_id', document.getElementById('e_type').value);
      fd.append('brand', document.getElementById('e_brand').value.trim());
      fd.append('model', document.getElementById('e_model').value.trim());
      fd.append('color', document.getElementById('e_color').value.trim());
      fd.append('owner_name', document.getElementById('e_owner').value.trim());
      fd.append('owner_phone', document.getElementById('e_phone').value.trim());
      fd.append('owner_email', document.getElementById('e_email').value.trim());

      const res = await fetch('edit_vehicle.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('✓ Vehicle updated!', 'success');
        closeModal('edit-modal');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'error');
      }
    }

    // ── DELETE VEHICLE ────────────────────────────────────────
    let pendingDeleteId = null;

    function openDeleteConfirm(vehicleId, plate) {
      pendingDeleteId = vehicleId;
      document.getElementById('del-plate-tag').textContent = plate;
      document.getElementById('confirm-delete-btn').disabled = false;
      openModal('delete-modal');
    }

    document.getElementById('confirm-delete-btn').addEventListener('click', async function () {
      if (!pendingDeleteId) return;
      this.disabled = true;
      this.textContent = 'Deleting…';

      const fd = new FormData();
      fd.append('vehicle_id', pendingDeleteId);

      const res = await fetch('delete_vehicle.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        showToast('Vehicle deleted.', 'success');
        closeModal('delete-modal');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message, 'error');
        this.disabled = false;
        this.textContent = '🗑 Delete';
      }
    });
  </script>
</body>

</html>