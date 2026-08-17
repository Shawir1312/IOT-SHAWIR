/**
 * wm_strings_id.h
 * Bahasa Indonesia - Tampilan Modern Dark UI
 * shawirWifi - based on WiFiManager by tzapu/tablatronix
 * Customized by Shawir
 * @license MIT
 */

#ifndef _WM_STRINGS_ID_H_
#define _WM_STRINGS_ID_H_

#ifndef WIFI_MANAGER_OVERRIDE_STRINGS

// strings files must include a consts file!
#include "wm_consts_en.h"

const char WM_LANGUAGE[] PROGMEM = "id-ID";

// ---------------------------------------------------------------------------
// HEAD

const char HTTP_HEAD_START[] PROGMEM =
  "<!DOCTYPE html>"
  "<html lang='id'><head>"
  "<meta name='format-detection' content='telephone=no'>"
  "<meta charset='UTF-8'>"
  "<meta name='viewport' content='width=device-width,initial-scale=1,user-scalable=no'/>"
  "<title>{v}</title>";

// JavaScript: klik item WiFi → isi form SSID, aktifkan/nonaktifkan password
const char HTTP_SCRIPT[] PROGMEM =
  "<script>"
  "function c(l){"
    "document.getElementById('s').value=l.getAttribute('data-ssid')||l.innerText||l.textContent;"
    "p=l.nextElementSibling.classList.contains('l');"
    "document.getElementById('p').disabled=!p;"
    "if(p)document.getElementById('p').focus();"
  "};"
  "function f(){"
    "var x=document.getElementById('p');"
    "x.type==='password'?x.type='text':x.type='password';"
  "}"
  "</script>";

// Akhir head + awal body
const char HTTP_HEAD_END[] PROGMEM = "</head><body class='{c}'><div class='wrap'>";

// Header beranda: logo SVG WiFi + judul + versi
const char HTTP_ROOT_MAIN[] PROGMEM =
  "<div class='logo'>"
    "<svg width='56' height='56' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'>"
      "<defs>"
        "<linearGradient id='wg' x1='0' y1='0' x2='1' y2='1'>"
          "<stop stop-color='#818cf8'/>"
          "<stop offset='1' stop-color='#c084fc'/>"
        "</linearGradient>"
      "</defs>"
      "<path d='M1.5 8.5C5 5 8.8 3 12 3s7 2 10.5 5.5'"
        " stroke='url(#wg)' stroke-width='2.2' stroke-linecap='round'/>"
      "<path d='M4.5 11.5C7 9 9.7 7.5 12 7.5s5 1.5 7.5 4'"
        " stroke='url(#wg)' stroke-width='2.2' stroke-linecap='round'/>"
      "<path d='M7.5 14.5C9.3 12.7 10.8 12 12 12s2.7.7 4.5 2.5'"
        " stroke='url(#wg)' stroke-width='2.2' stroke-linecap='round'/>"
      "<circle cx='12' cy='18' r='1.5' fill='url(#wg)'/>"
    "</svg>"
  "</div>"
  "<h1>{t}</h1>"
  "<p class='sub'>shawirWifi &bull; v{v}</p>";

// ---------------------------------------------------------------------------
// MENU PORTAL

const char * const HTTP_PORTAL_MENU[] PROGMEM = {
  "<form action='/wifi'    method='get'><button>&#128246; Pilih Jaringan WiFi</button></form><br/>\n",   // MENU_WIFI
  "<form action='/0wifi'  method='get'><button>Atur WiFi (Tanpa Scan)</button></form><br/>\n",          // MENU_WIFINOSCAN
  "<form action='/info'   method='get'><button>&#8505; Informasi Perangkat</button></form><br/>\n",     // MENU_INFO
  "<form action='/param'  method='get'><button>&#9881; Pengaturan Lanjutan</button></form><br/>\n",     // MENU_PARAM
  "<form action='/close'  method='get'><button>Tutup Portal</button></form><br/>\n",                    // MENU_CLOSE
  "<form action='/restart' method='get'><button>&#8635; Mulai Ulang ESP</button></form><br/>\n",        // MENU_RESTART
  "<form action='/exit'   method='get'><button>Keluar Portal</button></form><br/>\n",                   // MENU_EXIT
  "<form action='/erase'  method='get'><button class='D'>&#9888; Hapus Konfigurasi WiFi</button></form><br/>\n", // MENU_ERASE
  "<form action='/update' method='get'><button>&#8593; Update Firmware</button></form><br/>\n",         // MENU_UPDATE
  "<hr><br/>"                                                                                           // MENU_SEP
};

