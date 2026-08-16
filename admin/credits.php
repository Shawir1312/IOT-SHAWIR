<?php
/**
 * ShawirIOT - Admin Credit Management & Logs
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$admin = currentUser();
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

// Handle Grant Credits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'grant_credits') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $type   = sanitize($_POST['type'] ?? 'admin_grant');
        $note   = sanitize($_POST['note'] ?? 'Bonus kredit dari admin');

        $user = DB::row("SELECT name, email FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            flash('error', 'User tidak ditemukan.');
        } elseif ($amount === 0) {
            flash('error', 'Jumlah kredit tidak boleh 0.');
        } else {
            $realAmount = ($type === 'admin_deduct') ? -abs($amount) : abs($amount);
            adjustCredits($userId, $realAmount, $type, $note, $admin['id']);
            flash('success', "Berhasil memproses " . abs($amount) . " kredit untuk {$user['name']} ({$user['email']}).");
        }
        redirect('credits.php');
    }
}

// Stats
$totalGranted = (int) DB::value("SELECT SUM(amount) FROM credit_transactions WHERE amount > 0");
$totalSpent   = (int) DB::value("SELECT SUM(ABS(amount)) FROM credit_transactions WHERE amount < 0");
$activeUsersCount = DB::count('users', 'is_active = 1');

// Filters & Pagination
$filterType = sanitize($_GET['type'] ?? '');
$filterUser = (int)($_GET['user_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;
$offset     = ($page - 1) * $limit;

$where = "1=1";
$params = [];
if (!empty($filterType)) {
    $where .= " AND ct.type = ?";
    $params[] = $filterType;
}
if ($filterUser > 0) {
    $where .= " AND ct.user_id = ?";
    $params[] = $filterUser;
}

$totalTxn   = (int) DB::value("SELECT COUNT(*) FROM credit_transactions ct WHERE {$where}", $params);
$totalPages = ceil($totalTxn / $limit);

$transactions = DB::rows(
    "SELECT ct.*, u.name as user_name, u.email as user_email, adm.name as admin_name
     FROM credit_transactions ct
     JOIN users u ON ct.user_id = u.id
     LEFT JOIN users adm ON ct.admin_id = adm.id
     WHERE {$where}
     ORDER BY ct.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$allUsers = DB::rows("SELECT id, name, email, credits FROM users WHERE is_active = 1 ORDER BY name ASC");
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Kredit — <?= $platformName ?> Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <h1 class="topbar-title"><i class="fas fa-coins" style="color:var(--accent);margin-right:0.4rem"></i>Sistem Kredit</h1>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%)"
          onclick="document.getElementById('grant-card').scrollIntoView({behavior:'smooth'})">
          <i class="fas fa-plus"></i> <span>Beri Kredit</span>
        </button>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- CREDIT SUMMARY STATS -->
      <div class="admin-stat-grid">
        <div class="admin-stat s-credits">
          <div class="stat-lbl">Total Kredit Diberikan</div>
          <div class="stat-num" style="color:var(--success)">+<?= number_format($totalGranted) ?></div>
          <div class="stat-trend"><i class="fas fa-arrow-up"></i> Top-up / Grant</div>
        </div>
        <div class="admin-stat s-data">
          <div class="stat-lbl">Total Kredit Terpakai</div>
          <div class="stat-num" style="color:var(--danger)">-<?= number_format($totalSpent) ?></div>
          <div class="stat-trend"><i class="fas fa-arrow-down"></i> Upgrade paket</div>
        </div>
        <div class="admin-stat s-users">
          <div class="stat-lbl">User Aktif</div>
          <div class="stat-num"><?= number_format($activeUsersCount) ?></div>
          <div class="stat-trend"><i class="fas fa-users"></i> Siap menerima kredit</div>
        </div>
      </div>

      <!-- GRANT CREDIT FORM -->
      <div class="card mb-2" id="grant-card">
        <div class="card-header">
          <h3 class="card-title" style="color:var(--accent)"><i class="fas fa-gift"></i> Berikan / Kurangi Kredit Pengguna</h3>
        </div>
        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="grant_credits">
          <div class="grid-3col mb-2">
            <div class="form-group" style="margin:0">
              <label class="form-label">Pilih Pengguna *</label>
              <select name="user_id" class="form-control" required>
                <option value="">-- Pilih User --</option>
                <?php foreach ($allUsers as $au): ?>
                  <option value="<?= $au['id'] ?>" <?= ($filterUser === $au['id']) ? 'selected' : '' ?>>
                    <?= sanitize($au['name']) ?> (<?= sanitize($au['email']) ?>) — [<?= number_format($au['credits']) ?> kredit]
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label">Aksi</label>
              <select name="type" class="form-control">
                <option value="admin_grant">Tambah (+)</option>
                <option value="admin_deduct">Kurangi (-)</option>
              </select>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label">Jumlah Kredit *</label>
              <input type="number" name="amount" class="form-control" placeholder="100" min="1" required>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label">Catatan</label>
              <input type="text" name="note" class="form-control" placeholder="cth: Pembayaran paket Pro">
            </div>
            <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%)">
              <i class="fas fa-paper-plane"></i> Kirim
            </button>
          </div>
        </form>
      </div>

      <!-- TRANSACTION LOGS TABLE -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list"></i> Riwayat Semua Transaksi Kredit</h3>
          <!-- Filters -->
          <form method="GET" action="" style="display:flex;gap:0.5rem">
            <select name="type" class="form-control" style="font-size:0.8rem;padding:0.35rem 0.65rem" onchange="this.form.submit()">
              <option value="">Semua Tipe</option>
              <option value="admin_grant"   <?= $filterType==='admin_grant'?'selected':'' ?>>Admin Grant</option>
              <option value="admin_deduct"  <?= $filterType==='admin_deduct'?'selected':'' ?>>Admin Deduct</option>
              <option value="plan_upgrade"  <?= $filterType==='plan_upgrade'?'selected':'' ?>>Plan Upgrade</option>
              <option value="topup"         <?= $filterType==='topup'?'selected':'' ?>>Top Up</option>
            </select>
          </form>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Tipe</th>
                <th>Jumlah Kredit</th>
                <th>Keterangan</th>
                <th>Diproses Oleh</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($transactions)): ?>
                <tr>
                  <td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem">Belum ada riwayat transaksi</td>
                </tr>
              <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                  <tr>
                    <td style="font-size:0.8rem;color:var(--text-muted)"><?= formatDate($t['created_at'], 'd M Y H:i:s') ?></td>
                    <td>
                      <strong><?= sanitize($t['user_name']) ?></strong>
                      <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($t['user_email']) ?></div>
                    </td>
                    <td>
                      <span class="txn-type txn-<?= $t['type'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $t['type'])) ?>
                      </span>
                    </td>
                    <td class="txn-amount <?= $t['amount'] > 0 ? 'positive' : 'negative' ?>">
                      <?= $t['amount'] > 0 ? '+' : '' ?><?= number_format($t['amount']) ?>
                    </td>
                    <td style="font-size:0.85rem"><?= sanitize($t['note'] ?: '-') ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted)"><?= sanitize($t['admin_name'] ?: 'Sistem') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <div style="display:flex;justify-content:center;margin-top:1.5rem">
            <div class="pagination">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($filterType) ?>&user_id=<?= $filterUser ?>"
                   class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
</body>
</html>
