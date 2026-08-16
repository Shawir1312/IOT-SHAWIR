/**
 * ShawirIOT - Dashboard Edit & Drag-and-Drop
 */

// ============================================================
// EDIT MODE TOGGLE
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  const btnEdit   = document.getElementById('btn-edit-toggle');
  const btnCancel = document.getElementById('btn-cancel-edit');
  const btnSave   = document.getElementById('btn-save-layout');
  const btnAddWgt = document.getElementById('btn-add-widget');
  const toolbar   = document.getElementById('edit-toolbar');
  const canvas    = document.getElementById('dashboard-canvas');

  if (btnEdit) {
    btnEdit.addEventListener('click', () => {
      editMode = true;
      toolbar?.classList.remove('d-none');
      canvas?.classList.add('edit-mode');
      document.querySelectorAll('.widget').forEach(w => w.classList.add('edit-mode'));
      btnEdit.classList.add('d-none');
      initDragAndDrop();
    });
  }

  if (btnCancel) {
    btnCancel.addEventListener('click', () => {
      editMode = false;
      toolbar?.classList.add('d-none');
      canvas?.classList.remove('edit-mode');
      document.querySelectorAll('.widget').forEach(w => w.classList.remove('edit-mode'));
      btnEdit?.classList.remove('d-none');
      destroyDragAndDrop();
    });
  }

  if (btnSave) btnSave.addEventListener('click', saveLayout);

  if (btnAddWgt) {
    btnAddWgt.addEventListener('click', () => {
      document.getElementById('widget-panel')?.classList.add('open');
    });
  }
});

// ============================================================
// DRAG AND DROP (native HTML5)
// ============================================================
let draggedWidget = null;
let dragOffsetX   = 0;
let dragOffsetY   = 0;
let gridEl        = null;
let cellW         = 0;
let cellH         = 0;

function initDragAndDrop() {
  gridEl = document.getElementById('widget-grid');
  if (!gridEl) return;
  recalcGridDims();

  document.querySelectorAll('.widget.edit-mode').forEach(initWidgetDrag);
  initResizeHandles();
}

function destroyDragAndDrop() {
  document.querySelectorAll('.widget').forEach(w => {
    w.removeAttribute('draggable');
    const handle = w.querySelector('.widget-drag-handle');
    if (handle) handle.removeEventListener('mousedown', onDragHandleDown);
  });
}

function recalcGridDims() {
  if (!gridEl) return;
  const rect = gridEl.getBoundingClientRect();
  const cols  = 12;
  const gap   = 12;
  cellW = (rect.width - gap * (cols - 1)) / cols;
  cellH = 80; // matches CSS grid-auto-rows
}

function initWidgetDrag(widget) {
  const handle = widget.querySelector('.widget-drag-handle');
  if (!handle) return;

  handle.addEventListener('mousedown', onDragHandleDown);
}

function onDragHandleDown(e) {
  e.preventDefault();
  const widget = e.target.closest('.widget');
  if (!widget || !editMode) return;

  draggedWidget = widget;
  const rect = widget.getBoundingClientRect();
  dragOffsetX = e.clientX - rect.left;
  dragOffsetY = e.clientY - rect.top;

  widget.style.opacity    = '0.6';
  widget.style.zIndex     = '1000';
  widget.style.position   = 'fixed';
  widget.style.width      = rect.width + 'px';
  widget.style.height     = rect.height + 'px';
  widget.style.left       = rect.left + 'px';
  widget.style.top        = rect.top + 'px';
  widget.style.pointerEvents = 'none';

  document.addEventListener('mousemove', onDragMove);
  document.addEventListener('mouseup',   onDragEnd);
}

function onDragMove(e) {
  if (!draggedWidget) return;
  draggedWidget.style.left = (e.clientX - dragOffsetX) + 'px';
  draggedWidget.style.top  = (e.clientY - dragOffsetY) + 'px';
}

function onDragEnd(e) {
  if (!draggedWidget || !gridEl) return;

  const gridRect = gridEl.getBoundingClientRect();
  const gap = 12;

  // Calculate grid position
  const relX = (e.clientX - dragOffsetX) - gridRect.left;
  const relY = (e.clientY - dragOffsetY) - gridRect.top;

  const col = Math.max(0, Math.min(11, Math.round(relX / (cellW + gap))));
  const row = Math.max(0, Math.round(relY / (cellH + gap)));

  const wid = draggedWidget.dataset.id;
  const w   = widgets.find(w => w.id == wid);
  const wWidth = w ? w.width : 4;
  const finalCol = Math.min(col, 12 - wWidth);

  // Snap back to grid
  draggedWidget.style.position = '';
  draggedWidget.style.width    = '';
  draggedWidget.style.height   = '';
  draggedWidget.style.left     = '';
  draggedWidget.style.top      = '';
  draggedWidget.style.opacity  = '';
  draggedWidget.style.zIndex   = '';
  draggedWidget.style.pointerEvents = '';

  draggedWidget.style.gridColumn = `${finalCol + 1} / span ${wWidth}`;
  draggedWidget.style.gridRow    = `${row + 1} / span ${w ? w.height : 2}`;

  // Update widget data
  if (w) { w.pos_x = finalCol; w.pos_y = row; }

  document.removeEventListener('mousemove', onDragMove);
  document.removeEventListener('mouseup',   onDragEnd);
  draggedWidget = null;
}

