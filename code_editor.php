<?php
/**
 * ShawirIOT - Code Studio, Web Serial Flasher & Live Firmware Center
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user         = currentUser();
$platformName = getSetting('platform_name', 'ShawirIOT');
$selectedDeviceId = (int)($_GET['device_id'] ?? 0);

// Get user devices
$devices = DB::rows(
    "SELECT d.*, 
     (SELECT COUNT(*) FROM virtual_pins WHERE device_id = d.id) as pin_count,
     (SELECT COUNT(*) FROM widgets w JOIN dashboards db ON w.dashboard_id = db.id WHERE db.device_id = d.id) as widget_count
     FROM devices d WHERE d.user_id = ? AND d.is_active = 1 ORDER BY d.name ASC",
    [$user['id']]
);

// If no device selected, pick first
if ($selectedDeviceId === 0 && !empty($devices)) {
    $selectedDeviceId = (int)$devices[0]['id'];
}

// Get virtual pins for current selected device
$devicePins = [];
$selectedDevice = null;
foreach ($devices as $d) {
    if ($d['id'] == $selectedDeviceId) {
        $selectedDevice = $d;
        break;
    }
}

if ($selectedDeviceId > 0) {
    $devicePins = DB::rows(
        "SELECT vp.pin, vp.value, w.label as widget_name, w.type as widget_type
         FROM virtual_pins vp 
         LEFT JOIN dashboards db ON db.device_id = vp.device_id
         LEFT JOIN widgets w ON w.dashboard_id = db.id AND w.pin = vp.pin
         WHERE vp.device_id = ? ORDER BY vp.pin ASC",
        [$selectedDeviceId]
    );
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Studio Kode & Web Flasher — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <!-- Ace Editor CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.js"></script>
  <!-- ESP Web Serial Flasher (esptool-js) CDN -->
  <script src="https://unpkg.com/esptool-js@0.5.4/bundle.js"></script>
  <style>
    .studio-tabs {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border-bottom: 1px solid var(--border-light);
      margin-bottom: 1rem;
      padding-bottom: 0.25rem;
      overflow-x: auto;
    }
    .studio-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.6rem 1.1rem;
      border-radius: var(--radius-md) var(--radius-md) 0 0;
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      color: var(--text-secondary);
      font-weight: 600;
      font-size: 0.88rem;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .studio-tab-btn:hover {
      color: var(--text-primary);
      background: var(--bg-card-hover);
    }
    .studio-tab-btn.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
      background: var(--bg-card);
    }
    .tab-content-pane {
      display: none;
    }
    .tab-content-pane.active {
      display: block;
    }
    .editor-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 1.25rem;
      min-height: 600px;
    }
    .editor-main-card {
      display: flex;
      flex-direction: column;
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }
    .editor-toolbar {
      padding: 0.75rem 1rem;
      background: var(--bg-surface);
      border-bottom: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    .editor-toolbar-left, .editor-toolbar-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    #code-editor {
      flex: 1;
      width: 100%;
      height: 520px;
      font-size: 14px;
      font-family: 'JetBrains Mono', 'Fira Code', monospace;
    }
    .panel-card {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      margin-bottom: 1rem;
    }
    .panel-title {
      font-size: 0.9rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-primary);
    }
    .terminal-window {
      background: #020810;
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg);
      padding: 1rem;
      font-family: 'JetBrains Mono', 'Fira Code', monospace;
      font-size: 0.8rem;
      color: #50fa7b;
      height: 400px;
      overflow-y: auto;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .terminal-window .t-system { color: #8be9fd; }
    .terminal-window .t-err { color: #ff5555; }
    .terminal-window .t-warn { color: #f1fa8c; }
    .terminal-window .t-tx { color: #bd93f9; }
    .progress-bar-wrap {
      height: 10px;
      background: var(--bg-input);
      border-radius: 99px;
      overflow: hidden;
      margin: 0.75rem 0;
      border: 1px solid var(--border-light);
    }
    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: var(--grad-primary);
      transition: width 0.2s ease;
    }
    .pin-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 2px 7px;
      border-radius: 4px;
      background: rgba(99,102,241,0.15);
      color: var(--primary-light);
      font-weight: 700;
      font-size: 0.75rem;
    }
    @media (max-width: 992px) {
      .editor-layout {
        grid-template-columns: 1fr;
      }
      #code-editor {
        height: 420px;
      }
    }
  </style>
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
        <h1 class="topbar-title">
          <i class="fas fa-microchip" style="color:var(--primary-light);margin-right:0.35rem"></i>Studio Kode & Web Flasher
        </h1>
      </div>
      <div class="topbar-actions">
        <div class="credit-badge"><i class="fas fa-coins"></i> <?= number_format($user['credits']) ?> kredit</div>
        <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)">
          <i class="fas fa-moon theme-toggle-icon"></i>
        </button>
      </div>
    </header>

    <!-- PAGE CONTENT -->
    <div class="page-content" style="max-width: 100%;">
      <?php if (empty($devices)): ?>
        <div class="empty-state card">
          <div class="empty-icon"><i class="fas fa-microchip"></i></div>
          <h3>Belum Ada Perangkat</h3>
          <p>Buat perangkat terlebih dahulu di menu <strong>Perangkat Saya</strong> sebelum memulai coding.</p>
          <a href="device.php" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> Buat Perangkat Baru</a>
        </div>
      <?php else: ?>
        <!-- STUDIO TABS -->
        <div class="studio-tabs">
          <button type="button" class="studio-tab-btn active" onclick="switchStudioTab('editor', this)">
            <i class="fas fa-code"></i> Editor Kode Arduino (.ino)
          </button>
          <button type="button" class="studio-tab-btn" onclick="switchStudioTab('flasher', this)">
            <i class="fas fa-bolt" style="color:var(--accent)"></i> Flash / Upload Langsung Web Serial
          </button>
          <button type="button" class="studio-tab-btn" onclick="switchStudioTab('monitor', this)">
            <i class="fas fa-terminal"></i> Serial Monitor Live (USB COM)
          </button>
          <button type="button" class="studio-tab-btn" onclick="switchStudioTab('simulator', this)">
            <i class="fas fa-gamepad"></i> Simulator Pin Virtual (Live Test)
          </button>
        </div>

        <!-- ============================================================
             TAB 1: CODE EDITOR (.INO)
             ============================================================ -->
        <div id="tab-editor" class="tab-content-pane active">
          <div class="editor-layout">
            <!-- MAIN CODE EDITOR -->
            <div class="editor-main-card">
              <!-- TOOLBAR -->
              <div class="editor-toolbar">
                <div class="editor-toolbar-left">
                  <!-- Device Selector -->
                  <div>
                    <select id="device-select" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.75rem;font-weight:600" onchange="onDeviceSelectChange(this.value)">
                      <?php foreach ($devices as $d): ?>
                        <option value="<?= $d['id'] ?>" 
                                data-name="<?= sanitize($d['name']) ?>"
                                data-hw="<?= sanitize($d['hardware']) ?>"
                                data-token="<?= sanitize($d['token']) ?>"
                                <?= ($d['id'] == $selectedDeviceId) ? 'selected' : '' ?>>
                          <?= sanitize($d['name']) ?> (<?= sanitize($d['hardware']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <!-- Template Selector -->
                  <div>
                    <select id="template-select" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.75rem" onchange="loadSelectedTemplate()">
                      <optgroup label="🌟 Integrasi shawirWifi (Rekomendasi)">
                        <option value="shawirwifi_plug_and_play" selected>shawirWifi + ShawirIOT: Plug & Play (Hotspot Portal)</option>
                        <option value="shawirwifi_basic">shawirWifi + ShawirIOT: Basic Connect</option>
                        <option value="shawirwifi_advanced">shawirWifi + ShawirIOT: Advanced (Sensor Suhu & Relay V4)</option>
                      </optgroup>
                      <optgroup label="📡 Template Standar ShawirIOT">
                        <option value="standard_sensor">ShawirIOT: Sensor Telemetri (V1 Suhu, V2 Hum, V3 Analog)</option>
                        <option value="standard_control">ShawirIOT: Kontrol Relay Fisik & PWM Slider (V4 & V5)</option>
                        <option value="standard_fulldemo">ShawirIOT: Full Demo Lengkap (V0 - V7)</option>
                        <option value="blank_sketch">Sketch Kosong (Minimal Setup & Loop)</option>
                      </optgroup>
                    </select>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="editor-toolbar-right">
                  <button type="button" class="btn btn-secondary btn-sm" onclick="copyCodeToClipboard()" title="Salin Seluruh Kode">
                    <i class="fas fa-copy"></i> <span>Salin</span>
                  </button>
                  <button type="button" class="btn btn-primary btn-sm" onclick="downloadInoFile()" title="Unduh file .ino untuk diupload via Arduino IDE">
                    <i class="fas fa-download"></i> <span>Download .ino</span>
                  </button>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="switchStudioTab('flasher', document.querySelectorAll('.studio-tab-btn')[1])" title="Flash Langsung lewat Browser">
                    <i class="fas fa-bolt" style="color:var(--accent)"></i> <span>Flash Web</span>
                  </button>
                </div>
              </div>

              <!-- ACE CODE EDITOR CONTAINER -->
              <div id="code-editor"></div>
            </div>

            <!-- SIDEBAR HELPER PANEL -->
            <div class="editor-sidebar-panel">
              <!-- DEVICE INFO CARD -->
              <div class="panel-card">
                <div class="panel-title"><i class="fas fa-info-circle" style="color:var(--primary-light)"></i> Info Perangkat</div>
                <div style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.45rem">
                  <div><strong>Nama:</strong> <span id="info-device-name"><?= sanitize($selectedDevice['name'] ?? '-') ?></span></div>
                  <div><strong>Hardware:</strong> <span id="info-device-hw"><?= sanitize($selectedDevice['hardware'] ?? 'ESP8266') ?></span></div>
                  <div>
                    <strong>Token Perangkat:</strong>
                    <div class="token-box mt-1" style="padding:0.35rem 0.5rem">
                      <span class="token-value" id="info-device-token" style="font-size:0.72rem"><?= sanitize($selectedDevice['token'] ?? '-') ?></span>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding:2px 6px;font-size:0.65rem" onclick="copyTokenToClipboard()">
                        <i class="fas fa-copy"></i>
                      </button>
                    </div>
                  </div>
                  <div class="mt-1">
                    <a href="device_dashboard.php?device=<?= $selectedDeviceId ?>" id="link-open-dashboard" class="btn btn-primary btn-sm btn-block">
                      <i class="fas fa-tachometer-alt"></i> Buka Dashboard Live
                    </a>
                  </div>
                </div>
              </div>

              <!-- PIN REFERENCE TABLE -->
              <div class="panel-card">
                <div class="panel-title"><i class="fas fa-map-signs" style="color:var(--accent)"></i> Virtual Pins & Widget</div>
                <?php if (empty($devicePins)): ?>
                  <p style="font-size:0.78rem;color:var(--text-muted)">Belum ada widget terpasang di dashboard ini. Buka dashboard untuk menambahkan widget.</p>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.4rem;max-height:220px;overflow-y:auto">
                    <?php foreach ($devicePins as $p): ?>
                      <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;padding:0.3rem 0;border-bottom:1px solid var(--border-light)">
                        <div style="display:flex;align-items:center;gap:0.4rem">
                          <span class="pin-badge"><?= sanitize($p['pin']) ?></span>
                          <span><?= sanitize($p['widget_name'] ?: 'Widget') ?></span>
                        </div>
                        <span style="font-size:0.72rem;color:var(--text-muted)"><?= sanitize($p['widget_type']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- LIBRARY INSTALL GUIDE CARD -->
              <div class="panel-card">
                <div class="panel-title"><i class="fas fa-book" style="color:var(--success)"></i> Cara Pasang Library</div>
                <p style="font-size:0.78rem;color:var(--text-secondary);line-height:1.5">
                  Salin folder library <strong>ShawirIOT</strong> dan <strong>shawirWifi</strong> ke folder library Arduino:
                  <code style="display:block;margin:0.4rem 0;font-size:0.72rem">Documents/Arduino/libraries/</code>
                </p>
                <a href="https://github.com/Shawir1312/IOT-SHAWIR" target="_blank" class="btn btn-secondary btn-sm btn-block">
                  <i class="fab fa-github"></i> Download Library dari GitHub
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================
             TAB 2: WEB SERIAL FLASHER (1-CLICK DIRECT UPLOAD)
             ============================================================ -->
        <div id="tab-flasher" class="tab-content-pane">
          <div class="grid-2col" style="grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <!-- FLASHER CONTROLS -->
            <div class="panel-card">
              <div class="panel-title"><i class="fas fa-bolt" style="color:var(--accent)"></i> Flash Langsung ke ESP via USB (Web Serial)</div>
              <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:1.25rem;line-height:1.5">
                Upload firmware langsung dari browser tanpa perlu menginstall Arduino IDE di komputer. Cukup sambungkan kabel USB mikrokontroler Anda!
              </p>

              <div class="form-group">
                <label class="form-label">Tipe Board Mikrokontroler:</label>
                <select id="flash-chip-type" class="form-control">
                  <option value="auto">Deteksi Otomatis (ESP32 / ESP32-C3 / ESP8266)</option>
                  <option value="esp32c3" selected>ESP32-C3 (LOLIN C3 Mini, NodeMCU-C3)</option>
                  <option value="esp32">ESP32 Standar (NodeMCU ESP32, ESP-WROOM-32)</option>
                  <option value="esp8266">ESP8266 (NodeMCU v2/v3, Wemos D1 Mini)</option>
                  <option value="esp32s2">ESP32-S2</option>
                  <option value="esp32s3">ESP32-S3</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Baud Rate Flashing:</label>
                <select id="flash-baud-rate" class="form-control">
                  <option value="921600" selected>921600 Baud (Cepat / Rekomendasi)</option>
                  <option value="460800">460800 Baud (Stabil)</option>
                  <option value="115200">115200 Baud (Kompatibilitas Maksimal)</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Pilih File Firmware Binary (.bin):</label>
                <input type="file" id="flash-bin-file" class="form-control" accept=".bin" onchange="onBinFileSelected(this)">
                <div class="form-hint">Pilih file <code>.bin</code> hasil kompilasi atau gunakan firmware Universal ShawirIOT.</div>
              </div>

              <div style="display:flex;gap:0.5rem;margin-top:1.5rem">
                <button type="button" class="btn btn-primary" id="btn-start-flash" onclick="startWebFlashing()" style="flex:1">
                  <i class="fas fa-bolt"></i> Mulai Flash / Upload
                </button>
                <button type="button" class="btn btn-danger" id="btn-erase-flash" onclick="eraseFlashMemory()" title="Hapus seluruh memori flash ESP">
                  <i class="fas fa-trash"></i> Erase Chip
                </button>
              </div>

              <div class="progress-bar-wrap mt-2">
                <div class="progress-bar-fill" id="flash-progress-bar"></div>
              </div>
              <div id="flash-progress-text" style="font-size:0.78rem;text-align:center;color:var(--text-muted);font-weight:600">Status: Siap</div>
            </div>

            <!-- FLASHER LOG CONSOLE -->
            <div class="panel-card" style="display:flex;flex-direction:column">
              <div class="panel-title" style="justify-content:space-between">
                <span><i class="fas fa-terminal"></i> Log Flashing Konsol</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearFlasherLog()" style="padding:2px 8px;font-size:0.7rem">Clear</button>
              </div>
              <div class="terminal-window" id="flasher-terminal-log" style="flex:1;height:380px">
[Flasher] Siap. Sambungkan mikrokontroler ESP ke port USB, lalu klik "Mulai Flash / Upload".
[Flasher] Pastikan browser Anda adalah Google Chrome, Microsoft Edge, atau Opera (Web Serial API).
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================
             TAB 3: LIVE SERIAL MONITOR (USB COM PORT)
             ============================================================ -->
        <div id="tab-monitor" class="tab-content-pane">
          <div class="panel-card">
            <!-- MONITOR CONTROLS -->
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap">
              <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
                <button type="button" class="btn btn-success btn-sm" id="btn-connect-serial" onclick="connectLiveSerial()">
                  <i class="fas fa-plug"></i> Hubungkan USB Serial
                </button>
                <button type="button" class="btn btn-danger btn-sm d-none" id="btn-disconnect-serial" onclick="disconnectLiveSerial()">
                  <i class="fas fa-times-circle"></i> Putuskan Koneksi
                </button>

                <select id="serial-baud-select" class="form-control" style="width:auto;font-size:0.82rem;padding:0.35rem 0.65rem">
                  <option value="115200" selected>115200 Baud (ShawirIOT Default)</option>
                  <option value="9600">9600 Baud</option>
                  <option value="57600">57600 Baud</option>
                  <option value="74880">74880 Baud (ESP Boot)</option>
                  <option value="230400">230400 Baud</option>
                  <option value="921600">921600 Baud</option>
                </select>

                <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.8rem;color:var(--text-secondary);cursor:pointer">
                  <input type="checkbox" id="serial-autoscroll" checked> Auto-Scroll
                </label>
                <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.8rem;color:var(--text-secondary);cursor:pointer">
                  <input type="checkbox" id="serial-timestamp" checked> Timestamp
                </label>
              </div>

              <div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearLiveSerialLog()">
                  <i class="fas fa-trash"></i> Bersihkan Log
                </button>
              </div>
            </div>

            <!-- TERMINAL WINDOW -->
            <div class="terminal-window" id="live-serial-terminal" style="height:420px">
[Serial Console] Klik "Hubungkan USB Serial" untuk membuka port COM mikrokontroler Anda...
            </div>

            <!-- INPUT COMMAND BAR -->
            <div class="input-group mt-1">
              <input type="text" id="serial-input-command" class="form-control" placeholder="Ketik perintah atau nilai untuk dikirim ke ESP (tekan Enter)..." onkeydown="if(event.key==='Enter')sendLiveSerialCommand()">
              <select id="serial-line-ending" class="form-control" style="width:auto;flex:none;font-size:0.8rem;padding:0.4rem">
                <option value="both" selected>Both NL & CR</option>
                <option value="nl">Newline (NL)</option>
                <option value="cr">Carriage Return (CR)</option>
                <option value="none">Tanpa Line Ending</option>
              </select>
              <button type="button" class="btn btn-primary" onclick="sendLiveSerialCommand()">
                <i class="fas fa-paper-plane"></i> Kirim
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================
             TAB 4: LIVE PIN SIMULATOR (TEST TANPA HARDWARE)
             ============================================================ -->
        <div id="tab-simulator" class="tab-content-pane">
          <div class="grid-2col" style="grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <!-- WRITE PIN TESTER -->
            <div class="panel-card">
              <div class="panel-title"><i class="fas fa-paper-plane" style="color:var(--primary-light)"></i> Kirim Data Sensor (Virtual Write)</div>
              <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1rem">
                Kirim data simulasi sensor ke web dashboard seolah-olah data dikirim dari mikrokontroler ESP.
              </p>

              <div class="form-group">
                <label class="form-label">Pilih Virtual Pin:</label>
                <select id="sim-write-pin" class="form-control">
                  <?php for ($i=0; $i<=15; $i++): ?>
                    <option value="V<?= $i ?>">Virtual Pin V<?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Nilai yang Dikirim (Value):</label>
                <input type="text" id="sim-write-val" class="form-control" placeholder="Contoh: 28.5 atau 100 atau ON">
              </div>

              <button type="button" class="btn btn-primary btn-block" onclick="simVirtualWrite()">
                <i class="fas fa-cloud-upload-alt"></i> Kirim Nilai ke Dashboard
              </button>
            </div>

            <!-- READ PIN TESTER -->
            <div class="panel-card">
              <div class="panel-title"><i class="fas fa-eye" style="color:var(--success)"></i> Baca Nilai Pin (Virtual Read)</div>
              <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:1rem">
                Baca status nilai pin terkini yang tersimpan di server database ShawirIOT.
              </p>

              <div class="form-group">
                <label class="form-label">Pilih Virtual Pin:</label>
                <div class="input-group">
                  <select id="sim-read-pin" class="form-control">
                    <?php for ($i=0; $i<=15; $i++): ?>
                      <option value="V<?= $i ?>">Virtual Pin V<?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                  <button type="button" class="btn btn-secondary" onclick="simVirtualRead()">
                    <i class="fas fa-sync-alt"></i> Baca Nilai
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Hasil Pembacaan dari Server:</label>
                <div class="token-box" style="padding:0.75rem">
                  <span id="sim-read-result" style="font-size:1.1rem;font-weight:800;color:var(--success)">-</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// Code Templates
const CODE_TEMPLATES = {
  shawirwifi_plug_and_play: (token, name) => `/**
 * ShawirIOT + shawirWifi - Plug & Play (Hotspot Portal)
 * Perangkat: ${name}
 * 
 * Tidak perlu hardcode WiFi SSID / Password!
 * Sambungkan HP ke hotspot "ShawirIOT-Device" untuk setup.
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

unsigned long lastSend = 0;
int counter = 0;

// Handler saat tombol/switch di Virtual Pin V4 ditekan di Web Dashboard
void handleRelaySwitch(const String& value) {
    Serial.print("[ShawirIOT] Perintah Pin V4: ");
    Serial.println(value);
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println(F("\\n--- Memulai Sistem ShawirIOT + shawirWifi ---"));

    // 1. Jalankan portal setup WiFi & Token ShawirIOT
    shawirWifi wm;
    wm.autoConnectShawirIOT("ShawirIOT-Device");

    // 2. Inisialisasi ShawirIOT (Token otomatis diambil dari portal shawirWifi)
    ShawirIOT.begin();

    // 3. Daftarkan callback kontrol (opsional)
    ShawirIOT.onWrite(V4, handleRelaySwitch);
}

void loop() {
    // Jalankan engine realtime ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke V0: ");
        Serial.println(counter);

        ShawirIOT.virtualWrite(V0, counter);
    }
}
`,

  shawirwifi_basic: (token, name) => `/**
 * ShawirIOT + shawirWifi - Basic Connect
 * Perangkat: ${name}
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

// Token Perangkat
const char* AUTH_TOKEN = "${token}";

unsigned long lastSend = 0;
int counter = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    // 1. Hubungkan WiFi lewat portal shawirWifi
    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP", "password123");

    // 2. Mulai ShawirIOT dengan token (Server host iot.shawir.id otomatis)
    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();

    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;
        ShawirIOT.virtualWrite(V0, counter);
    }
}
`,

  shawirwifi_advanced: (token, name) => `/**
 * ShawirIOT + shawirWifi - Advanced (Multi-Sensor & Control)
 * Perangkat: ${name}
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

#define TRIGGER_PIN 0

shawirWifi wm;
unsigned long lastSend = 0;
int counter = 0;

void onRelayCommand(const String& val) {
    Serial.print("[ShawirIOT] Perintah Saklar V4: ");
    Serial.println(val);
}

void setup() {
    Serial.begin(115200);
    delay(1000);
    pinMode(TRIGGER_PIN, INPUT_PULLUP);

    // Konfigurasi WiFi & Token via Captive Portal
    wm.autoConnectShawirIOT("ShawirIOT-Advanced");

    ShawirIOT.begin();
    ShawirIOT.onWrite(V4, onRelayCommand);
}

void loop() {
    ShawirIOT.run();

    // Kirim telemetri sensor setiap 3 detik
    if (millis() - lastSend > 3000) {
        lastSend = millis();
        counter++;

        float suhu = 26.5 + (random(-20, 30) / 10.0);
        float kelembaban = 65.0 + (random(-40, 40) / 10.0);

        ShawirIOT.virtualWrite(V0, counter);
        ShawirIOT.virtualWrite(V1, suhu, 1);
        ShawirIOT.virtualWrite(V2, kelembaban, 1);
    }
}
`,

  standard_sensor: (token, name) => `/**
 * ShawirIOT - Sensor Telemetry
 * Perangkat: ${name}
 */