const char HTTP_PORTAL_OPTIONS[] PROGMEM = "";

// ---------------------------------------------------------------------------
// DAFTAR WIFI (SCAN RESULT)

// Blok sinyal: bar kekuatan + persentase + kelas 'l' untuk jaringan terenkripsi
// {i} = 'l' jika terenkripsi (dipakai JS untuk deteksi password)
// {q} = kualitas sinyal 0-4 (warna bar)
// {r} = persentase sinyal 0-100 (lebar bar & teks)
const char HTTP_ITEM_QI[] PROGMEM =
  "<div class='wsi {i}'>"
    "<div class='wsb'>"
      "<div class='wsf q-{q}' style='width:{r}%'></div>"
    "</div>"
    "<span class='wsp'>{r}%</span>"
  "</div>";

// Tidak digunakan (disimpan untuk kompatibilitas)
const char HTTP_ITEM_QP[] PROGMEM = "";

// Baris item jaringan WiFi — kelas 'wi' untuk styling card row
const char HTTP_ITEM[]    PROGMEM =
  "<div class='wi'>"
    "<a href='#p' onclick='c(this)' data-ssid='{V}'>{v}</a>"
    "{qi}"
  "</div>";

// ---------------------------------------------------------------------------
// FORM WiFi

const char HTTP_FORM_START[]       PROGMEM = "<form method='POST' action='{v}'>";

const char HTTP_FORM_WIFI[]        PROGMEM =
  "<label for='s'>Nama Jaringan (SSID)</label>"
  "<input id='s' name='s' maxlength='32' autocorrect='off'"
    " autocapitalize='none' placeholder='{v}'>"
  "<label for='p'>Kata Sandi WiFi</label>"
  "<input id='p' name='p' maxlength='64' type='password' placeholder='{p}'>"
  "<div class='cb'>"
    "<input type='checkbox' id='showpass' onclick='f()'>"
    "<label for='showpass' style='display:inline;text-transform:none;"
      "letter-spacing:0;margin:0;color:#94a3b8'>Tampilkan Kata Sandi</label>"
  "</div>";

const char HTTP_FORM_WIFI_END[]    PROGMEM = "";
const char HTTP_FORM_STATIC_HEAD[] PROGMEM = "<hr><br/>";
const char HTTP_FORM_END[]         PROGMEM =
  "<br/><button type='submit'>&#10003; Simpan &amp; Hubungkan</button></form>";
const char HTTP_FORM_LABEL[]       PROGMEM = "<label for='{i}'>{t}</label>";
const char HTTP_FORM_PARAM_HEAD[]  PROGMEM = "<hr><br/>";
const char HTTP_FORM_PARAM[]       PROGMEM =
  "<br/><input id='{i}' name='{n}' maxlength='{l}' value='{v}' {c}>\n";

// ---------------------------------------------------------------------------
// TOMBOL & PESAN

const char HTTP_SCAN_LINK[]  PROGMEM =
  "<br/><form action='/wifi?refresh=1' method='POST'>"
    "<button name='refresh' value='1'>&#8635; Segarkan Daftar WiFi</button>"
  "</form>";

const char HTTP_SAVED[]      PROGMEM =
  "<div class='msg P'>"
    "<strong>Menyimpan Kredensial...</strong><br/>"
    "Mencoba menghubungkan ESP ke jaringan WiFi.<br/>"
    "<em>Jika gagal, sambung kembali ke AP untuk mencoba lagi.</em>"
  "</div>";

const char HTTP_PARAMSAVED[] PROGMEM =
  "<div class='msg S'><strong>&#10003; Pengaturan Tersimpan!</strong><br/></div>";

const char HTTP_END[]        PROGMEM = "</div></body></html>";

const char HTTP_ERASEBTN[]   PROGMEM =
  "<br/><form action='/erase' method='get'>"
    "<button class='D'>&#9888; Hapus Konfigurasi WiFi</button>"
  "</form>";

const char HTTP_UPDATEBTN[]  PROGMEM =
  "<br/><form action='/update' method='get'>"
    "<button>&#8593; Update Firmware</button>"
  "</form>";

