<?php
/**
 * ShawirIOT - Admin Device Monitor
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$admin = currentUser();
$flash = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

checkOfflineDevices();

// Handle device deletion by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'admin_delete_device') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        DB::query("DELETE FROM devices WHERE id = ?", [$deviceId]);
        flash('success', 'Device berhasil dihapus dari sistem.');
        redirect('devices.php');
    }
}

// Search & Status Filters
$search = sanitize($_GET['q'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = "d.is_active = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (d.name LIKE ? OR d.token LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status === 'online') {
    $where .= " AND d.is_online = 1";
} elseif ($status === 'offline') {
    $where .= " AND d.is_online = 0";
}

$totalDevices = (int) DB::value(
    "SELECT COUNT(*) FROM devices d JOIN users u ON d.user_id = u.id WHERE {$where}",
    $params
);
$totalPages = ceil($totalDevices / $limit);

$devices = DB::rows(
    "SELECT d.*, u.name as owner_name, u.email as owner_email,
     (SELECT COUNT(*) FROM virtual_pins WHERE device_id = d.id) as pin_count,
     (SELECT COUNT(*) FROM widgets w JOIN dashboards db ON w.dashboard_id = db.id WHERE db.device_id = d.id) as widget_count
     FROM devices d
     JOIN users u ON d.user_id = u.id
     WHERE {$where}
     ORDER BY d.is_online DESC, d.last_seen DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$onlineCount  = DB::count('devices', 'is_active = 1 AND is_online = 1');
$offlineCount = DB::count('devices', 'is_active = 1 AND is_online = 0');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monitor Device — <?= $platformName ?> Admin</title>
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
        <h1 class="topbar-title"><i class="fas fa-server" style="color:var(--secondary);margin-right:0.4rem"></i>Monitor Device</h1>
      </div>
      <div class="topbar-actions">
        <form method="GET" action="" style="display:flex;gap:0.4rem;flex-wrap:wrap">
          <select name="status" class="form-control" style="font-size:0.8rem;padding:0.35rem 0.65rem;width:auto" onchange="this.form.submit()">
            <option value="">Semua (<?= $onlineCount + $offlineCount ?>)</option>
            <option value="online"  <?= $status==='online'?'selected':'' ?>>Online (<?= $onlineCount ?>)</option>
            <option value="offline" <?= $status==='offline'?'selected':'' ?>>Offline (<?= $offlineCount ?>)</option>
          </select>
          <input type="text" name="q" class="form-control" style="width:140px;padding:0.35rem 0.65rem;font-size:0.8rem"
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

      <div class="stat-grid mb-2">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fas fa-microchip"></i></div>
          <div class="stat-info">
            <div class="label">Total Device</div>
            <div class="value"><?= $onlineCount + $offlineCount ?></div>
            <div class="sub">Terdaftar di sistem</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-signal"></i></div>
          <div class="stat-info">
            <div class="label">Device Online</div>
            <div class="value" style="color:var(--success)"><?= $onlineCount ?></div>
            <div class="sub">Sedang streaming data</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red"><i class="fas fa-power-off"></i></div>
          <div class="stat-info">
            <div class="label">Device Offline</div>
            <div class="value" style="color:#f87171"><?= $offlineCount ?></div>
            <div class="sub">Tidak ada sinyal</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Device</th>
                <th>Pemilik</th>
                <th>Hardware / Koneksi</th>
                <th>Status</th>
                <th>Token Auth</th>
                <th>Pin / Widget</th>
                <th>IP & Terakhir Dilihat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($devices)): ?>
                <tr>
                  <td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">Tidak ada device ditemukan</td>
                </tr>
              <?php else: ?>
                <?php foreach ($devices as $d): ?>
                  <tr>
                    <td>
                      <strong><?= sanitize($d['name']) ?></strong>
                      <?php if ($d['description']): ?>
                        <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($d['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?= sanitize($d['owner_name']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($d['owner_email']) ?></div>
                    </td>
                    <td>
                      <span class="badge badge-primary"><?= sanitize($d['hardware']) ?></span>
                      <small style="color:var(--text-muted);display:block"><?= ucfirst($d['connection']) ?></small>
                    </td>
                    <td>
                      <span class="badge <?= $d['is_online'] ? 'badge-online' : 'badge-offline' ?>">
                        <span class="dot"></span><?= $d['is_online'] ? 'Online' : 'Offline' ?>
                      </span>
                    </td>
                    <td><span class="device-token-small"><?= $d['token'] ?></span></td>
                    <td style="font-size:0.8rem">
                      <span><?= $d['pin_count'] ?> pin</span> /
                      <span><?= $d['widget_count'] ?> widget</span>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted)">
                      <div><?= $d['last_ip'] ?: '-' ?></div>
                      <div><?= $d['last_seen'] ? timeAgo($d['last_seen']) : 'Belum pernah' ?></div>
                    </td>
                    <td>
                      <div style="display:flex;gap:0.35rem">
                        <a href="../dashboard.php?device=<?= $d['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Buka Dashboard Device">
                          <i class="fas fa-external-link-alt"></i>
                        </a>
                        <form method="POST" action="" onsubmit="return confirm('Hapus device ini secara paksa?')">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="admin_delete_device">
                          <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Hapus Device">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
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
                <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>"
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
