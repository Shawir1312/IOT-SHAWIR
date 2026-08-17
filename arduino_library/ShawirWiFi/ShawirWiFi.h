/**
 * shawirWifi.h
 * 
 * shawirWifi - Custom WiFi Manager & IoT Configurator Library
 * Based on WiFiManager by tzapu/tablatronix
 * for configuration of WiFi credentials and ShawirIOT Device Token
 * using a modern Captive Portal on ESP8266/ESP32 platforms.
 * 
 * Customized & Integrated by Shawir for ShawirIOT Platform
 * @license MIT
 */

#ifndef shawirWifi_h
#define shawirWifi_h

#include <Arduino.h>
#include <EEPROM.h>

// Include the core WiFiManager implementation
#include "WiFiManager.h"

#define SHAWIR_TOKEN_EEPROM_ADDR 400
#define SHAWIR_TOKEN_MAGIC_0 0x53 // 'S'
#define SHAWIR_TOKEN_MAGIC_1 0x48 // 'H'

/**
 * shawirWifiParameter is an alias for WiFiManagerParameter.
 */
using shawirWifiParameter = WiFiManagerParameter;

/**
 * shawirWifi - Smart WiFi & Device Configurator
 * Inherits from WiFiManager with built-in ShawirIOT token handling.
 */
class shawirWifi : public WiFiManager {
private:
    WiFiManagerParameter* _tokenParam;
    char _tokenBuf[65];

    void loadShawirToken() {
        memset(_tokenBuf, 0, sizeof(_tokenBuf));
        EEPROM.begin(512);
        if (EEPROM.read(SHAWIR_TOKEN_EEPROM_ADDR) == SHAWIR_TOKEN_MAGIC_0 && 
            EEPROM.read(SHAWIR_TOKEN_EEPROM_ADDR + 1) == SHAWIR_TOKEN_MAGIC_1) {
            for (int i = 0; i < 64; i++) {
                _tokenBuf[i] = (char)EEPROM.read(SHAWIR_TOKEN_EEPROM_ADDR + 2 + i);
            }
        }
        EEPROM.end();
    }

    void saveShawirToken(const char* token) {
        if (!token) return;
        EEPROM.begin(512);
        EEPROM.write(SHAWIR_TOKEN_EEPROM_ADDR, SHAWIR_TOKEN_MAGIC_0);
        EEPROM.write(SHAWIR_TOKEN_EEPROM_ADDR + 1, SHAWIR_TOKEN_MAGIC_1);
        for (int i = 0; i < 64; i++) {
            EEPROM.write(SHAWIR_TOKEN_EEPROM_ADDR + 2 + i, (i < (int)strlen(token)) ? token[i] : 0);
        }
        EEPROM.commit();
        EEPROM.end();
        strncpy(_tokenBuf, token, sizeof(_tokenBuf) - 1);
    }

public:
    shawirWifi() : WiFiManager() {
        _tokenParam = nullptr;
        loadShawirToken();
    }

    ~shawirWifi() {
        if (_tokenParam) {
            delete _tokenParam;
            _tokenParam = nullptr;
        }
    }

    /**
     * Buka Captive Portal dengan Form WiFi + Input Token Perangkat ShawirIOT
     * @param apName Nama Access Point hotspot (default: "ShawirIOT-Device")
     * @param apPassword Password Access Point (opsional)
     * @return true jika berhasil terhubung
     */
    bool autoConnectShawirIOT(const char* apName = "ShawirIOT-Device", const char* apPassword = NULL) {
        if (!_tokenParam) {
            // Label HTML kustom untuk input token
            const char* customHtml = "placeholder='Tempel token device dari web dashboard'";
            _tokenParam = new WiFiManagerParameter("shawir_token", "ShawirIOT Device Token", _tokenBuf, 64, customHtml);
            addParameter(_tokenParam);
        }

        // Atur callback saat konfigurasi disimpan
        setSaveParamsCallback([this]() {
            if (_tokenParam) {
                const char* val = _tokenParam->getValue();
                if (val && strlen(val) > 0) {
                    saveShawirToken(val);
                    Serial.print(F("[shawirWifi] Token ShawirIOT tersimpan: "));
                    Serial.println(_tokenBuf);
                }
            }
        });

        bool res = autoConnect(apName, apPassword);
        if (res && _tokenParam) {
            const char* val = _tokenParam->getValue();
            if (val && strlen(val) > 0) {
                saveShawirToken(val);
            }
        }
        return res;
    }

    /**
     * Ambil Token ShawirIOT yang tersimpan di flash memory
     */
    String getShawirToken() {
        if (strlen(_tokenBuf) == 0) {
            loadShawirToken();
        }
        return String(_tokenBuf);
    }

    /**
     * Simpan Token ShawirIOT secara manual ke memori flash
     */
    void setShawirToken(const char* token) {
        saveShawirToken(token);
    }

    /**
     * Hapus Token ShawirIOT dari memori flash
     */
    void eraseShawirToken() {
        EEPROM.begin(512);
        EEPROM.write(SHAWIR_TOKEN_EEPROM_ADDR, 0);
        EEPROM.write(SHAWIR_TOKEN_EEPROM_ADDR + 1, 0);
        EEPROM.commit();
        EEPROM.end();
        memset(_tokenBuf, 0, sizeof(_tokenBuf));
    }
};

#endif // shawirWifi_h
