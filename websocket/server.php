<?php
/**
 * ShawirIOT - Real-time WebSocket Server Daemon
 * 
 * Run with: php websocket/server.php
 * In aaPanel: Run as a Supervisor daemon or systemd service
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo "====================================================\n";
    echo " ShawirIOT WebSocket Server\n";
    echo "====================================================\n";
    echo "Perhatian: Ratchet belum terinstall.\n";
    echo "Jalankan perintah berikut di terminal:\n";
    echo "   cd " . __DIR__ . "\n";
    echo "   composer install\n";
    echo "Lalu jalankan lagi: php server.php\n";
    echo "====================================================\n";
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

class ShawirIoTHandler implements MessageComponentInterface {
    protected \SplObjectStorage $clients;
    // Map device_id => SplObjectStorage of client connections watching that device
    protected array $deviceSubscribers = [];
    // Map Connection => info [role, token, device_id]
    protected \SplObjectStorage $connInfo;

    public function __construct() {
        $this->clients   = new \SplObjectStorage;
        $this->connInfo  = new \SplObjectStorage;
        echo "[" . date('Y-m-d H:i:s') . "] ShawirIOT WebSocket Handler initialized.\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->connInfo->attach($conn, ['role' => 'unauth', 'device_id' => null]);
        echo "[CONNECT] New connection #{$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!is_array($data)) return;

        $type = $data['type'] ?? '';

        // PING / HEARTBEAT
        if ($type === 'ping') {
            $from->send(json_encode(['type' => 'pong', 'time' => time()]));
            return;
        }

        // AUTHENTICATION / REGISTRATION (from browser or device)
        if ($type === 'auth') {
            $role     = $data['role'] ?? 'client'; // 'client' | 'device'
            $token    = $data['token'] ?? '';
            $deviceId = (int)($data['device_id'] ?? 0);

            // Verify device from DB
            $device = null;
            if (!empty($token)) {
                $device = DB::row("SELECT id, user_id FROM devices WHERE token = ? AND is_active = 1", [$token]);
            } elseif ($deviceId > 0) {
                $device = DB::row("SELECT id, user_id FROM devices WHERE id = ? AND is_active = 1", [$deviceId]);
            }

            if ($device) {
                $devId = (int)$device['id'];
                $this->connInfo[$from] = [
                    'role'      => $role,
                    'device_id' => $devId,
                    'token'     => $token,
                ];

                if (!isset($this->deviceSubscribers[$devId])) {
                    $this->deviceSubscribers[$devId] = new \SplObjectStorage;
                }
                $this->deviceSubscribers[$devId]->attach($from);

                // Update device heartbeat if device connection
                if ($role === 'device') {
                    deviceHeartbeat($devId);
                    $this->broadcastToSubscribers($devId, [
                        'type'      => 'device_status',
                        'device_id' => $devId,
                        'online'    => true,
                    ]);
                }

                $from->send(json_encode([
                    'type'    => 'auth_ok',
                    'message' => 'Autentikasi berhasil',
                    'device_id' => $devId
                ]));

                echo "[AUTH] Connection #{$from->resourceId} authenticated as {$role} for Device #{$devId}\n";
            } else {
                $from->send(json_encode(['type' => 'auth_fail', 'message' => 'Token tidak valid']));
            }
            return;
        }

        // PIN UPDATE MESSAGE (e.g. from ESP32 via WS directly)
        if ($type === 'pin_write') {
            $info = $this->connInfo[$from] ?? null;
            if (!$info || empty($info['device_id'])) {
                $from->send(json_encode(['type' => 'error', 'message' => 'Unauthenticated']));
                return;
            }

            $devId = $info['device_id'];
            $pin   = strtoupper($data['pin'] ?? '');
            $val   = (string)($data['value'] ?? '');

            if ($pin) {
                // Save to database
                savePinValue($devId, $pin, $val);
                savePinHistory($devId, $pin, $val);
                deviceHeartbeat($devId);

                // Broadcast to all web clients watching this device
                $this->broadcastToSubscribers($devId, [
                    'type'      => 'pin_update',
                    'device_id' => $devId,
                    'pin'       => $pin,
                    'value'     => $val,
                ], $from);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $info = $this->connInfo[$conn] ?? null;
        if ($info && !empty($info['device_id'])) {
            $devId = $info['device_id'];
            if (isset($this->deviceSubscribers[$devId])) {
                $this->deviceSubscribers[$devId]->detach($conn);
            }

            // If device disconnected, notify subscribers
            if ($info['role'] === 'device') {
                $this->broadcastToSubscribers($devId, [
                    'type'      => 'device_status',
                    'device_id' => $devId,
                    'online'    => false,
                ]);
            }
        }

        $this->clients->detach($conn);
        $this->connInfo->detach($conn);
        echo "[DISCONNECT] Connection #{$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[ERROR] #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Broadcast to all watchers of a device
     */
    public function broadcastToSubscribers(int $deviceId, array $payload, ?ConnectionInterface $exclude = null): void {
        if (!isset($this->deviceSubscribers[$deviceId])) return;
        $json = json_encode($payload);
        foreach ($this->deviceSubscribers[$deviceId] as $client) {
            if ($exclude !== null && $client === $exclude) continue;
            $client->send($json);
        }
    }
}

$port = (int)(getSetting('websocket_port', '8080'));
echo "====================================================\n";
echo " ShawirIOT WebSocket Server v" . PLATFORM_VERSION . "\n";
echo " Menjalankan di port: {$port}...\n";
echo " Tekan Ctrl+C untuk berhenti.\n";
echo "====================================================\n";

$handler = new ShawirIoTHandler();
$server  = IoServer::factory(
    new HttpServer(
        new WsServer($handler)
    ),
    $port,
    '0.0.0.0'
);

$server->run();
