/**
 * ShawirWiFi + ShawirIOT - Full Integration Example
 * 
 * The ultimate plug-and-play IoT sketch:
 * - WiFi configuration handled by ShawirWiFi (Captive Portal, no hardcoded WiFi password)
 * - IoT communication handled by ShawirIOT (Server host iot.shawir.id is embedded by default!)
 */

#include <ShawirWiFi.h>
#include <ShawirIOT.h>

// Salin Token Device dari menu Perangkat Saya di web ShawirIOT
const char* AUTH_TOKEN = "YOUR_DEVICE_TOKEN_HERE";

unsigned long lastSend = 0;
int counter = 0;

// Handler saat tombol/switch di Virtual Pin V4 diubah dari web dashboard
void handleSwitch(const String& value) {
    Serial.print("[Web Command] Nilai Pin V4: ");
    Serial.println(value);
}

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("\n--- Memulai Sistem ShawirIOT + ShawirWiFi ---");

    // 1. Hubungkan WiFi lewat ShawirWiFi Portal
    ShawirWiFi.autoConnect("ShawirIOT-Device");

    // 2. Inisialisasi ShawirIOT (Server iot.shawir.id tersimpan otomatis di library!)
    ShawirIOT.begin(AUTH_TOKEN);

    // 3. Daftarkan callback perintah dari dashboard (opsional)
    ShawirIOT.onWrite(V4, handleSwitch);
}

void loop() {
    // Jalankan service realtime ShawirIOT
    ShawirIOT.run();

    // Kirim data sensor/counter ke Virtual Pin V0 setiap 2 detik
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        counter++;

        Serial.print("Mengirim counter ke Virtual Pin V0: ");
        Serial.println(counter);

        ShawirIOT.virtualWrite(V0, counter);
    }
}
