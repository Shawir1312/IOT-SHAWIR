<?php
/**
 * ShawirIOT - Admin User Management
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$admin = currentUser();
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // EDIT USER (Role, Plan, Status)
    if ($action === 'edit_user') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $role     = sanitize($_POST['role'] ?? 'user');
        $planId   = (int)($_POST['plan_id'] ?? 1);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $name     = sanitize($_POST['name'] ?? '');

        // Prevent superadmin self-demotion
        if ($userId === $admin['id'] && $role !== 'superadmin' && $admin['role'] === 'superadmin') {
            flash('error', 'Superadmin tidak dapat mengubah role akun sendiri.');
        } else {
            DB::query(
                "UPDATE users SET name = ?, role = ?, plan_id = ?, is_active = ? WHERE id = ?",
                [$name, $role, $planId, $isActive, $userId]
            );
            flash('success', 'Data user berhasil diperbarui.');
        }
        redirect('users.php');
    }

    // ADJUST CREDITS
    if ($action === 'adjust_credits') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $type   = sanitize($_POST['type'] ?? 'admin_grant');
        $note   = sanitize($_POST['note'] ?? 'Penyesuaian oleh admin ' . $admin['name']);

        if ($amount === 0) {
            flash('error', 'Jumlah kredit tidak boleh 0.');
        } else {
            $realAmount = ($type === 'admin_deduct') ? -abs($amount) : abs($amount);
            adjustCredits($userId, $realAmount, $type, $note, $admin['id']);
            flash('success', "Berhasil menyesuaikan {$amount} kredit untuk user.");
        }
        redirect('users.php');
    }

    // RESET PASSWORD
    if ($action === 'reset_password') {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 8) {
            flash('error', 'Password minimal 8 karakter.');
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_ROUNDS]);
            DB::query("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId]);
            flash('success', 'Password user berhasil direset.');
        }
        redirect('users.php');
    }

    // DELETE USER
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $admin['id']) {
            flash('error', 'Tidak dapat menghapus akun sendiri!');
        } else {
            DB::query("DELETE FROM users WHERE id = ?", [$userId]);
            flash('success', 'User berhasil dihapus.');
        }
        redirect('users.php');
    }
}

// Search & Pagination
$search = sanitize($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$totalUsers = (int) DB::value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
$totalPages = ceil($totalUsers / $limit);

$users = DB::rows(
    "SELECT u.*, p.name as plan_name,
     (SELECT COUNT(*) FROM devices WHERE user_id = u.id AND is_active = 1) as device_count
     FROM users u JOIN plans p ON u.plan_id = p.id
     WHERE {$where} ORDER BY u.created_at DESC LIMIT {$limit} OFFSET {$offset}",
    $params
);

$plans = DB::rows("SELECT * FROM plans WHERE is_active = 1");
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Manajemen Pengguna — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body class="admin-layout">
<div class="app-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main-content">
    <header class="topbar admin-topbar">
      <div class="topbar-left">
        <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><i class="fas fa-users" style="color:#fb923c;margin-right:0.4rem"></i>Manajemen User</h1>
        <span class="badge badge-primary"><?= number_format($totalUsers) ?> Akun</span>
      </div>
      <div class="topbar-actions">
        <form method="GET" action="" style="display:flex;gap:0.4rem;max-width:100%">
          <input type="text" name="q" class="form-control" style="width:160px;padding:0.35rem 0.65rem;font-size:0.82rem"
            placeholder="Cari..." value="<?= sanitize($search) ?>">
          <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </form>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Paket</th>
                <th>Kredit</th>
                <th>Device</th>
                <th>Status</th>
                <th>Terdaftar</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr class="user-row">
                  <td>
                    <div class="user-info-cell">
                      <div class="user-avatar-sm"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                      <div>
                        <div class="user-name"><?= sanitize($u['name']) ?></div>
                        <div class="user-email"><?= sanitize($u['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge role-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span>
                  </td>
                  <td>
                    <span class="plan-badge plan-<?= strtolower($u['plan_name']) ?>"><?= sanitize($u['plan_name']) ?></span>
                  </td>
                  <td>
                    <strong style="color:var(--accent);font-size:0.95rem"><?= number_format($u['credits']) ?></strong>
                  </td>
                  <td>
                    <span class="badge badge-primary"><?= $u['device_count'] ?> unit</span>
                  </td>
                  <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-online' : 'badge-offline' ?>">
                      <span class="dot"></span><?= $u['is_active'] ? 'Aktif' : 'Banned' ?>
                    </span>
                  </td>
                  <td style="font-size:0.8rem;color:var(--text-muted)"><?= formatDate($u['created_at'], 'd M Y') ?></td>
                  <td>
                    <div style="display:flex;gap:0.35rem">
                      <!-- Edit Button -->
                      <button class="btn btn-secondary btn-sm btn-icon" title="Edit User"
                        onclick="openEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <!-- Give Credit Button -->
                      <button class="btn btn-primary btn-sm btn-icon" title="Beri/Kurangi Kredit"
                        style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%)"
                        onclick="openAdjustCredit(<?= $u['id'] ?>, '<?= sanitize($u['name']) ?>', <?= $u['credits'] ?>)">
                        <i class="fas fa-coins"></i>
                      </button>
                      <!-- Reset Password Button -->
                      <button class="btn btn-secondary btn-sm btn-icon" title="Reset Password"
                        onclick="openResetPass(<?= $u['id'] ?>, '<?= sanitize($u['name']) ?>')">
                        <i class="fas fa-key"></i>
                      </button>
                      <!-- Delete Button -->
                      <?php if ($u['id'] !== $admin['id']): ?>
                        <button class="btn btn-danger btn-sm btn-icon" title="Hapus User"
                          onclick="confirmDelete(<?= $u['id'] ?>, '<?= sanitize($u['name']) ?>')">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <div style="display:flex;justify-content:center;margin-top:1.5rem">
            <div class="pagination">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- MODAL: EDIT USER -->
<div class="modal-overlay" id="modal-edit-user">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-user-edit" style="color:var(--primary-light)"></i> Edit User</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">&times;</button>
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit-user-id">

      <div class="form-group">
        <label class="form-label">Nama Pengguna</label>
        <input type="text" name="name" id="edit-user-name" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Alamat Email</label>
        <input type="email" id="edit-user-email" class="form-control" disabled>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" id="edit-user-role" class="form-control">
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="superadmin">Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Paket Langganan</label>
          <select name="plan_id" id="edit-user-plan" class="form-control">
            <?php foreach ($plans as $p): ?>
              <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group" style="display:flex;align-items:center;gap:0.5rem">
        <input type="checkbox" name="is_active" id="edit-user-active" value="1"
          style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer">
        <label for="edit-user-active" style="font-size:0.875rem;cursor:pointer;margin:0">Akun Aktif (centang untuk membuka ban)</label>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-user')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: ADJUST CREDITS -->
<div class="modal-overlay" id="modal-credit">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" style="color:var(--accent)"><i class="fas fa-coins"></i> Kelola Kredit Pengguna</h3>
      <button class="modal-close" onclick="closeModal('modal-credit')">&times;</button>
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="adjust_credits">
      <input type="hidden" name="user_id" id="credit-user-id">

      <div style="background:rgba(255,255,255,0.03);padding:0.75rem 1rem;border-radius:var(--radius-md);margin-bottom:1rem">
        <div style="font-size:0.8rem;color:var(--text-muted)">Pengguna: <strong id="credit-user-name" style="color:var(--text-primary)"></strong></div>
        <div style="font-size:0.8rem;color:var(--text-muted)">Saldo Saat Ini: <strong id="credit-user-balance" style="color:var(--accent)"></strong> kredit</div>
      </div>

      <div class="form-group">
        <label class="form-label">Tipe Transaksi</label>
        <select name="type" class="form-control">
          <option value="admin_grant">Tambah Kredit (+ Grant)</option>
          <option value="admin_deduct">Kurangi Kredit (- Deduct)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Jumlah Kredit</label>
        <input type="number" name="amount" class="form-control" placeholder="cth: 100" min="1" required>
      </div>

      <div class="form-group">
        <label class="form-label">Catatan / Keterangan</label>
        <input type="text" name="note" class="form-control" placeholder="cth: Bonus registrasi / Pembayaran manual">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-credit')">Batal</button>
        <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%)">
          <i class="fas fa-check"></i> Proses Kredit
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: RESET PASSWORD -->
<div class="modal-overlay" id="modal-reset-pass">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-key" style="color:var(--warning)"></i> Reset Password Pengguna</h3>
      <button class="modal-close" onclick="closeModal('modal-reset-pass')">&times;</button>
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset-user-id">

      <p style="margin-bottom:1rem;font-size:0.85rem">Reset password untuk akun: <strong id="reset-user-name"></strong></p>

      <div class="form-group">
        <label class="form-label">Password Baru</label>
        <input type="password" name="new_password" class="form-control" placeholder="Minimal 8 karakter" required>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-reset-pass')">Batal</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: DELETE CONFIRMATION -->
<div class="modal-overlay" id="modal-delete-user">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" style="color:var(--danger)"><i class="fas fa-trash"></i> Hapus Akun User</h3>
      <button class="modal-close" onclick="closeModal('modal-delete-user')">&times;</button>
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="del-user-id">

      <p>Apakah Anda yakin ingin menghapus akun <strong id="del-user-name"></strong>?</p>
      <p style="font-size:0.8rem;color:var(--danger);margin-top:0.5rem">Semua device, dashboard, dan riwayat data milik user ini akan dihapus permanen!</p>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete-user')">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus Permanen</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openEditUser(user) {
  document.getElementById('edit-user-id').value    = user.id;
  document.getElementById('edit-user-name').value  = user.name;
  document.getElementById('edit-user-email').value = user.email;
  document.getElementById('edit-user-role').value  = user.role;
  document.getElementById('edit-user-plan').value  = user.plan_id;
  document.getElementById('edit-user-active').checked = (user.is_active == 1);
  openModal('modal-edit-user');
}

function openAdjustCredit(id, name, credits) {
  document.getElementById('credit-user-id').value = id;
  document.getElementById('credit-user-name').textContent = name;
  document.getElementById('credit-user-balance').textContent = Number(credits).toLocaleString();
  openModal('modal-credit');
}

function openResetPass(id, name) {
  document.getElementById('reset-user-id').value = id;
  document.getElementById('reset-user-name').textContent = name;
  openModal('modal-reset-pass');
}

function confirmDelete(id, name) {
  document.getElementById('del-user-id').value = id;
  document.getElementById('del-user-name').textContent = name;
  openModal('modal-delete-user');
}
</script>
</body>
</html>
