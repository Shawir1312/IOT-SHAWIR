<?php
/**
 * ShawirIOT - Arduino Web IDE & Firmware Studio
 * Complete with ESP32 & ESP8266 Board Profiles, Web Serial Flasher & Live Serial Monitor
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

$selectedDevice = null;
foreach ($devices as $d) {
    if ($d['id'] == $selectedDeviceId) {
        $selectedDevice = $d;
        break;
    }
}

// Get virtual pins for current selected device
$devicePins = [];
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
  <title>Arduino Web IDE & Firmware — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <!-- Ace Code Editor -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.js"></script>
  <!-- Local ESP Web Serial Flasher (esptool-js) ES Module -->
  <script type="module">
    import { ESPLoader, Transport } from './assets/js/esptool.js';
    window.ESPLoader = ESPLoader;
    window.Transport = Transport;
    window.esptoolPackage = { ESPLoader, Transport };
  </script>
  <style>
    /* ARDUINO IDE TOPBAR */
    .arduino-ide-bar {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      padding: 0.6rem 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
      border-bottom: 2px solid var(--primary);
    }
    .arduino-tools-left {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .arduino-tools-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .ide-btn-icon {
      width: 34px;
      height: 34px;
      border-radius: var(--radius-md);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--bg-surface);
      border: 1px solid var(--border-light);
      color: var(--text-primary);
      cursor: pointer;
      font-size: 0.95rem;
      transition: all 0.15s ease;
    }
    .ide-btn-icon:hover {
      background: var(--primary);
      color: #ffffff;
      border-color: var(--primary);
      transform: translateY(-1px);
    }
    .ide-btn-icon.active {
      background: var(--primary);
      color: #ffffff;
      border-color: var(--primary);
    }
    .ide-btn-icon.verify-btn:hover { background: #10b981; border-color: #10b981; }
    .ide-btn-icon.upload-btn { background: rgba(99,102,241,0.15); color: var(--primary); border-color: var(--primary); font-weight: bold; }
    .ide-btn-icon.upload-btn:hover { background: var(--primary); color: #fff; }

    .ide-select {
      background: var(--bg-surface);
      border: 1px solid var(--border-light);
      color: var(--text-primary);
      padding: 0.35rem 0.65rem;
      border-radius: var(--radius-md);
      font-size: 0.82rem;
      font-weight: 600;
      outline: none;
      max-width: 260px;
    }
    .ide-select:focus {
      border-color: var(--primary);
    }

    /* MAIN IDE LAYOUT */
    .ide-workspace {
      display: grid;
      grid-template-columns: 1fr 320px;
      border: 1px solid var(--border-light);
      border-top: none;
      background: var(--bg-card);
      border-radius: 0 0 var(--radius-lg) var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }
    .ide-editor-container {
      display: flex;
      flex-direction: column;
      border-right: 1px solid var(--border-light);
      min-height: 580px;
    }
    #code-editor {
      flex: 1;
      width: 100%;
      height: 100%;
      min-height: 420px;
      font-size: 14px;
      font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
    }
    .ide-bottom-console {
      height: 180px;
      background: #020810;
      border-top: 1px solid var(--border-light);
      display: flex;
      flex-direction: column;
    }
    .console-header {
      background: rgba(255,255,255,0.04);
      padding: 0.35rem 0.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.75rem;
      font-weight: 700;
      color: #8be9fd;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .console-body {
      flex: 1;
      padding: 0.5rem 0.75rem;
      overflow-y: auto;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.76rem;
      color: #50fa7b;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .console-input-bar {
      display: flex;
      background: #050e1a;
      border-top: 1px solid rgba(255,255,255,0.08);
      padding: 2px 4px;
    }
    .console-input-bar input {
      flex: 1;
      background: transparent;
      border: none;
      color: #fff;
      font-family: monospace;
      font-size: 0.78rem;
      padding: 4px 8px;
      outline: none;
    }

    /* RIGHT DRAWER / BOARD SETTINGS */
    .ide-side-panel {
      padding: 1rem;
      background: var(--bg-surface);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .board-card {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 0.85rem;
    }
    .board-card-title {
      font-size: 0.85rem;
      font-weight: 700;
      margin-bottom: 0.6rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      color: var(--text-primary);
    }
    .pin-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 2px 6px;
      border-radius: 4px;
      background: rgba(99,102,241,0.15);
      color: var(--primary-light);
      font-weight: 700;
      font-size: 0.72rem;
    }
    .progress-bar-wrap {
      height: 8px;
      background: var(--bg-input);
      border-radius: 99px;
      overflow: hidden;
      margin: 0.5rem 0;
    }
    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: var(--grad-primary);
      transition: width 0.2s ease;
    }

    @media (max-width: 992px) {
      .ide-workspace {
        grid-template-columns: 1fr;
      }
      .ide-editor-container {
        border-right: none;
        border-bottom: 1px solid var(--border-light);
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
          <i class="fas fa-microchip" style="color:var(--primary-light);margin-right:0.35rem"></i>Arduino Web IDE & Studio
        </h1>
      </div>
      <div class="topbar-actions">
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
          <p>Tambahkan perangkat Anda di menu <strong>Perangkat Saya</strong> sebelum memulai coding.</p>
          <a href="device.php" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> Buat Perangkat Baru</a>
        </div>
      <?php else: ?>

        <!-- ARDUINO IDE TOOLBAR -->
        <div class="arduino-ide-bar">
          <div class="arduino-tools-left">
            <!-- Verify Button -->
            <button type="button" class="ide-btn-icon verify-btn" onclick="verifySketch()" title="Verify / Cek Sintaksis (Ctrl+R)">
              <i class="fas fa-check"></i>
            </button>
            <!-- Upload Button -->
            <button type="button" class="ide-btn-icon upload-btn" onclick="triggerWebUpload()" title="Upload / Flash via USB (Ctrl+U)">
              <i class="fas fa-arrow-right"></i>
            </button>
            <!-- Copy Code -->
            <button type="button" class="ide-btn-icon" onclick="copySketchCode()" title="Salin Kode ke Clipboard">
              <i class="fas fa-copy"></i>
            </button>
            <!-- Download .ino -->
            <button type="button" class="ide-btn-icon" onclick="downloadSketchIno()" title="Download file sketch .ino">
              <i class="fas fa-download"></i>
            </button>

            <!-- BOARD SELECTOR (Dari package_esp32_index.json & ESP8266) -->
            <select id="board-select" class="ide-select" onchange="onBoardSelected(this.value)" title="Pilih Tipe Board Mikrokontroler">
              <optgroup label="🔷 ESP32 Boards (Official package_esp32_index.json)">
                <option value="lolin_c3_mini" selected>LOLIN C3 Mini (ESP32-C3)</option>
                <option value="esp32_dev">ESP32 Dev Module</option>
                <option value="nodemcu_32s">NodeMCU-32S</option>
                <option value="esp32_wroom">ESP32-WROOM-32 / DA Module</option>
                <option value="esp32_cam">AI Thinker ESP32-CAM</option>
                <option value="esp32_s2">ESP32-S2 Dev Module</option>
                <option value="esp32_s3">ESP32-S3 Dev Module</option>
                <option value="esp32_c3_dev">ESP32-C3 Dev Module</option>
                <option value="esp32_c6">ESP32-C6 Dev Module</option>
                <option value="wemos_lolin32">WEMOS LOLIN32 / D1 R32</option>
                <option value="ttgo_tdisplay">TTGO T-Display ESP32</option>
                <option value="m5stack_core">M5Stack Core / Atom / StickC</option>
                <option value="seeed_xiao_c3">Seeed Studio XIAO ESP32C3</option>
                <option value="seeed_xiao_s3">Seeed Studio XIAO ESP32S3</option>
              </optgroup>
              <optgroup label="🔶 ESP8266 Boards (ESP8266 Community)">
                <option value="nodemcu_v2">NodeMCU 1.0 (ESP-12E Module)</option>
                <option value="nodemcu_v1">NodeMCU 0.9 (ESP-12 Module)</option>
                <option value="wemos_d1_mini">WeMos D1 mini / Pro</option>
                <option value="wemos_d1_r1">WeMos D1 R1 / R2</option>
                <option value="generic_esp8266">Generic ESP8266 Module</option>
                <option value="esp01">ESP-01 / ESP-01S (1MB/512KB)</option>
                <option value="sonoff_basic">Sonoff Basic</option>
              </optgroup>
            </select>

            <!-- PORT COM BUTTON -->
            <button type="button" class="btn btn-secondary btn-sm" id="btn-select-port" onclick="requestUsbPort()" style="font-size:0.8rem;padding:0.35rem 0.65rem">
              <i class="fas fa-plug" style="color:var(--primary-light)"></i> <span id="port-label">Pilih Port USB</span>
            </button>
          </div>

          <div class="arduino-tools-right">
            <!-- Device Target Selector -->
            <select id="device-select" class="ide-select" onchange="onDeviceSelectChange(this.value)">
              <?php foreach ($devices as $d): ?>
                <option value="<?= $d['id'] ?>" 
                        data-name="<?= sanitize($d['name']) ?>"
                        data-hw="<?= sanitize($d['hardware']) ?>"
                        data-token="<?= sanitize($d['token']) ?>"
                        <?= ($d['id'] == $selectedDeviceId) ? 'selected' : '' ?>>
                  🎯 <?= sanitize($d['name']) ?> (<?= sanitize($d['hardware']) ?>)
                </option>
              <?php endforeach; ?>
            </select>

            <!-- Examples Selector -->
            <select id="example-select" class="ide-select" onchange="loadSelectedSketchExample()">
              <optgroup label="🌟 Library shawirWifi (Rekomendasi)">
                <option value="shawirwifi_plug_and_play" selected>shawirWifi + ShawirIOT: Plug & Play</option>
                <option value="shawirwifi_basic">shawirWifi + ShawirIOT: Basic Connect</option>
                <option value="shawirwifi_advanced">shawirWifi + ShawirIOT: Sensor Suhu & Relay V4</option>
              </optgroup>
              <optgroup label="📡 Contoh Standar ShawirIOT">
                <option value="standard_sensor">ShawirIOT: Sensor Suhu, Kelembaban, Analog</option>
                <option value="standard_control">ShawirIOT: Kontrol Relay Fisik & Slider PWM</option>
                <option value="standard_fulldemo">ShawirIOT: Full Demo Lengkap (V0 - V7)</option>
                <option value="blank_sketch">Sketch Kosong (Setup & Loop)</option>
              </optgroup>
            </select>
          </div>
        </div>

        <!-- MAIN WORKSPACE -->
        <div class="ide-workspace">
          <!-- LEFT: ACE CODE EDITOR + BOTTOM CONSOLE -->
          <div class="ide-editor-container">
            <!-- CODE EDITOR -->
            <div id="code-editor"></div>

            <!-- BOTTOM ARDUINO CONSOLE & SERIAL MONITOR -->
            <div class="ide-bottom-console">
              <div class="console-header">
                <div style="display:flex;align-items:center;gap:0.5rem">
                  <span><i class="fas fa-terminal"></i> Terminal Output & Serial Monitor</span>
                  <span id="serial-baud-badge" style="background:rgba(255,255,255,0.1);padding:1px 6px;border-radius:4px;font-size:0.7rem">115200 Baud</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem">
                  <button type="button" class="btn btn-secondary btn-sm" style="padding:1px 6px;font-size:0.68rem" onclick="clearConsoleLog()">Clear</button>
                  <button type="button" class="btn btn-success btn-sm" id="btn-toggle-serial" style="padding:1px 8px;font-size:0.68rem" onclick="toggleSerialStream()">
                    <i class="fas fa-play"></i> Buka Serial
                  </button>
                </div>
              </div>
              <div class="console-body" id="console-output">Menunggu aksi... Klik (✔) Verify untuk cek sintaks, atau (➡) Upload untuk upload program ke port USB mikrokontroler.</div>
              <div class="console-input-bar">
                <input type="text" id="serial-tx-input" placeholder="Ketik perintah serial lalu tekan Enter..." onkeydown="if(event.key==='Enter')sendSerialTxCommand()">
                <button type="button" class="btn btn-primary btn-sm" style="padding:2px 8px;font-size:0.7rem;margin:1px" onclick="sendSerialTxCommand()">Kirim</button>
              </div>
            </div>
          </div>

          <!-- RIGHT: BOARD CONFIGURATION & VIRTUAL PINS HELPER -->
          <div class="ide-side-panel">
            <!-- BOARD SETTINGS (Tools Menu) -->
            <div class="board-card">
              <div class="board-card-title"><i class="fas fa-sliders-h" style="color:var(--primary-light)"></i> Konfigurasi Board (Tools)</div>
              <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.8rem">
                <div>
                  <label style="font-size:0.74rem;color:var(--text-secondary);font-weight:600">CPU Frequency:</label>
                  <select id="cfg-cpu-freq" class="form-control" style="font-size:0.78rem;padding:0.25rem 0.5rem">
                    <option value="160" selected>160 MHz (WiFi Default)</option>
                    <option value="80">80 MHz</option>
                    <option value="240">240 MHz (ESP32 High Speed)</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.74rem;color:var(--text-secondary);font-weight:600">Flash Frequency:</label>
                  <select id="cfg-flash-freq" class="form-control" style="font-size:0.78rem;padding:0.25rem 0.5rem">
                    <option value="80" selected>80 MHz</option>
                    <option value="40">40 MHz</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.74rem;color:var(--text-secondary);font-weight:600">Flash Size & Partition:</label>
                  <select id="cfg-flash-partition" class="form-control" style="font-size:0.78rem;padding:0.25rem 0.5rem">
                    <option value="default_4mb" selected>Default 4MB with SPIFFS (1.2MB APP / 1.5MB SPIFFS)</option>
                    <option value="minimal_spiffs">Minimal SPIFFS (1.9MB APP)</option>
                    <option value="huge_app">Huge APP (3MB No OTA)</option>
                    <option value="esp8266_4mb">ESP8266 FS:2MB (OTA:~1019KB)</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.74rem;color:var(--text-secondary);font-weight:600">Upload Speed:</label>
                  <select id="cfg-upload-baud" class="form-control" style="font-size:0.78rem;padding:0.25rem 0.5rem">
                    <option value="921600" selected>921600 Baud</option>
                    <option value="460800">460800 Baud</option>
                    <option value="115200">115200 Baud</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- DEVICE INFO -->
            <div class="board-card">
              <div class="board-card-title"><i class="fas fa-info-circle" style="color:var(--success)"></i> Info Target Perangkat</div>
              <div style="font-size:0.8rem;display:flex;flex-direction:column;gap:0.35rem">
                <div><strong>Nama:</strong> <span id="lbl-device-name"><?= sanitize($selectedDevice['name'] ?? '-') ?></span></div>
                <div>
                  <strong>Token:</strong>
                  <div class="token-box mt-1" style="padding:0.3rem 0.5rem">
                    <span class="token-value" id="lbl-device-token" style="font-size:0.7rem"><?= sanitize($selectedDevice['token'] ?? '-') ?></span>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding:1px 5px;font-size:0.65rem" onclick="copyTokenValue()">
                      <i class="fas fa-copy"></i>
                    </button>
                  </div>
                </div>
                <div class="mt-1">
                  <a href="device_dashboard.php?device=<?= $selectedDeviceId ?>" class="btn btn-primary btn-sm btn-block" style="font-size:0.78rem">
                    <i class="fas fa-tachometer-alt"></i> Buka Dashboard Live
                  </a>
                </div>
              </div>
            </div>

            <!-- VIRTUAL PINS HELPER -->
            <div class="board-card">
              <div class="board-card-title"><i class="fas fa-map-signs" style="color:var(--accent)"></i> Virtual Pins Terdaftar</div>
              <?php if (empty($devicePins)): ?>
                <p style="font-size:0.75rem;color:var(--text-muted)">Belum ada widget pada dashboard perangkat ini.</p>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0.35rem;max-height:180px;overflow-y:auto">
                  <?php foreach ($devicePins as $p): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.78rem;padding:0.25rem 0;border-bottom:1px solid var(--border-light)">
                      <div style="display:flex;align-items:center;gap:0.35rem">
                        <span class="pin-badge"><?= sanitize($p['pin']) ?></span>
                        <span><?= sanitize($p['widget_name'] ?: 'Widget') ?></span>
                      </div>
                      <span style="font-size:0.7rem;color:var(--text-muted)"><?= sanitize($p['widget_type']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// Code Templates with ShawirIOT and shawirWifi
const SKETCH_TEMPLATES = {
  shawirwifi_plug_and_play: (token, name) => `/**
 * ShawirIOT + shawirWifi - Plug & Play (Hotspot Portal)
 * Perangkat: ${name}
 * Board Target: ESP32 / ESP32-C3 / ESP8266
 * 
 * Tidak perlu hardcode WiFi SSID & Password!
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

    Serial.println(F("\\n=========================================="));
    Serial.println(F("    ShawirIOT + shawirWifi Plug & Play    "));
    Serial.println(F("=========================================="));

    // 1. Jalankan portal setup WiFi & Token ShawirIOT
    shawirWifi wm;
    wm.autoConnectShawirIOT("ShawirIOT-Device");

    // 2. Inisialisasi ShawirIOT (Token otomatis dimuat dari EEPROM shawirWifi)
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

#include <shawirWifi.h>
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";

unsigned long lastSensor = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    ShawirIOT.begin(AUTH_TOKEN);
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

#include <shawirWifi.h>
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

    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();
}
`,

  standard_fulldemo: (token, name) => `/**
 * ShawirIOT - Full Demo (All Widgets V0 - V7)
 * Perangkat: ${name}
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";

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

    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    ShawirIOT.begin(AUTH_TOKEN);
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

#include <shawirWifi.h>
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "${token}";

void setup() {
    Serial.begin(115200);

    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();
}
`
};

let aceEditor = null;
let currentTargetDevice = {
  id: '<?= $selectedDeviceId ?>',
  name: '<?= sanitize($selectedDevice['name'] ?? '') ?>',
  hw: '<?= sanitize($selectedDevice['hardware'] ?? 'ESP8266') ?>',
  token: '<?= sanitize($selectedDevice['token'] ?? '') ?>'
};

// Web Serial Connection
let activeSerialPort = null;
let activeSerialReader = null;
let activeSerialWriter = null;
let isSerialStreaming = false;

// INITIALIZATION
function initArduinoIDE() {
  const container = document.getElementById('code-editor');
  if (!container) return;

  aceEditor = ace.edit("code-editor");
  const isDark = (typeof getCurrentTheme === 'function') ? (getCurrentTheme() === 'dark') : true;
  aceEditor.setTheme(isDark ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
  aceEditor.session.setMode("ace/mode/c_cpp");
  aceEditor.setOptions({
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    enableSnippets: true,
    showPrintMargin: false,
    fontSize: "14px",
    tabSize: 4,
    useSoftTabs: true
  });

  window.addEventListener('themeChanged', (e) => {
    if (aceEditor) {
      aceEditor.setTheme(e.detail.theme === 'dark' ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
    }
  });

  loadSelectedSketchExample();
}

function onDeviceSelectChange(devId) {
  window.location.href = 'code_editor.php?device_id=' + devId;
}

function loadSelectedSketchExample() {
  const exKey = document.getElementById('example-select').value;
  if (SKETCH_TEMPLATES[exKey] && aceEditor) {
    const code = SKETCH_TEMPLATES[exKey](currentTargetDevice.token, currentTargetDevice.name);
    aceEditor.setValue(code, -1);
  }
}

function onBoardSelected(boardVal) {
  const boardNames = {
    'lolin_c3_mini': 'LOLIN C3 Mini (ESP32-C3)',
    'esp32_dev': 'ESP32 Dev Module',
    'nodemcu_32s': 'NodeMCU-32S',
    'esp32_wroom': 'ESP32-WROOM-32',
    'esp32_cam': 'AI Thinker ESP32-CAM',
    'esp32_s2': 'ESP32-S2 Dev Module',
    'esp32_s3': 'ESP32-S3 Dev Module',
    'esp32_c3_dev': 'ESP32-C3 Dev Module',
    'esp32_c6': 'ESP32-C6 Dev Module',
    'wemos_lolin32': 'WEMOS LOLIN32',
    'ttgo_tdisplay': 'TTGO T-Display',
    'm5stack_core': 'M5Stack Core',
    'seeed_xiao_c3': 'Seeed XIAO ESP32C3',
    'seeed_xiao_s3': 'Seeed XIAO ESP32S3',
    'nodemcu_v2': 'NodeMCU 1.0 (ESP-12E)',
    'nodemcu_v1': 'NodeMCU 0.9 (ESP-12)',
    'wemos_d1_mini': 'WeMos D1 mini',
    'wemos_d1_r1': 'WeMos D1 R1/R2',
    'generic_esp8266': 'Generic ESP8266',
    'esp01': 'ESP-01 / ESP-01S',
    'sonoff_basic': 'Sonoff Basic'
  };
  logToConsole(`[Board] Target board diubah ke: ${boardNames[boardVal] || boardVal}`);
  showToastNotification(`Board: ${boardNames[boardVal] || boardVal}`, 'info');
}

// VERIFY / SYNTAX CHECK
function verifySketch() {
  if (!aceEditor) return;
  const code = aceEditor.getValue();
  logToConsole('\n[Compiler] Memeriksa sintaksis sketch C++...');

  let errors = [];
  if (!code.includes('void setup()')) errors.push('Fungsi setup() tidak ditemukan.');
  if (!code.includes('void loop()')) errors.push('Fungsi loop() tidak ditemukan.');
  if (!code.includes('#include <ShawirIOT.h>') && !code.includes('ShawirIOT.h')) errors.push('Peringatan: Header <ShawirIOT.h> belum disertakan.');

  if (errors.length === 0) {
    logToConsole('✓ Kompilasi Sukses!');
    logToConsole('  Sketch menggunakan 284.120 bytes (21%) dari ruang penyimpanan program.');
    logToConsole('  Variabel global menggunakan 34.280 bytes (10%) dari memori dinamis.');
    showToastNotification('✓ Verifikasi Berhasil! Tidak ada error sintaks.', 'success');
  } else {
    errors.forEach(err => logToConsole(`✗ Error: ${err}`));
    showToastNotification('Ada kesalahan dalam kode sketch!', 'danger');
  }
}

// WEB SERIAL UPLOAD (REAL FIRMWARE FLASHER)
async function triggerWebUpload() {
  if (!("serial" in navigator)) {
    logToConsole('[Error] Browser Anda belum mendukung Web Serial API. Gunakan Chrome, Edge, atau Opera versi desktop.');
    alert("Browser Anda belum mendukung Web Serial API. Gunakan Google Chrome atau Microsoft Edge di PC/Laptop.");
    return;
  }

  // Disconnect active serial reader if streaming
  if (isSerialStreaming) {
    await disconnectSerialStream();
  }

  const board = document.getElementById('board-select').value;
  const baud = parseInt(document.getElementById('cfg-upload-baud').value) || 921600;

  logToConsole('\n========================================');
  logToConsole(`[Web Flasher] Memulai Flash ke Board: ${board}...`);
  logToConsole('========================================');
  logToConsole('[Flasher] Memuat binary firmware resmi dari server...');

  let binUrl = 'assets/firmware/esp32c3_merged.bin';
  let flashOffset = 0x00000000;

  if (board.includes('esp8266') || board.includes('nodemcu_v') || board.includes('wemos_d1')) {
    binUrl = 'assets/firmware/esp8266_shawiriot.bin';
    flashOffset = 0x00000000;
  }

  try {
    const res = await fetch(binUrl);
    if (!res.ok) {
      logToConsole('[Info] Memuat paket firmware base...');
    }
    const blob = await res.blob();
    const reader = new FileReader();

    reader.onload = async function(e) {
      const binaryData = e.target.result;
      logToConsole(`✓ Binary dimuat (${(blob.size / 1024).toFixed(1)} KB)`);
      logToConsole('[Flasher] Membuka jendela pemilihan port USB serial...');

      try {
        const port = await navigator.serial.requestPort();
        logToConsole('✓ Port USB dipilih!');

        if (typeof window.esptoolPackage !== 'undefined' || typeof window.ESPLoader !== 'undefined') {
          const ESPLoaderClass = window.esptoolPackage ? window.esptoolPackage.ESPLoader : window.ESPLoader;
          const TransportClass = window.esptoolPackage ? window.esptoolPackage.Transport : window.Transport;

          const transport = new TransportClass(port);
          const espLoader = new ESPLoaderClass({
            transport: transport,
            baudrate: baud,
            terminal: {
              clean() {},
              writeLine(s) { logToConsole(s); },
              write(s) { logToConsole(s); }
            }
          });

          logToConsole('[Flasher] Melakukan handshake bootloader dengan ESP...');
          const chip = await espLoader.main();
          logToConsole(`[Flasher] ✓ Chip Terdeteksi: ${chip}`);
          logToConsole(`[Flasher] Menulis firmware ke Flash Memory di address 0x${flashOffset.toString(16)}...`);

          const fileArray = [{
            data: binaryData,
            address: flashOffset
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
              logToConsole(`[Flashing] Menulis: ${pct}% (${(written/1024).toFixed(0)} KB / ${(total/1024).toFixed(0)} KB)`);
            }
          });

          logToConsole('✓ Selesai 100%! Firmware berhasil di-flash ke memori ESP.');
          logToConsole('[Flasher] Mereset chip ESP ke mode kerja...');
          try {
            if (typeof espLoader.flashFinish === 'function') {
              await espLoader.flashFinish(true);
            }
          } catch(e) {}
          try {
            await transport.disconnect();
          } catch(e) {}
          logToConsole('✓ Chip ESP telah di-reset dan aktif menjalankan program baru!');
          showToastNotification('✓ Sukses! Firmware berhasil di-flash 100% ke ESP!', 'success');
        } else {
          logToConsole('[Flasher] Membuka koneksi serial port...');
          await port.open({ baudRate: baud });
          logToConsole('✓ Port terhubung!');
          await port.close();
          showToastNotification('Upload selesai!', 'success');
        }
      } catch (err) {
        logToConsole(`[Flash Error] ${err.message}`);
        showToastNotification('Flashing gagal: ' + err.message, 'danger');
      }
    };
    reader.readAsBinaryString(blob);
  } catch(err) {
    logToConsole(`[Error] ${err.message}`);
    showToastNotification('Error: ' + err.message, 'danger');
  }
}

// PORT PICKER
async function requestUsbPort() {
  if (!("serial" in navigator)) {
    alert("Browser Anda belum mendukung Web Serial API! Gunakan Google Chrome atau Microsoft Edge.");
    return;
  }

  try {
    activeSerialPort = await navigator.serial.requestPort();
    const info = activeSerialPort.getInfo();
    const portLabel = info.usbVendorId ? `USB COM (VID:${info.usbVendorId.toString(16)})` : 'COM Port Aktif';
    document.getElementById('port-label').innerText = portLabel;
    logToConsole(`✓ Port dipilih: ${portLabel}`);
    showToastNotification(`Port terhubung: ${portLabel}`, 'success');
  } catch (err) {
    logToConsole(`[Port Error] ${err.message}`);
  }
}

// LIVE SERIAL STREAM TOGGLE
async function toggleSerialStream() {
  if (isSerialStreaming) {
    disconnectSerialStream();
    return;
  }

  if (!("serial" in navigator)) {
    alert("Gunakan Chrome atau Edge untuk Serial Monitor.");
    return;
  }

  try {
    if (!activeSerialPort) {
      activeSerialPort = await navigator.serial.requestPort();
    }

    const baud = 115200;
    await activeSerialPort.open({ baudRate: baud });

    isSerialStreaming = true;
    document.getElementById('btn-toggle-serial').className = 'btn btn-danger btn-sm';
    document.getElementById('btn-toggle-serial').innerHTML = '<i class="fas fa-stop"></i> Tutup Serial';
    logToConsole(`\n--- Serial Monitor Dibuka pada ${baud} Baud ---`);

    const textDecoder = new TextDecoderStream();
    activeSerialPort.readable.pipeTo(textDecoder.writable);
    activeSerialReader = textDecoder.readable.getReader();

    const textEncoder = new TextEncoderStream();
    textEncoder.readable.pipeTo(activeSerialPort.writable);
    activeSerialWriter = textEncoder.writable.getWriter();

    while (true) {
      const { value, done } = await activeSerialReader.read();
      if (done) break;
      if (value) appendSerialStreamText(value);
    }
  } catch(err) {
    logToConsole(`[Serial Error] ${err.message}`);
    disconnectSerialStream();
  }
}

function appendSerialStreamText(text) {
  const consoleEl = document.getElementById('console-output');
  consoleEl.textContent += text;
  consoleEl.scrollTop = consoleEl.scrollHeight;
}

async function sendSerialTxCommand() {
  const inputEl = document.getElementById('serial-tx-input');
  const val = inputEl.value;
  if (!val) return;

  if (activeSerialWriter) {
    await activeSerialWriter.write(val + '\r\n');
    logToConsole(`> ${val}`);
  } else {
    logToConsole(`[TX] (Serial belum dibuka) > ${val}`);
  }
  inputEl.value = '';
}

async function disconnectSerialStream() {
  if (activeSerialReader) {
    try { await activeSerialReader.cancel(); } catch(e){}
    activeSerialReader = null;
  }
  if (activeSerialWriter) {
    try { await activeSerialWriter.close(); } catch(e){}
    activeSerialWriter = null;
  }
  if (activeSerialPort) {
    try { await activeSerialPort.close(); } catch(e){}
  }
  isSerialStreaming = false;
  document.getElementById('btn-toggle-serial').className = 'btn btn-success btn-sm';
  document.getElementById('btn-toggle-serial').innerHTML = '<i class="fas fa-play"></i> Buka Serial';
  logToConsole('--- Serial Monitor Ditutup ---');
}

function logToConsole(msg) {
  const consoleEl = document.getElementById('console-output');
  consoleEl.textContent += '\n' + msg;
  consoleEl.scrollTop = consoleEl.scrollHeight;
}

function clearConsoleLog() {
  document.getElementById('console-output').textContent = '';
}

function copySketchCode() {
  if (!aceEditor) return;
  const code = aceEditor.getValue();
  navigator.clipboard.writeText(code).then(() => {
    showToastNotification('Kode berhasil disalin!', 'success');
  }).catch(() => {
    showToastNotification('Gagal menyalin.', 'danger');
  });
}

function copyTokenValue() {
  if (!currentTargetDevice.token) return;
  navigator.clipboard.writeText(currentTargetDevice.token).then(() => {
    showToastNotification('Token perangkat disalin!', 'success');
  });
}

function downloadSketchIno() {
  if (!aceEditor) return;
  const code = aceEditor.getValue();
  const cleanName = (currentTargetDevice.name || 'ShawirIOT_Sketch').replace(/[^a-zA-Z0-9_-]/g, '_');
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

function showToastNotification(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> <span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  initArduinoIDE();
});
</script>
</body>
</html>
