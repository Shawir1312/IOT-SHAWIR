/**
 * ShawirIOT - SendSensor Example (dengan shawirWifi)
 * 
 * Demonstrates reading sensors (Suhu, Kelembaban, Analog Potensiometer)
 * and streaming them to Gauge, Line Chart, and Bar Chart widgets on ShawirIOT dashboard.
 */

#include <shawirWifi.h> // Library WiFi Manager shawirWifi
#include <ShawirIOT.h>  // Library ShawirIOT Platform

// Salin Token Device dari menu Perangkat Saya di web dashboard ShawirIOT
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE";

unsigned long lastSensorRead = 0;

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println(F("\n[ShawirIOT] Inisialisasi Sensor & WiFi..."));

    // 1. Hubungkan WiFi lewat portal shawirWifi
    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    // 2. Hubungkan ke platform ShawirIOT (server iot.shawir.id otomatis)
    ShawirIOT.begin(AUTH_TOKEN);
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

        Serial.println(F("--- Update Data Sensor ---"));
        Serial.printf("Suhu: %.2f °C -> Pin V1\n", temperature);
        Serial.printf("Kelembaban: %.2f %% -> Pin V2\n", humidity);
        Serial.printf("Analog A0: %d -> Pin V3\n", analogVal);

        // Kirim ke Virtual Pins di Dashboard ShawirIOT
        ShawirIOT.virtualWrite(V1, temperature, 1); // V1: Gauge / Value Display Suhu
        ShawirIOT.virtualWrite(V2, humidity, 1);    // V2: Line Chart Kelembaban
        ShawirIOT.virtualWrite(V3, analogVal);      // V3: Value Display / Bar Chart
    }
}