const char HTTP_BACKBTN[]    PROGMEM =
  "<hr><br/><form action='/' method='get'>"
    "<button>&#8592; Kembali ke Beranda</button>"
  "</form>";

// ---------------------------------------------------------------------------
// STATUS KONEKSI

const char HTTP_STATUS_ON[]      PROGMEM =
  "<div class='msg S'>"
    "<strong>Terhubung</strong> ke {v}<br/>"
    "<em><small>Alamat IP: {i}</small></em>"
  "</div>";

const char HTTP_STATUS_OFF[]     PROGMEM =
  "<div class='msg D'><strong>Tidak Terhubung</strong> ke {v}{r}</div>";

const char HTTP_STATUS_OFFPW[]   PROGMEM = "<br/>Autentikasi gagal &mdash; kata sandi salah";
const char HTTP_STATUS_OFFNOAP[] PROGMEM = "<br/>Jaringan WiFi tidak ditemukan";
const char HTTP_STATUS_OFFFAIL[] PROGMEM = "<br/>Gagal terhubung ke jaringan";
const char HTTP_STATUS_NONE[]    PROGMEM =
  "<div class='msg'>Belum ada jaringan WiFi yang dikonfigurasi</div>";

const char HTTP_BR[] PROGMEM = "<br/>";

// ---------------------------------------------------------------------------
// CSS MODERN DARK UI

