<?php
/**
 * ShawirIOT - IoT Device Dashboard (Widget Monitor + Edit)
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$deviceId = (int)($_GET['device'] ?? 0);
$user = currentUser();

// Verify device ownership
$device = DB::row(
    "SELECT d.* FROM devices d WHERE d.id = ? AND d.user_id = ? AND d.is_active = 1",
    [$deviceId, $user['id']]
);

if (!$device) {
    flash('error', 'Device tidak ditemukan.');
    redirect(PLATFORM_URL . '/device.php');
}

$plan = getUserPlan($user['id']);

// Get or create dashboard
$dashboard = DB::row("SELECT * FROM dashboards WHERE device_id = ?", [$deviceId]);
if (!$dashboard) {
    $dbId = DB::insert("INSERT INTO dashboards (device_id, user_id) VALUES (?,?)", [$deviceId, $user['id']]);
    $dashboard = DB::row("SELECT * FROM dashboards WHERE id = ?", [$dbId]);
}

// Get widgets
$widgets = DB::rows("SELECT * FROM widgets WHERE dashboard_id = ? ORDER BY pos_y, pos_x", [$dashboard['id']]);

// Get current pin values
$pinValues = [];
$pins = DB::rows("SELECT * FROM virtual_pins WHERE device_id = ?", [$deviceId]);
foreach ($pins as $p) $pinValues[$p['pin']] = $p['value'];

$wsUrl = WS_URL;
$platformName = getSetting('platform_name', 'ShawirIOT');
checkOfflineDevices();
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($device['name']) ?> — <?= $platformName ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
          <i class="fas fa-bars"></i>
        </button>
        <a href="device.php" class="btn btn-secondary btn-sm btn-icon" title="Kembali ke Device"><i class="fas fa-arrow-left"></i></a>
        <div style="min-width:0;flex:1">
          <h1 class="topbar-title" style="font-size:0.98rem"><?= sanitize($device['name']) ?></h1>
          <div style="font-size:0.72rem;color:var(--text-muted);display:flex;align-items:center;gap:0.4rem">
            <span><?= sanitize($device['hardware']) ?></span> &middot;
            <span id="device-status-label">
              <?= $device['is_online'] ? '<span style="color:var(--success)">● Online</span>' : '<span style="color:var(--text-muted)">● Offline</span>' ?>
            </span>
          </div>
        </div>
      </div>
      <div class="topbar-actions">
        <div class="rt-status-bar">
          <div class="rt-indicator" id="ws-indicator"></div>
          <span id="ws-status" style="font-size:0.75rem">Menghubungkan...</span>
        </div>
        <button class="btn btn-secondary btn-sm" id="btn-edit-toggle">
          <i class="fas fa-edit"></i> <span>Edit Dashboard</span>
        </button>
      </div>
    </header>

    <!-- DASHBOARD TOOLBAR (Edit Mode) -->
    <div class="dashboard-toolbar d-none" id="edit-toolbar">
      <div class="dashboard-toolbar-left">
        <div class="edit-mode-badge"><i class="fas fa-pencil-alt"></i> Mode Edit Aktif</div>
        <span style="font-size:0.8rem;color:var(--text-muted)">Seret widget untuk memindahkan, sudut kanan bawah untuk mengubah ukuran</span>
      </div>
      <div class="dashboard-toolbar-right">
        <button class="btn btn-secondary btn-sm" id="btn-add-widget">
          <i class="fas fa-plus"></i> Tambah Widget
        </button>
        <button class="btn btn-success btn-sm" id="btn-save-layout">
          <i class="fas fa-save"></i> Simpan Layout
        </button>
        <button class="btn btn-secondary btn-sm" id="btn-cancel-edit">
          <i class="fas fa-times"></i> Selesai Edit
        </button>
      </div>
    </div>

    <!-- DASHBOARD CANVAS -->
    <div class="dashboard-canvas" id="dashboard-canvas"
         style="background-color:<?= sanitize($dashboard['bg_color']) ?>">
      <div id="widget-grid">
        <?php foreach ($widgets as $w): ?>
          <div class="widget w-<?= $w['type'] ?>"
               id="widget-<?= $w['id'] ?>"
               data-id="<?= $w['id'] ?>"
               data-type="<?= $w['type'] ?>"
               data-pin="<?= sanitize($w['pin']) ?>"
               data-min="<?= $w['min_value'] ?>"
               data-max="<?= $w['max_value'] ?>"
               data-on="<?= sanitize($w['on_value']) ?>"
               data-off="<?= sanitize($w['off_value']) ?>"
               style="
                 grid-column: <?= $w['pos_x'] + 1 ?> / span <?= $w['width'] ?>;
                 grid-row:    <?= $w['pos_y'] + 1 ?> / span <?= $w['height'] ?>;
               ">
            <div class="widget-header">
              <span class="widget-label"><?= sanitize($w['label']) ?></span>
              <?php if ($w['pin']): ?>
                <span class="widget-pin"><?= sanitize($w['pin']) ?></span>
              <?php endif; ?>
            </div>
            <div class="widget-body" id="widget-body-<?= $w['id'] ?>">
              <?php
                $val = $pinValues[$w['pin']] ?? '—';
                echo renderWidgetBody($w, $val);
              ?>
            </div>
            <!-- Edit controls (hidden unless edit mode) -->
            <div class="widget-edit-btn">
              <button class="widget-action-btn" onclick="editWidget(<?= $w['id'] ?>)" title="Edit"><i class="fas fa-cog"></i></button>
              <button class="widget-action-btn delete" onclick="deleteWidget(<?= $w['id'] ?>)" title="Hapus"><i class="fas fa-trash"></i></button>
            </div>
            <div class="widget-drag-handle"><i class="fas fa-grip-vertical"></i></div>
            <div class="widget-resize-handle"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- WIDGET PANEL (Add Widget) -->
<div class="widget-panel" id="widget-panel">
  <div class="widget-panel-header">
    <h3 style="font-size:1rem;font-weight:700"><i class="fas fa-plus-circle" style="color:var(--primary-light)"></i> Tambah Widget</h3>
    <button class="modal-close" onclick="closeWidgetPanel()">&times;</button>
  </div>
  <div class="widget-panel-scroll">
    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.75rem">
      Pilih tipe widget. Maks <?= $plan['max_widgets_per_device'] === 9999 ? '∞' : $plan['max_widgets_per_device'] ?> per device.
    </div>
    <div class="widget-type-grid">
      <?php
        $widgetTypes = [
          'value_display' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Value Display'],
          'line_chart'    => ['icon' => 'fas fa-chart-line',     'label' => 'Line Chart'],
          'bar_chart'     => ['icon' => 'fas fa-chart-bar',      'label' => 'Bar Chart'],
          'gauge'         => ['icon' => 'fas fa-gauge-high',      'label' => 'Gauge'],
          'button'        => ['icon' => 'fas fa-hand-pointer',   'label' => 'Button'],
          'slider'        => ['icon' => 'fas fa-sliders-h',      'label' => 'Slider'],
          'switch'        => ['icon' => 'fas fa-toggle-on',      'label' => 'Switch'],
          'led'           => ['icon' => 'fas fa-circle-dot',     'label' => 'LED'],
          'terminal'      => ['icon' => 'fas fa-terminal',       'label' => 'Terminal'],
          'label'         => ['icon' => 'fas fa-font',           'label' => 'Label Teks'],
          'map'           => ['icon' => 'fas fa-map-marker-alt', 'label' => 'GPS Map'],
        ];
        foreach ($widgetTypes as $type => $info):
      ?>
        <div class="widget-type-btn" onclick="selectWidgetType('<?= $type ?>')">
          <i class="<?= $info['icon'] ?> wt-icon"></i>
          <span class="wt-label"><?= $info['label'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- MODAL: Widget Config -->
<div class="modal-overlay" id="modal-widget-config">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="widget-config-title">Konfigurasi Widget</h3>
      <button class="modal-close" onclick="closeModal('modal-widget-config')">&times;</button>
    </div>
    <div id="widget-config-form-wrap">
      <!-- Diisi oleh JS -->
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// === DATA FROM PHP ===
const DEVICE_ID   = <?= $deviceId ?>;
const DASHBOARD_ID= <?= $dashboard['id'] ?>;
const WS_URL      = '<?= $wsUrl ?>';
const DEVICE_TOKEN= '<?= $device['token'] ?>';
const MAX_WIDGETS = <?= $plan['max_widgets_per_device'] ?>;
const CSRF_TOKEN  = '<?= csrfToken() ?>';
const PIN_VALUES  = <?= json_encode($pinValues) ?>;
let widgets       = <?= json_encode($widgets) ?>;
let editMode      = false;
let ws            = null;
let charts        = {};
</script>
<script src="assets/js/widgets.js"></script>
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/realtime.js"></script>
</body>
</html>
<?php
/**
 * Render widget body HTML based on type
 */
