/**
 * shawirWifi.h
 * 
 * shawirWifi - Custom WiFi Manager Library
 * Based on WiFiManager by tzapu/tablatronix
 * for configuration of WiFi credentials using a Captive Portal
 * on ESP8266/ESP32 platforms.
 * 
 * Customized by Shawir
 * @license MIT
 */

#ifndef shawirWifi_h
#define shawirWifi_h

// Include the core WiFiManager implementation
#include "WiFiManager.h"

/**
 * shawirWifi is an alias for WiFiManager.
 * Use shawirWifi instead of WiFiManager in your sketches.
 * 
 * Example:
 *   shawirWifi wm;
 *   wm.autoConnect("shawirWifi-AP");
 */
using shawirWifi = WiFiManager;

/**
 * shawirWifiParameter is an alias for WiFiManagerParameter.
 */
using shawirWifiParameter = WiFiManagerParameter;

#endif // shawirWifi_h
