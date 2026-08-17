# ShawirIOT & ShawirWiFi Arduino Libraries

Koleksi library resmi Arduino / PlatformIO untuk platform **ShawirIOT** (ESP8266 & ESP32).

---

## 📦 1. ShawirIOT Library (`arduino_library/ShawirIOT`)
Library utama untuk komunikasi data sensor dan aktuator secara real-time ke web dashboard ShawirIOT.

### Keunggulan:
- **Server Host Otomatis**: Secara default sudah tertanam domain `iot.shawir.id` (Port 80), jadi tidak perlu menulis `SERVER_HOST` berulang-ulang di sketch.
- **Kompatibel Penuh dengan ShawirWiFi**: Jika koneksi WiFi ditangani oleh `ShawirWiFi`, inisialisasi cukup dengan `ShawirIOT.begin(AUTH_TOKEN)`.
- **Dukungan Virtual Pins**: Mendukung pengiriman dan pembacaan data `V0` - `V255` (`virtualWrite`, `virtualRead`, `onWrite`).

### Cara Penggunaan Cepat:
```cpp
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "TOKEN_DEVICE_ANDA";
const char* WIFI_SSID  = "Nama_WiFi";
const char* WIFI_PASS  = "Password_WiFi";

void setup() {
    Serial.begin(115200);
    // Server iot.shawir.id tersimpan otomatis!
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();
    ShawirIOT.virtualWrite(V0, millis() / 1000);
    delay(2000);
}
```

---

## 📶 2. ShawirWiFi Library (`arduino_library/ShawirWiFi`)
Library Smart WiFi Manager & Captive Portal untuk ESP8266 dan ESP32.

### Keunggulan:
- **Tanpa Hardcode Password WiFi**: Mengatur WiFi melalui Web Portal Captive Portal modern di smartphone/laptop saat pertama kali dinyalakan.
- **Tersimpan di Memori Flash (EEPROM)**: Menghubungkan secara otomatis ke WiFi yang telah disimpan saat booting berikutnya.
- **Hotspot Konfigurasi Responsif**: Hotspot `ShawirWiFi-Setup` (IP `192.168.4.1`) dengan scan daftar WiFi terdekat.

### Cara Penggunaan Bersama ShawirIOT:
```cpp
#include <ShawirWiFi.h>
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "TOKEN_DEVICE_ANDA";

void setup() {
    Serial.begin(115200);

    // 1. Setup WiFi via Captive Portal (Hotspot: "ShawirIOT-Device")
    ShawirWiFi.autoConnect("ShawirIOT-Device");

    // 2. Mulai ShawirIOT (Server otomatis ke iot.shawir.id)
    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();
}
```

---

## 📁 Cara Install Library ke Arduino IDE:
1. Salin folder `ShawirIOT` dan `ShawirWiFi` ke direktori library Arduino Anda:
   - **Windows**: `Documents\Arduino\libraries\`
   - **macOS**: `~/Documents/Arduino/libraries/`
2. Restart Arduino IDE.
3. Buka menu **File > Examples > ShawirIOT** atau **ShawirWiFi**.
