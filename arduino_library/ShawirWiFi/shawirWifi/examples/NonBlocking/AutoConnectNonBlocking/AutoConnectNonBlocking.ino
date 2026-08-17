/**
 * shawirWifi - Contoh AutoConnect Non-Blocking
 * 
 * Menghubungkan ESP ke WiFi secara otomatis tanpa memblokir loop().
 * Portal konfigurasi berjalan di latar belakang — pastikan memanggil
 * wm.process() di setiap iterasi loop().
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

shawirWifi wm;

void setup() {
    WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
    Serial.begin(115200);
    
    // Reset pengaturan — hapus kredensial tersimpan (untuk pengujian)
    // wm.resetSettings();

    wm.setConfigPortalBlocking(false); // aktifkan mode non-blocking
    wm.setConfigPortalTimeout(60);     // tutup portal otomatis setelah 60 detik

    // Hubungkan otomatis menggunakan kredensial tersimpan.
    // Jika gagal, buka Access Point konfigurasi dengan nama yang ditentukan.
    if (wm.autoConnect("shawirWifi-AP")) {
        Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
    }
    else {
        Serial.println("[shawirWifi] Portal konfigurasi sedang berjalan...");
    }
}

void loop() {
    wm.process(); // wajib dipanggil agar portal konfigurasi bisa berjalan
    // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