// ============================================================
// RESIZE HANDLES
// ============================================================
function initResizeHandles() {
  document.querySelectorAll('.widget.edit-mode').forEach(widget => {
    const handle = widget.querySelector('.widget-resize-handle');
    if (!handle) return;

    handle.addEventListener('mousedown', onResizeStart);
  });
}

let resizingWidget = null;
let resizeStartX = 0;
let resizeStartY = 0;
let resizeStartW = 0;
let resizeStartH = 0;

function onResizeStart(e) {
  e.preventDefault();
  e.stopPropagation();
  resizingWidget = e.target.closest('.widget');
  if (!resizingWidget) return;

  resizeStartX = e.clientX;
  resizeStartY = e.clientY;

  const wid = resizingWidget.dataset.id;
  const w   = widgets.find(w => w.id == wid);
  resizeStartW = w ? w.width  : 4;
  resizeStartH = w ? w.height : 2;

  document.addEventListener('mousemove', onResizeMove);
  document.addEventListener('mouseup',   onResizeEnd);
}

function onResizeMove(e) {
  if (!resizingWidget || !gridEl) return;
  const gap = 12;
  const dx = e.clientX - resizeStartX;
  const dy = e.clientY - resizeStartY;

  const newW = Math.max(1, Math.min(12, resizeStartW + Math.round(dx / (cellW + gap))));
  const newH = Math.max(1, resizeStartH + Math.round(dy / (cellH + gap)));

  resizingWidget.style.gridColumn = resizingWidget.style.gridColumn.split('/')[0] + `/ span ${newW}`;
  resizingWidget.style.gridRow    = resizingWidget.style.gridRow.split('/')[0]    + `/ span ${newH}`;
}

function onResizeEnd(e) {
  if (!resizingWidget) return;
  const gap = 12;
  const dx = e.clientX - resizeStartX;
  const dy = e.clientY - resizeStartY;

  const wid = resizingWidget.dataset.id;
  const w   = widgets.find(w => w.id == wid);
  if (w) {
    w.width  = Math.max(1, Math.min(12, resizeStartW + Math.round(dx / (cellW + gap))));
    w.height = Math.max(1, resizeStartH + Math.round(dy / (cellH + gap)));
  }

  document.removeEventListener('mousemove', onResizeMove);
  document.removeEventListener('mouseup',   onResizeEnd);
  resizingWidget = null;

  // Reinit charts if resized
  setTimeout(initCharts, 100);
}

// ============================================================
// SAVE LAYOUT
// ============================================================
async function saveLayout() {
  const btn = document.getElementById('btn-save-layout');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Menyimpan...'; }

  // Collect positions from DOM
  const layout = [];
  document.querySelectorAll('.widget').forEach(el => {
    const wid = el.dataset.id;
    const style = el.style;
    const colMatch = style.gridColumn.match(/(\d+)\s*\/\s*span\s*(\d+)/);
    const rowMatch = style.gridRow.match(/(\d+)\s*\/\s*span\s*(\d+)/);
    if (colMatch && rowMatch) {
      layout.push({
        id:    parseInt(wid),
        pos_x: parseInt(colMatch[1]) - 1,
        pos_y: parseInt(rowMatch[1]) - 1,
        width: parseInt(colMatch[2]),
        height:parseInt(rowMatch[2]),
      });
    }
  });

  try {
    const resp = await fetch('api/dashboard.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:       'save_layout',
        dashboard_id: DASHBOARD_ID,
        layout,
        csrf_token:   CSRF_TOKEN,
      })
    });
    const data = await resp.json();
    if (data.success) {
      showToast('Layout berhasil disimpan!', 'success');
    } else {
      showToast(data.message || 'Gagal menyimpan.', 'danger');
    }
  } catch (err) {
    showToast('Error: ' + err.message, 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Layout'; }
  }
}

// Handle window resize
window.addEventListener('resize', () => {
  recalcGridDims();
  setTimeout(initCharts, 200);
});
