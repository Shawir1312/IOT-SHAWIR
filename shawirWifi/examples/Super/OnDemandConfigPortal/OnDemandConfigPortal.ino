/**
 * shawirWifi - Contoh Super (Unit Test / Pengujian Lengkap)
 * 
 * File ini berfungsi sebagai pengujian komprehensif untuk pengembangan.
 * Berisi hampir semua method publik shawirWifi.
 * 
 * Fitur yang diuji:
 * - Parameter kustom (teks, checkbox, radio, IP, password, select)
 * - Callback (AP, webserver, simpan WiFi, simpan parameter, OTA)
 * - Route kustom di webserver
 * - Dark mode
 * - Menu kustom
 * - On-demand config portal via tombol
 * - OTA update
 * - Sinkronisasi waktu NTP
 */
#include <shawirWifi.h> // library shawirWifi by Shawir
#include <time.h>
#include <stdio.h>

#define PAKAI_OTA
// Aktifkan OTA (Over The Air update)
#ifdef PAKAI_OTA
#include <WiFiUdp.h>
#include <ArduinoOTA.h>
#endif

const char* modes[] = { "NULL", "STA", "AP", "STA+AP" };

unsigned long mtime = 0;

shawirWifi wm;

// ======================= OPSI PENGUJIAN =======================
bool TEST_CP         = false; // selalu buka config portal meskipun AP ditemukan
int  TESP_CP_TIMEOUT = 90;   // timeout config portal (detik)

bool TEST_NET        = true; // lakukan uji jaringan setelah konek (ambil waktu NTP)
bool ALLOWONDEMAND   = true; // aktifkan on-demand portal
int  ONDDEMANDPIN    = 0;    // nomor pin GPIO untuk tombol
bool WMISBLOCKING    = true; // gunakan mode blocking atau non-blocking
                              // parameter global tidak bekerja di mode non-blocking

uint8_t BUTTONFUNC   = 1;   // 0=reset settings, 1=buka config portal, 2=autoconnect


// ========================= CALLBACK ==========================

// Dipanggil saat WiFi berhasil disimpan
void saveWifiCallback() {
  Serial.println("[CALLBACK] saveWifiCallback dipanggil");
}

// Dipanggil saat shawirWifi masuk mode konfigurasi (AP aktif)
void configModeCallback(shawirWifi *myWm) {
  Serial.println("[CALLBACK] configModeCallback dipanggil");
  // myWm->setAPStaticIPConfig(IPAddress(10,0,1,1), IPAddress(10,0,1,1), IPAddress(255,255,255,0));
  // Serial.println(WiFi.softAPIP());
  // Jika menggunakan nama AP otomatis, print SSID-nya:
  // Serial.println(myWm->getConfigPortalSSID());
}

// Dipanggil saat parameter disimpan
void saveParamCallback() {
  Serial.println("[CALLBACK] saveParamCallback dipanggil");
  // wm.stopConfigPortal();
}

// Dipanggil saat webserver siap — daftarkan route kustom di sini
void bindServerCallback() {
  wm.server->on("/custom", handleRoute);

  // Kamu bisa override endpoint bawaan wm, meski belum ada cara menghapus handler.
  // wm.server->on("/info", handleNotFound);
  // wm.server->on("/update", handleNotFound);
  wm.server->on("/erase", handleNotFound); // nonaktifkan tombol erase
}

// Handler untuk route kustom /custom
void handleRoute() {
  Serial.println("[HTTP] Route kustom /custom dipanggil");
  wm.server->send(200, "text/plain", "Halo dari kode pengguna!");
}

// Handler untuk override route bawaan
void handleNotFound() {
  Serial.println("[HTTP] Override route dipanggil");
  wm.handleNotFound();
}

// Dipanggil tepat sebelum OTA update dimulai
void handlePreOtaUpdateCallback() {
  Update.onProgress([](unsigned int progress, unsigned int total) {
    Serial.printf("[OTA] Progress: %u%%\r", (progress / (total / 100)));
  });
}

