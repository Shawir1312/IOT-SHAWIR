<?php
/**
 * ShawirIOT - Admin Dashboard Overview
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$admin = currentUser();
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

checkOfflineDevices();

// System Statistics
$totalUsers     = DB::count('users');
$totalDevices   = DB::count('devices', 'is_active = 1');
$onlineDevices  = DB::count('devices', 'is_active = 1 AND is_online = 1');
$totalCredits   = (int) DB::value("SELECT SUM(credits) FROM users");
$totalDataPoints= DB::count('pin_history');
$totalWidgets   = DB::count('widgets');

// Recent Registered Users
$recentUsers = DB::rows(
    "SELECT u.*, p.name as plan_name FROM users u JOIN plans p ON u.plan_id = p.id ORDER BY u.created_at DESC LIMIT 5"
);

// Recent Devices
$recentDevices = DB::rows(
    "SELECT d.*, u.name as owner_name FROM devices d JOIN users u ON d.user_id = u.id WHERE d.is_active = 1 ORDER BY d.created_at DESC LIMIT 5"
);

// Recent Credit Activity
$recentTransactions = DB::rows(
    "SELECT ct.*, u.name as user_name FROM credit_transactions ct JOIN users u ON ct.user_id = u.id ORDER BY ct.created_at DESC LIMIT 6"
);
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — <?= $platformName ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-layout">
<div class="app-layout">
  <!-- ADMIN SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%)">
        <i class="fas fa-shield-alt"></i>
      </div>
      <div class="logo-text" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent">
        <?= $platformName ?> Admin
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Panel Admin</div>
      <a href="index.php" class="nav-item active">
        <span class="nav-icon"><i class="fas fa-chart-pie"></i></span>
        <span>Overview</span>
      </a>
      <a href="users.php" class="nav-item">
        <span class="nav-icon"><i class="fas fa-users"></i></span>
        <span>Kelola User</span>
      </a>
      <a href="credits.php" class="nav-item">
        <span class="nav-icon"><i class="fas fa-coins"></i></span>
        <span>Sistem Kredit</span>
      </a>
      <a href="devices.php" class="nav-item">
        <span class="nav-icon"><i class="fas fa-server"></i></span>
        <span>Monitor Device</span>
      </a>
      <a href="settings.php" class="nav-item">
        <span class="nav-icon"><i class="fas fa-cog"></i></span>
        <span>Pengaturan Platform</span>
      </a>

      <div class="nav-section-label" style="margin-top:1rem">Navigasi User</div>
      <a href="../dashboard.php" class="nav-item">
        <span class="nav-icon"><i class="fas fa-arrow-left"></i></span>
        <span>Ke Dashboard User</span>
      </a>
      <a href="../logout.php" class="nav-item" style="color:var(--danger)">
        <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
        <span>Keluar</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%)">
          <?= strtoupper(substr($admin['name'], 0, 1)) ?>
        </div>
        <div class="user-info">
          <div class="name"><?= sanitize($admin['name']) ?></div>
          <div class="role" style="color:#fb923c"><?= strtoupper($admin['role']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar admin-topbar">
      <div style="display:flex;align-items:center;gap:0.75rem">
        <h1 class="topbar-title">
          <i class="fas fa-tachometer-alt" style="color:#fb923c;margin-right:0.4rem"></i>
          Admin Panel Overview
        </h1>
        <span class="admin-badge"><i class="fas fa-lock"></i> <?= strtoupper($admin['role']) ?></span>
      </div>
      <div class="topbar-actions">
        <a href="credits.php" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #f97316 0%, #ea580c 100%)">
          <i class="fas fa-coins"></i> Tambah Kredit User
        </a>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- ADMIN STATS -->
      <div class="admin-stat-grid">
        <div class="admin-stat s-users">
          <div class="stat-lbl">Total Pengguna</div>
          <div class="stat-num"><?= number_format($totalUsers) ?></div>
          <div class="stat-trend"><i class="fas fa-user-check"></i> Terdaftar aktif</div>
        </div>
        <div class="admin-stat s-devices">
          <div class="stat-lbl">Total Device</div>
          <div class="stat-num"><?= number_format($totalDevices) ?></div>
          <div class="stat-trend"><i class="fas fa-microchip"></i> Terhubung di sistem</div>
        </div>
        <div class="admin-stat s-online">
          <div class="stat-lbl">Device Online</div>
          <div class="stat-num" style="color:var(--success)"><?= number_format($onlineDevices) ?></div>
          <div class="stat-trend"><i class="fas fa-signal"></i> Real-time aktif</div>
        </div>
        <div class="admin-stat s-credits">
          <div class="stat-lbl">Total Kredit Beredar</div>
          <div class="stat-num" style="color:var(--accent)"><?= number_format($totalCredits) ?></div>
          <div class="stat-trend"><i class="fas fa-coins"></i> Di seluruh akun</div>
        </div>
        <div class="admin-stat s-data">
          <div class="stat-lbl">Data Poin Sensor</div>
          <div class="stat-num" style="color:#fb923c"><?= number_format($totalDataPoints) ?></div>
          <div class="stat-trend"><i class="fas fa-database"></i> Record histori</div>
        </div>
      </div>

      <!-- QUICK ACTIONS -->
      <div class="quick-actions">
        <a href="users.php" class="quick-action-btn">
          <i class="fas fa-user-plus qa-icon" style="color:var(--primary-light)"></i>
          <span class="qa-label">Kelola User</span>
        </a>
        <a href="credits.php" class="quick-action-btn">
          <i class="fas fa-coins qa-icon" style="color:var(--accent)"></i>
          <span class="qa-label">Beri Kredit</span>
        </a>
        <a href="devices.php" class="quick-action-btn">
          <i class="fas fa-server qa-icon" style="color:var(--secondary)"></i>
          <span class="qa-label">Monitor IoT</span>
        </a>
        <a href="settings.php" class="quick-action-btn">
          <i class="fas fa-sliders-h qa-icon" style="color:#fb923c"></i>
          <span class="qa-label">Pengaturan</span>
        </a>
      </div>

      <!-- TWO COLUMNS: RECENT USERS & CREDIT LOGS -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
        <!-- USER LIST -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users"></i> Pengguna Baru</h3>
            <a href="users.php" class="btn btn-secondary btn-sm">Semua User</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>User</th>
                  <th>Paket</th>
                  <th>Kredit</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentUsers as $u): ?>
                  <tr>
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
                      <span class="plan-badge plan-<?= strtolower($u['plan_name']) ?>">
                        <?= sanitize($u['plan_name']) ?>
                      </span>
                    </td>
                    <td><strong style="color:var(--accent)"><?= number_format($u['credits']) ?></strong></td>
                    <td>
                      <a href="credits.php?user_id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Kredit">
                        <i class="fas fa-coins"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- RECENT TRANSACTIONS -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Aktivitas Kredit Terkini</h3>
            <a href="credits.php" class="btn btn-secondary btn-sm">Lihat Log</a>
          </div>
          <div class="activity-list">
            <?php foreach ($recentTransactions as $rt): ?>
              <div class="activity-item">
                <div class="activity-icon" style="background:<?= $rt['amount'] > 0 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)' ?>;color:<?= $rt['amount'] > 0 ? 'var(--success)' : 'var(--danger)' ?>">
                  <i class="fas fa-<?= $rt['amount'] > 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                </div>
                <div class="activity-text">
                  <div>
                    <span class="actor"><?= sanitize($rt['user_name']) ?></span>:
                    <span style="color:<?= $rt['amount'] > 0 ? 'var(--success)' : 'var(--danger)' ?>;font-weight:700">
                      <?= $rt['amount'] > 0 ? '+' : '' ?><?= number_format($rt['amount']) ?> kredit
                    </span>
                  </div>
                  <small style="color:var(--text-muted)"><?= sanitize($rt['note'] ?: ucfirst($rt['type'])) ?></small>
                </div>
                <div class="activity-time"><?= timeAgo($rt['created_at']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- RECENT DEVICES -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-microchip"></i> Device Terdaftar Terbaru</h3>
          <a href="devices.php" class="btn btn-secondary btn-sm">Semua Device</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Device</th>
                <th>Pemilik</th>
                <th>Hardware</th>
                <th>Status</th>
                <th>Token</th>
                <th>Terakhir Online</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentDevices as $rd): ?>
                <tr>
                  <td><strong><?= sanitize($rd['name']) ?></strong></td>
                  <td><?= sanitize($rd['owner_name']) ?></td>
                  <td><span class="badge badge-primary"><?= sanitize($rd['hardware']) ?></span></td>
                  <td>
                    <span class="badge <?= $rd['is_online'] ? 'badge-online' : 'badge-offline' ?>">
                      <span class="dot"></span><?= $rd['is_online'] ? 'Online' : 'Offline' ?>
                    </span>
                  </td>
                  <td><span class="device-token-small"><?= $rd['token'] ?></span></td>
                  <td style="font-size:0.8rem;color:var(--text-muted)"><?= $rd['last_seen'] ? timeAgo($rd['last_seen']) : 'Belum pernah' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>
</body>
</html>
