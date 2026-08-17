/**
 * shawirWifi - Contoh Parameter dengan LittleFS
 * 
 * Contoh dasar menggunakan LittleFS untuk menyimpan data konfigurasi
 * secara persisten di flash storage ESP.
 * 
 * Tekan tombol TRIGGER_PIN untuk membuka portal konfigurasi secara on-demand.
 */

#include <Arduino.h>
#include <LittleFS.h>
#include <FS.h>

// Fungsi membaca file dari LittleFS
String bacaFile(fs::FS &fs, const char * path) {
  Serial.printf("Membaca file: %s\r\n", path);
  File file = fs.open(path, "r");
  if (!file || file.isDirectory()) {
    Serial.println("- file kosong atau gagal dibuka");
    return String();
  }
  Serial.println("- isi file:");
  String isiFile;
  while (file.available()) {
    isiFile += String((char)file.read());
  }
  file.close();
  Serial.println(isiFile);
  return isiFile;
}

// Fungsi menulis file ke LittleFS
void tulisFile(fs::FS &fs, const char * path, const char * pesan) {
  Serial.printf("Menulis file: %s\r\n", path);
  File file = fs.open(path, "w");
  if (!file) {
    Serial.println("- gagal membuka file untuk ditulis");
    return;
  }
  if (file.print(pesan)) {
    Serial.println("- file berhasil ditulis");
  } else {
    Serial.println("- gagal menulis file");
  }
  file.close();
}

int data = 4;

#include <shawirWifi.h> // library shawirWifi by Shawir
#define TRIGGER_PIN 2
int batasWaktu = 120; // durasi portal aktif (detik)

void setup() {
  Serial.begin(115200);
  
  // Inisialisasi LittleFS
  if (!LittleFS.begin()) {
    Serial.println("[LittleFS] Gagal mount LittleFS!");
    return;
  }
  
  // Baca data tersimpan dari file
  data = bacaFile(LittleFS, "/data.txt").toInt();
  
  WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
  pinMode(TRIGGER_PIN, INPUT_PULLUP);
  
  shawirWifi wm;
  // wm.resetSettings(); // hapus pengaturan WiFi tersimpan (untuk pengujian)
  
  bool hasil;
  hasil = wm.autoConnect("shawirWifi-Setup");
  if (!hasil) {
    Serial.println("[shawirWifi] Gagal terhubung ke WiFi!");
    // ESP.restart();
  }
}

void loop() {
  // Buka portal konfigurasi jika tombol ditekan
  if (digitalRead(TRIGGER_PIN) == LOW) {
    shawirWifi wm;
    // wm.resetSettings(); // hapus pengaturan WiFi tersimpan (untuk pengujian)
    wm.setConfigPortalTimeout(batasWaktu);
    if (!wm.startConfigPortal("shawirWifi-OnDemand")) {
      Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
      delay(3000);
      ESP.restart();
      delay(5000);
    }
    Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
  }
}