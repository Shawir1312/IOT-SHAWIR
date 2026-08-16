/**
 * ShawirIOT - ControlLED Example
 * 
 * Demonstrates controlling physical actuators (Relay / LED / PWM)
 * from Button, Switch, and Slider widgets on the ShawirIOT dashboard.
 */

#include <ShawirIOT.h>

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

// Konfigurasi Kredensial & Server
const char* AUTH_TOKEN  = "YOUR_DEVICE_TOKEN_HERE"; 
const char* WIFI_SSID   = "YOUR_WIFI_SSID";
const char* WIFI_PASS   = "YOUR_WIFI_PASSWORD";
const char* SERVER_HOST = "iot.shawir.id";          
const uint16_t SERVER_PORT = 80;

// Handler saat tombol / switch di Virtual Pin V4 ditekan di dashboard
void handleRelaySwitch(const String& value) {
    Serial.print("[Handler] V4 Nilai Diterima: ");
    Serial.println(value);

    if (value == "1" || value == "ON" || value == "true") {
        digitalWrite(LED_PIN, HIGH);
        Serial.println("-> LED Fisik DINYALAKAN (ON)");
    } else {
        digitalWrite(LED_PIN, LOW);
        Serial.println("-> LED Fisik DIMATIKAN (OFF)");
    }
}

// Handler saat Slider di Virtual Pin V5 digeser (0 - 255)
void handleSliderBrightness(const String& value) {
    int brightness = value.toInt();
    brightness = constrain(brightness, 0, 255);

    Serial.print("[Handler] V5 Kecerahan Slider: ");
    Serial.println(brightness);

    #if defined(ESP8266)
      analogWrite(PWM_PIN, brightness * 4); // ESP8266 10-bit range (0-1023)
    #elif defined(ESP32)
      // ESP32 ledc write atau analogWrite (ESP32 Arduino core 2.0+)
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

    // Daftarkan listener untuk Virtual Pins SEBELUM begin
    ShawirIOT.onWrite(V4, handleRelaySwitch);
    ShawirIOT.onWrite(V5, handleSliderBrightness);

    // Atur interval pengecekan perintah tombol (500ms agar responsif)
    ShawirIOT.setPollInterval(500);

    // Inisialisasi koneksi
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS, SERVER_HOST, SERVER_PORT);
}

void loop() {
    ShawirIOT.run();
}
