/**
 * ShawirWiFi - ResetSettings Example
 * 
 * Clears saved WiFi credentials from flash memory so that the ESP
 * will open the captive portal on the next boot.
 */

#include <ShawirWiFi.h>

void setup() {
    Serial.begin(115200);
    delay(2000);

    Serial.println("Mereset data konfigurasi WiFi tersimpan...");
    ShawirWiFi.resetSettings();
    Serial.println("Selesai! Silakan upload sketch utama Anda.");
}

void loop() {
}
