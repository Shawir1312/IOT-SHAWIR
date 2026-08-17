/**
 * ShawirIOT - BasicConnect Example (dengan shawirWifi)
 * 
 * Menghubungkan ESP8266 / ESP32 ke WiFi secara otomatis menggunakan library shawirWifi,
 * dan langsung streaming data counter ke Virtual Pin V0 di platform ShawirIOT.
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
    Serial.println(F("     ShawirIOT + shawirWifi Basic Setup   "));
    Serial.println(F("=========================================="));

    // 1. Hubungkan WiFi via portal hotspot shawirWifi (tanpa hardcode password wifi!)
    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    // 2. Inisialisasi ShawirIOT dengan token (otomatis terhubung ke server resmi iot.shawir.id)
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

        // Kirim ke Virtual Pin V0 di Web Dashboard ShawirIOT
        ShawirIOT.virtualWrite(V0, counter);
    }
}
