/**
 * ShawirIOT - Arduino & ESP Library Implementation
 * Version 1.0.0
 */

#include "ShawirIOT.h"

ShawirIOTClass::ShawirIOTClass() {
    _token = "";
    _ssid = "";
    _pass = "";
    _serverHost = "";
    _serverPort = 80;
    _baseApiUrl = "";
    _lastPollTime = 0;
    _pollInterval = 1000; // 1s default
    _lastHeartbeat = 0;
    _isConfigured = false;
    _listenerCount = 0;
}

void ShawirIOTClass::begin(const char* token, const char* ssid, const char* pass, const char* serverHost, uint16_t serverPort) {
    _token = String(token);
    _ssid = String(ssid);
    _pass = String(pass);
    _serverHost = String(serverHost);
    _serverPort = serverPort;

    // Form base URL
    if (_serverPort == 80) {
        _baseApiUrl = "http://" + _serverHost + "/api/data.php";
    } else {
        _baseApiUrl = "http://" + _serverHost + ":" + String(_serverPort) + "/api/data.php";
    }

    _isConfigured = true;

    Serial.println();
    Serial.println(F("===================================="));
    Serial.println(F("    ShawirIOT Client Initializing   "));
    Serial.println(F("===================================="));

    connectWiFi();
}

void ShawirIOTClass::connectWiFi() {
    if (_ssid.length() == 0) return;

    Serial.print(F("[ShawirIOT] Connecting to WiFi: "));
    Serial.println(_ssid);

    WiFi.mode(WIFI_STA);
    WiFi.begin(_ssid.c_str(), _pass.c_str());

    int timeout = 30; // 15 seconds
    while (WiFi.status() != WL_CONNECTED && timeout > 0) {
        delay(500);
        Serial.print(F("."));
        timeout--;
    }

    Serial.println();
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println(F("[ShawirIOT] WiFi Connected!"));
        Serial.print(F("[ShawirIOT] IP Address: "));
        Serial.println(WiFi.localIP());
        Serial.print(F("[ShawirIOT] Server API: "));
        Serial.println(_baseApiUrl);
        Serial.println(F("[ShawirIOT] Ready to stream data!"));
    } else {
        Serial.println(F("[ShawirIOT] WiFi Connection Failed! Check SSID/Password."));
    }
}

bool ShawirIOTClass::connected() {
    return WiFi.status() == WL_CONNECTED;
}

void ShawirIOTClass::setPollInterval(unsigned long ms) {
    _pollInterval = max((unsigned long)200, ms);
}

void ShawirIOTClass::run() {
    if (!_isConfigured) return;

    // Auto reconnect WiFi if dropped
    if (WiFi.status() != WL_CONNECTED) {
        static unsigned long lastReconnect = 0;
        if (millis() - lastReconnect > 10000) {
            lastReconnect = millis();
            Serial.println(F("[ShawirIOT] Reconnecting WiFi..."));
            WiFi.reconnect();
        }
        return;
    }

    // Poll registered virtual pins if any listeners exist
    if (_listenerCount > 0 && (millis() - _lastPollTime >= _pollInterval)) {
        _lastPollTime = millis();
        pollRegisteredPins();
    }
}

bool ShawirIOTClass::virtualWrite(const char* pin, const String& value) {
    if (!connected()) return false;
    return sendHttpPost(String(pin), value);
}

bool ShawirIOTClass::virtualWrite(const char* pin, int value) {
    return virtualWrite(pin, String(value));
}

bool ShawirIOTClass::virtualWrite(const char* pin, float value, int precision) {
    return virtualWrite(pin, String(value, precision));
}

bool ShawirIOTClass::virtualWrite(const char* pin, double value, int precision) {
    return virtualWrite(pin, String(value, precision));
}

bool ShawirIOTClass::sendHttpPost(const String& pin, const String& value) {
    WiFiClient client;
    HTTPClient http;

    if (!http.begin(client, _baseApiUrl)) {
        return false;
    }

    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String postData = "token=" + _token + "&pin=" + pin + "&value=" + value + "&source=device";
    int httpCode = http.POST(postData);

    bool success = (httpCode == 200);
    if (!success && httpCode > 0) {
        Serial.print(F("[ShawirIOT] Post Pin "));
        Serial.print(pin);
        Serial.print(F(" Error HTTP Code: "));
        Serial.println(httpCode);
    }

    http.end();
    return success;
}

String ShawirIOTClass::virtualRead(const char* pin) {
    if (!connected()) return "";

    WiFiClient client;
    HTTPClient http;

    String url = _baseApiUrl + "?token=" + _token + "&pin=" + String(pin);
    if (!http.begin(client, url)) {
        return "";
    }

    int httpCode = http.GET();
    String payload = "";

    if (httpCode == 200) {
        payload = http.getString();
        // Parse simple JSON: {"success":true,"data":{"pin":"V1","value":"1"}}
        int valIdx = payload.indexOf("\"value\":");
        if (valIdx != -1) {
            int startQuote = payload.indexOf("\"", valIdx + 8);
            if (startQuote != -1) {
                int endQuote = payload.indexOf("\"", startQuote + 1);
                if (endQuote != -1) {
                    payload = payload.substring(startQuote + 1, endQuote);
                }
            } else {
                // Numeric value without quotes
                int comma = payload.indexOf(",", valIdx);
                int brace = payload.indexOf("}", valIdx);
                int endIdx = (comma != -1 && comma < brace) ? comma : brace;
                payload = payload.substring(valIdx + 8, endIdx);
                payload.trim();
            }
        }
    }

    http.end();
    return payload;
}

int ShawirIOTClass::virtualReadInt(const char* pin) {
    return virtualRead(pin).toInt();
}

float ShawirIOTClass::virtualReadFloat(const char* pin) {
    return virtualRead(pin).toFloat();
}

void ShawirIOTClass::onWrite(const char* pin, ShawirPinHandler handler) {
    if (_listenerCount >= MAX_LISTENERS) {
        Serial.println(F("[ShawirIOT] Error: Max pin listeners reached!"));
        return;
    }

    _listeners[_listenerCount].pin = String(pin);
    _listeners[_listenerCount].handler = handler;
    _listeners[_listenerCount].lastValue = "";
    _listenerCount++;
}

void ShawirIOTClass::pollRegisteredPins() {
    for (int i = 0; i < _listenerCount; i++) {
        String currentVal = virtualRead(_listeners[i].pin.c_str());
        if (currentVal.length() > 0 && currentVal != _listeners[i].lastValue) {
            _listeners[i].lastValue = currentVal;
            if (_listeners[i].handler != nullptr) {
                _listeners[i].handler(currentVal);
            }
        }
    }
}

// Pre-instantiated object
ShawirIOTClass ShawirIOT;
