/**
 * shawirWifi + ShawirIOT: Plug & Play Zero-Code-Config Example
 * 
 * In this sketch, you DO NOT NEED to hardcode:
 * 1. WiFi SSID & Password
 * 2. ShawirIOT Server Host (Default: iot.shawir.id)
 * 3. ShawirIOT Device Token
 * 
 * Everything is configured through the modern shawirWifi Captive Portal!
 * 
 * Instructions:
 * 1. Upload this sketch to your ESP8266 or ESP32.
 * 2. Connect your smartphone/PC to the hotspot AP: "ShawirIOT-Device".
 * 3. A setup web page will appear automatically:
 *    - Select your WiFi network and enter its password.
 *    - Paste your Device Token from the ShawirIOT web dashboard.
 *    - Click "Simpan".
 * 4. The ESP will connect to WiFi and immediately stream data to ShawirIOT!
 */

#include <shawirWifi.h>
#include <ShawirIOT.h>

unsigned long lastSend = 0;
int counter = 0;

// Handler saat tombol/switch V4 ditekan di Web Dashboard
void handleRelaySwitch(const String& value) {
    Serial.print("[Web Command] Nilai Pin V4 Diterima: ");
    Serial.println(value);
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println(F("\n=========================================="));
    Serial.println(F("     shawirWifi + ShawirIOT Integrator    "));
    Serial.println(F("=========================================="));

    // 1. Jalankan portal setup WiFi & Token ShawirIOT
    shawirWifi wm;
    wm.autoConnectShawirIOT("ShawirIOT-Device");

    // 2. Inisialisasi ShawirIOT (Token otomatis diambil dari shawirWifi!)
    ShawirIOT.begin();

    // 3. Daftarkan callback kontrol (opsional)
    ShawirIOT.onWrite(V4, handleRelaySwitch);
}

void loop() {
    // Jalankan engine ShawirIOT
    ShawirIOT.run();

    // Kirim data counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke Virtual Pin V0: ");
        Serial.println(counter);

        ShawirIOT.virtualWrite(V0, counter);
    }
}
