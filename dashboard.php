<?php
/**
 * ShawirIOT - Main Dashboard (User Home)
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isset($_GET['device'])) {
    include __DIR__ . '/device_dashboard.php';
    exit;
}

$user  = currentUser();
$plan  = getUserPlan($user['id']);
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

checkOfflineDevices();

// Stats
$totalDevices = DB::count('devices', 'user_id = ? AND is_active = 1', [$user['id']]);
$onlineDevices = DB::count('devices', 'user_id = ? AND is_active = 1 AND is_online = 1', [$user['id']]);
$totalWidgets = (int) DB::value(
    "SELECT COUNT(*) FROM widgets w JOIN dashboards d ON w.dashboard_id = d.id WHERE d.user_id = ?",
    [$user['id']]
);
$totalPins = (int) DB::value(
    "SELECT COUNT(*) FROM virtual_pins vp JOIN devices d ON vp.device_id = d.id WHERE d.user_id = ?",
    [$user['id']]
);

// Recent devices
$devices = DB::rows(
    "SELECT * FROM devices WHERE user_id = ? AND is_active = 1 ORDER BY last_seen DESC LIMIT 6",
    [$user['id']]
);

// Recent transactions
$transactions = DB::rows(
    "SELECT * FROM credit_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Dashboard — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Buka Menu">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title">
          <i class="fas fa-home" style="color:var(--primary-light);margin-right:0.35rem"></i>
          Halo, <?= sanitize(explode(' ', $user['name'])[0]) ?>!
        </h1>
      </div>
      <div class="topbar-actions">
        <div class="credit-badge"><i class="fas fa-coins"></i> <?= number_format($user['credits']) ?> kredit</div>
        <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)">
          <i class="fas fa-moon theme-toggle-icon"></i>
        </button>
        <a href="device.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <span>Tambah</span></a>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- STAT CARDS -->
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fas fa-microchip"></i></div>
          <div class="stat-info">
            <div class="label">Total Perangkat</div>
            <div class="value"><?= $totalDevices ?></div>
            <div class="sub">Maks: <?= $plan['max_devices'] === 9999 ? '∞' : $plan['max_devices'] ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-signal"></i></div>
          <div class="stat-info">
            <div class="label">Perangkat Online</div>
            <div class="value"><?= $onlineDevices ?></div>
            <div class="sub">dari <?= $totalDevices ?> perangkat</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan"><i class="fas fa-th-large"></i></div>
          <div class="stat-info">
            <div class="label">Total Widget</div>
            <div class="value"><?= $totalWidgets ?></div>
            <div class="sub">di seluruh perangkat</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber"><i class="fas fa-coins"></i></div>
          <div class="stat-info">
            <div class="label">Sisa Saldo Kredit</div>
            <div class="value"><?= number_format($user['credits']) ?></div>
            <div class="sub">Paket: <?= $plan['name'] ?></div>
          </div>
        </div>
      </div>

      <!-- PLAN PROGRESS + DEVICES -->
      <div class="grid-2col mb-2">
        <!-- Paket Info -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-layer-group"></i> Paket Aktif</h3>
            <a href="profile.php" class="btn btn-secondary btn-sm">Upgrade Paket</a>
          </div>
          <div style="text-align:center;padding:1rem 0">
            <div style="font-size:2rem;font-weight:900;color:var(--primary-light)"><?= $plan['name'] ?></div>
            <div style="font-size:0.875rem;color:var(--text-muted);margin:0.5rem 0">
              <?= $plan['max_devices'] === 9999 ? 'Unlimited' : $plan['max_devices'] ?> Perangkat &middot;
              <?= $plan['max_widgets_per_device'] === 9999 ? 'Unlimited' : $plan['max_widgets_per_device'] ?> Widget &middot;
              Histori <?= $plan['history_days'] ?> Hari
            </div>
          </div>
          <!-- Device quota bar -->
          <?php $pct = $plan['max_devices'] === 9999 ? 5 : min(100, ($totalDevices / $plan['max_devices']) * 100); ?>
          <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.4rem;display:flex;justify-content:space-between">
            <span>Penggunaan Kuota Perangkat</span>
            <span><?= $totalDevices ?> / <?= $plan['max_devices'] === 9999 ? '∞' : $plan['max_devices'] ?></span>
          </div>
          <div style="height:8px;background:var(--bg-input);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--grad-primary);border-radius:99px;transition:width 1s ease"></div>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Kredit</h3>
            <a href="profile.php#transactions" class="btn btn-secondary btn-sm">Lihat Semua</a>
          </div>
          <?php if (empty($transactions)): ?>
            <div style="text-align:center;padding:1.5rem;color:var(--text-muted)">
              <i class="fas fa-coins" style="font-size:2rem;opacity:0.3"></i>
              <p style="margin-top:0.5rem;font-size:0.85rem">Belum ada riwayat transaksi</p>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.4rem">
              <?php foreach ($transactions as $txn): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border-light)">
                  <div style="font-size:0.8rem">
                    <div style="font-weight:600;color:var(--text-primary)"><?= sanitize($txn['note'] ?: ucfirst(str_replace('_', ' ', $txn['type']))) ?></div>
                    <div style="color:var(--text-muted)"><?= formatDate($txn['created_at'], 'd M Y H:i') ?></div>
                  </div>
                  <div style="font-weight:800;font-size:0.95rem;color:<?= $txn['amount'] > 0 ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $txn['amount'] > 0 ? '+' : '' ?><?= number_format($txn['amount']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- DEVICE LIST -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-microchip"></i> Device Aktif</h3>
          <a href="device.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</a>
        </div>

        <?php if (empty($devices)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-microchip"></i></div>
            <h3>Mulai IoT Pertama Anda</h3>
            <p>Buat device dan hubungkan ESP8266/ESP32/Arduino.</p>
            <a href="device.php" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> Tambah Device</a>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Device</th>
                  <th>Hardware</th>
                  <th>Status</th>
                  <th>Terakhir Online</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($devices as $dev): ?>
                  <tr>
                    <td>
                      <div style="font-weight:600"><?= sanitize($dev['name']) ?></div>
                      <div style="font-size:0.72rem;font-family:monospace;color:var(--primary-light)"><?= $dev['token'] ?></div>
                    </td>
                    <td><span class="badge badge-primary"><?= sanitize($dev['hardware']) ?></span></td>
                    <td>
                      <span class="badge <?= $dev['is_online'] ? 'badge-online' : 'badge-offline' ?>">
                        <span class="dot"></span><?= $dev['is_online'] ? 'Online' : 'Offline' ?>
                      </span>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted)">
                      <?= $dev['last_seen'] ? timeAgo($dev['last_seen']) : 'Belum pernah' ?>
                    </td>
                    <td>
                      <a href="dashboard.php?device=<?= $dev['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-th-large"></i> Dashboard
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<div id="toast-container"></div>
</body>
</html>
