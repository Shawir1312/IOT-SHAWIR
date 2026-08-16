/**
 * ShawirIOT - Widget Renderer & Manager
 * Handles: widget config forms, widget CRUD, pin value updates
 */

// ============================================================
// WIDGET TYPE DEFINITIONS
// ============================================================
const WIDGET_TYPES = {
  value_display: { label: 'Tampilan Nilai', icon: 'fa-tachometer-alt', defaultW: 3, defaultH: 2 },
  line_chart:    { label: 'Grafik Garis',    icon: 'fa-chart-line',     defaultW: 6, defaultH: 4 },
  bar_chart:     { label: 'Grafik Batang',   icon: 'fa-chart-bar',      defaultW: 6, defaultH: 4 },
  gauge:         { label: 'Speedometer (Gauge)', icon: 'fa-gauge-high', defaultW: 3, defaultH: 3 },
  button:        { label: 'Tombol Tekan',   icon: 'fa-hand-pointer',   defaultW: 3, defaultH: 2 },
  slider:        { label: 'Pengatur Nilai (Slider)', icon: 'fa-sliders-h', defaultW: 4, defaultH: 2 },
  switch:        { label: 'Saklar ON/OFF',  icon: 'fa-toggle-on',      defaultW: 2, defaultH: 2 },
  led:           { label: 'Indikator LED',  icon: 'fa-circle-dot',     defaultW: 2, defaultH: 2 },
  terminal:      { label: 'Terminal Log',   icon: 'fa-terminal',       defaultW: 6, defaultH: 4 },
  label:         { label: 'Label Teks',     icon: 'fa-font',           defaultW: 4, defaultH: 1 },
  map:           { label: 'Peta Lokasi GPS',icon: 'fa-map-marker-alt', defaultW: 6, defaultH: 5 },
};

let selectedWidgetType = null;
let editingWidgetId    = null;

// ============================================================
// WIDGET TYPE SELECTION → Show Config Form
// ============================================================
function selectWidgetType(type) {
  selectedWidgetType = type;
  editingWidgetId    = null;
  showWidgetConfigForm(type, null);
}

function editWidget(id) {
  const w = widgets.find(w => w.id == id);
  if (!w) return;
  editingWidgetId    = id;
  selectedWidgetType = w.type;
  showWidgetConfigForm(w.type, w);
}