// ========================== SETUP ============================
void setup() {
  // WiFi.mode(WIFI_STA); // atur mode WiFi ke STA saja

  Serial.begin(115200);
  delay(3000);
  // Serial.setDebugOutput(true);

  Serial.println("\n[shawirWifi] Memulai...");
  // WiFi.setSleepMode(WIFI_NONE_SLEEP); // nonaktifkan sleep untuk stabilitas AP

  // Contoh output serial berbeda level:
  Serial.println("Error - TEST");
  Serial.println("Informasi - TEST");
  Serial.println("[ERROR] TEST");
  Serial.println("[INFORMASI] TEST");

  // Aktifkan debug output
  wm.setDebugOutput(true, WM_DEBUG_DEV);
  wm.debugPlatformInfo();

  // Reset pengaturan WiFi — untuk pengujian
  // wm.resetSettings();
  // wm.erase();

  // =================== PARAMETER KUSTOM ====================

  // HTML kustom murni (tanpa input)
  shawirWifiParameter custom_html("<p style=\"color:pink;font-weight:Bold;\">Ini adalah HTML Kustom</p>");

  // Input teks biasa
  shawirWifiParameter custom_mqtt_server("server",    "Server MQTT",  "", 40);
  shawirWifiParameter custom_mqtt_port(  "port",      "Port MQTT",    "", 6);
  shawirWifiParameter custom_token(      "api_token", "Token API",    "", 16);

  // ID tidak valid (mengandung spasi) — akan diabaikan
  shawirWifiParameter custom_tokenb("invalid token", "Token Tidak Valid", "", 0);

  // Input IP Address dengan validasi pola (regex pattern)
  shawirWifiParameter custom_ipaddress("input_ip", "Input Alamat IP", "", 15,
    "pattern='\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}'");

  // Input password tersembunyi
  shawirWifiParameter custom_input_type("input_pwd", "Input Password", "", 15, "type='password'");

  // Checkbox
  const char _customHtml_checkbox[] = "type=\"checkbox\"";
  shawirWifiParameter custom_checkbox("my_checkbox", "Centang Saya", "T", 2,
    _customHtml_checkbox, WFM_LABEL_AFTER);

  // HTML kustom kompleks (radio + select)
  const char *bufferStr = R"(
  <!-- PILIHAN RADIO -->
  <br/>
  <p>Pilih Opsi</p>
  <input style='display: inline-block;' type='radio' id='pilihan1' name='program_selection' value='1'>
  <label for='pilihan1'>Pilihan 1</label><br/>
  <input style='display: inline-block;' type='radio' id='pilihan2' name='program_selection' value='2'>
  <label for='pilihan2'>Pilihan 2</label><br/>

  <!-- SELECT DROPDOWN -->
  <br/>
  <label for='input_select'>Label Dropdown</label>
  <select name="input_select" id="input_select" class="button">
  <option value="0">Opsi 1</option>
  <option value="1" selected>Opsi 2</option>
  <option value="2">Opsi 3</option>
  <option value="3">Opsi 4</option>
  </select>
  )";

  shawirWifiParameter custom_html_inputs(bufferStr);

  // =================== DAFTARKAN CALLBACK ====================
  wm.setAPCallback(configModeCallback);
  wm.setWebServerCallback(bindServerCallback);
  wm.setSaveConfigCallback(saveWifiCallback);
  wm.setSaveParamsCallback(saveParamCallback);
  wm.setPreOtaUpdateCallback(handlePreOtaUpdateCallback);

  // =================== TAMBAHKAN PARAMETER ===================
  wm.addParameter(&custom_html);
  wm.addParameter(&custom_mqtt_server);
  wm.addParameter(&custom_mqtt_port);
  wm.addParameter(&custom_token);
  wm.addParameter(&custom_tokenb);
  wm.addParameter(&custom_ipaddress);
  wm.addParameter(&custom_checkbox);
  wm.addParameter(&custom_input_type);
  wm.addParameter(&custom_html_inputs);

  // Set nilai parameter secara programatis
  custom_html.setValue("test", 4);
  custom_token.setValue("test", 4);

  // HTML kustom di dalam <head> (contoh: favicon, meta tag)
  // const char* headhtml = "<link rel='icon' type='image/png' href='data:image/...' />";
  // const char* headhtml = "<meta name='color-scheme' content='dark light'><style></style><script></script>";
  // wm.setCustomHeadElement(headhtml);

  // HTML kustom untuk item menu "custom"
  const char* menuhtml = "<form action='/custom' method='get'><button>Kustom</button></form><br/>\n";
  wm.setCustomMenuHTML(menuhtml);

  // Aktifkan dark mode
  wm.setDarkMode(true);

  // Tampilkan RSSI sebagai persentase (bukan ikon)
  // wm.setScanDispPerc(true);

  // Menu portal kustom via array atau vector
  // const char* menu[] = {"wifi","wifinoscan","info","param","close","sep","erase","restart","exit"};
  // wm.setMenu(menu, 9);
  std::vector<const char *> menu = {"wifi","wifinoscan","info","param","custom","close","sep","erase","update","restart","exit"};
  // wm.setMenu(menu);

  // Pisahkan halaman parameter dari halaman WiFi
  // wm.setParamsPage(true); // jangan kombinasikan dengan setMenu()!

  // IP statis STA (opsional)
  // wm.setSTAStaticIPConfig(IPAddress(10,0,1,99), IPAddress(10,0,1,1), IPAddress(255,255,255,0));
  // wm.setShowStaticFields(false);
  // wm.setShowDnsFields(false);

  // IP statis AP (opsional)
  // wm.setAPStaticIPConfig(IPAddress(10,0,1,1), IPAddress(10,0,1,1), IPAddress(255,255,255,0));

  // Atur kode negara WiFi (CN default, US memiliki channel berbeda)
  // wm.setCountry("US"); // bisa crash di esp32 2.0

  // Atur hostname
  // wm.setHostname(("shawirWifi_" + wm.getDefaultAPName()).c_str());
  // wm.setHostname("shawirWifi_1234");

  // Atur channel AP kustom
  // wm.setWiFiAPChannel(13);

  // Sembunyikan AP
  // wm.setAPHidden(true);

  // Tampilkan password tersimpan di form (perhatikan keamanan!)
  // wm.setShowPassword(true);

  // Mode blocking atau non-blocking
  // wm.setConfigPortalBlocking(false);
  if (!WMISBLOCKING) {
    wm.setConfigPortalBlocking(false);
  }

  // Timeout config portal (detik)
  wm.setConfigPortalTimeout(TESP_CP_TIMEOUT);

  // Kualitas sinyal minimum yang ditampilkan (default 8%)
  // wm.setMinimumSignalQuality(50);

  // Timeout koneksi WiFi
  // wm.setConnectTimeout(20);

  // Jumlah percobaan koneksi ulang
  // wm.setConnectRetries(2);

  // Jangan hubungkan setelah portal simpan, hanya simpan saja
  // wm.setSaveConnect(false);

  // Selalu tampilkan kolom IP statis
  // wm.setShowStaticFields(true);

  // Diperlukan agar saveWifiCallback berjalan
  wm.setBreakAfterConfig(true);

  // Port webserver kustom (captive portal tidak bekerja dengan port kustom!)
  // wm.setHttpPort(8080);

  // Preload kredensial untuk pengujian
  // wm.preloadWiFi("ssid","password");

  infoWifi();

  // Hubungkan otomatis; jika gagal buka config portal (blocking)
  if (!wm.autoConnect("shawirWifi-AP", "12345678")) {
    Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
  }
  else if (TEST_CP) {
    // Selalu buka config portal (untuk pengujian)
    delay(1000);
    Serial.println("[shawirWifi] TEST_CP aktif — membuka config portal...");
    wm.setConfigPortalTimeout(TESP_CP_TIMEOUT);
    wm.startConfigPortal("shawirWifi-Test", "12345678");
  }
  else {
    // Berhasil terhubung ke WiFi
    Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
  }

  infoWifi();
  pinMode(ONDDEMANDPIN, INPUT_PULLUP);

  #ifdef PAKAI_OTA
    ArduinoOTA.begin();
  #endif
}

