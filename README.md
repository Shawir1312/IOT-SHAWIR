# ShawirIOT — Platform IoT Modern & Library Arduino

Platform IoT full PHP (mirip Blynk) dengan dashboard monitoring drag-and-drop interaktif, sistem kredit, manajemen device, dan Arduino library untuk ESP8266, ESP32, dan Arduino.

---

## 🌟 Fitur Utama

1. **Dashboard Monitoring Real-time**
   - Drag-and-drop widget layout (atur posisi dan ukuran sesuka hati).
   - Mode Edit & Mode Monitor.
   - Pilihan widget lengkap:
     - **Value Display** (Tampilan angka sensor + satuan)
     - **Line Chart & Bar Chart** (Grafik histori data real-time via Chart.js)
     - **Gauge** (Speedometer digital)
     - **Button & Toggle Switch** (Kontrol relay/lampu ON/OFF)
     - **Slider** (Kontrol PWM / kecerahan 0-255)
     - **LED Indicator** (Indikator status digital)
     - **Terminal** (Log debug serial/teks)
     - **GPS Map** (Lokasi koordinat Google Maps)

2. **Sistem Autentikasi & Akun**
   - Registrasi & Login aman (Bcrypt password hash + CSRF protection).
   - Fitur "Ingat Saya" (Remember Me cookie 30 hari).
   - Halaman Profil, ganti nama & perbarui password.

3. **Sistem Kredit ala Blynk**
   - Kredit diberikan dan dikelola oleh Admin dari panel admin.
   - User menukarkan saldo kredit untuk upgrade paket langganan:
     - **Free**: 1 Device, 5 Widget/Device, Histori 1 Hari (0 Kredit)
     - **Basic**: 5 Device, 20 Widget/Device, Histori 7 Hari (100 Kredit)
     - **Pro**: 20 Device, 100 Widget/Device, Histori 30 Hari (300 Kredit)
     - **Enterprise**: Unlimited Device & Widget, Histori 1 Tahun (1000 Kredit)
   - Log mutasi kredit tercatat lengkap di sisi user dan admin.

4. **Panel Admin Lengkap (`/admin`)**
   - Statistik real-time (Total user, device aktif, device online, total kredit, total data poin).
   - Manajemen User (Edit peran user/admin/superadmin, ganti paket, blokir/buka ban, reset password, hapus user).
   - Pusat Kelola Kredit (Tambah/kurangi kredit user dalam 1 klik dengan catatan transaksi).
   - Monitor Seluruh Device IoT (Lihat IP, token, status online/offline, dan buka dashboard device).
   - Pengaturan Platform (Nama platform, port websocket, buka/tutup pendaftaran, ubah harga kredit paket).

5. **Library Arduino C++ Resmi (`ShawirIOT`)**
   - Kompatibel dengan **ESP8266** (NodeMCU, Wemos D1), **ESP32**, dan **Arduino WiFi**.
   - Mudah digunakan: `ShawirIOT.begin(...)`, `ShawirIOT.virtualWrite(V1, 25.5)`, `ShawirIOT.onWrite(V4, callback)`.
   - Otomatis auto-reconnect WiFi.
   - Dilengkapi 4 contoh sketch siap pakai di folder `examples/`.

6. **Dukungan Real-Time Ganda (Dual Mode)**
   - **WebSocket Server (PHP Ratchet)** untuk latensi sangat rendah.
   - **HTTP Polling Fallback Otomatis** (jika WebSocket belum dinyalakan, dashboard tetap jalan lancar tanpa error).

---

## 🚀 Panduan Instalasi Cepat (1-Klik via Browser)

### Opsi 1: Menggunakan Web Installer Wizard (Sangat Mudah)
1. Unggah/tempatkan seluruh folder proyek ke server Anda (aaPanel / XAMPP / VPS).
2. Buka browser dan akses:
   ```
   http://domain-anda.com/install.php
   ```
3. Wizard akan otomatis memeriksa persyaratan server, membuat database & seluruh tabel, mengkonfigurasi file `config.php`, dan membuat akun Super Admin.
4. Selesai! Anda langsung diarahkan ke halaman login.

---

### Opsi 2: Instalasi Manual
1. Buka **phpMyAdmin** atau MySQL console di server Anda.
2. Buat database baru bernama `shawiriot`.
3. Import file `database/schema.sql`.
4. Akun Super Admin bawaan:
   - **Email**: `admin@shawiriot.com`
   - **Password**: `password`
5. Buka `includes/config.php` dan sesuaikan kredensial database.