function renderWidgetBody(array $w, string $val): string {
    $type  = $w['type'];
    $color = $w['color'] ?? '#6366f1';
    $unit  = $w['unit'] ?? '';
    $min   = (float)($w['min_value'] ?? 0);
    $max   = (float)($w['max_value'] ?? 100);

    switch ($type) {
        case 'value_display':
            return "<div class='w-value-display' style='width:100%;text-align:center'>
                      <div class='val-number' style='color:{$color}'>{$val}</div>
                      <div class='val-unit'>{$unit}</div>
                    </div>";

        case 'led':
            $on  = $val === $w['on_value'];
            $cls = $on ? 'on' : '';
            return "<div class='led-bulb {$cls}' style='" . ($on ? "background:{$color};box-shadow:0 0 20px {$color}88,0 0 40px {$color}44" : '') . "'></div>
                    <div class='led-label'>" . ($on ? 'ON' : 'OFF') . "</div>";

        case 'button':
            return "<button class='widget-btn' style='background:{$color}' onclick='sendPinValue(\"{$w['pin']}\", \"{$w['on_value']}\")'>
                      <i class='fas fa-hand-pointer'></i> {$w['label']}
                    </button>";

        case 'switch':
            $checked = $val === $w['on_value'] ? 'checked' : '';
            return "<label class='toggle-switch'>
                      <input type='checkbox' {$checked} onchange='sendPinValue(\"{$w['pin']}\", this.checked ? \"{$w['on_value']}\" : \"{$w['off_value']}\")'>
                      <div class='toggle-track'></div>
                    </label>
                    <div style='font-size:0.8rem;color:var(--text-secondary)'>" . ($val === $w['on_value'] ? 'ON' : 'OFF') . "</div>";

        case 'slider':
            $numVal = is_numeric($val) ? (float)$val : $min;
            return "<div class='slider-val' style='color:{$color}'>{$numVal}{$unit}</div>
                    <input type='range' class='widget-slider' min='{$min}' max='{$max}' value='{$numVal}'
                      style='accent-color:{$color}'
                      oninput='this.previousElementSibling.textContent=this.value+\"{$unit}\"'
                      onchange='sendPinValue(\"{$w['pin']}\", this.value)'>";

        case 'gauge':
            $numVal = is_numeric($val) ? (float)$val : $min;
            $pct    = $max > $min ? (($numVal - $min) / ($max - $min)) : 0;
            $deg    = -130 + ($pct * 260);
            return "<svg class='gauge-svg' viewBox='0 0 120 70'>
                      <path d='M10,70 A60,60 0 0,1 110,70' fill='none' stroke='#1e293b' stroke-width='10'/>
                      <path id='gauge-arc-{$w['id']}' d='M10,70 A60,60 0 0,1 110,70' fill='none' stroke='{$color}' stroke-width='10'
                        stroke-dasharray='188.5' stroke-dashoffset='" . (188.5 - $pct * 188.5) . "' stroke-linecap='round'/>
                      <text x='60' y='60' text-anchor='middle' fill='#f1f5f9' font-size='16' font-weight='800'>{$numVal}</text>
                    </svg>
                    <div class='gauge-label'>{$unit}</div>";

        case 'line_chart':
        case 'bar_chart':
            $chartType = $type === 'line_chart' ? 'line' : 'bar';
            return "<div class='chart-canvas-wrap'><canvas id='chart-{$w['id']}' data-type='{$chartType}' data-color='{$color}' data-pin='{$w['pin']}'></canvas></div>";

        case 'terminal':
            return "<div class='terminal-output' id='terminal-{$w['id']}'><span class='t-time'>[" . date('H:i:s') . "]</span> Terminal siap...\n</div>";

        case 'label':
            return "<div class='label-text' style='color:{$color}'>{$w['label']}</div>";

        case 'map':
            $parts = explode(',', $val);
            $lat = isset($parts[0]) && is_numeric($parts[0]) ? (float)$parts[0] : -6.2088;
            $lng = isset($parts[1]) && is_numeric($parts[1]) ? (float)$parts[1] : 106.8456;
            return "<iframe class='map-frame' src='https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed' loading='lazy'></iframe>";

        default:
            return "<div style='color:var(--text-muted);font-size:0.85rem'>{$type}</div>";
    }
}
?>
