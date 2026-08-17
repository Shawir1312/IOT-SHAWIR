/**
 * ShawirIOT + shawirWifi Example
 * 
 * Demonstrates combining your custom shawirWifi library with ShawirIOT:
 * 1. WiFi setup handled by shawirWifi (Captive Portal, no hardcoded WiFi password)
 * 2. IoT communication handled by ShawirIOT (Server host "iot.shawir.id" is default!)
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

// Salin Token Device dari web ShawirIOT (menu Perangkat Saya)
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE";

unsigned long lastSend = 0;
int counter = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("\n--- Memulai Sistem shawirWifi + ShawirIOT ---");

    // 1. Inisialisasi WiFi Manager shawirWifi (Portal Hotspot AP)
    shawirWifi wm;
    bool res = wm.autoConnect("shawirWifi-AP"); // Hotspot: shawirWifi-AP (IP 192.168.4.1)

    if (!res) {
        Serial.println("Gagal terhubung ke WiFi atau timeout.");
        // ESP.restart();
    } else {
        Serial.println("WiFi berhasil terhubung via shawirWifi!");
    }

    // 2. Inisialisasi ShawirIOT hanya dengan Token (Server host iot.shawir.id otomatis!)
    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    // Jalankan service ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke V0: ");
        Serial.println(counter);

        ShawirIOT.virtualWrite(V0, counter);
    }
}
