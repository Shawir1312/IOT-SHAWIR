/**
 * shawirWifi - Contoh Anonymous Callback
 * 
 * Menunjukkan cara menggunakan callback anonim (lambda) dengan shawirWifi.
 * Callback ini dipanggil saat ESP masuk ke mode konfigurasi (AP mode).
 * 
 * Catatan: Contoh ini menggunakan resetSettings() untuk selalu memaksa
 * masuk ke mode konfigurasi (hanya untuk demo).
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

bool _sudahMasukModeKonfigurasi = false;

void setup() {
    Serial.begin(115200);
    shawirWifi wm;

    // Daftarkan callback anonim (lambda) menggunakan capture by reference [&]
    // wm.setAPCallback([this](shawirWifi* wm) { ... }); // untuk class member
    wm.setAPCallback([&](shawirWifi* wm) {
        Serial.printf("[shawirWifi] Masuk mode konfigurasi: IP=%s, SSID='%s'\n",
                        WiFi.softAPIP().toString().c_str(),
                        wm->getConfigPortalSSID().c_str());
        _sudahMasukModeKonfigurasi = true;
    });

    // Reset pengaturan agar selalu masuk ke mode konfigurasi (untuk demo)
    wm.resetSettings();

    if (!wm.autoConnect()) {
        Serial.printf("[shawirWifi] *** Gagal terhubung atau waktu habis!\n");
        ESP.restart();
        delay(1000);
    }
}

void loop() {
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