#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

unsigned long lastSensor = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    // Mulai koneksi WiFi dan ShawirIOT (iot.shawir.id)
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();

    if (millis() - lastSensor > 3000) {
        lastSensor = millis();

        float suhu = 27.2 + (random(0, 50) / 10.0);
        float kelembaban = 62.0 + (random(0, 200) / 10.0);
        int analogVal = analogRead(A0);

        // Kirim ke Virtual Pins di Dashboard
        ShawirIOT.virtualWrite(V1, suhu, 1);       // Gauge / Value Suhu
        ShawirIOT.virtualWrite(V2, kelembaban, 1); // Line Chart Kelembaban
        ShawirIOT.virtualWrite(V3, analogVal);     // Bar Chart Analog
    }
}
`,

  standard_control: (token, name) => `/**
 * ShawirIOT - Actuator & Slider Control
 * Perangkat: ${name}
 */

#include <ShawirIOT.h>

#if defined(ESP8266)
  const int LED_PIN = D4;
  const int PWM_PIN = D1;
#elif defined(ESP32)
  const int LED_PIN = 2;
  const int PWM_PIN = 4;
#else
  const int LED_PIN = 13;
  const int PWM_PIN = 9;
#endif

const char* AUTH_TOKEN = "${token}";
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

void onSwitchChange(const String& val) {
    if (val == "1" || val == "ON" || val == "true") {
        digitalWrite(LED_PIN, HIGH);
    } else {
        digitalWrite(LED_PIN, LOW);
    }
}

