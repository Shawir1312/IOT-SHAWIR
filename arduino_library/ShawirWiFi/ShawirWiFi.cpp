/**
 * ShawirWiFi - Smart WiFi Manager & Captive Portal Implementation
 * Version 1.0.0
 */

#include "ShawirWiFi.h"

#define EEPROM_SIZE 256
#define MAGIC_BYTE_0 0x53 // 'S'
#define MAGIC_BYTE_1 0x57 // 'W'

const byte DNS_PORT = 53;

ShawirWiFiClass::ShawirWiFiClass() {
    _server = nullptr;
    _dnsServer = nullptr;
    _ssid = "";
    _pass = "";
    _apName = "ShawirWiFi-Setup";
    _apPassword = "";
    _connectTimeout = 15; // 15s default
    _configTimeout = 0;   // 0 = infinite until connected
    _portalActive = false;
    _configSaved = false;
}

ShawirWiFiClass::~ShawirWiFiClass() {
    if (_server) { delete _server; _server = nullptr; }
    if (_dnsServer) { delete _dnsServer; _dnsServer = nullptr; }
}

void ShawirWiFiClass::loadCredentials() {
    EEPROM.begin(EEPROM_SIZE);
    if (EEPROM.read(0) == MAGIC_BYTE_0 && EEPROM.read(1) == MAGIC_BYTE_1) {
        char ssidBuf[33];
        char passBuf[65];
        memset(ssidBuf, 0, sizeof(ssidBuf));
        memset(passBuf, 0, sizeof(passBuf));

        for (int i = 0; i < 32; i++) {
            ssidBuf[i] = (char)EEPROM.read(2 + i);
        }
        for (int i = 0; i < 64; i++) {
            passBuf[i] = (char)EEPROM.read(34 + i);
        }

        _ssid = String(ssidBuf);
        _pass = String(passBuf);
        _ssid.trim();
        _pass.trim();
    }
    EEPROM.end();
}

void ShawirWiFiClass::saveCredentials(const String& ssid, const String& pass) {
    EEPROM.begin(EEPROM_SIZE);
    EEPROM.write(0, MAGIC_BYTE_0);
    EEPROM.write(1, MAGIC_BYTE_1);

    for (int i = 0; i < 32; i++) {
        EEPROM.write(2 + i, (i < (int)ssid.length()) ? ssid[i] : 0);
    }
    for (int i = 0; i < 64; i++) {
        EEPROM.write(34 + i, (i < (int)pass.length()) ? pass[i] : 0);
    }

    EEPROM.commit();
    EEPROM.end();

    _ssid = ssid;
    _pass = pass;
}

void ShawirWiFiClass::resetSettings() {
    EEPROM.begin(EEPROM_SIZE);
    for (int i = 0; i < EEPROM_SIZE; i++) {
        EEPROM.write(i, 0);
    }
    EEPROM.commit();
    EEPROM.end();

    _ssid = "";
    _pass = "";
    WiFi.disconnect(true);
    Serial.println(F("[ShawirWiFi] Kredensial WiFi berhasil direset dari memori flash."));
}

bool ShawirWiFiClass::connectToSavedWiFi() {
    loadCredentials();

    if (_ssid.length() == 0) {
        Serial.println(F("[ShawirWiFi] Belum ada konfigurasi WiFi tersimpan."));
        return false;
    }

    Serial.print(F("[ShawirWiFi] Menghubungkan ke WiFi tersimpan: "));
    Serial.println(_ssid);

    WiFi.mode(WIFI_STA);
    WiFi.begin(_ssid.c_str(), _pass.c_str());

    unsigned long start = millis();
    while (WiFi.status() != WL_CONNECTED && (millis() - start < _connectTimeout * 1000)) {
        delay(500);
        Serial.print(F("."));
    }

    Serial.println();
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println(F("[ShawirWiFi] Berhasil Terhubung!"));
        Serial.print(F("[ShawirWiFi] IP Address: "));
        Serial.println(WiFi.localIP());
        return true;
    }

    Serial.println(F("[ShawirWiFi] Gagal terhubung ke WiFi tersimpan!"));
    return false;
}

