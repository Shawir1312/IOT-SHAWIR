<?php
/**
 * ShawirIOT - In-Browser Code Editor & Firmware Generator
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
if ($selectedDeviceId > 0) {
    $devicePins = DB::rows(
        "SELECT vp.pin, vp.value, vp.data_type, w.name as widget_name, w.type as widget_type
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
  <title>Editor Kode & Firmware — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <!-- Ace Editor CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.js"></script>
  <style>
    .editor-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 1.25rem;
      height: calc(100vh - 120px);
      min-height: 550px;
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
    .editor-toolbar-left {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      flex-wrap: wrap;
    }
    .editor-toolbar-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    #code-editor {
      flex: 1;
      width: 100%;
      height: 100%;
      min-height: 400px;
      font-size: 14px;
      font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
    }
    .editor-sidebar-panel {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
    }
    .panel-card {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
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
    .serial-terminal-drawer {
      display: none;
      height: 180px;
      background: #020810;
      border-top: 1px solid var(--border-light);
      padding: 0.5rem 0.75rem;
      font-family: monospace;
      font-size: 0.75rem;
      color: #50fa7b;
      overflow-y: auto;
      white-space: pre-wrap;
    }
    .serial-terminal-drawer.open { display: block; }
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
        height: auto;
      }
      #code-editor {
        min-height: 450px;
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
          <i class="fas fa-code" style="color:var(--primary-light);margin-right:0.35rem"></i>Editor Kode & Firmware
        </h1>
      </div>
      <div class="topbar-actions">
        <button type="button" class="btn btn-secondary btn-sm" onclick="openSerialTerminal()" id="btn-web-serial" title="Hubungkan USB ESP ke browser untuk Serial Monitor">
          <i class="fas fa-terminal"></i> <span>Serial Monitor</span>
        </button>
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
        <div class="editor-layout">
          <!-- MAIN CODE EDITOR CARD -->
          <div class="editor-main-card">
            <!-- TOOLBAR -->
            <div class="editor-toolbar">
              <div class="editor-toolbar-left">
                <!-- Device Selector -->
                <div>
                  <select id="device-select" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.75rem;font-weight:600" onchange="onDeviceChange(this.value)">
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
                  <select id="template-select" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.75rem" onchange="onTemplateChange(this.value)">
                    <optgroup label="🌟 Integrasi shawirWifi (Rekomendasi)">
                      <option value="shawirwifi_plug_and_play" selected>shawirWifi + ShawirIOT: Plug & Play (Hotspot Portal)</option>
                      <option value="shawirwifi_basic">shawirWifi + ShawirIOT: Basic Connect</option>
                      <option value="shawirwifi_advanced">shawirWifi + ShawirIOT: Advanced (Multi-Sensor & Relay)</option>
                    </optgroup>
                    <optgroup label="📡 Template Standar ShawirIOT">
                      <option value="standard_sensor">ShawirIOT: Sensor Telemetri (Suhu, Hum, Analog)</option>
                      <option value="standard_control">ShawirIOT: Kontrol Relay & PWM Slider</option>
                      <option value="standard_fulldemo">ShawirIOT: Full Demo Lengkap (V0 - V7)</option>
                      <option value="blank_sketch">Sketch Kosong (Minimal Setup & Loop)</option>
                    </optgroup>
                  </select>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="editor-toolbar-right">
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyCode()" title="Salin Kode ke Clipboard">
                  <i class="fas fa-copy"></i> <span>Salin</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="downloadIno()" title="Unduh file .ino untuk diupload di Arduino IDE">
                  <i class="fas fa-download"></i> <span>Download .ino</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openFlashModal()" title="Panduan Flash & Upload">
                  <i class="fas fa-bolt"></i> <span>Upload</span>
                </button>
              </div>
            </div>

            <!-- ACE CODE EDITOR CONTAINER -->
            <div id="code-editor"></div>

            <!-- SERIAL TERMINAL DRAWER -->
            <div class="serial-terminal-drawer" id="serial-terminal">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:2px">
                <span style="color:#8be9fd;font-weight:bold"><i class="fas fa-terminal"></i> Web Serial Console (115200 Baud)</span>
                <div>
                  <button class="btn btn-sm btn-secondary" style="padding:1px 6px;font-size:0.65rem" onclick="clearSerialLog()">Clear</button>
                  <button class="btn btn-sm btn-danger" style="padding:1px 6px;font-size:0.65rem" onclick="closeSerialTerminal()">Tutup</button>
                </div>
              </div>
              <div id="serial-log">Menunggu koneksi Serial USB... Klik tombol "Serial Monitor" untuk memilih port COM ESP Anda.\n</div>
            </div>
          </div>

          <!-- SIDEBAR HELPER PANEL -->
          <div class="editor-sidebar-panel">
            <!-- DEVICE INFO CARD -->
            <div class="panel-card">
              <div class="panel-title"><i class="fas fa-info-circle" style="color:var(--primary-light)"></i> Info Perangkat</div>
              <div style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.4rem">
                <div><strong>Nama:</strong> <span id="info-device-name">-</span></div>
                <div><strong>Hardware:</strong> <span id="info-device-hw">-</span></div>
                <div>
                  <strong>Token:</strong>
                  <div class="token-box mt-1" style="padding:0.35rem 0.5rem">
                    <span class="token-value" id="info-device-token" style="font-size:0.72rem">-</span>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding:2px 6px;font-size:0.65rem" onclick="copyToken()">
                      <i class="fas fa-copy"></i>
                    </button>
                  </div>
                </div>
                <div class="mt-1">
                  <a href="device_dashboard.php?id=<?= $selectedDeviceId ?>" id="link-open-dashboard" class="btn btn-secondary btn-sm btn-block">
                    <i class="fas fa-tachometer-alt"></i> Buka Dashboard Live
                  </a>
                </div>
              </div>
            </div>

            <!-- PIN REFERENCE TABLE -->
            <div class="panel-card">
              <div class="panel-title"><i class="fas fa-map-signs" style="color:var(--accent)"></i> Virtual Pins & Widget</div>
              <?php if (empty($devicePins)): ?>
                <p style="font-size:0.78rem;color:var(--text-muted)">Belum ada widget pada dashboard perangkat ini. Tambahkan widget di Dashboard.</p>
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
                Pastikan folder library <strong>ShawirIOT</strong> dan <strong>shawirWifi</strong> sudah disalin ke:
                <code style="display:block;margin:0.4rem 0;font-size:0.72rem">Documents/Arduino/libraries/</code>
              </p>
              <a href="https://github.com/Shawir1312/IOT-SHAWIR" target="_blank" class="btn btn-secondary btn-sm btn-block">
                <i class="fab fa-github"></i> Download ZIP Library
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- FLASHING GUIDE MODAL -->
<div class="modal-overlay" id="flash-modal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-bolt" style="color:var(--accent)"></i> Panduan Upload / Flashing</h3>
      <button type="button" class="modal-close" onclick="closeFlashModal()"><i class="fas fa-times"></i></button>
    </div>
    <div style="font-size:0.88rem;line-height:1.6;color:var(--text-secondary)">
      <div style="margin-bottom:1rem">
        <h4 style="color:var(--text-primary);font-size:0.95rem;margin-bottom:0.3rem">1. Upload via Arduino IDE (Rekomendasi)</h4>
        <ol style="padding-left:1.25rem">
          <li>Klik tombol <strong>"Download .ino"</strong> di atas.</li>
          <li>Buka file <code>.ino</code> tersebut dengan <strong>Arduino IDE</strong>.</li>
          <li>Pilih Board (<strong>NodeMCU 1.0 / ESP32 Dev Module</strong>) dan Port COM.</li>
          <li>Klik <strong>Upload</strong> (ikon panah kanan).</li>
        </ol>
      </div>

      <div style="margin-bottom:1rem">
        <h4 style="color:var(--text-primary);font-size:0.95rem;margin-bottom:0.3rem">2. Konfigurasi Setelah Upload (Zero-Code)</h4>
        <ol style="padding-left:1.25rem">
          <li>Hubungkan smartphone ke WiFi hotspot: <strong>ShawirIOT-Device</strong>.</li>
          <li>Pilih WiFi rumah/kantor dan masukkan Token Perangkat Anda.</li>
          <li>Klik Simpan, ESP akan langsung terhubung ke server <strong><?= PLATFORM_URL ?></strong>.</li>
        </ol>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" onclick="closeFlashModal()">Mengerti</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// Code Templates
const TEMPLATES = {
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

let editor;
let currentDevice = {
  id: '',
  name: '',
  hw: '',
  token: ''
};

// Initialize Ace Editor
function initEditor() {
  const editorEl = document.getElementById('code-editor');
  if (!editorEl) return;

  editor = ace.edit("code-editor");
  editor.setTheme(getCurrentTheme() === 'dark' ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
  editor.session.setMode("ace/mode/c_cpp");
  editor.setOptions({
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    enableSnippets: true,
    showPrintMargin: false,
    fontSize: "14px",
    tabSize: 4,
    useSoftTabs: true
  });

  // Sync theme changes
  window.addEventListener('themeChanged', (e) => {
    if (editor) {
      editor.setTheme(e.detail.theme === 'dark' ? "ace/theme/tomorrow_night_eighties" : "ace/theme/chrome");
    }
  });

  // Load initial device
  const devSelect = document.getElementById('device-select');
  if (devSelect && devSelect.selectedOptions.length > 0) {
    const opt = devSelect.selectedOptions[0];
    currentDevice = {
      id: opt.value,
      name: opt.getAttribute('data-name'),
      hw: opt.getAttribute('data-hw'),
      token: opt.getAttribute('data-token')
    };
    updateDeviceInfoUI();
    loadTemplate();
  }
}

function updateDeviceInfoUI() {
  document.getElementById('info-device-name').innerText = currentDevice.name || '-';
  document.getElementById('info-device-hw').innerText = currentDevice.hw || '-';
  document.getElementById('info-device-token').innerText = currentDevice.token || '-';
  const linkDash = document.getElementById('link-open-dashboard');
  if (linkDash) linkDash.href = 'device_dashboard.php?id=' + currentDevice.id;
}

function onDeviceChange(deviceId) {
  const devSelect = document.getElementById('device-select');
  const opt = devSelect.selectedOptions[0];
  currentDevice = {
    id: opt.value,
    name: opt.getAttribute('data-name'),
    hw: opt.getAttribute('data-hw'),
    token: opt.getAttribute('data-token')
  };
  updateDeviceInfoUI();
  loadTemplate();
}

function onTemplateChange() {
  loadTemplate();
}

function loadTemplate() {
  const tplKey = document.getElementById('template-select').value;
  if (TEMPLATES[tplKey] && editor) {
    const code = TEMPLATES[tplKey](currentDevice.token, currentDevice.name);
    editor.setValue(code, -1);
  }
}

function copyCode() {
  if (!editor) return;
  const code = editor.getValue();
  navigator.clipboard.writeText(code).then(() => {
    showToast('Kode berhasil disalin ke clipboard!', 'success');
  }).catch(() => {
    showToast('Gagal menyalin kode.', 'danger');
  });
}

function copyToken() {
  if (!currentDevice.token) return;
  navigator.clipboard.writeText(currentDevice.token).then(() => {
    showToast('Token device disalin!', 'success');
  });
}

function downloadIno() {
  if (!editor) return;
  const code = editor.getValue();
  const cleanName = (currentDevice.name || 'ShawirIOT').replace(/[^a-zA-Z0-9_-]/g, '_');
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
  showToast('File ' + filename + ' berhasil diunduh!', 'success');
}

function openFlashModal() {
  document.getElementById('flash-modal').classList.add('active');
}
function closeFlashModal() {
  document.getElementById('flash-modal').classList.remove('active');
}

// Web Serial API Console Monitor
let serialPort = null;
let serialReader = null;

async function openSerialTerminal() {
  const terminal = document.getElementById('serial-terminal');
  terminal.classList.add('open');

  if (!("serial" in navigator)) {
    appendSerialLog('Browser Anda belum mendukung Web Serial API. Gunakan Google Chrome, Microsoft Edge, atau Opera.');
    return;
  }

  try {
    serialPort = await navigator.serial.requestPort();
    await serialPort.open({ baudRate: 115200 });
    appendSerialLog('✓ Terhubung ke Port Serial pada 115200 baud!\n----------------------------------------\n');

    const textDecoder = new TextDecoderStream();
    const readableStreamClosed = serialPort.readable.pipeTo(textDecoder.writable);
    serialReader = textDecoder.readable.getReader();

    while (true) {
      const { value, done } = await serialReader.read();
      if (done) break;
      if (value) appendSerialLog(value);
    }
  } catch (err) {
    appendSerialLog('\n[Serial Error] ' + err.message + '\n');
  }
}

function appendSerialLog(text) {
  const logEl = document.getElementById('serial-log');
  logEl.textContent += text;
  const termEl = document.getElementById('serial-terminal');
  termEl.scrollTop = termEl.scrollHeight;
}

function clearSerialLog() {
  document.getElementById('serial-log').textContent = '';
}

async function closeSerialTerminal() {
  const terminal = document.getElementById('serial-terminal');
  terminal.classList.remove('open');
  if (serialReader) {
    try { await serialReader.cancel(); } catch(e){}
    serialReader = null;
  }
  if (serialPort) {
    try { await serialPort.close(); } catch(e){}
    serialPort = null;
  }
}

function showToast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> <span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  initEditor();
});
</script>
</body>
</html>
