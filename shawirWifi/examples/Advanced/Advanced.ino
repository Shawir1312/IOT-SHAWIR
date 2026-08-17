/**
 * shawirWifi - Contoh Lanjutan (Advanced)
 * 
 * Berisi opsi konfigurasi lanjutan:
 * - Tombol TRIGGER_PIN: tekan sekali untuk membuka portal konfigurasi,
 *   tahan 3 detik untuk reset semua pengaturan WiFi.
 * - Mode blocking dan non-blocking.
 * - Parameter custom (input HTML).
 * - Menu portal kustom.
 * - Tema gelap (dark mode).
 * - Timeout portal otomatis.
 */
#include <shawirWifi.h> // library shawirWifi by Shawir

#define TRIGGER_PIN 0

// shawirWifi dapat berjalan dalam mode blocking atau non-blocking.
// Pastikan loop() Anda tidak menggunakan delay() jika memakai non-blocking.
bool wm_nonblocking = false; // ubah ke true untuk menggunakan non-blocking

shawirWifi wm;                   // instance wm global
shawirWifiParameter custom_field; // parameter global (untuk non-blocking dengan parameter)

void setup() {
  WiFi.mode(WIFI_STA); // atur mode WiFi ke STA (Station) saja
  Serial.begin(115200);
  Serial.setDebugOutput(true);
  delay(3000);
  Serial.println("\n[shawirWifi] Memulai...");

  pinMode(TRIGGER_PIN, INPUT);
  
  // wm.resetSettings(); // hapus semua pengaturan WiFi tersimpan

  if (wm_nonblocking) wm.setConfigPortalBlocking(false);

  // Tambahkan input field kustom
  int panjangFieldKustom = 40;

  // Contoh input teks biasa:
  // new (&custom_field) shawirWifiParameter("customfieldid", "Label Field", "Nilai Default", panjangFieldKustom, "placeholder=\"Placeholder Field\"");
  
  // Contoh input checkbox:
  // new (&custom_field) shawirWifiParameter("customfieldid", "Label Field", "Nilai Default", panjangFieldKustom, "placeholder=\"Placeholder Field\" type=\"checkbox\"");
  
  // Contoh input radio button (HTML kustom):
  const char* custom_radio_str = "<br/><label for='customfieldid'>Pilih Opsi</label><input type='radio' name='customfieldid' value='1' checked> Satu<br><input type='radio' name='customfieldid' value='2'> Dua<br><input type='radio' name='customfieldid' value='3'> Tiga";
  new (&custom_field) shawirWifiParameter(custom_radio_str); // HTML input kustom
  
  wm.addParameter(&custom_field);
  wm.setSaveParamsCallback(saveParamCallback);

  // Konfigurasi menu portal via array atau vector
  // Token menu: "wifi","wifinoscan","info","param","close","sep","erase","restart","exit"
  // "sep" = pemisah, jika "param" ada di menu maka param tidak akan muncul di halaman wifi
  // const char* menu[] = {"wifi","info","param","sep","restart","exit"};
  // wm.setMenu(menu, 6);
  std::vector<const char *> menu = {"wifi","info","param","sep","restart","exit"};
  wm.setMenu(menu);

  // Aktifkan tema gelap (dark mode)
  wm.setClass("invert");

  // Konfigurasi IP statis (opsional):
  // wm.setSTAStaticIPConfig(IPAddress(10,0,1,99), IPAddress(10,0,1,1), IPAddress(255,255,255,0));
  // wm.setShowStaticFields(true); // selalu tampilkan kolom IP statis
  // wm.setShowDnsFields(true);    // selalu tampilkan kolom DNS

  // wm.setConnectTimeout(20); // batas waktu percobaan koneksi (detik)
  wm.setConfigPortalTimeout(30); // tutup portal otomatis setelah 30 detik
  // wm.setCaptivePortalEnable(false); // nonaktifkan redirect captive portal
  // wm.setAPClientCheck(true);        // jangan timeout jika ada client di AP

  // Pengaturan scan WiFi:
  // wm.setRemoveDuplicateAPs(false); // tampilkan AP duplikat (default: dihapus)
  // wm.setMinimumSignalQuality(20);  // kualitas sinyal minimum % untuk ditampilkan
  // wm.setShowInfoErase(false);      // sembunyikan tombol hapus di halaman info
  // wm.setScanDispPerc(true);        // tampilkan RSSI sebagai persentase, bukan ikon
  
  // wm.setBreakAfterConfig(true); // selalu keluar dari portal meski koneksi gagal

  bool hasil;
  // hasil = wm.autoConnect();                          // nama AP dari chip ID otomatis
  // hasil = wm.autoConnect("shawirWifi-AP");           // AP tanpa password
  hasil = wm.autoConnect("shawirWifi-AP", "password"); // AP dengan password

  if (!hasil) {
    Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
    // ESP.restart();
  } 
  else {
    // Jika sampai sini berarti sudah terhubung ke WiFi
    Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
  }
}

void cekTombol() {
  // Deteksi penekanan tombol
  if (digitalRead(TRIGGER_PIN) == LOW) {
    // Debounce sederhana — tidak ideal untuk produksi
    delay(50);
    if (digitalRead(TRIGGER_PIN) == LOW) {
      Serial.println("[shawirWifi] Tombol ditekan");
      // Tahan selama 3 detik untuk reset pengaturan
      delay(3000);
      if (digitalRead(TRIGGER_PIN) == LOW) {
        Serial.println("[shawirWifi] Tombol ditahan — menghapus pengaturan WiFi...");
        wm.resetSettings();
        ESP.restart();
      }
      
      // Buka portal konfigurasi dengan delay
      Serial.println("[shawirWifi] Membuka portal konfigurasi...");
      wm.setConfigPortalTimeout(120);
      
      if (!wm.startConfigPortal("shawirWifi-OnDemand", "password")) {
        Serial.println("[shawirWifi] Gagal terhubung atau waktu habis!");
        delay(3000);
        // ESP.restart();
      } else {
        // Berhasil terhubung ke WiFi
        Serial.println("[shawirWifi] Berhasil terhubung ke WiFi!");
      }
    }
  }
}


String ambilParameter(String nama) {
  // Baca nilai parameter dari server (untuk HTML input kustom)
  String nilai;
  if (wm.server->hasArg(nama)) {
    nilai = wm.server->arg(nama);
  }
  return nilai;
}

void saveParamCallback() {
  Serial.println("[CALLBACK] saveParamCallback dipanggil");
  Serial.println("Nilai customfieldid = " + ambilParameter("customfieldid"));
}

void loop() {
  if (wm_nonblocking) wm.process(); // proses portal jika non-blocking, hindari delay()
  cekTombol();
  // Tulis kode utama di sini, akan dijalankan berulang-ulang
}