const char HTTP_STYLE[] PROGMEM = "<style>"
  // ── Reset & Base ──────────────────────────────────────────────────────────
  "*{box-sizing:border-box}"
  "body{"
    "font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;"
    "background:linear-gradient(135deg,#0f0c29 0%,#302b63 60%,#24243e 100%);"
    "min-height:100vh;color:#e2e8f0;text-align:center;padding:16px 12px"
  "}"

  // ── Glass Card Container ───────────────────────────────────────────────────
  ".wrap{"
    "display:inline-block;text-align:left;min-width:280px;max-width:440px;width:100%;"
    "background:rgba(255,255,255,.07);"
    "backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);"
    "border:1px solid rgba(255,255,255,.13);"
    "border-radius:20px;padding:28px 22px 32px;"
    "box-shadow:0 25px 60px rgba(0,0,0,.55);"
    "animation:su .4s ease"
  "}"

  // ── Headings ──────────────────────────────────────────────────────────────
  "h1{"
    "text-align:center;font-size:1.7rem;font-weight:700;"
    "background:linear-gradient(135deg,#818cf8,#c084fc);"
    "-webkit-background-clip:text;background-clip:text;"
    "-webkit-text-fill-color:transparent;"
    "margin:10px 0 4px"
  "}"
  "h3{text-align:center;color:#94a3b8;font-size:.82rem;margin-bottom:16px;font-weight:400}"
  "h4{color:#94a3b8;font-size:.78rem;font-weight:600;text-transform:uppercase;"
      "letter-spacing:.06em;margin:0 0 10px}"

  // ── Logo & Subtitle ───────────────────────────────────────────────────────
  ".logo{text-align:center;margin:4px 0 0;line-height:1}"
  ".sub{text-align:center;font-size:.74rem;color:#64748b;margin:2px 0 18px}"

  // ── Labels ────────────────────────────────────────────────────────────────
  "label{"
    "display:block;font-size:.78rem;font-weight:600;"
    "color:#94a3b8;margin:12px 0 5px;"
    "text-transform:uppercase;letter-spacing:.05em"
  "}"

  // ── Inputs & Select ───────────────────────────────────────────────────────
  "input,select{"
    "display:block;width:100%;padding:11px 14px;"
    "background:rgba(255,255,255,.08);"
    "border:1.5px solid rgba(255,255,255,.12);"
    "border-radius:10px;color:#e2e8f0;font-size:.95rem;"
    "outline:none;transition:border .2s,box-shadow .2s;margin:3px 0"
  "}"
  "input::placeholder{color:#475569}"
  "input:focus{"
    "border-color:#818cf8;"
    "box-shadow:0 0 0 3px rgba(129,140,248,.22)"
  "}"
  "input[type=checkbox],input[type=radio]{"
    "width:auto;display:inline;cursor:pointer;"
    "margin:0 6px 0 0;vertical-align:middle"
  "}"
  "input[type=file]{border:1.5px solid rgba(129,140,248,.4);color:#94a3b8}"

  // ── Checkbox Row ──────────────────────────────────────────────────────────
  ".cb{display:flex;align-items:center;margin:10px 0 4px}"

  // ── Buttons ───────────────────────────────────────────────────────────────
  "button,input[type=submit],input[type=button]{"
    "display:block;width:100%;padding:12px;"
    "background:linear-gradient(135deg,#667eea,#764ba2);"
    "border:none;border-radius:10px;color:#fff;"
    "font-size:.95rem;font-weight:600;cursor:pointer;"
    "margin:7px 0;"
    "transition:transform .15s,box-shadow .15s,opacity .1s;"
    "box-shadow:0 4px 15px rgba(102,126,234,.4);"
    "letter-spacing:.02em;line-height:1.5"
  "}"
  "button:hover{"
    "transform:translateY(-2px);"
    "box-shadow:0 8px 25px rgba(102,126,234,.55)"
  "}"
  "button:active{opacity:.75;transform:none;transition-delay:0s;cursor:wait}"
  "button.D{"
    "background:linear-gradient(135deg,#ef4444,#b91c1c);"
    "box-shadow:0 4px 15px rgba(239,68,68,.35)"
  "}"
  "button.D:hover{box-shadow:0 8px 25px rgba(239,68,68,.5)}"

  // ── Links ─────────────────────────────────────────────────────────────────
  "a{color:#818cf8;font-weight:500;text-decoration:none}"
  "a:hover{color:#c084fc;text-decoration:underline}"

  // ── WiFi List Row Card ────────────────────────────────────────────────────
  ".wi{"
    "display:flex;align-items:center;justify-content:space-between;"
    "padding:11px 14px;margin:6px 0;"
    "background:rgba(255,255,255,.05);"
    "border:1px solid rgba(255,255,255,.09);"
    "border-radius:12px;transition:background .2s,border .2s,transform .2s;"
    "cursor:pointer"
  "}"
  ".wi:hover{"
    "background:rgba(129,140,248,.15);"
    "border-color:rgba(129,140,248,.4);"
    "transform:translateX(3px)"
  "}"

  // Nama WiFi (link <a> di dalam .wi)
  ".wi>a{"
    "flex:1;color:#e2e8f0;font-weight:500;font-size:.9rem;"
    "text-decoration:none;padding:0;margin:0;"
    "white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:56%"
  "}"
  ".wi:hover>a{color:#c7d2fe}"

  // ── Blok Info Sinyal (kanan) ──────────────────────────────────────────────
  ".wsi{"
    "display:flex;align-items:center;gap:8px;"
    "flex-shrink:0;margin-left:10px"
  "}"

  // Track bar sinyal
  ".wsb{"
    "width:56px;height:7px;"
    "background:rgba(255,255,255,.12);"
    "border-radius:4px;overflow:hidden"
  "}"

  // Isi bar sinyal — warna berdasarkan kualitas (q-0 s/d q-4)
  ".wsf{height:100%;border-radius:4px;transition:width .4s ease}"
  ".wsf.q-0{background:linear-gradient(90deg,#ef4444,#dc2626)}"
  ".wsf.q-1{background:linear-gradient(90deg,#f97316,#ea580c)}"
  ".wsf.q-2{background:linear-gradient(90deg,#eab308,#ca8a04)}"
  ".wsf.q-3{background:linear-gradient(90deg,#4ade80,#22c55e)}"
  ".wsf.q-4{background:linear-gradient(90deg,#22c55e,#16a34a)}"

  // Teks persentase sinyal
  ".wsp{font-size:.68rem;color:#94a3b8;min-width:30px;text-align:right;white-space:nowrap}"
  ".wi:hover .wsp{color:#818cf8}"

  // Ikon gembok untuk jaringan terenkripsi
  ".wsi.l::after{content:'\\1F512';font-size:.62rem;opacity:.75;margin-left:1px}"

  // ── Message Boxes ─────────────────────────────────────────────────────────
  ".msg{"
    "padding:14px 18px;margin:14px 0;"
    "border-radius:12px;border-left:4px solid #475569;"
    "background:rgba(255,255,255,.05)"
  "}"
  ".msg.S{border-left-color:#22c55e;background:rgba(34,197,94,.08)}"
  ".msg.D{border-left-color:#ef4444;background:rgba(239,68,68,.08)}"
  ".msg.P{border-left-color:#818cf8;background:rgba(129,140,248,.08)}"
  ".msg h4{margin:0 0 5px}"

  // ── Separator ─────────────────────────────────────────────────────────────
  "hr{border:none;border-top:1px solid rgba(255,255,255,.1);margin:16px 0}"

  // ── Info Page (Definition List) ───────────────────────────────────────────
  "dt{"
    "font-weight:600;color:#94a3b8;font-size:.72rem;"
    "text-transform:uppercase;letter-spacing:.07em;margin-top:12px"
  "}"
  "dd{margin:3px 0 0;padding-bottom:6px;min-height:12px;color:#e2e8f0}"
  "td{vertical-align:top}"

  // ── Progress Bar ──────────────────────────────────────────────────────────
  "progress{width:100%;height:5px;margin:5px 0;border-radius:4px}"

  // ── Utilities ─────────────────────────────────────────────────────────────
  ".c{text-align:center}"
  ".h{display:none}"
  ":disabled{opacity:.4}"

  // ── Dark Mode Variant (body.invert) ───────────────────────────────────────
  "body.invert .wrap{background:rgba(0,0,0,.45)}"

  // ── Animasi ───────────────────────────────────────────────────────────────
  "@keyframes su{"
    "from{opacity:0;transform:translateY(14px)}"
    "to{opacity:1;transform:translateY(0)}"
  "}"
  "</style>";

