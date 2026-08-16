/**
 * ShawirIOT - Real-time WebSocket Client
 * Falls back to polling if WebSocket unavailable
 */

let wsReconnectTimer = null;
let wsReconnectDelay = 2000;
let wsConnected      = false;
let pollTimer        = null;
const POLL_INTERVAL  = 2000; // ms

// ============================================================
// WEBSOCKET CONNECTION
// ============================================================
function connectWebSocket() {
  try {
    ws = new WebSocket(WS_URL);
  } catch (e) {
    console.warn('WebSocket not available, falling back to polling.');
    startPolling();
    return;
  }

  ws.addEventListener('open', () => {
    wsConnected = true;
    wsReconnectDelay = 2000;
    setWsStatus(true);

    // Authenticate as a web client watching this device
    ws.send(JSON.stringify({
      type:      'auth',
      role:      'client',
      token:     DEVICE_TOKEN,
      device_id: DEVICE_ID,
    }));

    // Stop polling if it was running
    stopPolling();
  });

  ws.addEventListener('message', (event) => {
    try {
      const msg = JSON.parse(event.data);
      handleWsMessage(msg);
    } catch (e) {
      console.error('WS message parse error:', e);
    }
  });

  ws.addEventListener('close', () => {
    wsConnected = false;
    setWsStatus(false);
    // Start fallback polling while WS reconnects
    startPolling();
    // Try to reconnect
    clearTimeout(wsReconnectTimer);
    wsReconnectTimer = setTimeout(() => {
      wsReconnectDelay = Math.min(wsReconnectDelay * 1.5, 30000);
      connectWebSocket();
    }, wsReconnectDelay);
  });

  ws.addEventListener('error', () => {
    console.warn('WebSocket error. Falling back to polling.');
    startPolling();
  });
}

// ============================================================
// HANDLE INCOMING WS MESSAGE
// ============================================================
function handleWsMessage(msg) {
  switch (msg.type) {
    case 'pin_update':
      // { type: 'pin_update', pin: 'V1', value: '25.6', device_id: 123 }
      if (msg.device_id == DEVICE_ID) {
        updateWidgetValue(msg.pin, String(msg.value));
        setLastUpdate();
      }
      break;

    case 'device_status':
      // { type: 'device_status', device_id: 123, online: true }
      if (msg.device_id == DEVICE_ID) {
        updateDeviceStatusBadge(msg.online);
      }
      break;

    case 'pong':
      // heartbeat response
      break;
  }
}

// ============================================================
// FALLBACK: HTTP POLLING
// ============================================================
function startPolling() {
  if (pollTimer) return; // already polling
  console.log('Starting fallback polling every', POLL_INTERVAL, 'ms');
  pollTimer = setInterval(pollPinValues, POLL_INTERVAL);
  pollPinValues(); // immediate first call
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

let lastPollValues = (typeof PIN_VALUES === 'object' && PIN_VALUES !== null) ? Object.assign({}, PIN_VALUES) : {};

async function pollPinValues() {
  try {
    const resp = await fetch(`api/data.php?token=${DEVICE_TOKEN}&all=1`);
    if (!resp.ok) return;
    const json = await resp.json();
    if (!json.success || !json.data) return;

    const payload = json.data;
    const pins = Array.isArray(payload) ? payload : (payload.pins || []);

    pins.forEach(item => {
      const pin = item.pin;
      const val = String(item.value ?? '');
      if (typeof lastPollValues[pin] === 'undefined') {
        // Initial setup - do not trigger fake chart update
        lastPollValues[pin] = val;
      } else if (lastPollValues[pin] !== val) {
        lastPollValues[pin] = val;
        updateWidgetValue(pin, val);
      }
    });

    // Check device online status
    if (typeof payload.is_online !== 'undefined') {
      updateDeviceStatusBadge(Boolean(payload.is_online));
    }

    setLastUpdate();
    setWsStatus(false, true); // polling mode
  } catch (e) {
    console.error('Poll error:', e);
  }
}

// ============================================================
// HEARTBEAT (keep WS alive)
// ============================================================
setInterval(() => {
  if (ws && ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify({ type: 'ping' }));
  }
}, 30000);

// ============================================================
// UI HELPERS
// ============================================================
function setWsStatus(connected, polling = false) {
  const indicator = document.getElementById('ws-indicator');
  const statusEl  = document.getElementById('ws-status');
  if (!indicator || !statusEl) return;

  if (connected) {
    indicator.classList.add('connected');
    indicator.style.background = 'var(--success)';
    statusEl.textContent = 'Realtime Aktif';
    statusEl.style.color = 'var(--success)';
  } else if (polling) {
    indicator.classList.remove('connected');
    indicator.style.background = 'var(--primary-light)';
    statusEl.textContent = 'Sinkron Realtime';
    statusEl.style.color = 'var(--primary-light)';
  } else {
    indicator.classList.remove('connected');
    indicator.style.background = 'var(--text-muted)';
    statusEl.textContent = 'Menghubungkan...';
    statusEl.style.color = 'var(--text-muted)';
  }
}

function setLastUpdate() {
  const el = document.getElementById('last-update');
  if (el) el.textContent = 'Pembaruan: ' + new Date().toLocaleTimeString('id-ID');
}

function updateDeviceStatusBadge(online) {
  const el = document.getElementById('device-status-label');
  if (el) {
    el.innerHTML = online
      ? '<span style="color:var(--success);font-weight:600">● Terhubung</span>'
      : '<span style="color:var(--text-muted)">● Terputus</span>';
  }
}

// ============================================================
// INIT
// ============================================================
window.addEventListener('DOMContentLoaded', () => {
  connectWebSocket();
  // Timeout: if WS doesn't connect in 3s, start polling
  setTimeout(() => {
    if (!wsConnected) startPolling();
  }, 3000);
});

// Cleanup on page leave
window.addEventListener('beforeunload', () => {
  stopPolling();
  if (ws) ws.close();
});
