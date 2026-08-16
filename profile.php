<?php
/**
 * ShawirIOT - User Profile, Credits & Plan Upgrade
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user  = currentUser();
$plan  = getUserPlan($user['id']);
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

// Handle Profile & Credit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // UPDATE PROFILE
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        if (strlen($name) < 2) {
            flash('error', 'Nama minimal 2 karakter.');
        } else {
            DB::query("UPDATE users SET name = ? WHERE id = ?", [$name, $user['id']]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Profil berhasil diperbarui.');
        }
        redirect(PLATFORM_URL . '/profile.php');
    }

    // CHANGE PASSWORD
    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $dbUser = DB::row("SELECT password FROM users WHERE id = ?", [$user['id']]);
        if (!password_verify($oldPass, $dbUser['password'])) {
            flash('error', 'Password saat ini salah.');
        } elseif ($newPass !== $confirm) {
            flash('error', 'Konfirmasi password baru tidak cocok.');
        } else {
            $pwErrors = validatePassword($newPass);
            if (!empty($pwErrors)) {
                flash('error', implode(', ', $pwErrors));
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_ROUNDS]);
                DB::query("UPDATE users SET password = ? WHERE id = ?", [$hash, $user['id']]);
                flash('success', 'Password berhasil diubah.');
            }
        }
        redirect(PLATFORM_URL . '/profile.php');
    }

    // UPGRADE PLAN
    if ($action === 'upgrade_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $res = upgradePlan($user['id'], $planId);
        if ($res['success']) {
            flash('success', $res['message']);
        } else {
            flash('error', $res['message']);
        }
        redirect(PLATFORM_URL . '/profile.php');
    }
}

// Fetch all available plans
$plans = DB::rows("SELECT * FROM plans WHERE is_active = 1 ORDER BY credits_required ASC");

// Fetch credit transactions
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$totalTxn = DB::count('credit_transactions', 'user_id = ?', [$user['id']]);
$totalPages = ceil($totalTxn / $limit);

$transactions = DB::rows(
    "SELECT * FROM credit_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$user['id'], $limit, $offset]
);
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil & Kredit — <?= $platformName ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <header class="topbar">
      <h1 class="topbar-title">
        <i class="fas fa-user-circle" style="color:var(--primary-light);margin-right:0.4rem"></i>
        Profil & Sistem Kredit
      </h1>
      <div class="topbar-actions">
        <div class="credit-badge"><i class="fas fa-coins"></i> <?= number_format($user['credits']) ?> kredit</div>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- PROFILE & STATS -->
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:1.5rem">
        <!-- USER CARD -->
        <div class="card" style="text-align:center">
          <div style="width:80px;height:80px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;margin:0 auto 1rem;box-shadow:var(--shadow-glow)">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
          <h2 style="font-size:1.3rem;margin-bottom:0.25rem"><?= sanitize($user['name']) ?></h2>
          <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem"><?= sanitize($user['email']) ?></p>

          <div style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(99,102,241,0.1);padding:0.4rem 1rem;border-radius:99px;margin-bottom:1.25rem">
            <span style="font-size:0.8rem;color:var(--text-muted)">Paket:</span>
            <strong style="color:var(--primary-light)"><?= $plan['name'] ?></strong>
          </div>

          <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-md);padding:1rem;margin-bottom:1rem">
            <div style="font-size:0.75rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:0.05em">Saldo Kredit</div>
            <div style="font-size:2.2rem;font-weight:900;color:var(--accent);line-height:1.2;margin:0.25rem 0"><?= number_format($user['credits']) ?></div>
            <small style="color:var(--text-muted)">Kredit diberikan oleh admin</small>
          </div>

          <div style="font-size:0.78rem;color:var(--text-muted)">
            Bergabung sejak: <?= formatDate($user['created_at'], 'd M Y') ?>
          </div>
        </div>

        <!-- EDIT PROFILE & PASSWORD TABS -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-edit"></i> Pengaturan Akun</h3>
          </div>

          <!-- Profile Form -->
          <form method="POST" action="" style="margin-bottom:2rem">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <h4 style="font-size:0.95rem;margin-bottom:1rem;color:var(--primary-light)"><i class="fas fa-id-card"></i> Informasi Akun</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($user['name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Alamat Email</label>
                <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                <div class="form-hint">Email tidak dapat diubah</div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Nama</button>
          </form>

          <hr class="divider">

          <!-- Change Password Form -->
          <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <h4 style="font-size:0.95rem;margin-bottom:1rem;color:var(--primary-light)"><i class="fas fa-key"></i> Ubah Password</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem">
              <div class="form-group">
                <label class="form-label">Password Lama</label>
                <input type="password" name="old_password" class="form-control" required placeholder="••••••••">
              </div>
              <div class="form-group">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control" required placeholder="Min. 8 karakter">
              </div>
              <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Ulangi baru">
              </div>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-lock"></i> Perbarui Password</button>
          </form>
        </div>
      </div>

      <!-- UPGRADE PLAN SECTION -->
      <div class="card mb-3">
        <div class="card-header">
          <div>
            <h3 class="card-title"><i class="fas fa-cubes"></i> Pilihan Paket Langganan</h3>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-top:0.2rem">Tukar kredit Anda untuk mengaktifkan paket dengan fitur lebih banyak.</p>
          </div>
        </div>

        <div class="plan-grid">
          <?php foreach ($plans as $p): ?>
            <?php
              $isCurrent = ($p['id'] == $user['plan_id']);
              $isPopular = ($p['slug'] === 'pro');
              $canAfford = ($user['credits'] >= $p['credits_required']);
            ?>
            <div class="plan-card <?= $isPopular ? 'popular' : '' ?>" style="<?= $isCurrent ? 'border-color:var(--success);' : '' ?>">
              <div class="plan-name"><?= sanitize($p['name']) ?></div>
              <?php if ($isCurrent): ?>
                <span class="badge badge-online mb-1"><span class="dot"></span> Paket Aktif</span>
              <?php endif; ?>
              <div class="plan-credits">
                <?= $p['credits_required'] ?> <span>kredit</span>
              </div>
              <p style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($p['description']) ?></p>

              <ul class="plan-features">
                <li><?= $p['max_devices'] === 9999 ? 'Unlimited' : $p['max_devices'] ?> Device</li>
                <li><?= $p['max_widgets_per_device'] === 9999 ? 'Unlimited' : $p['max_widgets_per_device'] ?> Widget/Device</li>
                <li>Histori Data <?= $p['history_days'] ?> Hari</li>
                <li>REST & WebSocket API</li>
              </ul>

              <?php if ($isCurrent): ?>
                <button class="btn btn-secondary btn-block btn-sm" disabled>Paket Anda Saat Ini</button>
              <?php else: ?>
                <form method="POST" action="" onsubmit="return confirm('Gunakan <?= $p['credits_required'] ?> kredit untuk upgrade ke paket <?= sanitize($p['name']) ?>?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="upgrade_plan">
                  <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn <?= $isPopular ? 'btn-primary' : 'btn-secondary' ?> btn-block btn-sm"
                    <?= !$canAfford ? 'disabled' : '' ?>>
                    <?= $canAfford ? 'Tukar ' . $p['credits_required'] . ' Kredit' : 'Kredit Tidak Cukup' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- TRANSACTION HISTORY -->
      <div class="card" id="transactions">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-receipt"></i> Riwayat Transaksi Kredit</h3>
          <span style="font-size:0.8rem;color:var(--text-muted)">Total <?= $totalTxn ?> transaksi</span>
        </div>

        <?php if (empty($transactions)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-coins"></i></div>
            <h3>Belum Ada Transaksi</h3>
            <p>Admin dapat memberikan kredit ke akun Anda untuk digunakan upgrade paket.</p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Tipe</th>
                  <th>Jumlah</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transactions as $t): ?>
                  <tr>
                    <td style="font-size:0.8rem;color:var(--text-muted)"><?= formatDate($t['created_at'], 'd M Y H:i:s') ?></td>
                    <td>
                      <span class="txn-type txn-<?= $t['type'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $t['type'])) ?>
                      </span>
                    </td>
                    <td class="txn-amount <?= $t['amount'] > 0 ? 'positive' : 'negative' ?>">
                      <?= $t['amount'] > 0 ? '+' : '' ?><?= number_format($t['amount']) ?> kredit
                    </td>
                    <td style="font-size:0.85rem"><?= sanitize($t['note'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:center;margin-top:1.5rem">
              <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a href="?page=<?= $i ?>#transactions" class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
</body>
</html>
