<?php
/**
 * ShawirIOT - Device Management Page
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user     = currentUser();
$plan     = getUserPlan($user['id']);
$flash    = getFlash();
$platformName = getSetting('platform_name', 'ShawirIOT');

// Handle device actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // CREATE DEVICE
    if ($action === 'create') {
        $deviceCount = DB::count('devices', 'user_id = ? AND is_active = 1', [$user['id']]);
        if ($deviceCount >= $plan['max_devices']) {
            flash('error', "Batas device paket {$plan['name']} adalah {$plan['max_devices']} device. Upgrade paket untuk menambah lebih.");
        } else {
            $name     = sanitize($_POST['device_name'] ?? 'Device Baru');
            $hw       = sanitize($_POST['hardware'] ?? 'ESP8266');
            $conn     = sanitize($_POST['connection'] ?? 'wifi');
            $desc     = sanitize($_POST['description'] ?? '');
            $token    = generateDeviceToken();
            $deviceId = DB::insert(
                "INSERT INTO devices (user_id, name, hardware, connection, description, token) VALUES (?,?,?,?,?,?)",
                [$user['id'], $name, $hw, $conn, $desc, $token]
            );
            // Auto-create dashboard for this device
            DB::insert("INSERT INTO dashboards (device_id, user_id) VALUES (?,?)", [$deviceId, $user['id']]);
            flash('success', "Device \"{$name}\" berhasil dibuat!");
        }
        redirect(PLATFORM_URL . '/device.php');
    }

    // DELETE DEVICE
    if ($action === 'delete') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        $device   = DB::row("SELECT * FROM devices WHERE id = ? AND user_id = ?", [$deviceId, $user['id']]);
        if ($device) {
            DB::query("UPDATE devices SET is_active = 0 WHERE id = ?", [$deviceId]);
            flash('success', "Device \"{$device['name']}\" dihapus.");
        }
        redirect(PLATFORM_URL . '/device.php');
    }

    // REGENERATE TOKEN
    if ($action === 'regen_token') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        $device   = DB::row("SELECT * FROM devices WHERE id = ? AND user_id = ?", [$deviceId, $user['id']]);
        if ($device) {
            $newToken = generateDeviceToken();
            DB::query("UPDATE devices SET token = ? WHERE id = ?", [$newToken, $deviceId]);
            flash('success', 'Token device berhasil diperbarui.');
        }
        redirect(PLATFORM_URL . '/device.php');
    }
}

// Mark offline devices
checkOfflineDevices();

// Get devices
$devices = DB::rows(
    "SELECT d.*, (SELECT COUNT(*) FROM widgets w JOIN dashboards db ON w.dashboard_id = db.id WHERE db.device_id = d.id) as widget_count
     FROM devices d WHERE d.user_id = ? AND d.is_active = 1 ORDER BY d.created_at DESC",
    [$user['id']]
);
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Perangkat Saya — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Buka Menu">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><i class="fas fa-microchip" style="color:var(--primary-light);margin-right:0.35rem"></i>Perangkat Saya</h1>
      </div>
      <div class="topbar-actions">
        <div class="credit-badge"><i class="fas fa-coins"></i> <?= number_format($user['credits']) ?> kredit</div>
        <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)">
          <i class="fas fa-moon theme-toggle-icon"></i>
        </button>
        <button class="btn btn-primary" id="btn-add-device">
          <i class="fas fa-plus"></i> <span>Tambah</span>
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

      <!-- QUOTA BAR -->
      <div class="card mb-2" style="margin-bottom:1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
          <div>
            <div style="font-weight:700;font-size:0.95rem">Kuota Perangkat</div>
            <div style="font-size:0.8rem;color:var(--text-muted)">Paket: <strong style="color:var(--primary-light)"><?= $plan['name'] ?></strong></div>
          </div>
          <div style="font-size:0.875rem;color:var(--text-muted)">
            <strong style="color:var(--text-primary)"><?= count($devices) ?></strong> / <?= $plan['max_devices'] === 9999 ? '∞' : $plan['max_devices'] ?>
          </div>
        </div>
        <?php $pct = $plan['max_devices'] === 9999 ? 10 : min(100, (count($devices) / $plan['max_devices']) * 100); ?>
        <div style="height:6px;background:var(--bg-input);border-radius:99px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:var(--grad-primary);border-radius:99px;transition:width 1s ease"></div>
        </div>
      </div>

      <?php if (empty($devices)): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="fas fa-microchip"></i></div>
          <h3>Belum Ada Perangkat</h3>
          <p>Tambah perangkat pertama Anda dan mulai monitoring IoT.</p>
          <button class="btn btn-primary mt-2" id="btn-add-device-2">
            <i class="fas fa-plus"></i> Tambah Perangkat Pertama
          </button>
        </div>
      <?php else: ?>
        <div class="device-grid">
          <?php foreach ($devices as $dev): ?>
            <div class="device-card <?= $dev['is_online'] ? 'online' : '' ?>">
              <div class="device-card-header">
                <div>
                  <div class="device-card-name"><?= sanitize($dev['name']) ?></div>
                  <div class="device-card-hw"><i class="fas fa-microchip"></i> <?= sanitize($dev['hardware']) ?> &middot; <?= ucfirst($dev['connection']) ?></div>
                </div>
                <span class="badge <?= $dev['is_online'] ? 'badge-online' : 'badge-offline' ?>">
                  <span class="dot"></span>
                  <?= $dev['is_online'] ? 'Terhubung' : 'Terputus' ?>
                </span>
              </div>

              <?php if ($dev['description']): ?>
                <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.5rem"><?= sanitize($dev['description']) ?></p>
              <?php endif; ?>

              <!-- Token -->
              <div class="token-box" style="margin-bottom:0.75rem">
                <span class="token-value" id="token-<?= $dev['id'] ?>"><?= $dev['token'] ?></span>
                <button class="btn btn-secondary btn-sm btn-icon" title="Salin Token"
                  onclick="copyToken('<?= $dev['token'] ?>', this)">
                  <i class="fas fa-copy"></i>
                </button>
              </div>

              <div class="device-card-meta">
                <span><i class="fas fa-th-large"></i> <?= $dev['widget_count'] ?> widget</span>
                <span><i class="fas fa-clock"></i> <?= $dev['last_seen'] ? timeAgo($dev['last_seen']) : 'Belum pernah' ?></span>
              </div>

              <div class="device-card-actions">
                <a href="device_dashboard.php?device=<?= $dev['id'] ?>" class="btn btn-primary btn-sm" style="flex:1">
                  <i class="fas fa-th-large"></i> Buka Dashboard
                </a>
                <a href="code_editor.php?device_id=<?= $dev['id'] ?>" class="btn btn-secondary btn-sm" title="Buka Editor Kode & Program">
                  <i class="fas fa-code"></i>
                </a>
                <button class="btn btn-secondary btn-sm" onclick="regenToken(<?= $dev['id'] ?>, '<?= sanitize($dev['name']) ?>')" title="Buat Ulang Token">
                  <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteDevice(<?= $dev['id'] ?>, '<?= sanitize($dev['name']) ?>')" title="Hapus Perangkat">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- MODAL: Tambah Device -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary-light)"></i> Tambah Perangkat Baru</h3>
      <button class="modal-close" onclick="closeModal('modal-add')">&times;</button>
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">

      <div class="form-group">
        <label class="form-label">Nama Device *</label>
        <input type="text" name="device_name" class="form-control" placeholder="cth: Ruang Tamu, Greenhouse" required>
      </div>

      <div class="form-group">
        <label class="form-label">Hardware</label>
        <select name="hardware" class="form-control">
          <option value="ESP8266">ESP8266</option>
          <option value="ESP32">ESP32</option>
          <option value="Arduino UNO">Arduino UNO</option>
          <option value="Arduino Mega">Arduino Mega</option>
          <option value="Arduino Nano">Arduino Nano</option>
          <option value="Raspberry Pi">Raspberry Pi</option>
          <option value="Other">Lainnya</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Koneksi</label>
        <select name="connection" class="form-control">
          <option value="wifi">Wi-Fi</option>
          <option value="ethernet">Ethernet</option>
          <option value="gsm">GSM/4G</option>
          <option value="other">Lainnya</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Deskripsi (opsional)</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan device..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Device</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Konfirmasi Hapus -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" style="color:var(--danger)"><i class="fas fa-trash"></i> Hapus Device</h3>
      <button class="modal-close" onclick="closeModal('modal-delete')">&times;</button>
    </div>
    <p>Yakin ingin menghapus device <strong id="delete-device-name"></strong>? Semua widget dan data akan ikut terhapus.</p>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="device_id" id="delete-device-id">
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Regenerate Token -->
<div class="modal-overlay" id="modal-regen">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-sync-alt" style="color:var(--warning)"></i> Regenerate Token</h3>
      <button class="modal-close" onclick="closeModal('modal-regen')">&times;</button>
    </div>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i>
      <strong>Perhatian!</strong> Token lama akan tidak valid. Anda harus mengupdate token di kode Arduino Anda.
    </div>
    <p>Regenerate token untuk device <strong id="regen-device-name"></strong>?</p>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="regen_token">
      <input type="hidden" name="device_id" id="regen-device-id">
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-regen')">Batal</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Regenerate</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

document.getElementById('btn-add-device').addEventListener('click', () => openModal('modal-add'));
const btn2 = document.getElementById('btn-add-device-2');
if (btn2) btn2.addEventListener('click', () => openModal('modal-add'));

function deleteDevice(id, name) {
  document.getElementById('delete-device-id').value = id;
  document.getElementById('delete-device-name').textContent = name;
  openModal('modal-delete');
}

function regenToken(id, name) {
  document.getElementById('regen-device-id').value = id;
  document.getElementById('regen-device-name').textContent = name;
  openModal('modal-regen');
}

function copyToken(token, btn) {
  navigator.clipboard.writeText(token).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check" style="color:var(--success)"></i>';
    showToast('Token disalin!', 'success');
    setTimeout(() => btn.innerHTML = orig, 2000);
  });
}

function showToast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}" style="color:var(--${type === 'success' ? 'success' : 'primary-light'})"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('active'); });
});
</script>
</body>
</html>