// ============================================================
// CONFIG FORM GENERATOR
// ============================================================
function showWidgetConfigForm(type, existing) {
  const title  = existing ? 'Edit Pengaturan Widget' : `Tambah ${WIDGET_TYPES[type]?.label || type}`;
  document.getElementById('widget-config-title').textContent = title;

  const isChart  = type === 'line_chart' || type === 'bar_chart';
  const hasPin   = !['label'].includes(type);
  const hasOnOff = ['button','switch','led'].includes(type);
  const hasRange = ['slider','gauge','value_display'].includes(type);
  const hasUnit  = ['value_display','slider','gauge'].includes(type);

  const v = existing || {};

  const html = `
    <form id="widget-config-form" style="display:flex;flex-direction:column;gap:1rem">
      <div class="form-group">
        <label class="form-label">Nama / Label Widget</label>
        <input type="text" id="cfg-label" class="form-control" value="${escHtml(v.label || WIDGET_TYPES[type]?.label || '')}" placeholder="Nama Widget" required>
      </div>

      ${hasPin ? `
      <div class="form-group">
        <label class="form-label">Virtual Pin (Arduino/ESP)</label>
        <select id="cfg-pin" class="form-control">
          ${Array.from({length:256},(_,i)=>`<option value="V${i}" ${v.pin==='V'+i?'selected':''}>V${i}</option>`).join('')}
        </select>
        <div class="form-hint">Pin virtual yang digunakan pada sketch Arduino Anda</div>
      </div>` : ''}

      ${hasRange ? `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
        <div class="form-group">
          <label class="form-label">Batas Minimum</label>
          <input type="number" id="cfg-min" class="form-control" value="${v.min_value ?? 0}" step="any">
        </div>
        <div class="form-group">
          <label class="form-label">Batas Maksimum</label>
          <input type="number" id="cfg-max" class="form-control" value="${v.max_value ?? 100}" step="any">
        </div>
      </div>` : ''}

      ${hasUnit ? `
      <div class="form-group">
        <label class="form-label">Satuan Nilai</label>
        <input type="text" id="cfg-unit" class="form-control" value="${escHtml(v.unit || '')}" placeholder="Contoh: °C, %, Watt, Volt">
      </div>` : ''}

      ${hasOnOff ? `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
        <div class="form-group">
          <label class="form-label">Nilai Saat ON (Aktif)</label>
          <input type="text" id="cfg-on" class="form-control" value="${escHtml(v.on_value || '1')}">
        </div>
        <div class="form-group">
          <label class="form-label">Nilai Saat OFF (Mati)</label>
          <input type="text" id="cfg-off" class="form-control" value="${escHtml(v.off_value || '0')}">
        </div>
      </div>` : ''}

      <div class="form-group">
        <label class="form-label">Warna Utama Widget</label>
        <div class="color-picker-row">
          <div class="color-swatch" id="color-swatch-main">
            <input type="color" id="cfg-color" value="${v.color || '#6366f1'}" oninput="document.getElementById('color-swatch-main').style.background=this.value">
          </div>
          <input type="text" id="cfg-color-text" class="form-control" value="${v.color || '#6366f1'}"
            oninput="document.getElementById('cfg-color').value=this.value"
            placeholder="#6366f1">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
        <div class="form-group">
          <label class="form-label">Lebar Tampilan</label>
          <select id="cfg-w" class="form-control">
            <option value="6" ${(v.width || WIDGET_TYPES[type]?.defaultW || 4) <= 6 ? 'selected' : ''}>Setengah Layar (50% / 2 Kolom)</option>
            <option value="12" ${(v.width || WIDGET_TYPES[type]?.defaultW || 4) > 6 ? 'selected' : ''}>Layar Penuh (100% / 1 Kolom)</option>
            <option value="4" ${(v.width == 4) ? 'selected' : ''}>Sepertiga Layar (33%)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tinggi Tampilan</label>
          <select id="cfg-h" class="form-control">
            <option value="1" ${v.height == 1 ? 'selected' : ''}>1x (Ringkas)</option>
            <option value="2" ${(v.height == 2 || !v.height) ? 'selected' : ''}>2x (Standar)</option>
            <option value="3" ${v.height == 3 ? 'selected' : ''}>3x (Sedang)</option>
            <option value="4" ${v.height == 4 ? 'selected' : ''}>4x (Tinggi / Grafik)</option>
            <option value="5" ${v.height == 5 ? 'selected' : ''}>5x (Ekstra Besar)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer" style="padding:0;margin:0">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-widget-config')">Batal</button>
        <button type="button" class="btn btn-primary" onclick="submitWidgetConfig()">
          ${existing ? '<i class="fas fa-save"></i> Simpan Perubahan' : '<i class="fas fa-plus"></i> Tambah Widget'}
        </button>
      </div>
    </form>
  `;

  document.getElementById('widget-config-form-wrap').innerHTML = html;

  // sync color swatch initial
  const colorInput = document.getElementById('cfg-color');
  if (colorInput) {
    document.getElementById('color-swatch-main').style.background = colorInput.value;
    colorInput.addEventListener('input', () => {
      document.getElementById('cfg-color-text').value = colorInput.value;
    });
  }

  openModal('modal-widget-config');
}

