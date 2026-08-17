/**
 * shawirWifi - Contoh Dasar (Basic)
 * 
 * Menghubungkan ESP ke jaringan WiFi secara otomatis menggunakan
 * kredensial yang tersimpan. Jika gagal, akan membuka portal konfigurasi
 * berupa Access Point untuk memasukkan SSID dan password baru.
 */
#include <shawirWifi.h> // library shawirWifi by Shawir


void setup() {
    WiFi.mode(WIFI_STA); // atur mode WiFi secara eksplisit (STA saja)
    // Disarankan selalu set mode agar perilaku WiFi sesuai keinginan.

    // Inisialisasi serial monitor
    Serial.begin(115200);
    delay(1000); // tunggu Serial Monitor siap membaca output
    
    // Buat instance shawirWifi (lokal, tidak perlu disimpan global)
    shawirWifi wm;

    // Reset pengaturan WiFi - hapus kredensial tersimpan (untuk pengujian)
    // wm.resetSettings();

    // Hubungkan otomatis menggunakan kredensial tersimpan.
    // Jika gagal, buka Access Point dengan nama "shawirWifi-AP".
    // Jika nama kosong, nama AP akan digenerate otomatis dari chip ID.
    // Jika password kosong, AP tidak berpassword (wm.autoConnect())
    // Program akan menunggu di sini sampai berhasil atau timeout.

    bool hasil;
    // hasil = wm.autoConnect();                          // nama AP otomatis dari chip ID
    // hasil = wm.autoConnect("shawirWifi-AP");           // AP tanpa password
    hasil = wm.autoConnect("shawirWifi-AP","password");   // AP dengan password

    if (!hasil) {
        Serial.println("[shawirWifi] Gagal terhubung ke WiFi!");
        // ESP.restart(); // restart jika gagal
    } 
    else {
        // Jika sampai sini berarti sudah terhubung ke WiFi
        Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
    }
}

void loop() {
    // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