bool ShawirWiFiClass::autoConnect(const char* apName, const char* apPassword) {
    Serial.println();
    Serial.println(F("===================================="));
    Serial.println(F("     ShawirWiFi Manager v1.0.0      "));
    Serial.println(F("===================================="));

    if (connectToSavedWiFi()) {
        return true;
    }

    // Jika gagal terhubung, aktifkan Access Point & Captive Portal
    return startConfigPortal(apName, apPassword);
}

bool ShawirWiFiClass::startConfigPortal(const char* apName, const char* apPassword) {
    _apName = (apName != nullptr && strlen(apName) > 0) ? String(apName) : "ShawirWiFi-Setup";
    _apPassword = (apPassword != nullptr) ? String(apPassword) : "";

    Serial.println(F("[ShawirWiFi] Membuka Mode Access Point Captive Portal..."));

    WiFi.mode(WIFI_AP_STA);
    if (_apPassword.length() >= 8) {
        WiFi.softAP(_apName.c_str(), _apPassword.c_str());
    } else {
        WiFi.softAP(_apName.c_str());
    }

    IPAddress apIP = WiFi.softAPIP();
    Serial.print(F("[ShawirWiFi] Hotspot Aktif: "));
    Serial.println(_apName);
    Serial.print(F("[ShawirWiFi] IP Portal: http://"));
    Serial.println(apIP);
    Serial.println(F("[ShawirWiFi] Hubungkan HP/Laptop ke hotspot tersebut untuk memilih WiFi."));

    if (_dnsServer) delete _dnsServer;
    _dnsServer = new DNSServer();
    _dnsServer->setErrorReplyCode(DNSReplyCode::NoError);
    _dnsServer->start(DNS_PORT, "*", apIP);

    setupWebServer();

    _portalActive = true;
    _configSaved = false;
    unsigned long portalStart = millis();

    while (_portalActive) {
        _dnsServer->processNextRequest();
        _server->handleClient();
        delay(1);

        if (_configSaved) {
            delay(1500);
            Serial.println(F("[ShawirWiFi] Kredensial baru diterima. Menghubungkan..."));
            WiFi.softAPdisconnect(true);
            delay(500);
            _portalActive = false;
            break;
        }

        if (_configTimeout > 0 && (millis() - portalStart > _configTimeout * 1000)) {
            Serial.println(F("[ShawirWiFi] Portal timeout tercapai."));
            _portalActive = false;
            break;
        }
    }

    if (_server) { delete _server; _server = nullptr; }
    if (_dnsServer) { delete _dnsServer; _dnsServer = nullptr; }

    return connectToSavedWiFi();
}

String ShawirWiFiClass::getNetworkScanOptions() {
    int n = WiFi.scanNetworks();
    String options = "";
    if (n == 0) {
        options += "<option value=''>Tidak ada WiFi ditemukan</option>";
    } else {
        for (int i = 0; i < n; ++i) {
            String ssid = WiFi.SSID(i);
            int rssi = WiFi.RSSI(i);
            options += "<option value='" + ssid + "'>" + ssid + " (" + String(rssi) + " dBm)</option>";
        }
    }
    return options;
}

void ShawirWiFiClass::setupWebServer() {
    if (_server) delete _server;
    _server = new ShawirWebServer(80);

    _server->on("/", [this]() { handleRoot(); });
    _server->on("/save", HTTP_POST, [this]() { handleSave(); });
    _server->on("/reset", [this]() { handleReset(); });
    _server->on("/generate_204", [this]() { handleRoot(); }); // Android captive
    _server->on("/fwlink", [this]() { handleRoot(); });       // Windows captive
    _server->onNotFound([this]() { handleNotFound(); });

    _server->begin();
}