// ============================================================
// SUBMIT WIDGET CONFIG
// ============================================================
async function submitWidgetConfig() {
  const type = selectedWidgetType;
  if (!type) return;

  const payload = {
    action:       editingWidgetId ? 'update' : 'create',
    dashboard_id: DASHBOARD_ID,
    type,
    label:  document.getElementById('cfg-label')?.value || WIDGET_TYPES[type]?.label,
    pin:    document.getElementById('cfg-pin')?.value || null,
    color:  document.getElementById('cfg-color')?.value || '#6366f1',
    min_value: parseFloat(document.getElementById('cfg-min')?.value || 0),
    max_value: parseFloat(document.getElementById('cfg-max')?.value || 100),
    unit:      document.getElementById('cfg-unit')?.value || '',
    on_value:  document.getElementById('cfg-on')?.value || '1',
    off_value: document.getElementById('cfg-off')?.value || '0',
    width:  parseInt(document.getElementById('cfg-w')?.value || WIDGET_TYPES[type]?.defaultW || 4),
    height: parseInt(document.getElementById('cfg-h')?.value || WIDGET_TYPES[type]?.defaultH || 2),
    pos_x: 0, pos_y: 0,
    csrf_token: CSRF_TOKEN,
  };

  if (editingWidgetId) payload.widget_id = editingWidgetId;

  try {
    const resp = await fetch('api/widget.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await resp.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('modal-widget-config');
      closeWidgetPanel();
      setTimeout(() => location.reload(), 600);
    } else {
      showToast(data.message || 'Gagal menyimpan widget.', 'danger');
    }
  } catch (err) {
    showToast('Error: ' + err.message, 'danger');
  }
}

// ============================================================
// DELETE WIDGET
// ============================================================
async function deleteWidget(id) {
  if (!confirm('Yakin ingin menghapus widget ini?')) return;
  try {
    const resp = await fetch('api/widget.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', widget_id: id, csrf_token: CSRF_TOKEN })
    });
    const data = await resp.json();
    if (data.success) {
      document.getElementById('widget-' + id)?.remove();
      widgets = widgets.filter(w => w.id != id);
      showToast('Widget berhasil dihapus.', 'success');
    }
  } catch (err) {
    showToast('Gagal menghapus widget.', 'danger');
  }
}

// ============================================================
// SEND PIN VALUE (Button, Switch, Slider)
// ============================================================
async function sendPinValue(pin, value) {
  try {
    await fetch(`api/data.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `token=${DEVICE_TOKEN}&pin=${pin}&value=${encodeURIComponent(value)}&source=dashboard`
    });
    // Update PIN_VALUES locally
    PIN_VALUES[pin] = value;
    // WebSocket will broadcast change, or update locally
    updateWidgetValue(pin, value);
  } catch (e) {
    showToast('Gagal mengirim perintah.', 'danger');
  }
}

// ============================================================
// UPDATE WIDGET VALUE (called from realtime.js)
// ============================================================
function updateWidgetValue(pin, value) {
  widgets.forEach(w => {
    if (w.pin !== pin) return;
    const body = document.getElementById('widget-body-' + w.id);
    if (!body) return;

    switch (w.type) {
      case 'value_display':
        body.querySelector('.val-number').textContent = value;
        break;

      case 'led': {
        const bulb = body.querySelector('.led-bulb');
        const lbl  = body.querySelector('.led-label');
        const isOn = value === w.on_value;
        bulb.className = 'led-bulb' + (isOn ? ' on' : '');
        bulb.style.background = isOn ? w.color : '';
        bulb.style.boxShadow  = isOn ? `0 0 20px ${w.color}88,0 0 40px ${w.color}44` : '';
        lbl.textContent = isOn ? 'ON' : 'OFF';
        break;
      }

      case 'switch': {
        const chk = body.querySelector('input[type=checkbox]');
        const lbl = body.querySelector('div');
        if (chk) chk.checked = value === w.on_value;
        if (lbl) lbl.textContent = value === w.on_value ? 'ON' : 'OFF';
        break;
      }

      case 'slider': {
        const inp = body.querySelector('input[type=range]');
        const lbl = body.querySelector('.slider-val');
        if (inp) inp.value = value;
        if (lbl) lbl.textContent = value + (w.unit || '');
        break;
      }

      case 'gauge': {
        const numVal = parseFloat(value) || 0;
        const min = parseFloat(w.min_value) || 0;
        const max = parseFloat(w.max_value) || 100;
        const pct = Math.max(0, Math.min(1, (numVal - min) / (max - min)));
        const arc = document.getElementById(`gauge-arc-${w.id}`);
        if (arc) arc.setAttribute('stroke-dashoffset', (188.5 - pct * 188.5).toFixed(1));
        const txt = body.querySelector('text');
        if (txt) txt.textContent = numVal;
        break;
      }

      case 'line_chart':
      case 'bar_chart': {
        const chart = charts[w.id];
        const numVal = parseFloat(value);
        if (chart && !isNaN(numVal) && value !== '' && value !== '—' && value !== null) {
          const now = new Date().toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
          chart.data.labels.push(now);
          chart.data.datasets[0].data.push(numVal);
          if (chart.data.labels.length > 20) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
          }
          chart.update('none');
        }
        break;
      }

      case 'terminal': {
        const term = document.getElementById('terminal-' + w.id);
        if (term) {
          const now = new Date().toLocaleTimeString('id-ID');
          term.innerHTML += `<span class="t-time">[${now}]</span> <span class="t-pin">${pin}</span> = <span class="t-val">${value}</span>\n`;
          term.scrollTop = term.scrollHeight;
        }
        break;
      }

      case 'map': {
        const parts = value.split(',');
        if (parts.length >= 2) {
          const lat = parseFloat(parts[0]), lng = parseFloat(parts[1]);
          if (!isNaN(lat) && !isNaN(lng)) {
            const iframe = body.querySelector('iframe');
            if (iframe) iframe.src = `https://maps.google.com/maps?q=${lat},${lng}&z=15&output=embed`;
          }
        }
        break;
      }
    }
  });
}

