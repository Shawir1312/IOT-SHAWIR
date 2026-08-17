/**
 * shawirWifi - Contoh OnDemand Config Portal
 * 
 * Menjalankan config portal AP secara manual (on demand), terpisah dari captive portal.
 * Tekan tombol TRIGGER_PIN untuk membuka config portal AP selama 120 detik,
 * kemudian portal akan otomatis tertutup.
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

// Pilih pin yang akan memicu portal konfigurasi saat ditekan (LOW)
#define TRIGGER_PIN 0

int batasWaktu = 120; // durasi portal aktif (detik)

void setup() {
  WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
  Serial.begin(115200);
  Serial.println("\n[shawirWifi] Memulai...");
  pinMode(TRIGGER_PIN, INPUT_PULLUP);
}

void loop() {
  // Buka portal konfigurasi jika tombol ditekan
  if (digitalRead(TRIGGER_PIN) == LOW) {
    shawirWifi wm; // buat instance lokal

    // Reset pengaturan — untuk pengujian
    // wm.resetSettings();
  
    // Atur batas waktu portal konfigurasi
    wm.setConfigPortalTimeout(batasWaktu);

    if (!wm.startConfigPortal("shawirWifi-OnDemand")) {
      Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
      delay(3000);
      // Reset dan coba lagi, atau masuk deep sleep
      ESP.restart();
      delay(5000);
    }

    // Jika sampai sini berarti sudah terhubung ke WiFi
    Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
  }

  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