// ---------------------------------------------------------------------------
// HALAMAN BANTUAN (INFO TERSEDIA)

#ifndef WM_NOHELP
const char HTTP_HELP[] PROGMEM =
  "<br/><h3>Halaman yang Tersedia</h3><hr>"
  "<table>"
  "<thead><tr><th>Halaman</th><th>Fungsi</th></tr></thead><tbody>"
  "<tr><td><a href='/'>/</a></td>"
    "<td>Halaman menu utama.</td></tr>"
  "<tr><td><a href='/wifi'>/wifi</a></td>"
    "<td>Tampilkan hasil scan WiFi dan masukkan konfigurasi. (/0wifi tanpa scan)</td></tr>"
  "<tr><td><a href='/wifisave'>/wifisave</a></td>"
    "<td>Simpan konfigurasi WiFi. Memerlukan parameter yang dikirim.</td></tr>"
  "<tr><td><a href='/param'>/param</a></td>"
    "<td>Halaman parameter kustom.</td></tr>"
  "<tr><td><a href='/info'>/info</a></td>"
    "<td>Halaman informasi perangkat.</td></tr>"
  "<tr><td><a href='/u'>/u</a></td>"
    "<td>Update firmware OTA.</td></tr>"
  "<tr><td><a href='/close'>/close</a></td>"
    "<td>Tutup popup captive portal, portal tetap aktif.</td></tr>"
  "<tr><td>/exit</td>"
    "<td>Keluar dari config portal, portal akan ditutup.</td></tr>"
  "<tr><td>/restart</td>"
    "<td>Mulai ulang perangkat ESP.</td></tr>"
  "<tr><td>/erase</td>"
    "<td>Hapus konfigurasi WiFi dan mulai ulang. Perangkat tidak akan terhubung "
        "sampai konfigurasi WiFi baru dimasukkan.</td></tr>"
  "</tbody></table>"
  "<p/>GitHub: <a href='https://github.com/Shawir1312'>ShawirWiFi</a>";
#else
const char HTTP_HELP[] PROGMEM = "";
#endif

// ---------------------------------------------------------------------------
// UPDATE FIRMWARE OTA

const char HTTP_UPDATE[] PROGMEM =
  "Unggah firmware baru<br/>"
  "<form method='POST' action='u' enctype='multipart/form-data'"
    " onchange=\"(function(el){"
      "document.getElementById('uploadbin').style.display="
        "el.value==''?'none':'initial';"
    "})(this)\">"
    "<input type='file' name='update' accept='.bin,application/octet-stream'>"
    "<button id='uploadbin' type='submit' class='h D'>Unggah Firmware</button>"
  "</form>"
  "<small>"
    "<a href='http://192.168.4.1/update' target='_blank'>"
      "* Mungkin tidak berfungsi di dalam captive portal, "
      "buka di browser: http://192.168.4.1"
    "</a>"
  "</small>";

const char HTTP_UPDATE_FAIL[]    PROGMEM =
  "<div class='msg D'>"
    "<strong>Update gagal!</strong><br/>Mulai ulang perangkat dan coba lagi."
  "</div>";

