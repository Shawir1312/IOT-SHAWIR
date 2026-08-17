# ⚡ ShawirIOT & shawirWifi Arduino Libraries

[![Arduino Compatible](https://img.shields.io/badge/Arduino-Compatible-00979C?style=for-the-badge&logo=arduino&logoColor=white)](https://www.arduino.cc/)
[![ESP32 Supported](https://img.shields.io/badge/ESP32-Supported-E7352C?style=for-the-badge&logo=espressif&logoColor=white)](https://www.espressif.com/)
[![ESP8266 Supported](https://img.shields.io/badge/ESP8266-Supported-4183C4?style=for-the-badge&logo=espressif&logoColor=white)](https://www.espressif.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)
[![GitHub release](https://img.shields.io/badge/Release-v1.1.0-blue?style=for-the-badge)](https://github.com/Shawir1312/IOT-LIBLARY)

Koleksi library resmi Arduino / ESP8266 / ESP32 untuk ekosistem platform **ShawirIOT**. Library ini memudahkan integrasi mikrokontroler ke platform ShawirIOT untuk streaming sensor telemetri, kontrol aktuator dua arah, serta konfigurasi WiFi pintar tanpa hardcode password via Captive Portal.

---

## 🌟 Apa yang Ada di Repositori Ini?

Repositori ini memuat **2 library utama** dan kumpulan **contoh sketch (examples)** siap pakai:

| Library | Deskripsi | Arsitektur |
| :--- | :--- | :--- |
| **📦 [ShawirIOT](ShawirIOT/)** | Library komunikasi data telemetri sensor & kontrol aktuator dua arah via Virtual Pins (`V0` - `V255`). Default server: `iot.shawir.id`. | ESP8266, ESP32, Arduino WiFi |
| **📶 [shawirWifi](shawirWifi/)** | Smart WiFi Manager & Captive Portal dengan dukungan kustom konfigurasi dan input Token ShawirIOT langsung dari Web UI Smartphone. | ESP8266, ESP32 |

---

## 📂 Struktur Direktori

```text
arduino_library/
├── ShawirIOT/                      # Core ShawirIOT Library
│   ├── ShawirIOT.h                 # Header file & Virtual Pin definitions (V0-V255)
│   ├── ShawirIOT.cpp               # Implementation (HTTP/REST & Polling engine)
│   ├── keywords.txt                # Arduino IDE syntax highlighting
│   ├── library.properties          # Arduino library metadata
│   └── examples/                   # Library examples
│       ├── BasicConnect/           # Basic connection & counter stream
│       ├── ControlLED/             # Relay & PWM Slider control
│       ├── SendSensor/             # Multi-sensor telemetry (Temp, Hum, Analog)
│       ├── ESP32_FullDemo/         # Complete showcase with GPS Map
│       └── WithShawirWiFi/         # shawirWifi + ShawirIOT integration
├── shawirWifi/                     # Smart WiFi Manager Library
│   ├── shawirWifi.h                # WiFi Manager Header & ShawirIOT helper
│   ├── WiFiManager.h / .cpp        # Core Captive Portal engine
│   ├── wm_strings_*.h              # Multi-language support (ID, EN, FR, DE, ES, PT)
│   ├── library.properties          # Metadata
│   └── examples/                   # WiFi configuration examples
│       ├── ShawirIOT_PlugAndPlay/  # Zero-code config (WiFi + Token via Portal)
│       ├── Basic/                  # Standalone auto-connect
│       └── Advanced/               # Custom parameters & callbacks
├── LICENSE                         # MIT License
└── README.md                       # Dokumentasi Lengkap
```

---

## 🚀 Fitur Unggulan

- **🌐 Server Cloud Otomatis**: Secara default sudah tertanam domain `iot.shawir.id` (Port 80). Tidak perlu menulis ulang alamat IP atau domain server di sketch Anda.
- **⚡ Kompatibel Virtual Pins (V0 - V255)**: Pola pemrograman mirip Blynk (`ShawirIOT.virtualWrite(V1, 28.5)` dan `ShawirIOT.onWrite(V4, callback)`).
- **📱 Plug & Play Captive Portal (`shawirWifi`)**: Hubungkan perangkat ke WiFi baru dan atur Token Device langsung dari smartphone tanpa perlu meng-upload ulang sketch ke ESP!
- **🔄 Auto-Reconnect & Failover**: Otomatis menyambung kembali jika koneksi internet terputus.
- **📊 Multi-Widget Support**: Mendukung Gauge, Line Chart, Bar Chart, Value Display, Toggle Switch, Push Button, Slider (PWM), LED Indicator, dan GPS Map Widget.

---

## 📥 Panduan Instalasi ke Arduino IDE

### Cara 1: Manual Copy (Direkomendasikan)
1. Download atau clone repositori ini:
   ```bash
   git clone https://github.com/Shawir1312/IOT-LIBLARY.git
   ```
2. Salin folder `ShawirIOT` dan folder `shawirWifi` ke folder `libraries` Arduino Anda:
   - **Windows**: `C:\Users\<Nama_User>\Documents\Arduino\libraries\`
   - **macOS**: `~/Documents/Arduino/libraries/`
   - **Linux**: `~/Arduino/libraries/`
3. Restart software **Arduino IDE**.
4. Buka menu: **File > Examples > ShawirIOT** atau **File > Examples > shawirWifi**.

### Cara 2: Install via ZIP di Arduino IDE
1. Download repositori ini sebagai ZIP dari GitHub (`Code > Download ZIP`).
2. Di Arduino IDE, buka menu **Sketch > Include Library > Add .ZIP Library...**.
3. Pilih file zip yang telah didownload.

---

## 💻 Contoh Penggunaan Singkat

### 1. Zero-Config Plug & Play (shawirWifi + ShawirIOT)
> *Tanpa menulis password WiFi atau Token di dalam sketch! Semua diatur lewat web browser HP saat ESP menyala.*

```cpp
#include <shawirWifi.h>
#include <ShawirIOT.h>

void setup() {
    Serial.begin(115200);

    // 1. Buka Captive Portal jika belum ada WiFi tersimpan (Hotspot: "ShawirIOT-Device")
    shawirWifi wm;
    wm.autoConnectShawirIOT("ShawirIOT-Device");

    // 2. Mulai ShawirIOT (Token otomatis dimuat dari EEPROM/Captive Portal)
    ShawirIOT.begin();
}

void loop() {
    ShawirIOT.run();

    // Kirim data dummy ke Virtual Pin V0 setiap 2 detik
    static unsigned long lastSend = 0;
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        ShawirIOT.virtualWrite(V0, millis() / 1000);
    }
}
```

---

### 2. Standalone WiFi + ShawirIOT
> *Menggunakan WiFi biasa (SSID & Password ditulis di sketch).*

```cpp
#include <ShawirIOT.h>

const char* AUTH_TOKEN = "TOKEN_DEVICE_ANDA";
const char* WIFI_SSID  = "Nama_WiFi";
const char* WIFI_PASS  = "Password_WiFi";

void setup() {
    Serial.begin(115200);

    // Inisialisasi otomatis menghubungkan WiFi & Server ShawirIOT
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS);
}

void loop() {
    ShawirIOT.run();

    static unsigned long lastTime = 0;
    if (millis() - lastTime > 3000) {
        lastTime = millis();

        float suhu = 28.4;
        float kelembaban = 65.0;

        ShawirIOT.virtualWrite(V1, suhu, 1);       // Suhu ke Pin V1 (Gauge)
        ShawirIOT.virtualWrite(V2, kelembaban, 1); // Kelembaban ke Pin V2 (Chart)
    }
}
```

---

### 3. Kontrol Aktuator Dua Arah (Relay, LED, PWM Slider)

```cpp
#include <shawirWifi.h>
#include <ShawirIOT.h>

#define LED_PIN 2 // Built-in LED ESP32 / D4 ESP8266

const char* AUTH_TOKEN = "TOKEN_DEVICE_ANDA";

// Callback saat tombol / switch V4 ditekan di Web Dashboard
void handleRelay(const String& val) {
    bool state = (val == "1" || val == "ON" || val == "true");
    digitalWrite(LED_PIN, state ? HIGH : LOW);
    Serial.println(state ? "Relay: NYALA" : "Relay: MATI");
}

// Callback saat slider V5 digeser (0 - 255)
void handlePWM(const String& val) {
    int brightness = val.toInt();
    analogWrite(LED_PIN, brightness);
    Serial.printf("Kecerahan: %d\n", brightness);
}

void setup() {
    Serial.begin(115200);
    pinMode(LED_PIN, OUTPUT);

    // Daftarkan listener Virtual Pin sebelum begin
    ShawirIOT.onWrite(V4, handleRelay);
    ShawirIOT.onWrite(V5, handlePWM);
    ShawirIOT.setPollInterval(400); // Respon cepat 400ms

    shawirWifi wm;
    wm.autoConnect("Shawir-AP");

    ShawirIOT.begin(AUTH_TOKEN);
}

void loop() {
    ShawirIOT.run();
}
```

---

## 📌 Pemetaan Virtual Pin & Widget

| Pin | Tipe Widget | Contoh Tipe Data | Format / Keterangan |
| :--- | :--- | :--- | :--- |
| `V0` | Value Display / Counter | Integer / String | Status runtime, detik, heartbeat |
| `V1` | Gauge / Display Suhu | Float (`28.5`) | `ShawirIOT.virtualWrite(V1, 28.5, 1)` |
| `V2` | Line Chart / Kelembaban | Float (`65.2`) | `ShawirIOT.virtualWrite(V2, 65.2, 1)` |
| `V3` | Bar Chart / Potensiometer | Integer (`0` - `1023`) | `ShawirIOT.virtualWrite(V3, analogVal)` |
| `V4` | Toggle Switch / Button | String (`"1"` / `"0"`) | `ShawirIOT.onWrite(V4, callback)` |
| `V5` | Slider Widget | Integer (`0` - `255`) | `ShawirIOT.onWrite(V5, callback)` |
| `V6` | LED Status Indicator | String (`"1"` / `"0"`) | Indikator aktif/nonaktif |
| `V7` | Map Widget (GPS) | String (`"-6.175392,106.827153"`) | Format: `"latitude,longitude"` |
| `V8`..`V255` | Custom Widgets | String, Float, Integer | Sesuai kebutuhan proyek |

---

## 🛠️ API Reference

### Kelas `ShawirIOT`
- `ShawirIOT.begin(const char* token)` : Memulai koneksi dengan token device (WiFi diurus oleh library lain seperti `shawirWifi`).
- `ShawirIOT.begin(const char* token, const char* ssid, const char* pass)` : Memulai koneksi mandiri dengan kredensial WiFi.
- `ShawirIOT.begin()` : Memulai koneksi dengan mengambil Token otomatis yang tersimpan dari `shawirWifi`.
- `ShawirIOT.run()` : Memproses antrean data masuk dan keluar. **Wajib dipanggil di dalam `void loop()`**.
- `ShawirIOT.virtualWrite(const char* pin, int / float / String val, [int decimals])` : Mengirim data ke Virtual Pin di dashboard.
- `ShawirIOT.virtualRead(const char* pin)` : Mengambil nilai terkini dari Virtual Pin.
- `ShawirIOT.onWrite(const char* pin, ShawirPinHandler callback)` : Mendaftarkan fungsi handler saat tombol/slider di web dashboard digerakkan.
- `ShawirIOT.setPollInterval(unsigned long ms)` : Mengatur interval polling perintah web (default: 500 ms).
- `ShawirIOT.setServer(const char* host, uint16_t port)` : Mengarahkan ke server custom/lokal (default: `iot.shawir.id:80`).
- `ShawirIOT.connected()` : Mengembalikan status `true` jika terhubung ke internet dan server.

---

## 🤝 Berkontribusi

Kontribusi, perbaikan bug, dan penambahan fitur baru selalu kami sambut dengan hangat!
1. Fork repositori ini
2. Buat branch fitur baru (`git checkout -b feature/FiturKeren`)
3. Commit perubahan (`git commit -m 'Menambahkan fitur keren'`)
4. Push ke branch (`git push origin feature/FiturKeren`)
5. Buat **Pull Request**

---

## 📜 Lisensi

Proyek ini dilisensikan di bawah [Lisensi MIT](LICENSE). Dibuat dengan ❤️ untuk komunitas IoT Indonesia oleh **Mushawir Odegoa** & **ShawirIOT Team**.