// ======================== FUNGSI INFO WIFI ====================
void infoWifi() {
  // Catatan: bisa berisi data sampah di ESP32 jika WiFi belum siap
  Serial.println("[WiFi] ===== INFO WiFi =====");
  WiFi.printDiag(Serial);
  Serial.println("[WiFi] Mode    : " + (String)(wm.getModeString(WiFi.getMode())));
  Serial.println("[WiFi] Tersimpan: " + (String)(wm.getWiFiIsSaved() ? "YA" : "TIDAK"));
  Serial.println("[WiFi] SSID    : " + (String)wm.getWiFiSSID());
  Serial.println("[WiFi] Pass    : " + (String)wm.getWiFiPass());
  // Serial.println("[WiFi] Hostname: " + (String)WiFi.getHostname());
}

// ========================== LOOP ==============================
void loop() {
  if (!WMISBLOCKING) {
    wm.process(); // proses portal jika non-blocking
  }

  #ifdef PAKAI_OTA
  ArduinoOTA.handle();
  #endif

  // Cek apakah tombol ditekan untuk on-demand portal
  if (ALLOWONDEMAND && digitalRead(ONDDEMANDPIN) == LOW) {
    delay(100);
    if (digitalRead(ONDDEMANDPIN) == LOW || BUTTONFUNC == 2) {
      Serial.println("[shawirWifi] TOMBOL DITEKAN");

      // Fungsi 0: reset pengaturan dan restart
      if (BUTTONFUNC == 0) {
        wm.resetSettings();
        wm.reboot();
        delay(200);
        return;
      }

      // Fungsi 1: buka config portal on-demand
      if (BUTTONFUNC == 1) {
        if (!wm.startConfigPortal("shawirWifi-OnDemand", "12345678")) {
          Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
          delay(3000);
        }
        return;
      }

      // Fungsi 2: autoconnect (sebagai reconnect, dll)
      if (BUTTONFUNC == 2) {
        wm.setConfigPortalTimeout(TESP_CP_TIMEOUT);
        wm.autoConnect();
        return;
      }
    }
    else {
      // Berhasil terhubung ke WiFi
      Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
      ambilWaktu();
    }
  }

  // Jalankan setiap 10 detik
  if (millis() - mtime > 10000) {
    if (WiFi.status() == WL_CONNECTED) {
      ambilWaktu();
    }
    else {
      Serial.println("[WiFi] Tidak terhubung ke WiFi");
    }
    mtime = millis();
  }

  // Tulis kode utama di sini, akan dijalankan berulang-ulang
  delay(100);
}

