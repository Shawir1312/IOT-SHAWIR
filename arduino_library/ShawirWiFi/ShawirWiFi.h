/**
 * ShawirWiFi - Smart WiFi Manager & Captive Portal Library
 * Version 1.0.0
 * 
 * Elegant, zero-configuration WiFi manager for ESP8266 and ESP32.
 * Allows easy WiFi setup via a sleek web portal without hardcoding credentials in your sketch.
 * 
 * Perfectly integrated with ShawirIOT platform.
 */

#ifndef SHAWIR_WIFI_H
#define SHAWIR_WIFI_H

#include <Arduino.h>

#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266WebServer.h>
  #include <DNSServer.h>
  #include <EEPROM.h>
  typedef ESP8266WebServer ShawirWebServer;
#elif defined(ESP32)
  #include <WiFi.h>
  #include <WebServer.h>
  #include <DNSServer.h>
  #include <EEPROM.h>
  typedef WebServer ShawirWebServer;
#else
  #error "ShawirWiFi library only supports ESP8266 and ESP32 architecture."
#endif

class ShawirWiFiClass {
private:
    ShawirWebServer* _server;
    DNSServer* _dnsServer;

    String _ssid;
    String _pass;
    String _apName;
    String _apPassword;

    unsigned long _connectTimeout; // seconds
    unsigned long _configTimeout;  // seconds
    bool _portalActive;
    bool _configSaved;

    void loadCredentials();
    void saveCredentials(const String& ssid, const String& pass);
    bool connectToSavedWiFi();
    void setupWebServer();
    void handleRoot();
    void handleSave();
    void handleReset();
    void handleNotFound();
    String getNetworkScanOptions();

public:
    ShawirWiFiClass();
    ~ShawirWiFiClass();

    /**
     * Otomatis terhubung ke WiFi tersimpan, atau buka web portal setup jika belum terhubung
     * @param apName Nama Access Point hotspot saat mode setup (default: "ShawirWiFi-Setup")
     * @param apPassword Password Access Point (opsional, default: open / tanpa password)
     * @return true jika berhasil terhubung ke WiFi
     */
    bool autoConnect(const char* apName = "ShawirWiFi-Setup", const char* apPassword = NULL);

    /**
     * Buka Web Portal Captive Portal secara manual untuk mengganti WiFi
     * @param apName Nama Access Point hotspot
     * @param apPassword Password Access Point
     * @return true jika user berhasil memasukkan kredensial baru
     */
    bool startConfigPortal(const char* apName = "ShawirWiFi-Setup", const char* apPassword = NULL);

    /**
     * Hapus kredensial WiFi yang tersimpan di memori Flash / EEPROM
     */
    void resetSettings();

    /**
     * Cek apakah ESP sedang terhubung ke WiFi
     */
    bool isConnected();

    /**
     * Ambil nama SSID WiFi yang sedang aktif
     */
    String getSSID();

    /**
     * Ambil Password WiFi yang tersimpan
     */
    String getPassword();

    /**
     * Set batas waktu percobaan koneksi ke WiFi dalam detik (default 15 detik)
     */
    void setConnectTimeout(unsigned long seconds);

    /**
     * Set batas waktu portal konfigurasi aktif dalam detik (0 = tanpa batas waktu)
     */
    void setConfigTimeout(unsigned long seconds);
};

extern ShawirWiFiClass ShawirWiFi;

#endif // SHAWIR_WIFI_H