const char HTTP_UPDATE_SUCCESS[] PROGMEM =
  "<div class='msg S'>"
    "<strong>Update berhasil!</strong><br/>Perangkat sedang dimulai ulang..."
  "</div>";

// ---------------------------------------------------------------------------
// INFO PAGE - LABEL PERANGKAT

#ifdef ESP32
  const char HTTP_INFO_esphead[]   PROGMEM = "<h3>ESP32</h3><hr><dl>";
  const char HTTP_INFO_chiprev[]   PROGMEM = "<dt>Revisi Chip</dt><dd>{1}</dd>";
  const char HTTP_INFO_lastreset[] PROGMEM =
    "<dt>Alasan Reset Terakhir</dt><dd>CPU0: {1}<br/>CPU1: {2}</dd>";
  const char HTTP_INFO_aphost[]    PROGMEM =
    "<dt>Nama Host Access Point</dt><dd>{1}</dd>";
  const char HTTP_INFO_psrsize[]   PROGMEM = "<dt>Ukuran PSRAM</dt><dd>{1} bytes</dd>";
  const char HTTP_INFO_temp[]      PROGMEM =
    "<dt>Suhu</dt><dd>{1} C&deg; / {2} F&deg;</dd>";
  const char HTTP_INFO_hall[]      PROGMEM = "<dt>Hall Sensor</dt><dd>{1}</dd>";
#else
  const char HTTP_INFO_esphead[]   PROGMEM = "<h3>ESP8266</h3><hr><dl>";
  const char HTTP_INFO_fchipid[]   PROGMEM = "<dt>ID Chip Flash</dt><dd>{1}</dd>";
  const char HTTP_INFO_corever[]   PROGMEM = "<dt>Versi Core</dt><dd>{1}</dd>";
  const char HTTP_INFO_bootver[]   PROGMEM = "<dt>Versi Boot</dt><dd>{1}</dd>";
  const char HTTP_INFO_lastreset[] PROGMEM = "<dt>Alasan Reset Terakhir</dt><dd>{1}</dd>";
  const char HTTP_INFO_flashsize[] PROGMEM = "<dt>Ukuran Flash Asli</dt><dd>{1} bytes</dd>";
#endif

const char HTTP_INFO_memsmeter[]  PROGMEM =
  "<br/><progress value='{1}' max='{2}'></progress></dd>";
const char HTTP_INFO_memsketch[]  PROGMEM =
  "<dt>Memori - Ukuran Sketch</dt><dd>Terpakai / Total bytes<br/>{1} / {2}";
const char HTTP_INFO_freeheap[]   PROGMEM =
  "<dt>Memori - Heap Bebas</dt><dd>{1} bytes tersedia</dd>";
const char HTTP_INFO_wifihead[]   PROGMEM = "<br/><h3>WiFi</h3><hr>";
const char HTTP_INFO_uptime[]     PROGMEM =
  "<dt>Waktu Aktif</dt><dd>{1} menit {2} detik</dd>";
const char HTTP_INFO_chipid[]     PROGMEM = "<dt>ID Chip</dt><dd>{1}</dd>";
const char HTTP_INFO_idesize[]    PROGMEM = "<dt>Ukuran Flash</dt><dd>{1} bytes</dd>";
const char HTTP_INFO_sdkver[]     PROGMEM = "<dt>Versi SDK</dt><dd>{1}</dd>";
const char HTTP_INFO_cpufreq[]    PROGMEM = "<dt>Frekuensi CPU</dt><dd>{1} MHz</dd>";
const char HTTP_INFO_apip[]       PROGMEM = "<dt>IP Access Point</dt><dd>{1}</dd>";
const char HTTP_INFO_apmac[]      PROGMEM = "<dt>MAC Access Point</dt><dd>{1}</dd>";
const char HTTP_INFO_apssid[]     PROGMEM = "<dt>SSID Access Point</dt><dd>{1}</dd>";
const char HTTP_INFO_apbssid[]    PROGMEM = "<dt>BSSID</dt><dd>{1}</dd>";
const char HTTP_INFO_stassid[]    PROGMEM = "<dt>SSID Station</dt><dd>{1}</dd>";
const char HTTP_INFO_staip[]      PROGMEM = "<dt>IP Station</dt><dd>{1}</dd>";
const char HTTP_INFO_stagw[]      PROGMEM = "<dt>Gateway Station</dt><dd>{1}</dd>";
const char HTTP_INFO_stasub[]     PROGMEM = "<dt>Subnet Station</dt><dd>{1}</dd>";
const char HTTP_INFO_dnss[]       PROGMEM = "<dt>Server DNS</dt><dd>{1}</dd>";
const char HTTP_INFO_host[]       PROGMEM = "<dt>Nama Host</dt><dd>{1}</dd>";
const char HTTP_INFO_stamac[]     PROGMEM = "<dt>MAC Station</dt><dd>{1}</dd>";
const char HTTP_INFO_conx[]       PROGMEM = "<dt>Terhubung</dt><dd>{1}</dd>";
const char HTTP_INFO_autoconx[]   PROGMEM = "<dt>Auto-Connect</dt><dd>{1}</dd>";

