#include <FS.h>              // Harus di-include pertama, atau semuanya akan crash!
#include <shawirWifi.h>      // library shawirWifi by Shawir

#ifdef ESP32
  #include <SPIFFS.h>
#endif

#include <ArduinoJson.h>     // https://github.com/bblanchon/ArduinoJson

// Definisikan nilai default di sini.
// Jika ada nilai berbeda di config.json, nilai tersebut akan menimpa yang di bawah.
char mqtt_server[40];
char mqtt_port[6] = "8080";
char api_token[34] = "TOKEN_API_KAMU";

// Flag untuk menandai perlu menyimpan konfigurasi
bool perluSimpanKonfig = false;

// Callback: dipanggil saat konfigurasi perlu disimpan
void saveConfigCallback() {
  Serial.println("[shawirWifi] Konfigurasi akan disimpan...");
  perluSimpanKonfig = true;
}

void setup() {
  Serial.begin(115200);
  Serial.println();

  // Format SPIFFS untuk pengujian (hapus komentar jika perlu)
  // SPIFFS.format();

  // Baca konfigurasi dari file JSON di SPIFFS
  Serial.println("[SPIFFS] Mencoba mount file system...");

  if (SPIFFS.begin()) {
    Serial.println("[SPIFFS] File system berhasil di-mount");
    if (SPIFFS.exists("/config.json")) {
      // File konfigurasi ada, baca dan muat
      Serial.println("[SPIFFS] Membaca file konfigurasi...");
      File configFile = SPIFFS.open("/config.json", "r");
      if (configFile) {
        Serial.println("[SPIFFS] File konfigurasi berhasil dibuka");
        size_t ukuran = configFile.size();
        // Alokasi buffer untuk menyimpan isi file
        std::unique_ptr<char[]> buf(new char[ukuran]);

        configFile.readBytes(buf.get(), ukuran);

 #if defined(ARDUINOJSON_VERSION_MAJOR) && ARDUINOJSON_VERSION_MAJOR >= 6
        DynamicJsonDocument json(1024);
        auto deserializeError = deserializeJson(json, buf.get());
        serializeJson(json, Serial);
        if (!deserializeError) {
#else
        DynamicJsonBuffer jsonBuffer;
        JsonObject& json = jsonBuffer.parseObject(buf.get());
        json.printTo(Serial);
        if (json.success()) {
#endif
          Serial.println("\n[SPIFFS] JSON berhasil diparse");
          strcpy(mqtt_server, json["mqtt_server"]);
          strcpy(mqtt_port, json["mqtt_port"]);
          strcpy(api_token, json["api_token"]);
        } else {
          Serial.println("[SPIFFS] Gagal memuat konfigurasi JSON");
        }
        configFile.close();
      }
    }
  } else {
    Serial.println("[SPIFFS] Gagal mount file system");
  }
  // Selesai baca konfigurasi

  // Parameter tambahan yang bisa dikonfigurasi melalui portal
  // Setelah terhubung, gunakan parameter.getValue() untuk mendapatkan nilai
  // Format: ID/nama, placeholder/label, nilai default, panjang maks
  shawirWifiParameter param_server_mqtt("server", "Server MQTT", mqtt_server, 40);
  shawirWifiParameter param_port_mqtt("port", "Port MQTT", mqtt_port, 6);
  shawirWifiParameter param_api_token("apikey", "Token API", api_token, 32);

  // Inisialisasi shawirWifi
  shawirWifi wm;

  // Daftarkan callback untuk menyimpan konfigurasi
  wm.setSaveConfigCallback(saveConfigCallback);

  // Konfigurasi IP statis
  wm.setSTAStaticIPConfig(IPAddress(10, 0, 1, 99), IPAddress(10, 0, 1, 1), IPAddress(255, 255, 255, 0));

  // Tambahkan semua parameter ke portal
  wm.addParameter(&param_server_mqtt);
  wm.addParameter(&param_port_mqtt);
  wm.addParameter(&param_api_token);

  // Reset pengaturan — untuk pengujian (hapus komentar jika perlu)
  // wm.resetSettings();

  // Atur kualitas sinyal minimum (default 8%)
  // wm.setMinimumSignalQuality();

  // Atur timeout portal konfigurasi (dalam detik)
  // wm.setTimeout(120);

  // Ambil SSID dan password, coba hubungkan.
  // Jika gagal, buka Access Point dengan nama yang ditentukan
  // dan tunggu sampai dikonfigurasi (blocking).
  if (!wm.autoConnect("shawirWifi-AP", "password")) {
    Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
    delay(3000);
    // Reset dan coba lagi, atau masuk deep sleep
    ESP.restart();
    delay(5000);
  }

  // Jika sampai sini berarti sudah terhubung ke WiFi
  Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");

  // Baca nilai parameter yang sudah diperbarui
  strcpy(mqtt_server, param_server_mqtt.getValue());
  strcpy(mqtt_port, param_port_mqtt.getValue());
  strcpy(api_token, param_api_token.getValue());
  Serial.println("Nilai dalam konfigurasi:");
  Serial.println("\tmqtt_server : " + String(mqtt_server));
  Serial.println("\tmqtt_port   : " + String(mqtt_port));
  Serial.println("\tapi_token   : " + String(api_token));

  // Simpan parameter kustom ke SPIFFS jika ada perubahan
  if (perluSimpanKonfig) {
    Serial.println("[SPIFFS] Menyimpan konfigurasi...");
 #if defined(ARDUINOJSON_VERSION_MAJOR) && ARDUINOJSON_VERSION_MAJOR >= 6
    DynamicJsonDocument json(1024);
#else
    DynamicJsonBuffer jsonBuffer;
    JsonObject& json = jsonBuffer.createObject();
#endif
    json["mqtt_server"] = mqtt_server;
    json["mqtt_port"] = mqtt_port;
    json["api_token"] = api_token;

    File configFile = SPIFFS.open("/config.json", "w");
    if (!configFile) {
      Serial.println("[SPIFFS] Gagal membuka file konfigurasi untuk ditulis");
    }

#if defined(ARDUINOJSON_VERSION_MAJOR) && ARDUINOJSON_VERSION_MAJOR >= 6
    serializeJson(json, Serial);
    serializeJson(json, configFile);
#else
    json.printTo(Serial);
    json.printTo(configFile);
#endif
    configFile.close();
    // Selesai simpan
  }

  Serial.println("IP lokal: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
