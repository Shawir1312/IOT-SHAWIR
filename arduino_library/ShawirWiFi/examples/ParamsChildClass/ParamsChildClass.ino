/**
 * shawirWifi - Contoh Child Class Parameter
 * 
 * Menunjukkan cara membuat class turunan dari shawirWifiParameter
 * untuk mendukung tipe data khusus: IP Address, Integer, dan Float.
 * 
 * Tekan tombol SETUP_PIN saat boot untuk masuk mode konfigurasi portal.
 * Biarkan untuk masuk mode kerja normal (menggunakan kredensial tersimpan).
 */
#include <shawirWifi.h> // library shawirWifi by Shawir
#include <Arduino.h>
#include <EEPROM.h>

#define SETUP_PIN 0

// Class parameter untuk tipe IPAddress
class ParameterIPAddress : public shawirWifiParameter {
public:
    ParameterIPAddress(const char *id, const char *placeholder, IPAddress address)
        : shawirWifiParameter("") {
        init(id, placeholder, address.toString().c_str(), 16, "", WFM_LABEL_BEFORE);
    }

    bool getValue(IPAddress &ip) {
        return ip.fromString(shawirWifiParameter::getValue());
    }
};

// Class parameter untuk tipe Integer (bilangan bulat)
class ParameterInt : public shawirWifiParameter {
public:
    ParameterInt(const char *id, const char *placeholder, long nilai, const uint8_t panjang = 10)
        : shawirWifiParameter("") {
        init(id, placeholder, String(nilai).c_str(), panjang, "", WFM_LABEL_BEFORE);
    }

    long getValue() {
        return String(shawirWifiParameter::getValue()).toInt();
    }
};

// Class parameter untuk tipe Float (bilangan desimal)
class ParameterFloat : public shawirWifiParameter {
public:
    ParameterFloat(const char *id, const char *placeholder, float nilai, const uint8_t panjang = 10)
        : shawirWifiParameter("") {
        init(id, placeholder, String(nilai).c_str(), panjang, "", WFM_LABEL_BEFORE);
    }

    float getValue() {
        return String(shawirWifiParameter::getValue()).toFloat();
    }
};

// Struktur pengaturan yang disimpan di EEPROM
struct Pengaturan {
    float f;
    int i;
    char s[20];
    uint32_t ip;
} peng;


void setup() {
    WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
    pinMode(SETUP_PIN, INPUT_PULLUP);
    Serial.begin(115200);

    // Beri waktu untuk menekan tombol SETUP
    Serial.println("Tekan tombol SETUP untuk masuk mode konfigurasi...");
    for (int detik = 3; detik > 0; detik--) {
        Serial.print(detik);
        Serial.print("..");
        delay(1000);
    }

    // Peringatan: ini hanya untuk contoh — selalu inisialisasi flash dengan benar
    // atau tambahkan checksum bits untuk memvalidasi data tersimpan
    EEPROM.begin(512);
    EEPROM.get(0, peng);
    Serial.println("Pengaturan berhasil dimuat");
    
    if (digitalRead(SETUP_PIN) == LOW) {
        // Tombol ditekan → masuk mode konfigurasi
        Serial.println("[SETUP] Masuk mode konfigurasi portal...");

        shawirWifi wm;
        
        peng.s[19] = '\0'; // tambahkan null terminator untuk mencegah overflow
        shawirWifiParameter param_string( "str",   "Parameter String",  peng.s, 20);
        ParameterFloat      param_float(  "float", "Parameter Float",   peng.f);
        ParameterInt        param_int(    "int",   "Parameter Integer", peng.i);

        IPAddress ip(peng.ip);
        ParameterIPAddress  param_ip("ip", "Parameter IP Address", ip);

        wm.addParameter(&param_string);
        wm.addParameter(&param_float);
        wm.addParameter(&param_int);
        wm.addParameter(&param_ip);

        // Parameter SSID & password sudah disertakan otomatis
        wm.startConfigPortal();

        strncpy(peng.s, param_string.getValue(), 20);
        peng.s[19] = '\0';
        peng.f = param_float.getValue();
        peng.i = param_int.getValue();

        Serial.print("Parameter String : ");
        Serial.println(peng.s);
        Serial.print("Parameter Float  : ");
        Serial.println(peng.f);
        Serial.print("Parameter Integer: ");
        Serial.println(peng.i, DEC);
        
        if (param_ip.getValue(ip)) {
            peng.ip = ip;
            Serial.print("Parameter IP     : ");
            Serial.println(ip);
        } else {
            Serial.println("[ERROR] Format IP tidak valid!");
        }

        EEPROM.put(0, peng);
        if (EEPROM.commit()) {
            Serial.println("[EEPROM] Pengaturan berhasil disimpan!");
        } else {
            Serial.println("[EEPROM] Gagal menyimpan pengaturan!");
        }
    } 
    else {
        // Tombol tidak ditekan → mode kerja normal
        Serial.println("[KERJA] Mode kerja normal — menghubungkan ke WiFi...");

        // Hubungkan menggunakan SSID tersimpan
        WiFi.begin();

        // Lakukan pekerjaan utama
        Serial.print("Parameter String : ");
        Serial.println(peng.s);
        Serial.print("Parameter Float  : ");
        Serial.println(peng.f);
        Serial.print("Parameter Integer: ");
        Serial.println(peng.i, DEC);
        Serial.print("Parameter IP     : ");
        IPAddress ip(peng.ip);
        Serial.println(ip);
    }
}

void loop() {
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
