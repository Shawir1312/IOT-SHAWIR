/**
 * ShawirIOT - SendSensor Example
 * 
 * Demonstrates reading sensors (Suhu, Kelembaban, Potensiometer/LDR)
 * and streaming them to Gauge & Line Chart widgets on the ShawirIOT dashboard.
 */

#include <ShawirIOT.h>

// Konfigurasi Kredensial & Server
const char* AUTH_TOKEN  = "YOUR_DEVICE_TOKEN_HERE"; 
const char* WIFI_SSID   = "YOUR_WIFI_SSID";
const char* WIFI_PASS   = "YOUR_WIFI_PASSWORD";
const char* SERVER_HOST = "192.168.1.100";          
const uint16_t SERVER_PORT = 80;

unsigned long lastSensorRead = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    // Hubungkan ke WiFi dan ShawirIOT Platform
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS, SERVER_HOST, SERVER_PORT);
}

void loop() {
    ShawirIOT.run();

    // Baca sensor dan kirim ke dashboard setiap 3 detik
    if (millis() - lastSensorRead > 3000) {
        lastSensorRead = millis();

        // Simulasi pembacaan suhu & kelembaban (atau gunakan library DHT / BME280)
        float temperature = 25.0 + random(0, 100) / 10.0; // 25.0 - 35.0 °C
        float humidity    = 60.0 + random(0, 300) / 10.0; // 60.0 - 90.0 %
        int analogVal     = analogRead(A0);               // Pin Analog A0

        Serial.println("--- Update Data Sensor ---");
        Serial.printf("Suhu: %.2f °C -> V1\n", temperature);
        Serial.printf("Kelembaban: %.2f %% -> V2\n", humidity);
        Serial.printf("Analog A0: %d -> V3\n", analogVal);

        // Kirim ke Virtual Pins di Dashboard
        ShawirIOT.virtualWrite(V1, temperature, 1); // V1: Gauge / Value Suhu
        ShawirIOT.virtualWrite(V2, humidity, 1);    // V2: Line Chart Kelembaban
        ShawirIOT.virtualWrite(V3, analogVal);      // V3: Value Display / Bar Chart
    }
}
