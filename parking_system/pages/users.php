<?php
// ============================================================
//  User Management (Admin only)
//  File: pages/users.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();
$user = current_user();
$db   = getDB();

$success = '';
$error   = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD USER
    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'operator';

        if (!$username || !$full_name || !$password) {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $check = $db->prepare("SELECT operator_id FROM operators WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = "Username '$username' already exists.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare(
                        "INSERT INTO operators (username, password, full_name, role) VALUES (?, ?, ?, ?)"
                    )->execute([$username, $hash, $full_name, $role]);
                    $success = "User '$username' added successfully.";
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }

    // EDIT USER
    if ($action === 'edit') {
        $id        = (int)($_POST['operator_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = $_POST['role'] ?? 'operator';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password  = $_POST['password'] ?? '';

        // Prevent changing own role OR deactivating own account (either condition blocks)
        if ($id === (int)$user['id'] && $role !== $user['role']) {
            $error = 'You cannot change your own role.';
        } elseif ($id === (int)$user['id'] && !$is_active) {
            $error = 'You cannot deactivate your own account.';
        } elseif (!$full_name) {
            $error = 'Full name is required.';
        } else {
            try {
                if ($password && strlen($password) >= 6) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare(
                        "UPDATE operators SET full_name=?, role=?, is_active=?, password=? WHERE operator_id=?"
                    )->execute([$full_name, $role, $is_active, $hash, $id]);
                } else {
                    $db->prepare(
                        "UPDATE operators SET full_name=?, role=?, is_active=? WHERE operator_id=?"
                    )->execute([$full_name, $role, $is_active, $id]);
                }
                $success = 'User updated successfully.';
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }

    // DELETE USER
    if ($action === 'delete') {
        $id = (int)($_POST['operator_id'] ?? 0);
        if ($id === (int)$user['id']) {
            $error = 'You cannot delete your own account.';
        } else {
            try {
                $db->prepare("DELETE FROM operators WHERE operator_id = ?")->execute([$id]);
                $success = 'User deleted.';
            } catch (Exception $e) {
                $error = 'Cannot delete user — they may have existing transactions.';
            }
        }
    }
}

// ── Fetch all users ──────────────────────────────────────────
$operators = $db->query(
    "SELECT operator_id, username, full_name, role, is_active, created_at
     FROM operators ORDER BY created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PU Parking — User Management</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div id="toast" class="toast"></div>

<div class="app-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand" style="padding:.5rem .75rem 1.2rem;">
      <img src="../images/logo.svg" alt="PU Parking"
           style="width:100%;max-width:180px;display:block;margin:0 auto">
    </div>
    <nav>
      <a href="../index.php"> Dashboard</a>
      <a href="vehicles.php"> Vehicles</a>
      <a href="parking.php"> Parking</a>
      <a href="../reports/report.php"> Reports</a>
      <a href="users.php" class="active"> Users</a>
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

  <!-- Main -->
  <main class="main-content">
    <div class="page-header flex-between">
      <div>
        <h1>User Management</h1>
        <p class="text-muted">Manage operator accounts and access</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('add-modal')">+ Add User</button>
    </div>

    <?php if ($success): ?>
    <div style="background:var(--accent-2-muted);border:1px solid var(--accent-2);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:var(--accent-2);font-size:.88rem">
      ✓ <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div style="background:var(--danger-muted);border:1px solid var(--danger);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:var(--danger);font-size:.88rem">
      ⚠ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Username</th>
              <th>Full Name</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($operators as $op): ?>
            <tr>
              <td class="text-muted"><?= $op['operator_id'] ?></td>
              <td><strong><?= htmlspecialchars($op['username']) ?></strong>
                <?php if ($op['operator_id'] == $user['id']): ?>
                  <span style="font-size:.7rem;color:var(--accent);margin-left:.3rem">(you)</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($op['full_name']) ?></td>
              <td>
                <span class="badge <?= $op['role'] === 'admin' ? 'badge-blue' : 'badge-green' ?>">
                  <?= $op['role'] ?>
                </span>
              </td>
              <td>
                <?php if ($op['is_active']): ?>
                  <span style="color:var(--success);font-size:.82rem">● Active</span>
                <?php else: ?>
                  <span style="color:var(--danger);font-size:.82rem">● Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-muted" style="font-size:.82rem"><?= date('d M Y', strtotime($op['created_at'])) ?></td>
              <td>
                <button class="btn btn-outline btn-sm"
                  onclick='openEdit(<?= json_encode([
                    "id"        => $op["operator_id"],
                    "username"  => $op["username"],
                    "full_name" => $op["full_name"],
                    "role"      => $op["role"],
                    "is_active" => $op["is_active"]
                  ]) ?>)'>Edit</button>
                <?php if ($op['operator_id'] != $user['id']): ?>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete user <?= htmlspecialchars($op['username']) ?>?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add User Modal -->
<div id="add-modal" class="modal-overlay">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2>Add New User</h2>
      <button class="btn btn-outline btn-sm" onclick="closeModal('add-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" placeholder="e.g. operator2" required>
        </div>
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" placeholder="e.g. Budi Santoso" required>
        </div>
        <div class="form-group">
          <label>Password * <span style="color:var(--muted);font-size:.78rem">(min 6 chars)</span></label>
          <input type="password" name="password" placeholder="Enter password" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role">
            <option value="operator">Operator</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full mt-2">Add User</button>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div id="edit-modal" class="modal-overlay">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2>Edit User</h2>
      <button class="btn btn-outline btn-sm" onclick="closeModal('edit-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="operator_id" id="edit_id">
      <div class="form-group mb-1">
        <label>Username</label>
        <input type="text" id="edit_username" disabled
               style="opacity:.5;cursor:not-allowed;background:var(--surface-2)">
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" id="edit_fullname" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" id="edit_role">
            <option value="operator">Operator</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>New Password <span style="color:var(--muted);font-size:.78rem">(leave blank to keep)</span></label>
          <input type="password" name="password" placeholder="Leave blank to keep current">
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:.6rem;padding-top:1.6rem">
          <input type="checkbox" name="is_active" id="edit_active" value="1" style="width:16px;height:16px">
          <label for="edit_active" style="margin:0;cursor:pointer">Active account</label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full mt-2">Save Changes</button>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEdit(op) {
  document.getElementById('edit_id').value       = op.id;
  document.getElementById('edit_username').value = op.username;
  document.getElementById('edit_fullname').value = op.full_name;
  document.getElementById('edit_role').value     = op.role;
  document.getElementById('edit_active').checked = op.is_active == 1;
  openModal('edit-modal');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body>
</html>