// =================== FUNGSI AMBIL WAKTU NTP ==================
void ambilWaktu() {
  int zona_waktu  = 7;  // WIB (UTC+7)
  int dst         = 0;
  time_t sekarang = time(nullptr);
  unsigned batasWaktu = 5000; // batas waktu tunggu (ms)
  unsigned mulai = millis();
  configTime(zona_waktu * 3600, dst * 3600, "pool.ntp.org", "time.nist.gov");
  Serial.print("[NTP] Menunggu sinkronisasi waktu NTP: ");
  while (sekarang < 8 * 3600 * 2) {
    delay(100);
    Serial.print(".");
    sekarang = time(nullptr);
    if ((millis() - mulai) > batasWaktu) {
      Serial.println("\n[ERROR] Gagal mendapatkan waktu NTP.");
      return;
    }
  }
  Serial.println("");
  struct tm infoWaktu;
  gmtime_r(&sekarang, &infoWaktu);
  Serial.print("[NTP] Waktu saat ini (UTC): ");
  Serial.print(asctime(&infoWaktu));
}

// =================== FUNGSI DEBUG CHIP ID ====================
void debugchipid() {
  // WiFi.mode(WIFI_STA);
  // WiFi.printDiag(Serial);
  // Serial.println(modes[WiFi.getMode()]);
  
  // ESP.eraseConfig();
  // wm.resetSettings();
  // wm.erase(true);
  WiFi.mode(WIFI_AP);
  // WiFi.softAP();
  WiFi.enableAP(true);
  delay(500);
  delay(1000);
  WiFi.printDiag(Serial);
  delay(60000);
  ESP.restart();
}