const char HTTP_INFO_aboutver[]     PROGMEM = "<dt>shawirWifi</dt><dd>{1}</dd>";
const char HTTP_INFO_aboutarduino[] PROGMEM = "<dt>Arduino</dt><dd>{1}</dd>";
const char HTTP_INFO_aboutsdk[]     PROGMEM = "<dt>ESP-SDK/IDF</dt><dd>{1}</dd>";
const char HTTP_INFO_aboutdate[]    PROGMEM = "<dt>Tanggal Build</dt><dd>{1}</dd>";

// ---------------------------------------------------------------------------
// STRING UMUM

const char S_brand[]              PROGMEM = "shawirWifi";
const char S_debugPrefix[]        PROGMEM = "*sw:";
const char S_y[]                  PROGMEM = "Ya";
const char S_n[]                  PROGMEM = "Tidak";
const char S_enable[]             PROGMEM = "Aktif";
const char S_disable[]            PROGMEM = "Nonaktif";
const char S_GET[]                PROGMEM = "GET";
const char S_POST[]               PROGMEM = "POST";
const char S_NA[]                 PROGMEM = "Tidak Diketahui";
const char S_passph[]             PROGMEM = "********";
const char S_titlewifisaved[]     PROGMEM = "Kredensial Disimpan";
const char S_titlewifisettings[]  PROGMEM = "Pengaturan Disimpan";
const char S_titlewifi[]          PROGMEM = "Konfigurasi WiFi";
const char S_titleinfo[]          PROGMEM = "Informasi Perangkat";
const char S_titleparam[]         PROGMEM = "Pengaturan Lanjutan";
const char S_titleparamsaved[]    PROGMEM = "Pengaturan Tersimpan";
const char S_titleexit[]          PROGMEM = "Keluar";
const char S_titlereset[]         PROGMEM = "Reset";
const char S_titleerase[]         PROGMEM = "Hapus Konfigurasi";
const char S_titleclose[]         PROGMEM = "Tutup Portal";
const char S_options[]            PROGMEM = "opsi";
const char S_nonetworks[]         PROGMEM = "Tidak ada jaringan ditemukan. Segarkan untuk scan ulang.";
const char S_staticip[]           PROGMEM = "IP Statis";
const char S_staticgw[]           PROGMEM = "Gateway Statis";
const char S_staticdns[]          PROGMEM = "DNS Statis";
const char S_subnet[]             PROGMEM = "Subnet";
const char S_exiting[]            PROGMEM = "Keluar dari Portal";
const char S_resetting[]          PROGMEM = "shawirWifi: Modul akan reset dalam beberapa detik.";
const char S_closing[]            PROGMEM = "Anda dapat menutup halaman ini, portal tetap aktif.";
const char S_error[]              PROGMEM = "Terjadi kesalahan";
const char S_notfound[]           PROGMEM = "File tidak ditemukan\n\n";
const char S_uri[]                PROGMEM = "URI: ";
const char S_method[]             PROGMEM = "\nMetode: ";
const char S_args[]               PROGMEM = "\nArgumen: ";
const char S_parampre[]           PROGMEM = "param_";

// Debug
const char D_HR[] PROGMEM = "--------------------";

// Prefix SSID default
#ifdef ESP8266
  const char S_ssidpre[] PROGMEM = "ESP";
#elif defined(ESP32)
  const char S_ssidpre[] PROGMEM = "ESP32";
#else
  const char S_ssidpre[] PROGMEM = "WM";
#endif

// END WIFI_MANAGER_OVERRIDE_STRINGS
#endif

#endif // _WM_STRINGS_ID_H_