### 3. Menjalankan WebSocket Server (Opsional, Disarankan)
Di aaPanel / VPS Linux:
```bash
cd websocket
composer install
php server.php
```
*Tips di aaPanel: Gunakan plugin **Supervisor Manager** di aaPanel App Store untuk menjalankan `php /path/to/websocket/server.php` secara otomatis di background.*

---

## 🔌 Panduan Penggunaan Library di Arduino IDE

### 1. Install Library ke Arduino IDE
1. Salin folder `arduino_library/ShawirIOT` ke direktori library Arduino Anda:
   - **Windows**: `Documents\Arduino\libraries\ShawirIOT`
   - **Mac**: `~/Documents/Arduino/libraries/ShawirIOT`
   - **Linux**: `~/Arduino/libraries/ShawirIOT`
2. Buka **Arduino IDE** -> **File** -> **Examples** -> **ShawirIOT** -> **BasicConnect**.

### 2. Contoh Kode ESP32 / ESP8266
```cpp
#include <ShawirIOT.h>

const char* AUTH_TOKEN  = "XXXX-XXXX-XXXX-XXXX"; // Salin dari menu Device Saya di web
const char* WIFI_SSID   = "Nama_WiFi";
const char* WIFI_PASS   = "Password_WiFi";
const char* SERVER_HOST = "ip_atau_domain_server_anda"; // Contoh: "192.168.1.10" atau "iot.domain.com"
const uint16_t SERVER_PORT = 80;

void setup() {
    Serial.begin(115200);
    // Hubungkan ke platform
    ShawirIOT.begin(AUTH_TOKEN, WIFI_SSID, WIFI_PASS, SERVER_HOST, SERVER_PORT);
}

void loop() {
    ShawirIOT.run();

    // Kirim data suhu ke Virtual Pin V1 setiap 2 detik
    static unsigned long lastSend = 0;
    if (millis() - lastSend > 2000) {
        lastSend = millis();
        float suhu = 28.5;
        ShawirIOT.virtualWrite(V1, suhu);
    }
}
```

---

## 📱 REST API Mikrokontroler

| Metode | URL | Deskripsi |
|---|---|---|
| `GET` | `/api/data.php?token=TOKEN&pin=V1` | Membaca nilai terakhir pin V1 |
| `GET` | `/api/data.php?token=TOKEN&all=1` | Membaca semua pin & status device |
| `GET` | `/api/data.php?token=TOKEN&history=V1&n=50` | Membaca 50 data riwayat pin V1 |
| `POST` | `/api/data.php` (body: `token=TOKEN&pin=V1&value=27.5`) | Mengirimkan nilai pin baru ke server |

---

## 📂 Struktur Direktori Proyek

```
APP-WEB IOT/
├── index.php                 # Halaman utama landing page publik
├── login.php                 # Halaman login user
├── register.php              # Halaman pendaftaran akun
├── logout.php                # Logout handler
├── dashboard.php             # Dashboard monitoring user
├── device_dashboard.php      # Dashboard widget monitoring & canvas drag-drop
├── device.php                # Halaman manajemen device user
├── profile.php               # Halaman profil & tukar kredit
├── admin/                    # Panel Administrasi
│   ├── index.php             # Dashboard statistik admin
│   ├── users.php             # Kelola & ban user, reset password
│   ├── credits.php           # Pusat kelola & log transaksi kredit
│   ├── devices.php           # Monitor seluruh device IoT sistem
│   └── settings.php          # Konfigurasi platform & harga paket
├── api/                      # REST API Endpoints
│   ├── data.php              # Push/Pull data sensor dari mikrokontroler
│   ├── widget.php            # CRUD Widget di dashboard
│   └── dashboard.php         # Simpan koordinat drag & drop
├── assets/                   # Aset Tampilan
│   ├── css/
│   │   ├── style.css         # Styling global & dark mode modern
│   │   ├── dashboard.css     # Styling grid widget, tombol, slider, gauge
│   │   └── admin.css         # Styling panel admin
│   └── js/
│       ├── widgets.js        # Logika render widget & konfigurasi form
│       ├── dashboard.js      # Drag-and-drop & resize widget
│       └── realtime.js       # WebSocket client & HTTP polling fallback
├── websocket/                # WebSocket Server Daemon (Ratchet)
│   ├── server.php            # Daemon server real-time
│   └── composer.json         # Dependensi PHP
├── arduino_library/          # Library Arduino IDE
│   └── ShawirIOT/
│       ├── ShawirIOT.h       # Library Header
│       ├── ShawirIOT.cpp     # Library Source Code
│       ├── library.properties
│       └── examples/         # Contoh sketch lengkap
└── database/
    └── schema.sql            # Skema database MySQL lengkap
```
