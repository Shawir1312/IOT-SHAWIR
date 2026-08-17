/**
 * shawirWifi - Contoh OnDemand Non-Blocking
 * 
 * Menjalankan web portal atau config portal secara manual dan non-blocking.
 * Tekan tombol TRIGGER_PIN untuk membuka portal selama 120 detik,
 * kemudian portal akan otomatis tertutup.
 * 
 * Jika startAP = true, maka config portal AP + web portal akan dibuka.
 * Jika startAP = false, hanya web portal yang dibuka (ESP harus sudah terhubung WiFi).
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

// Sertakan MDNS untuk hostname lokal
#ifdef ESP8266
#include <ESP8266mDNS.h>
#elif defined(ESP32)
#include <ESPmDNS.h>
#endif

// Pilih pin yang akan memicu portal konfigurasi saat ditekan (LOW)
#define TRIGGER_PIN 0

shawirWifi wm;

unsigned int  batasWaktu  = 120; // durasi portal aktif (detik)
unsigned int  waktuMulai  = millis();
bool portalBerjalan       = false;
bool startAP              = false; // true = buka AP + webserver, false = webserver saja

void setup() {
  WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
  Serial.begin(115200);
  Serial.setDebugOutput(true);
  delay(1000);
  Serial.println("\n[shawirWifi] Memulai...");

  pinMode(TRIGGER_PIN, INPUT_PULLUP);

  // wm.resetSettings(); // hapus pengaturan WiFi tersimpan
  wm.setHostname("MDNS-shawirWifi");
  // wm.setEnableConfigPortal(false);
  // wm.setConfigPortalBlocking(false);
  wm.autoConnect(); // hubungkan otomatis menggunakan kredensial tersimpan
}

void loop() {
  #ifdef ESP8266
  MDNS.update(); // perbarui MDNS di setiap loop (hanya ESP8266)
  #endif
  kelolaportal();
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}

void kelolaportal() {
  // Proses portal jika sedang berjalan
  if (portalBerjalan) {
    wm.process(); // proses request web portal

    // Cek apakah sudah melewati batas waktu
    if ((millis() - waktuMulai) > (batasWaktu * 1000)) {
      Serial.println("[shawirWifi] Waktu portal habis, menutup portal...");
      portalBerjalan = false;
      if (startAP) {
        wm.stopConfigPortal();
      } else {
        wm.stopWebPortal();
      }
    }
  }

  // Cek apakah tombol ditekan untuk membuka portal
  if (digitalRead(TRIGGER_PIN) == LOW && (!portalBerjalan)) {
    if (startAP) {
      Serial.println("[shawirWifi] Tombol ditekan — Membuka Config Portal...");
      wm.setConfigPortalBlocking(false);
      wm.startConfigPortal();
    } else {
      Serial.println("[shawirWifi] Tombol ditekan — Membuka Web Portal...");
      wm.startWebPortal();
    }
    portalBerjalan = true;
    waktuMulai = millis();
  }
}
