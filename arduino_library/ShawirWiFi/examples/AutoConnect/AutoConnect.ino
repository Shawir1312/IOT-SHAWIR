/**
 * ShawirWiFi - AutoConnect Example
 * 
 * Demonstrates how to use ShawirWiFi to connect an ESP8266 or ESP32
 * to WiFi without hardcoding SSID and Password in the sketch.
 * 
 * How it works:
 * 1. ESP boots and tries to connect to previously saved WiFi.
 * 2. If no WiFi is saved or connection fails, it opens an Access Point (hotspot):
 *    SSID: "ShawirWiFi-Setup" (IP: 192.168.4.1)
 * 3. Connect your smartphone/laptop to "ShawirWiFi-Setup", select your WiFi from
 *    the list, input password, and click "Hubungkan WiFi".
 * 4. ESP saves credentials to flash memory and connects automatically!
 */

#include <ShawirWiFi.h>

void setup() {
    Serial.begin(115200);
    delay(1000);

    // Otomatis terhubung ke WiFi tersimpan atau buka portal "ShawirWiFi-Setup"
    if (ShawirWiFi.autoConnect("ShawirWiFi-Setup")) {
        Serial.println("WiFi berhasil terhubung!");
        Serial.print("IP Address: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("Gagal terhubung ke WiFi.");
    }
}

void loop() {
    // Kode program Anda di sini...
}
