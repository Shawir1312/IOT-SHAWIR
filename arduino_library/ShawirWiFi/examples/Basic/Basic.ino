/**
 * shawirWifi + ShawirIOT - Contoh Dasar (Basic)
 * 
 * Menghubungkan ESP ke jaringan WiFi secara otomatis menggunakan shawirWifi
 * dan langsung menghubungkan perangkat ke platform IoT ShawirIOT.
 * 
 * Server host "iot.shawir.id" (Port 80) sudah tertanam otomatis di library.
 */

#include <shawirWifi.h> // Library WiFi Manager shawirWifi
#include <ShawirIOT.h>  // Library ShawirIOT Platform

// Salin Token Device dari menu Perangkat Saya di web dashboard ShawirIOT
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE";

unsigned long lastSend = 0;
int counter = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println(F("\n=========================================="));
    Serial.println(F("    shawirWifi + ShawirIOT Basic Setup    "));
    Serial.println(F("=========================================="));

    // 1. Hubungkan WiFi secara otomatis atau buka hotspot "shawirWifi-AP"
    shawirWifi wm;
    bool hasil = wm.autoConnect("shawirWifi-AP", "password123");

    if (!hasil) {
        Serial.println(F("[shawirWifi] Gagal terhubung ke WiFi atau portal timeout!"));
        // ESP.restart();
    } else {
        Serial.println(F("[shawirWifi] Berhasil terhubung ke WiFi!"));
    }

    // 2. Mulai ShawirIOT dengan token device (Server otomatis ke iot.shawir.id)
    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    // 3. Jalankan service realtime ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print(F("Mengirim counter ke Virtual Pin V0: "));
        Serial.println(counter);

        // Kirim nilai counter ke Pin V0 di Web Dashboard ShawirIOT
        ShawirIOT.virtualWrite(V0, counter);
    }
}