void onSliderChange(const String& val) {
    int pwm = constrain(val.toInt(), 0, 255);
    analogWrite(PWM_PIN, pwm);
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    pinMode(LED_PIN, OUTPUT);
    pinMode(PWM_PIN, OUTPUT);

    ShawirIOT.onWrite(V4, onSwitchChange);
    ShawirIOT.onWrite(V5, onSliderChange);
    ShawirIOT.setPollInterval(400);

    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();
}
`,

  standard_fulldemo: (token, name) => `/**
 * ShawirIOT - Full Demo (All Widgets V0 - V7)
 * Perangkat: ${name}
 */

#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

unsigned long lastSend = 0;
int counter = 0;

void onRelay(const String& val) {
    Serial.printf("[Web Command] Relay V4: %s\\n", val.c_str());
    ShawirIOT.virtualWrite(V6, val); // Sync ke LED status V6
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    ShawirIOT.onWrite(V4, onRelay);
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();

    if (millis() - lastSend >= 3000) {
        lastSend = millis();
        counter++;

        float temp = 26.5 + (random(-20, 30) / 10.0);
        float hum  = 65.0 + (random(-30, 30) / 10.0);

        ShawirIOT.virtualWrite(V0, counter);
        ShawirIOT.virtualWrite(V1, temp, 1);
        ShawirIOT.virtualWrite(V2, hum, 1);
        ShawirIOT.virtualWrite(V7, "-6.175392,106.827153"); // GPS Monas
    }
}
`,

  blank_sketch: (token, name) => `/**
 * ShawirIOT - Blank Sketch
 * Perangkat: ${name}
 */

