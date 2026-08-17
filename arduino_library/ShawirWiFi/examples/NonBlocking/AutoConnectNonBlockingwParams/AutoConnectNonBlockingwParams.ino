/**
 * shawirWifi - Contoh AutoConnect Non-Blocking dengan Parameter
 * 
 * Menghubungkan ESP ke WiFi secara non-blocking, sekaligus menambahkan
 * parameter kustom (contoh: alamat server MQTT) ke portal konfigurasi.
 * Nilai parameter dapat dibaca setelah portal ditutup.
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

shawirWifi wm;
// Parameter kustom: ID, label tampilan, nilai default, panjang maks
shawirWifiParameter param_server_mqtt("server", "Alamat Server MQTT", "", 40);

void setup() {
    WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
    Serial.begin(115200);
    
    // Reset pengaturan — hapus kredensial tersimpan (untuk pengujian)
    // wm.resetSettings();

    wm.addParameter(&param_server_mqtt);         // tambahkan parameter ke portal
    wm.setConfigPortalBlocking(false);            // aktifkan mode non-blocking
    wm.setSaveParamsCallback(saveParamsCallback); // callback saat parameter disimpan

    // Hubungkan otomatis menggunakan kredensial tersimpan.
    // Jika gagal, buka Access Point konfigurasi.
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

// Callback dipanggil ketika parameter disimpan oleh pengguna
void saveParamsCallback() {
  Serial.println("[shawirWifi] Parameter berhasil disimpan:");
  Serial.print("  ");
  Serial.print(param_server_mqtt.getID());
  Serial.print(" : ");
  Serial.println(param_server_mqtt.getValue());
}
