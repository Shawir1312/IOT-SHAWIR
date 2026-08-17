/**
 * shawirWifi - Contoh OnDemand Web Portal
 * 
 * Menjalankan web portal secara manual (selalu NON-blocking).
 * Tekan tombol TRIGGER_PIN sekali untuk membuka portal,
 * tekan lagi untuk menutupnya.
 * 
 * Catatan: Web portal hanya bisa berjalan jika ESP sudah terhubung ke WiFi.
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

// Pilih pin yang akan memicu portal web saat ditekan (LOW)
#define TRIGGER_PIN 0

shawirWifi wm;

bool portalBerjalan = false;

void setup() {
  WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
  Serial.begin(115200);
  Serial.println("\n[shawirWifi] Memulai...");
  pinMode(TRIGGER_PIN, INPUT_PULLUP);
}

void loop() {
  cekTombol();
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}

void cekTombol() {
  // Proses portal jika sedang berjalan
  if (portalBerjalan) {
    wm.process();
  }

  // Cek apakah tombol ditekan
  if (digitalRead(TRIGGER_PIN) == LOW) {
    delay(50); // debounce sederhana
    if (digitalRead(TRIGGER_PIN) == LOW) {
      if (!portalBerjalan) {
        Serial.println("[shawirWifi] Tombol ditekan — Membuka Web Portal...");
        wm.startWebPortal();
        portalBerjalan = true;
      }
      else {
        Serial.println("[shawirWifi] Tombol ditekan — Menutup Web Portal...");
        wm.stopWebPortal();
        portalBerjalan = false;
      }
    }
  }
}
