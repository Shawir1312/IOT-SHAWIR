<?php
/**
 * ShawirIOT - Data API (Push/Pull untuk Mikrokontroler)
 * 
 * GET  ?token=XXX&pin=V1           → Baca nilai pin
 * GET  ?token=XXX&all=1            → Baca semua pin
 * GET  ?token=XXX&history=V1&n=50  → Histori pin
 * POST token=XXX&pin=V1&value=25   → Set nilai pin
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token)) {
    jsonResponse(false, 'Token diperlukan.', null, 401);
}

$device = getDeviceByToken($token);
if (!$device) {
    jsonResponse(false, 'Token tidak valid atau device tidak aktif.', null, 401);
}

$deviceId = $device['id'];
$userId   = $device['user_id'];

// ============================================================
// GET: Baca nilai pin / Heartbeat
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // HEARTBEAT DARI DEVICE (ESP/Arduino)
    if (isset($_GET['heartbeat'])) {
        deviceHeartbeat($deviceId);
        jsonResponse(true, 'Heartbeat OK', ['is_online' => true]);
    }

    // ALL PINS (Cek status dari dashboard atau device)
    if (isset($_GET['all'])) {
        // Cek offline devices terlebih dahulu agar akurat
        checkOfflineDevices();
        $pins    = DB::rows("SELECT pin, value, updated_at FROM virtual_pins WHERE device_id = ?", [$deviceId]);
        $online  = (int)DB::value("SELECT is_online FROM devices WHERE id = ?", [$deviceId]);
        jsonResponse(true, 'OK', ['pins' => $pins, 'is_online' => (bool)$online]);
    }

    // SINGLE PIN (Dibaca oleh perangkat atau dashboard)
    if (isset($_GET['pin'])) {
        if (!isset($_GET['source']) || $_GET['source'] !== 'dashboard') {
            deviceHeartbeat($deviceId);
        }
        $pin = strtoupper(sanitize($_GET['pin']));
        $row = DB::row("SELECT value FROM virtual_pins WHERE device_id = ? AND pin = ?", [$deviceId, $pin]);
        jsonResponse(true, 'OK', ['pin' => $pin, 'value' => $row ? $row['value'] : null]);
    }

    // HISTORY (Request dari Dashboard untuk grafik)
    if (isset($_GET['history'])) {
        $pin  = strtoupper(sanitize($_GET['history']));
        $n    = min(1000, max(1, (int)($_GET['n'] ?? 50)));
        $rows = DB::rows(
            "SELECT value, recorded_at FROM pin_history WHERE device_id = ? AND pin = ? ORDER BY recorded_at DESC LIMIT ?",
            [$deviceId, $pin, $n]
        );
        jsonResponse(true, 'OK', array_reverse($rows));
    }

    jsonResponse(false, 'Parameter tidak dikenali. Gunakan ?pin=V0 atau ?all=1', null, 400);
}

// ============================================================
// POST: Set nilai pin
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin    = strtoupper(sanitize($_POST['pin'] ?? ''));
    $value  = $_POST['value'] ?? '';
    $source = $_POST['source'] ?? 'device'; // 'device' | 'dashboard'

    if (empty($pin)) jsonResponse(false, 'Parameter pin diperlukan.', null, 400);

    // Update heartbeat HANYA jika data dikirim langsung oleh perangkat IoT fisik
    if ($source !== 'dashboard') {
        deviceHeartbeat($deviceId);
    }

    // Get user plan for history
    $plan = DB::row("SELECT p.* FROM plans p JOIN users u ON u.plan_id = p.id WHERE u.id = ?", [$userId]);
    $historyDays = $plan ? (int)$plan['history_days'] : 1;

    // Save current value
    savePinValue($deviceId, $pin, $value);

    // Save to history (hanya data dari device fisik yang disimpan ke histori)
    if ($source !== 'dashboard') {
        savePinHistory($deviceId, $pin, $value);
    }

    // Clean old history based on plan
    DB::query(
        "DELETE FROM pin_history WHERE device_id = ? AND pin = ? AND recorded_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$deviceId, $pin, $historyDays]
    );

    // Broadcast via WebSocket (if server is running)
    broadcastPinUpdate($deviceId, $pin, $value);

    jsonResponse(true, 'OK', ['pin' => $pin, 'value' => $value, 'device_id' => $deviceId]);
}

jsonResponse(false, 'Method tidak didukung.', null, 405);

/**
 * Broadcast pin update to WebSocket server
 * (via UDP/socket atau shared DB notify)
 */
function broadcastPinUpdate(int $deviceId, string $pin, string $value): void {
    // Option 1: Write ke tabel broadcast dan WS server baca dari DB
    // Option 2: Send HTTP ke internal WS server
    $wsPort = (int)(getSetting('websocket_port', '8080'));
    
    // Simpan ke DB untuk polling clients
    // WS server akan pick up dari sini
    try {
        @DB::query(
            "INSERT INTO websocket_connections (connection_id, type, device_id, last_ping) VALUES (?, 'device', ?, NOW())
             ON DUPLICATE KEY UPDATE last_ping = NOW()",
            ['device_' . $deviceId, $deviceId]
        );
    } catch (\Throwable $e) { /* silent */ }

    // Try notify WS server via HTTP (non-blocking)
    $ctx = @stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode(['type'=>'pin_update','device_id'=>$deviceId,'pin'=>$pin,'value'=>$value]),
            'timeout' => 0.2, // non-blocking
        ]
    ]);
    @file_get_contents("http://127.0.0.1:{$wsPort}/internal/broadcast", false, $ctx);
}