void ShawirWiFiClass::handleRoot() {
    String scanOptions = getNetworkScanOptions();

    String html = F("<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'>"
        "<meta name='viewport' content='width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no'>"
        "<title>ShawirWiFi Setup</title><style>"
        "*{box-sizing:border-box;margin:0;padding:0;font-family:sans-serif;}"
        "body{background:#0b0f19;color:#f1f5f9;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:1rem;}"
        ".card{background:#162032;border:1px solid rgba(99,102,241,0.25);border-radius:18px;max-width:380px;width:100%;padding:1.75rem;box-shadow:0 10px 30px rgba(0,0,0,0.5);text-align:center;}"
        ".logo{width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#4f46e5,#0891b2);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.5rem;font-weight:900;color:#fff;}"
        "h2{font-size:1.35rem;margin-bottom:0.25rem;color:#fff;}"
        "p{font-size:0.85rem;color:#94a3b8;margin-bottom:1.5rem;}"
        ".form-group{text-align:left;margin-bottom:1rem;}"
        "label{display:block;font-size:0.8rem;font-weight:600;color:#cbd5e1;margin-bottom:0.35rem;}"
        "select,input{width:100%;padding:0.75rem;border-radius:10px;background:#0b0f19;border:1px solid rgba(255,255,255,0.12);color:#fff;font-size:0.9rem;outline:none;}"
        "select:focus,input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.2);}"
        ".btn{width:100%;padding:0.85rem;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#0891b2);color:#fff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;margin-top:0.5rem;transition:all 0.2s;}"
        ".btn:active{transform:scale(0.97);}"
        ".footer{font-size:0.75rem;color:#64748b;margin-top:1.5rem;}"
        "</style></head><body><div class='card'>"
        "<div class='logo'>📶</div>"
        "<h2>ShawirWiFi Setup</h2>"
        "<p>Pilih jaringan WiFi untuk perangkat Anda</p>"
        "<form action='/save' method='POST'>"
        "<div class='form-group'><label>Pilih WiFi Terdekat:</label>"
        "<select name='ssid_select' onchange='if(this.value){document.getElementById(\"ssid\").value=this.value;}'>"
        "<option value=''>-- Pilih SSID --</option>");

    html += scanOptions;

    html += F("</select></div>"
        "<div class='form-group'><label>Nama WiFi (SSID):</label>"
        "<input type='text' id='ssid' name='ssid' placeholder='Nama WiFi' required></div>"
        "<div class='form-group'><label>Password WiFi:</label>"
        "<input type='password' name='pass' placeholder='Password WiFi'></div>"
        "<button type='submit' class='btn'>Hubungkan WiFi</button>"
        "</form><div class='footer'>Platform IoT ShawirIOT &middot; v1.0</div></div></body></html>");

    _server->send(200, "text/html", html);
}

void ShawirWiFiClass::handleSave() {
    String newSsid = _server->arg("ssid");
    String newPass = _server->arg("pass");
    newSsid.trim();
    newPass.trim();

    if (newSsid.length() > 0) {
        saveCredentials(newSsid, newPass);
        _configSaved = true;

        String html = F("<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'>"
            "<meta name='viewport' content='width=device-width,initial-scale=1'>"
            "<title>Tersimpan</title><style>"
            "body{background:#0b0f19;color:#fff;display:flex;justify-content:center;align-items:center;min-height:100vh;font-family:sans-serif;text-align:center;padding:1rem;}"
            ".card{background:#162032;padding:2rem;border-radius:18px;max-width:350px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.5);border:1px solid #10b981;}"
            "h2{color:#10b981;margin-bottom:0.5rem;font-size:1.4rem;}"
            "p{color:#94a3b8;font-size:0.88rem;line-height:1.5;}"
            "</style></head><body><div class='card'>"
            "<h2>✓ Berhasil Disimpan!</h2>"
            "<p>Perangkat sedang menghubungkan ke jaringan <strong>");
        html += newSsid;
        html += F("</strong>.<br><br>Hotspot ini akan ditutup secara otomatis.</p></div></body></html>");

        _server->send(200, "text/html", html);
    } else {
        _server->send(400, "text/plain", "SSID tidak boleh kosong.");
    }
}

void ShawirWiFiClass::handleReset() {
    resetSettings();
    _server->send(200, "text/plain", "Kredensial WiFi berhasil direset.");
}

void ShawirWiFiClass::handleNotFound() {
    _server->sendHeader("Location", String("http://") + WiFi.softAPIP().toString() + "/", true);
    _server->send(302, "text/plain", "");
}

bool ShawirWiFiClass::isConnected() {
    return WiFi.status() == WL_CONNECTED;
}

String ShawirWiFiClass::getSSID() {
    return _ssid;
}

String ShawirWiFiClass::getPassword() {
    return _pass;
}

void ShawirWiFiClass::setConnectTimeout(unsigned long seconds) {
    _connectTimeout = max((unsigned long)5, seconds);
}

void ShawirWiFiClass::setConfigTimeout(unsigned long seconds) {
    _configTimeout = seconds;
}

ShawirWiFiClass ShawirWiFi;
