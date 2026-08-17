/**
 * shawirWifi + ShawirIOT - Contoh Lanjutan (Advanced)
 * 
 * Berisi opsi konfigurasi lanjutan:
 * - Tombol TRIGGER_PIN: tekan sekali untuk membuka portal konfigurasi,
 *   tahan 3 detik untuk reset semua pengaturan WiFi.
 * - Integrasi penuh dengan ShawirIOT platform.
 * - Mode blocking dan non-blocking.
 * - Parameter custom (input Token / HTML).
 * - Tema gelap (dark mode) & menu portal kustom.
 */

#include <shawirWifi.h> // Library shawirWifi by Shawir
#include <ShawirIOT.h>  // Library ShawirIOT Platform

#define TRIGGER_PIN 0

// Mode non-blocking (ubah ke true jika ingin portal berjalan asynchronous di background)
bool wm_nonblocking = false; 

shawirWifi wm; 
unsigned long lastSend = 0;
int counter = 0;

// Handler saat relay / tombol virtual pin V4 ditekan di Web Dashboard
void onRelayCommand(const String& val) {
    Serial.print(F("[ShawirIOT] Perintah Pin V4 Diterima: "));
    Serial.println(val);
}

void setup() {
    WiFi.mode(WIFI_STA);
    Serial.begin(115200);
    Serial.setDebugOutput(true);
    delay(1000);

    Serial.println(F("\n[shawirWifi] Memulai Advanced Setup..."));
    pinMode(TRIGGER_PIN, INPUT_PULLUP);

    if (wm_nonblocking) wm.setConfigPortalBlocking(false);

    // Menu portal kustom
    std::vector<const char *> menu = {"wifi","info","param","sep","restart","exit"};
    wm.setMenu(menu);

    // Aktifkan tema gelap (dark mode)
    wm.setClass("invert");
    wm.setConfigPortalTimeout(120); // Batas waktu portal 120 detik

    // 1. Hubungkan WiFi & Ambil Token ShawirIOT via Captive Portal
    bool hasil = wm.autoConnectShawirIOT("ShawirIOT-Advanced", "password123");

    if (!hasil) {
        Serial.println(F("[shawirWifi] Gagal terhubung ke WiFi atau portal timeout!"));
    } else {
        Serial.println(F("[shawirWifi] Berhasil terhubung ke WiFi!"));
        
        // 2. Mulai ShawirIOT (Token diambil otomatis dari portal!)
        ShawirIOT.begin();
        ShawirIOT.onWrite(V4, onRelayCommand);
    }
}

void cekTombol() {
    if (digitalRead(TRIGGER_PIN) == LOW) {
        delay(50);
        if (digitalRead(TRIGGER_PIN) == LOW) {
            Serial.println(F("[shawirWifi] Tombol ditekan"));
            delay(3000);
            if (digitalRead(TRIGGER_PIN) == LOW) {
                Serial.println(F("[shawirWifi] Tombol ditahan 3s — Menghapus pengaturan WiFi & Token..."));
                wm.resetSettings();
                wm.eraseShawirToken();
                ESP.restart();
            }
            
            Serial.println(F("[shawirWifi] Membuka portal konfigurasi on-demand..."));
            wm.setConfigPortalTimeout(120);
            if (wm.startConfigPortal("ShawirIOT-OnDemand", "password123")) {
                Serial.println(F("[shawirWifi] Berhasil terhubung kembali!"));
                ShawirIOT.begin();
            }
        }
    }
}

void loop() {
    if (wm_nonblocking) wm.process();
    cekTombol();

    // Jalankan engine realtime ShawirIOT
    ShawirIOT.run();

    // Kirim telemetri sensor setiap 3 detik
    if (millis() - lastSend > 3000) {
        lastSend = millis();
        counter++;

        float suhu = 26.0 + random(0, 80) / 10.0;
        float kelembaban = 60.0 + random(0, 300) / 10.0;

        Serial.printf("[Telemetri] Counter: %d | Suhu: %.1f °C | Hum: %.1f %%\n", counter, suhu, kelembaban);

        ShawirIOT.virtualWrite(V0, counter);
        ShawirIOT.virtualWrite(V1, suhu, 1);
        ShawirIOT.virtualWrite(V2, kelembaban, 1);
    }
}
