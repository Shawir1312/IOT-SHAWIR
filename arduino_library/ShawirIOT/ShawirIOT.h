/**
 * ShawirIOT - Arduino & ESP Library
 * Version 1.1.0
 * 
 * An elegant IoT communication library for ESP8266, ESP32, and Arduino.
 * Easily push sensor data and listen to dashboard commands with Virtual Pins (V0 - V255).
 * 
 * Default Server Host: iot.shawir.id (Port 80)
 * Fully compatible with ShawirWiFi (WiFi Manager) & standalone WiFi.
 */

#ifndef SHAWIR_IOT_H
#define SHAWIR_IOT_H

#include <Arduino.h>

#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WiFiClient.h>
#elif defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <WiFiClient.h>
#else
  #warning "Make sure to include appropriate WiFi library for your architecture."
#endif

// Default Server Configuration
#define SHAWIR_DEFAULT_HOST "iot.shawir.id"
#define SHAWIR_DEFAULT_PORT 80

// Virtual Pin Definitions
#define V0   "V0"
#define V1   "V1"
#define V2   "V2"
#define V3   "V3"
#define V4   "V4"
#define V5   "V5"
#define V6   "V6"
#define V7   "V7"
#define V8   "V8"
#define V9   "V9"
#define V10  "V10"
#define V11  "V11"
#define V12  "V12"
#define V13  "V13"
#define V14  "V14"
#define V15  "V15"
#define V16  "V16"
#define V17  "V17"
#define V18  "V18"
#define V19  "V19"
#define V20  "V20"
#define V21  "V21"
#define V22  "V22"
#define V23  "V23"
#define V24  "V24"
#define V25  "V25"
#define V26  "V26"
#define V27  "V27"
#define V28  "V28"
#define V29  "V29"
#define V30  "V30"
#define V31  "V31"
#define V32  "V32"

// Callback type for pin change listeners
typedef void (*ShawirPinHandler)(const String& value);

struct PinListener {
    String pin;
    ShawirPinHandler handler;
    String lastValue;
};

class ShawirIOTClass {
private:
    String _token;
    String _ssid;
    String _pass;
    String _serverHost;
    uint16_t _serverPort;
    String _baseApiUrl;
    
    unsigned long _lastPollTime;
    unsigned long _pollInterval; // ms
    unsigned long _lastHeartbeat;
    bool _isConfigured;

    static const int MAX_LISTENERS = 32;
    PinListener _listeners[MAX_LISTENERS];
    int _listenerCount;

    void connectWiFi();
    void pollRegisteredPins();
    bool sendHttpPost(const String& pin, const String& value);

public:
    ShawirIOTClass();

    /**
     * Inisialisasi otomatis: Mengambil Token yang tersimpan oleh shawirWifi
     * dan menggunakan koneksi WiFi yang sudah aktif ke server iot.shawir.id:80
     */
    void begin();

    /**
     * Inisialisasi hanya dengan Token (WiFi dikelola oleh shawirWifi atau sketch Anda)
     * Otomatis terhubung ke server resmi: iot.shawir.id:80
     * @param token Token unik device dari web ShawirIOT
     */
    void begin(const char* token);

    /**
     * Inisialisasi dengan Token dan Kredensial WiFi
     * Otomatis terhubung ke server resmi: iot.shawir.id:80
     * @param token Token unik device dari web ShawirIOT
     * @param ssid Nama WiFi
     * @param pass Password WiFi
     */
    void begin(const char* token, const char* ssid, const char* pass);

    /**
     * Inisialisasi dengan Token dan Custom Server Host (WiFi sudah terhubung)
     * @param token Token unik device dari web ShawirIOT
     * @param serverHost Domain / IP Server (default: iot.shawir.id)
     * @param serverPort Port HTTP server (default: 80)
     */
    void begin(const char* token, const char* serverHost, uint16_t serverPort = SHAWIR_DEFAULT_PORT);

    /**
     * Inisialisasi lengkap dengan Kredensial WiFi dan Custom Server Host
     * @param token Token unik device dari web ShawirIOT
     * @param ssid Nama WiFi
     * @param pass Password WiFi
     * @param serverHost Domain / IP Server
     * @param serverPort Port HTTP server
     */
    void begin(const char* token, const char* ssid, const char* pass, const char* serverHost, uint16_t serverPort = SHAWIR_DEFAULT_PORT);

    /**
     * Ubah konfigurasi server host dan port
     */
    void setServer(const char* serverHost, uint16_t serverPort = SHAWIR_DEFAULT_PORT);

    /**
     * Loop runner — panggil di dalam loop() sketch Anda
     */
    void run();

    /**
     * Kirim data ke virtual pin di server
     * @param pin Virtual pin (cth: V0, V1, "V2")
     * @param value Nilai yang dikirim (string, integer, float, double)
     */
    bool virtualWrite(const char* pin, const String& value);
    bool virtualWrite(const char* pin, int value);
    bool virtualWrite(const char* pin, float value, int precision = 2);
    bool virtualWrite(const char* pin, double value, int precision = 2);

    /**
     * Baca nilai virtual pin saat ini dari server
     * @param pin Virtual pin (cth: V0, V1)
     * @return String nilai pin
     */
    String virtualRead(const char* pin);
    int virtualReadInt(const char* pin);
    float virtualReadFloat(const char* pin);

    /**
     * Daftarkan handler saat nilai pin di dashboard diubah user
     * @param pin Virtual pin yang dipantau
     * @param handler Fungsi callback void myFunc(const String& val)
     */
    void onWrite(const char* pin, ShawirPinHandler handler);

    /**
     * Cek status koneksi WiFi
     */
    bool connected();

    /**
     * Set interval polling pembacaan perintah (default 1000ms)
     */
    void setPollInterval(unsigned long ms);
};

extern ShawirIOTClass ShawirIOT;

#endif // SHAWIR_IOT_H
