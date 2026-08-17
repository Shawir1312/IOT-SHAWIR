/**
 * ShawirIOT - BasicConnect Example
 * 
 * Demonstrates how to connect an ESP8266 or ESP32 to ShawirIOT platform
 * and send a simple counter value to Virtual Pin V0.
 * 
 * Server host "iot.shawir.id" is embedded directly in the library by default.
 */

#include <ShawirIOT.h>

// Salin Token Device dari web ShawirIOT (menu Perangkat Saya)
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE"; 
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

unsigned long lastSend = 0;
int counter = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    // Inisialisasi koneksi ShawirIOT (otomatis terhubung ke server resmi iot.shawir.id)
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    // Jalankan service ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke Virtual Pin V0: ");
        Serial.println(counter);

        // Kirim ke Virtual Pin V0
        ShawirIOT.virtualWrite(V0, counter);
    }
}
