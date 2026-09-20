<?php
/**
 * ShawirIOT - Admin Device & Widget Monitor
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

// Search, User, and Status Filters
$search       = sanitize($_GET['q'] ?? '');
$status       = sanitize($_GET['status'] ?? '');
$filterUserId = (int)($_GET['user_id'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 20;
$offset       = ($page - 1) * $limit;

$where = "d.is_active = 1";
$params = [];

$filterUser = null;
if ($filterUserId > 0) {
    $where .= " AND d.user_id = ?";
    $params[] = $filterUserId;
    $filterUser = DB::row("SELECT id, name, email FROM users WHERE id = ?", [$filterUserId]);
}

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
$totalWidgets = DB::count('widgets');

// Prefetch Widgets, Pins, and Dashboards for the current devices
$deviceWidgets    = [];
$devicePins       = [];
$deviceDashboards = [];
$devicesDataMap   = [];

if (!empty($devices)) {
    $devIds = array_column($devices, 'id');
    $inSql = implode(',', array_map('intval', $devIds));

    $dashRows = DB::rows("SELECT id, device_id, title, bg_color FROM dashboards WHERE device_id IN ({$inSql})");
    foreach ($dashRows as $dr) {
        $deviceDashboards[$dr['device_id']] = $dr;
    }

    $wRows = DB::rows(
        "SELECT w.*, d.device_id 
         FROM widgets w 
         JOIN dashboards d ON w.dashboard_id = d.id 
         WHERE d.device_id IN ({$inSql}) 
         ORDER BY w.pos_y, w.pos_x"
    );
    foreach ($wRows as $wr) {
        $wr['min_value'] = (float)$wr['min_value'];
        $wr['max_value'] = (float)$wr['max_value'];
        $deviceWidgets[$wr['device_id']][] = $wr;
    }

    $pinRows = DB::rows("SELECT * FROM virtual_pins WHERE device_id IN ({$inSql}) ORDER BY pin ASC");
    foreach ($pinRows as $pr) {
        $devicePins[$pr['device_id']][] = $pr;
    }

    foreach ($devices as $d) {
        $did = (int)$d['id'];
        $devicesDataMap[$did] = [
            'id'           => $did,
            'name'         => $d['name'],
            'description'  => $d['description'] ?? '',
            'hardware'     => $d['hardware'],
            'connection'   => ucfirst($d['connection']),
            'token'        => $d['token'],
            'is_online'    => (bool)$d['is_online'],
            'last_seen'    => $d['last_seen'] ? timeAgo($d['last_seen']) : 'Belum pernah',
            'last_ip'      => $d['last_ip'] ?: '-',
            'owner_name'   => $d['owner_name'],
            'owner_email'  => $d['owner_email'],
            'dashboard_id' => $deviceDashboards[$did]['id'] ?? 0,
            'widgets'      => $deviceWidgets[$did] ?? [],
            'pins'         => $devicePins[$did] ?? []
        ];
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Perangkat & Widget User — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="../assets/js/theme.js"></script>
  <style>
    .widget-preview-card {
      background: var(--bg-surface);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 0.85rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .widget-preview-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 4px; height: 100%;
      background: var(--widget-accent, var(--primary));
    }
    .widget-preview-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
      gap: 0.5rem;
    }
    .widget-preview-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--text-primary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .widget-preview-pin {
      background: rgba(99, 102, 241, 0.15);
      border: 1px solid rgba(99, 102, 241, 0.3);
      color: var(--primary-light);
      padding: 2px 7px;
      border-radius: 4px;
      font-family: monospace;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .widget-preview-val {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--primary-light);
      margin: 0.25rem 0;
      font-family: monospace;
    }
  </style>
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
        <h1 class="topbar-title"><i class="fas fa-server" style="color:var(--secondary);margin-right:0.4rem"></i>Perangkat & Widget User</h1>
      </div>
      <div class="topbar-actions">
        <form method="GET" action="" style="display:flex;gap:0.4rem;flex-wrap:wrap">
          <?php if ($filterUserId > 0): ?>
            <input type="hidden" name="user_id" value="<?= $filterUserId ?>">
          <?php endif; ?>
          <select name="status" class="form-control" style="font-size:0.8rem;padding:0.35rem 0.65rem;width:auto" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="online"  <?= $status==='online'?'selected':'' ?>>Online (<?= $onlineCount ?>)</option>
            <option value="offline" <?= $status==='offline'?'selected':'' ?>>Offline (<?= $offlineCount ?>)</option>
          </select>
          <input type="text" name="q" class="form-control" style="width:150px;padding:0.35rem 0.65rem;font-size:0.8rem"
            placeholder="Cari device/user..." value="<?= sanitize($search) ?>">
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

      <?php if ($filterUser): ?>
        <div style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:var(--radius-md);padding:0.75rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
          <div style="display:flex;align-items:center;gap:0.6rem">
            <i class="fas fa-filter" style="color:var(--primary-light)"></i>
            <span>Menampilkan perangkat milik pengguna: <strong><?= sanitize($filterUser['name']) ?></strong> (<?= sanitize($filterUser['email']) ?>)</span>
          </div>
          <a href="devices.php" class="btn btn-secondary btn-sm" style="font-size:0.78rem">
            <i class="fas fa-times"></i> Hapus Filter Pengguna
          </a>
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
          <div class="stat-icon orange"><i class="fas fa-th-large"></i></div>
          <div class="stat-info">
            <div class="label">Total Widget</div>
            <div class="value" style="color:#fb923c"><?= $totalWidgets ?></div>
            <div class="sub">Aktif di dashboard user</div>
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
                <th>Widget & Pin</th>
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
                      <strong style="font-size:0.95rem;color:var(--text-primary)"><?= sanitize($d['name']) ?></strong>
                      <?php if ($d['description']): ?>
                        <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($d['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div>
                        <a href="devices.php?user_id=<?= $d['user_id'] ?>" style="color:var(--primary-light);font-weight:600;text-decoration:none" title="Filter perangkat user ini">
                          <?= sanitize($d['owner_name']) ?> <i class="fas fa-external-link-alt" style="font-size:0.65rem"></i>
                        </a>
                      </div>
                      <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($d['owner_email']) ?></div>
                    </td>
                    <td>
                      <span class="badge badge-primary"><?= sanitize($d['hardware']) ?></span>
                      <small style="color:var(--text-muted);display:block;margin-top:2px"><?= ucfirst($d['connection']) ?></small>
                    </td>
                    <td>
                      <span class="badge <?= $d['is_online'] ? 'badge-online' : 'badge-offline' ?>">
                        <span class="dot"></span><?= $d['is_online'] ? 'Online' : 'Offline' ?>
                      </span>
                    </td>
                    <td>
                      <span class="device-token-small" title="Token Device"><?= $d['token'] ?></span>
                    </td>
                    <td>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openWidgetModal(<?= $d['id'] ?>)"
                        style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.35rem 0.65rem;font-size:0.8rem;border-color:rgba(99,102,241,0.3);background:rgba(99,102,241,0.08);color:var(--primary-light);font-weight:600"
                        title="Klik untuk melihat widget & pin perangkat ini">
                        <i class="fas fa-th-large"></i>
                        <span><?= $d['widget_count'] ?> Widget &middot; <?= $d['pin_count'] ?> Pin</span>
                      </button>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted)">
                      <div><?= $d['last_ip'] ?: '-' ?></div>
                      <div><?= $d['last_seen'] ? timeAgo($d['last_seen']) : 'Belum pernah' ?></div>
                    </td>
                    <td>
                      <div style="display:flex;gap:0.35rem;align-items:center">
                        <!-- View Widgets & Pins Modal Button -->
                        <button type="button" class="btn btn-secondary btn-sm btn-icon" onclick="openWidgetModal(<?= $d['id'] ?>)" title="Lihat Rincian Widget & Pin">
                          <i class="fas fa-th-large" style="color:var(--primary-light)"></i>
                        </button>
                        <!-- Open Live Dashboard in Admin Mode -->
                        <a href="../dashboard.php?device=<?= $d['id'] ?>" target="_blank" class="btn btn-primary btn-sm btn-icon" style="background:linear-gradient(135deg, #f97316 0%, #ea580c 100%)" title="Buka Live Dashboard Device (Mode Admin)">
                          <i class="fas fa-external-link-alt"></i>
                        </a>
                        <!-- Delete Device -->
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
                <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>"
                   class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- MODAL: DETAIL PERANGKAT & WIDGET USER -->
<div class="modal-overlay" id="modal-device-widgets">
  <div class="modal" style="max-width:800px;width:95%">
    <div class="modal-header" style="border-bottom:1px solid var(--border-light);padding-bottom:1rem">
      <div>
        <h3 class="modal-title" id="m-device-name" style="font-size:1.2rem;display:flex;align-items:center;gap:0.5rem">
          <i class="fas fa-microchip" style="color:var(--primary-light)"></i> Detail Perangkat & Widget
        </h3>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:3px" id="m-device-owner"></div>
      </div>
      <button class="modal-close" onclick="closeModal('modal-device-widgets')">&times;</button>
    </div>

    <div style="padding:1rem 0;max-height:72vh;overflow-y:auto">
      <!-- Device Quick Info Card -->
      <div style="background:var(--bg-surface);border:1px solid var(--border-light);border-radius:var(--radius-md);padding:0.9rem 1.1rem;margin-bottom:1.25rem;display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem">
        <div>
          <span style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700">Hardware & Koneksi</span>
          <div id="m-device-hw" style="font-weight:600;font-size:0.88rem;margin-top:2px"></div>
        </div>
        <div>
          <span style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700">Status Jaringan</span>
          <div id="m-device-status" style="margin-top:2px"></div>
        </div>
        <div>
          <span style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700">Token Auth</span>
          <div id="m-device-token" style="margin-top:2px;font-family:monospace;font-size:0.82rem;color:var(--primary-light)"></div>
        </div>
        <div>
          <span style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700">Terakhir Terhubung</span>
          <div id="m-device-seen" style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px"></div>
        </div>
      </div>

      <!-- Section: Widgets -->
      <div style="margin-bottom:1.5rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
          <h4 style="font-size:0.95rem;font-weight:700;margin:0;display:flex;align-items:center;gap:6px">
            <i class="fas fa-th-large" style="color:var(--primary-light)"></i> Daftar Widget Terpasang (<span id="m-widget-count">0</span>)
          </h4>
          <a id="m-live-dashboard-btn" href="#" target="_blank" class="btn btn-primary btn-sm" style="font-size:0.78rem;background:linear-gradient(135deg, #f97316 0%, #ea580c 100%)">
            <i class="fas fa-external-link-alt"></i> Buka Live Dashboard
          </a>
        </div>
        <div id="m-widgets-container" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:0.75rem">
          <!-- Populated by JS -->
        </div>
      </div>

      <!-- Section: Virtual Pins -->
      <div>
        <h4 style="font-size:0.95rem;font-weight:700;margin:0 0 0.75rem 0;display:flex;align-items:center;gap:6px">
          <i class="fas fa-plug" style="color:#10b981"></i> Nilai Virtual Pin Terakhir (<span id="m-pin-count">0</span>)
        </h4>
        <div id="m-pins-container" class="table-wrap" style="max-height:180px;overflow-y:auto;border:1px solid var(--border-light);border-radius:var(--radius-md)">
          <!-- Populated by JS -->
        </div>
      </div>
    </div>

    <div class="modal-footer" style="border-top:1px solid var(--border-light);padding-top:1rem;display:flex;justify-content:space-between">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-device-widgets')">Tutup</button>
      <a id="m-live-dashboard-btn-2" href="#" target="_blank" class="btn btn-primary" style="background:linear-gradient(135deg, #f97316 0%, #ea580c 100%)">
        <i class="fas fa-external-link-alt"></i> Buka Live Dashboard Lengkap
      </a>
    </div>
  </div>
</div>

<script>
const allDevices = <?= json_encode($devicesDataMap) ?>;

const widgetTypeLabels = {
  'value_display': { name: 'Display Nilai', icon: 'tachometer-alt' },
  'line_chart':    { name: 'Grafik Garis', icon: 'chart-line' },
  'bar_chart':     { name: 'Grafik Batang', icon: 'chart-bar' },
  'gauge':         { name: 'Gauge Meter', icon: 'tachometer-alt' },
  'radial_gauge':  { name: 'Radial Gauge', icon: 'circle-notch' },
  'button':        { name: 'Tombol Push', icon: 'hand-pointer' },
  'switch':        { name: 'Saklar Toggle', icon: 'toggle-on' },
  'slider':        { name: 'Slider Nilai', icon: 'sliders-h' },
  'led':           { name: 'Lampu LED', icon: 'lightbulb' },
  'terminal':      { name: 'Terminal Log', icon: 'terminal' },
  'label':         { name: 'Label Teks', icon: 'font' },
  'map':           { name: 'Peta GPS', icon: 'map-marker-alt' }
};

function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openWidgetModal(deviceId) {
  const d = allDevices[deviceId];
  if (!d) return;

  document.getElementById('m-device-name').innerHTML = '<i class="fas fa-microchip" style="color:var(--primary-light)"></i> ' + escapeHtml(d.name);
  document.getElementById('m-device-owner').innerHTML = 'Pemilik: <strong>' + escapeHtml(d.owner_name) + '</strong> &middot; ' + escapeHtml(d.owner_email);
  document.getElementById('m-device-hw').textContent = d.hardware + ' (' + d.connection + ')';
  
  const statusHtml = d.is_online 
    ? '<span class="badge badge-online"><span class="dot"></span> Online</span>' 
    : '<span class="badge badge-offline"><span class="dot"></span> Offline</span>';
  document.getElementById('m-device-status').innerHTML = statusHtml;
  document.getElementById('m-device-token').textContent = d.token;
  document.getElementById('m-device-seen').textContent = d.last_seen + (d.last_ip !== '-' ? ' (' + d.last_ip + ')' : '');

  // Live Dashboard Link
  const dashUrl = '../dashboard.php?device=' + d.id;
  document.getElementById('m-live-dashboard-btn').href = dashUrl;
  document.getElementById('m-live-dashboard-btn-2').href = dashUrl;

  // Pin Map for Fast Lookup
  const pinValMap = {};
  if (d.pins && d.pins.length) {
    d.pins.forEach(p => {
      pinValMap[p.pin] = p.value !== null ? p.value : '-';
    });
  }

  // Render Widgets
  const widgetsContainer = document.getElementById('m-widgets-container');
  widgetsContainer.innerHTML = '';
  document.getElementById('m-widget-count').textContent = d.widgets ? d.widgets.length : 0;

  if (!d.widgets || d.widgets.length === 0) {
    widgetsContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:var(--text-muted);background:var(--bg-surface);border-radius:var(--radius-md);border:1px dashed var(--border-light)">Belum ada widget yang ditambahkan pada dashboard perangkat ini.</div>';
  } else {
    d.widgets.forEach(w => {
      const typeInfo = widgetTypeLabels[w.type] || { name: w.type, icon: 'cube' };
      const curVal = pinValMap[w.pin] !== undefined ? pinValMap[w.pin] : '-';
      const color = w.color || '#6366f1';

      const card = document.createElement('div');
      card.className = 'widget-preview-card';
      card.style.setProperty('--widget-accent', color);

      card.innerHTML = `
        <div>
          <div class="widget-preview-header">
            <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px">
              <i class="fas fa-${typeInfo.icon}"></i> ${escapeHtml(typeInfo.name)}
            </div>
            <span class="widget-preview-pin">${escapeHtml(w.pin || '-')}</span>
          </div>
          <div class="widget-preview-title">${escapeHtml(w.label || 'Widget')}</div>
          <div class="widget-preview-val">${escapeHtml(curVal)} <span style="font-size:0.8rem;color:var(--text-muted)">${escapeHtml(w.unit || '')}</span></div>
        </div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.4rem;border-top:1px solid var(--border-light);padding-top:0.4rem;display:flex;justify-content:space-between">
          <span>Rentang: ${w.min_value} - ${w.max_value}</span>
          <span>Grid: ${w.width}x${w.height}</span>
        </div>
      `;
      widgetsContainer.appendChild(card);
    });
  }

  // Render Virtual Pins
  const pinsContainer = document.getElementById('m-pins-container');
  pinsContainer.innerHTML = '';
  document.getElementById('m-pin-count').textContent = d.pins ? d.pins.length : 0;

  if (!d.pins || d.pins.length === 0) {
    pinsContainer.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-muted)">Belum ada data virtual pin tersimpan.</div>';
  } else {
    let pinTable = '<table style="margin:0;font-size:0.82rem"><thead><tr><th>Pin</th><th>Nilai Terakhir</th><th>Terakhir Update</th></tr></thead><tbody>';
    d.pins.forEach(p => {
      pinTable += `<tr>
        <td><strong style="color:var(--primary-light);font-family:monospace;font-size:0.85rem">${escapeHtml(p.pin)}</strong></td>
        <td><span style="font-family:monospace;font-weight:700">${escapeHtml(p.value !== null ? p.value : '-')}</span></td>
        <td style="color:var(--text-muted)">${escapeHtml(p.updated_at || '-')}</td>
      </tr>`;
    });
    pinTable += '</tbody></table>';
    pinsContainer.innerHTML = pinTable;
  }

  openModal('modal-device-widgets');
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
</script>
</body>
</html>