// ============================================================
// INIT CHARTS
// ============================================================
function initCharts() {
  document.querySelectorAll('canvas[data-pin]').forEach(canvas => {
    const widgetEl = canvas.closest('.widget');
    if (!widgetEl) return;
    const wid   = widgetEl.dataset.id;
    const type  = canvas.dataset.type || 'line';
    const color = canvas.dataset.color || '#6366f1';
    const pin   = canvas.dataset.pin;

    if (charts[wid]) {
      charts[wid].destroy();
    }

    // Load last 20 history points
    fetch(`api/data.php?token=${DEVICE_TOKEN}&history=${pin}&n=20`)
      .then(r => r.json())
      .then(data => {
        const labels = [], values = [];
        if (data.success && Array.isArray(data.data)) {
          data.data.forEach(d => {
            labels.push(new Date(d.recorded_at).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'}));
            values.push(parseFloat(d.value) || 0);
          });
        }

        const ctx = canvas.getContext('2d');
        charts[wid] = new Chart(ctx, {
          type,
          data: {
            labels,
            datasets: [{
              label: pin,
              data: values,
              borderColor: color,
              backgroundColor: type === 'line'
                ? hexToRgba(color, 0.1)
                : hexToRgba(color, 0.6),
              borderWidth: 2,
              fill: type === 'line',
              tension: 0.3,
              pointRadius: 3,
              pointHoverRadius: 5,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) => ` ${pin}: ${ctx.parsed.y}`
                }
              }
            },
            scales: {
              x: {
                ticks: { color: '#475569', maxTicksLimit: 6, font: { size: 9 } },
                grid:  { color: 'rgba(255,255,255,0.04)' }
              },
              y: {
                ticks: { color: '#475569', font: { size: 9 } },
                grid:  { color: 'rgba(255,255,255,0.04)' }
              }
            }
          }
        });
      })
      .catch(() => {});
  });
}

// ============================================================
// UTILS
// ============================================================
function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1,3), 16);
  const g = parseInt(hex.slice(3,5), 16);
  const b = parseInt(hex.slice(5,7), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function openModal(id) { document.getElementById(id)?.classList.add('active'); }
function closeModal(id){ document.getElementById(id)?.classList.remove('active'); }

function showToast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icons = { success:'check-circle', danger:'exclamation-circle', warning:'exclamation-triangle', info:'info-circle' };
  t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}" style="color:var(--${type==='danger'?'danger':type==='success'?'success':'primary-light'})"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3500);
}

function closeWidgetPanel() {
  document.getElementById('widget-panel')?.classList.remove('open');
}

// Init on load
window.addEventListener('DOMContentLoaded', initCharts);
