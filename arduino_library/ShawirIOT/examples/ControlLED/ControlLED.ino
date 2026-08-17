/**
 * ShawirIOT - ControlLED Example (dengan shawirWifi)
 * 
 * Demonstrates controlling physical actuators (Relay / LED / PWM Slider)
 * from Button, Switch, and Slider widgets on the ShawirIOT dashboard.
 */

#include <shawirWifi.h> // Library WiFi Manager shawirWifi
#include <ShawirIOT.h>  // Library ShawirIOT Platform

// Definisi Pin Fisik
#if defined(ESP8266)
  const int LED_PIN = D4; // Built-in LED on NodeMCU / Wemos D1 mini (Active LOW)
  const int PWM_PIN = D1; // Pin PWM untuk kontrol kecerahan
#elif defined(ESP32)
  const int LED_PIN = 2;  // Built-in LED on ESP32
  const int PWM_PIN = 4;  // Pin PWM LED
#else
  const int LED_PIN = 13;
  const int PWM_PIN = 9;
#endif

// Salin Token Device dari web dashboard ShawirIOT (menu Perangkat Saya)
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE";

// Handler saat tombol / switch di Virtual Pin V4 ditekan di dashboard
void handleRelaySwitch(const String& value) {
    Serial.print(F("[ShawirIOT] V4 Perintah Diterima: "));
    Serial.println(value);

    if (value == "1" || value == "ON" || value == "true") {
        digitalWrite(LED_PIN, HIGH);
        Serial.println(F("-> LED / Relay Fisik DINYALAKAN (ON)"));
    } else {
        digitalWrite(LED_PIN, LOW);
        Serial.println(F("-> LED / Relay Fisik DIMATIKAN (OFF)"));
    }
}

// Handler saat Slider di Virtual Pin V5 digeser (0 - 255)
void handleSliderBrightness(const String& value) {
    int brightness = constrain(value.toInt(), 0, 255);

    Serial.print(F("[ShawirIOT] V5 Kecerahan PWM: "));
    Serial.println(brightness);

    #if defined(ESP8266)
      analogWrite(PWM_PIN, brightness * 4); // ESP8266 10-bit range (0-1023)
    #elif defined(ESP32)
      analogWrite(PWM_PIN, brightness);
    #else
      analogWrite(PWM_PIN, brightness);
    #endif
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    pinMode(LED_PIN, OUTPUT);
    pinMode(PWM_PIN, OUTPUT);
    digitalWrite(LED_PIN, LOW);

    // 1. Daftarkan listener kontrol Virtual Pins
    ShawirIOT.onWrite(V4, handleRelaySwitch);
    ShawirIOT.onWrite(V5, handleSliderBrightness);
    ShawirIOT.setPollInterval(400); // Polling responsif 400ms

    // 2. Hubungkan WiFi via shawirWifi
    shawirWifi wm;
    wm.autoConnect("shawirWifi-AP");

    // 3. Mulai ShawirIOT (server host iot.shawir.id otomatis)
    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();
}