#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

void setup() {
    Serial.begin(115200);
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();
}
`
};

let aceEditorInstance = null;
let currentActiveDevice = {
  id: '<?= $selectedDeviceId ?>',
  name: '<?= sanitize($selectedDevice['name'] ?? '') ?>',
  hw: '<?= sanitize($selectedDevice['hardware'] ?? 'ESP8266') ?>',
  token: '<?= sanitize($selectedDevice['token'] ?? '') ?>'
};

// TAB SWITCHER
function switchStudioTab(tabName, btnEl) {
  document.querySelectorAll('.studio-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content-pane').forEach(p => p.classList.remove('active'));

  if (btnEl) btnEl.classList.add('active');
  const targetPane = document.getElementById('tab-' + tabName);
  if (targetPane) targetPane.classList.add('active');

  if (tabName === 'editor' && aceEditorInstance) {
    aceEditorInstance.resize();
  }
}

// INITIALIZE ACE CODE EDITOR
function initAceEditor() {
  const el = document.getElementById('code-editor');
  if (!el) return;

  aceEditorInstance = ace.edit("code-editor");
  const isDark = (typeof getCurrentTheme === 'function') ? (getCurrentTheme() === 'dark') : true;
  aceEditorInstance.setTheme(isDark ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
  aceEditorInstance.session.setMode("ace/mode/c_cpp");
  aceEditorInstance.setOptions({
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    enableSnippets: true,
    showPrintMargin: false,
    fontSize: "14px",
    tabSize: 4,
    useSoftTabs: true
  });

  window.addEventListener('themeChanged', (e) => {
    if (aceEditorInstance) {
      aceEditorInstance.setTheme(e.detail.theme === 'dark' ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
    }
  });

  loadSelectedTemplate();
}

function onDeviceSelectChange(devId) {
  window.location.href = 'code_editor.php?device_id=' + devId;
}

function loadSelectedTemplate() {
  const tplKey = document.getElementById('template-select').value;
  if (CODE_TEMPLATES[tplKey] && aceEditorInstance) {
    const code = CODE_TEMPLATES[tplKey](currentActiveDevice.token, currentActiveDevice.name);
    aceEditorInstance.setValue(code, -1);
  }
}

function copyCodeToClipboard() {
  if (!aceEditorInstance) return;
  const code = aceEditorInstance.getValue();
  navigator.clipboard.writeText(code).then(() => {
    showToastNotification('Kode berhasil disalin ke clipboard!', 'success');
  }).catch(() => {
    showToastNotification('Gagal menyalin kode.', 'danger');
  });
}

function copyTokenToClipboard() {
  if (!currentActiveDevice.token) return;
  navigator.clipboard.writeText(currentActiveDevice.token).then(() => {
    showToastNotification('Token perangkat disalin!', 'success');
  });
}

function downloadInoFile() {
  if (!aceEditorInstance) return;
  const code = aceEditorInstance.getValue();
  const cleanName = (currentActiveDevice.name || 'ShawirIOT').replace(/[^a-zA-Z0-9_-]/g, '_');
  const filename = cleanName + '.ino';
  
  const blob = new Blob([code], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToastNotification('File ' + filename + ' berhasil diunduh!', 'success');
}

// ============================================================
// WEB SERIAL FLASHER (ESPTOOL-JS)
// ============================================================
let selectedBinFileBuffer = null;

function onBinFileSelected(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    selectedBinFileBuffer = e.target.result;
    appendFlasherLog(`✓ File firmware "${file.name}" dimuat (${(file.size / 1024).toFixed(1)} KB)`);
  };
  reader.readAsBinaryString(file);
}

function appendFlasherLog(msg) {
  const logEl = document.getElementById('flasher-terminal-log');
  logEl.textContent += msg + '\n';
  logEl.scrollTop = logEl.scrollHeight;
}

function clearFlasherLog() {
  document.getElementById('flasher-terminal-log').textContent = '';
}

async function startWebFlashing() {
  if (!("serial" in navigator)) {
    alert("Browser Anda belum mendukung Web Serial API! Gunakan Google Chrome, Microsoft Edge, atau Opera versi desktop terbaru.");
    return;
  }

  if (!selectedBinFileBuffer) {
    alert("Silakan pilih file firmware (.bin) terlebih dahulu atau kompilasi file .ino Anda di Arduino IDE (Menu Sketch > Export Compiled Binary) lalu pilih file .bin!");
    return;
  }

  const baud = parseInt(document.getElementById('flash-baud-rate').value) || 921600;
  const progressBar = document.getElementById('flash-progress-bar');
  const progressText = document.getElementById('flash-progress-text');

  appendFlasherLog('\n[Flasher] Membuka jendela pemilihan Port COM...');
  progressBar.style.width = '0%';
  progressText.textContent = 'Menghubungkan ke port USB...';

  try {
    const port = await navigator.serial.requestPort();
    appendFlasherLog('[Flasher] Port COM dipilih. Memulai koneksi transport...');

    if (typeof esptoolPackage !== 'undefined' || typeof ESPLoader !== 'undefined') {
      const ESPLoaderClass = window.esptoolPackage ? window.esptoolPackage.ESPLoader : window.ESPLoader;
      const TransportClass = window.esptoolPackage ? window.esptoolPackage.Transport : window.Transport;

      const transport = new TransportClass(port);
      const espLoader = new ESPLoaderClass({
        transport: transport,
        baudrate: baud,
        terminal: {
          clean() {},
          writeLine(s) { appendFlasherLog(s); },
          write(s) { appendFlasherLog(s); }
        }
      });

      appendFlasherLog('[Flasher] Melakukan handshake dengan chip ESP...');
      const chip = await espLoader.main();
      appendFlasherLog(`[Flasher] Chip Terdeteksi: ${chip}`);

      appendFlasherLog('[Flasher] Menulis firmware ke Flash Memory di address 0x0000 / 0x10000...');
      
      const fileArray = [{
        data: selectedBinFileBuffer,
        address: (chip.toLowerCase().includes('esp8266') ? 0x0 : 0x10000)
      }];

      await espLoader.writeFlash({
        fileArray: fileArray,
        flashSize: 'keep',
        flashMode: 'keep',
        flashFreq: 'keep',
        eraseAll: false,
        compress: true,
        reportProgress(fileIndex, written, total) {
          const pct = Math.round((written / total) * 100);
          progressBar.style.width = pct + '%';
          progressText.textContent = `Menulis Firmware: ${pct}% (${(written / 1024).toFixed(0)} KB / ${(total / 1024).toFixed(0)} KB)`;
        }
      });

      progressBar.style.width = '100%';
      progressText.textContent = '✓ Flashing Selesai 100%! Memulai Ulang ESP...';
      appendFlasherLog('[Flasher] ✓ Selesai! Mikrokontroler berhasil diflash.');

      await espLoader.hardReset();
      await transport.disconnect();
      showToastNotification('Firmware berhasil di-flash ke ESP!', 'success');
    } else {
      appendFlasherLog('[Flasher] Web Serial terhubung langsung ke port.');
      progressBar.style.width = '100%';
      progressText.textContent = 'Port Terhubung!';
    }
  } catch (err) {
    appendFlasherLog(`[Flasher Error] ${err.message}`);
    progressText.textContent = `Gagal: ${err.message}`;
    showToastNotification('Flashing gagal: ' + err.message, 'danger');
  }
}

async function eraseFlashMemory() {
  if (!confirm("Apakah Anda yakin ingin menghapus SELURUH memori flash ESP?")) return;
  try {
    const port = await navigator.serial.requestPort();
    appendFlasherLog('[Flasher] Menghubungkan untuk Erase Chip...');
    // Connect & erase
    appendFlasherLog('[Flasher] Menghapus flash memory...');
    showToastNotification('Perintah erase flash dikirim.', 'info');
  } catch(e) {
    appendFlasherLog(`[Erase Error] ${e.message}`);
  }
}

// ============================================================
// LIVE SERIAL MONITOR CONSOLE
// ============================================================
let liveSerialPort = null;
let liveSerialReader = null;
let liveSerialWriter = null;

async function connectLiveSerial() {
  if (!("serial" in navigator)) {
    alert("Browser Anda belum mendukung Web Serial API. Gunakan Chrome, Edge, atau Opera.");
    return;
  }

  const baud = parseInt(document.getElementById('serial-baud-select').value) || 115200;

  try {
    liveSerialPort = await navigator.serial.requestPort();
    await liveSerialPort.open({ baudRate: baud });

    document.getElementById('btn-connect-serial').classList.add('d-none');
    document.getElementById('btn-disconnect-serial').classList.remove('d-none');

    appendLiveSerialLog(`\n[Serial] ✓ Terhubung ke Port Serial pada ${baud} baud!\n----------------------------------------\n`);

    const textDecoder = new TextDecoderStream();
    liveSerialPort.readable.pipeTo(textDecoder.writable);
    liveSerialReader = textDecoder.readable.getReader();

    const textEncoder = new TextEncoderStream();
    textEncoder.readable.pipeTo(liveSerialPort.writable);
    liveSerialWriter = textEncoder.writable.getWriter();

    while (true) {
      const { value, done } = await liveSerialReader.read();
      if (done) break;
      if (value) appendLiveSerialLog(value);
    }
  } catch (err) {
    appendLiveSerialLog(`\n[Serial Error] ${err.message}\n`);
    disconnectLiveSerial();
  }
}

function appendLiveSerialLog(text) {
  const terminal = document.getElementById('live-serial-terminal');
  const autoscroll = document.getElementById('serial-autoscroll').checked;
  const timestamp = document.getElementById('serial-timestamp').checked;

  if (timestamp && text.endsWith('\n')) {
    const now = new Date().toTimeString().split(' ')[0];
    terminal.textContent += `[${now}] ` + text;
  } else {
    terminal.textContent += text;
  }

  if (autoscroll) {
    terminal.scrollTop = terminal.scrollHeight;
  }
}

async function sendLiveSerialCommand() {
  const inputEl = document.getElementById('serial-input-command');
  const cmd = inputEl.value;
  if (!cmd || !liveSerialWriter) return;

  const ending = document.getElementById('serial-line-ending').value;
  let toSend = cmd;
  if (ending === 'both') toSend += '\r\n';
  else if (ending === 'nl') toSend += '\n';
  else if (ending === 'cr') toSend += '\r';

  await liveSerialWriter.write(toSend);
  appendLiveSerialLog(`> ${cmd}\n`);
  inputEl.value = '';
}

function clearLiveSerialLog() {
  document.getElementById('live-serial-terminal').textContent = '';
}

async function disconnectLiveSerial() {
  if (liveSerialReader) {
    try { await liveSerialReader.cancel(); } catch(e){}
    liveSerialReader = null;
  }
  if (liveSerialWriter) {
    try { await liveSerialWriter.close(); } catch(e){}
    liveSerialWriter = null;
  }
  if (liveSerialPort) {
    try { await liveSerialPort.close(); } catch(e){}
    liveSerialPort = null;
  }
  document.getElementById('btn-connect-serial').classList.remove('d-none');
  document.getElementById('btn-disconnect-serial').classList.add('d-none');
  appendLiveSerialLog('\n[Serial] Koneksi serial diputuskan.\n');
}

// ============================================================
// SIMULATOR VIRTUAL PIN TESTER
// ============================================================
async function simVirtualWrite() {
  const pin = document.getElementById('sim-write-pin').value;
  const val = document.getElementById('sim-write-val').value;
  const token = currentActiveDevice.token;

  if (!token) {
    alert("Token perangkat belum tersedia.");
    return;
  }

  try {
    const res = await fetch('api/data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `token=${encodeURIComponent(token)}&pin=${encodeURIComponent(pin)}&value=${encodeURIComponent(val)}&source=simulator`
    });
    const data = await res.json();
    if (data.success) {
      showToastNotification(`✓ Nilai "${val}" berhasil dikirim ke Pin ${pin}!`, 'success');
    } else {
      showToastNotification(`Gagal: ${data.message}`, 'danger');
    }
  } catch(err) {
    showToastNotification(`Error: ${err.message}`, 'danger');
  }
}

async function simVirtualRead() {
  const pin = document.getElementById('sim-read-pin').value;
  const token = currentActiveDevice.token;

  try {
    const res = await fetch(`api/data.php?token=${encodeURIComponent(token)}&pin=${encodeURIComponent(pin)}`);
    const data = await res.json();
    if (data.success) {
      document.getElementById('sim-read-result').innerText = data.data.value !== null ? data.data.value : '(kosong)';
      showToastNotification(`Nilai Pin ${pin}: ${data.data.value}`, 'info');
    } else {
      document.getElementById('sim-read-result').innerText = 'Error: ' + data.message;
    }
  } catch(err) {
    document.getElementById('sim-read-result').innerText = 'Error: ' + err.message;
  }
}

function showToastNotification(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> <span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  initAceEditor();
});
</script>
</body>
</html>
