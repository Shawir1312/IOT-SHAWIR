/**
 * ShawirIOT - BasicConnect Example
 * 
 * Demonstrates how to connect an ESP8266 or ESP32 to ShawirIOT platform
 * and send a simple counter value to Virtual Pin V0.
 */

#include <ShawirIOT.h>

// Ganti dengan konfigurasi Anda
const char* AUTH_TOKEN  = "YOUR_DEVICE_TOKEN_HERE"; // Salin dari dashboard web ShawirIOT
const char* WIFI_SSID   = "YOUR_WIFI_SSID";
const char* WIFI_PASS   = "YOUR_WIFI_PASSWORD";
const char* SERVER_HOST = "192.168.1.100";          // IP server lokal atau domain (cth: "iot.anda.com")
const uint16_t SERVER_PORT = 80;                    // Port web server (default 80)

unsigned long lastSend = 0;
int counter = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    // Inisialisasi koneksi ShawirIOT
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS, SERVER_HOST, SERVER_PORT);
}

void loop() {
    // Jalankan service ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke V0: ");
        Serial.println(counter);

        // Kirim ke Virtual Pin V0
        ShawirIOT.virtualWrite(V0, counter);
    }
}
