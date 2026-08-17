/**
 * ShawirIOT - Complete ESP32 / ESP8266 Full Demo
 * 
 * Demonstrates ALL features:
 * - V0: Counter / Heartbeat (Value Display)
 * - V1: Temperature °C (Gauge)
 * - V2: Humidity % (Line Chart)
 * - V3: Potentiometer (Bar Chart)
 * - V4: Remote Relay Switch (Toggle Switch / Button)
 * - V5: PWM Slider (Slider)
 * - V6: Status LED Indicator (LED Widget)
 * - V7: GPS Location (Map Widget)
 */

#include <ShawirIOT.h>

// Salin Token Device dari web ShawirIOT (menu Perangkat Saya)
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE"; 
const char* WIFI_SSID  = "YOUR_WIFI_SSID";
const char* WIFI_PASS  = "YOUR_WIFI_PASSWORD";

#if defined(ESP8266)
  const int LED_PIN = D4;
#elif defined(ESP32)
  const int LED_PIN = 2;
#else
  const int LED_PIN = 13;
#endif

unsigned long lastSend = 0;
int counter = 0;
bool relayState = false;

// Callback: Saat user menekan tombol/switch V4 di web
void onRelayChange(const String& val) {
    relayState = (val == "1" || val == "ON" || val == "true");
    digitalWrite(LED_PIN, relayState ? HIGH : LOW);
    
    // Update status LED widget di dashboard (V6)
    ShawirIOT.virtualWrite(V6, relayState ? "1" : "0");
    
    Serial.printf("[Command] Relay diubah ke: %s\n", relayState ? "ON" : "OFF");
}

// Callback: Saat user menggeser slider V5
void onSliderChange(const String& val) {
    int pwmVal = val.toInt();
    Serial.printf("[Command] Slider PWM: %d\n", pwmVal);
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    pinMode(LED_PIN, OUTPUT);
    digitalWrite(LED_PIN, LOW);

    // Daftarkan listener kontrol
    ShawirIOT.onWrite(V4, onRelayChange);
    ShawirIOT.onWrite(V5, onSliderChange);
    ShawirIOT.setPollInterval(400); // Cek perintah setiap 400ms

    // Hubungkan ke server resmi ShawirIOT (iot.shawir.id)
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();

    // Kirim telemetri sensor setiap 3 detik
    if (millis() - lastSend >= 3000) {
        lastSend = millis();
        counter++;

        // 1. Data Simulasi Sensor
        float temp = 26.5 + (random(-20, 40) / 10.0);  // Suhu 24.5 - 30.5 °C
        float hum  = 65.0 + (random(-50, 50) / 10.0);  // Kelembaban 60 - 70 %
        int potVal = random(0, 1024);                   // Nilai analog 0 - 1023

        // 2. Kirim ke Dashboard ShawirIOT
        ShawirIOT.virtualWrite(V0, counter);
        ShawirIOT.virtualWrite(V1, temp, 1);
        ShawirIOT.virtualWrite(V2, hum, 1);
        ShawirIOT.virtualWrite(V3, potVal);

        // 3. Kirim Koordinat GPS untuk Widget Map (Format: "latitude,longitude")
        // Contoh: Monas Jakarta (-6.175392, 106.827153)
        ShawirIOT.virtualWrite(V7, "-6.175392,106.827153");

        Serial.printf("[Telemetri #%d] Suhu: %.1f °C | Kelembaban: %.1f %% | Analog: %d\n", 
                      counter, temp, hum, potVal);
    }
}
