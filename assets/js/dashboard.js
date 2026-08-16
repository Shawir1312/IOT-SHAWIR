/**
 * ShawirIOT - Dashboard Edit & Freeform Drag-and-Drop
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
      applyGridCoordinates();
    });
  }

  if (btnSave) btnSave.addEventListener('click', () => saveLayout(false));

  if (btnAddWgt) {
    btnAddWgt.addEventListener('click', () => {
      document.getElementById('widget-panel')?.classList.add('open');
    });
  }

  applyGridCoordinates();
  initResizeHandles();
});

// ============================================================
// FREEFORM DRAG AND DROP (Unified Touch & Mouse for Mobile/Desktop)
// ============================================================
let draggedWidget = null;
let startTouchX = 0;
let startTouchY = 0;
let startWidgetX = 0;
let startWidgetY = 0;

function getEventXY(e) {
  if (e.touches && e.touches.length > 0) {
    return { clientX: e.touches[0].clientX, clientY: e.touches[0].clientY };
  }
  if (e.changedTouches && e.changedTouches.length > 0) {
    return { clientX: e.changedTouches[0].clientX, clientY: e.changedTouches[0].clientY };
  }
  return { clientX: e.clientX, clientY: e.clientY };
}

function initDragAndDrop() {
  document.querySelectorAll('.widget.edit-mode').forEach(w => {
    const handle = w.querySelector('.widget-drag-handle');
    if (handle) {
      handle.removeEventListener('mousedown', onDragStart);
      handle.removeEventListener('touchstart', onDragStart);
      handle.addEventListener('mousedown', onDragStart);
      handle.addEventListener('touchstart', onDragStart, { passive: false });
    }
  });
  initResizeHandles();
}

function destroyDragAndDrop() {
  document.querySelectorAll('.widget').forEach(w => {
    const handle = w.querySelector('.widget-drag-handle');
    if (handle) {
      handle.removeEventListener('mousedown', onDragStart);
      handle.removeEventListener('touchstart', onDragStart);
    }
  });
}

function onDragStart(e) {
  e.preventDefault();
  e.stopPropagation();
  if (window.getSelection) window.getSelection().removeAllRanges();

  const widget = e.target.closest('.widget');
  if (!widget || !editMode) return;

  draggedWidget = widget;
  const pos = getEventXY(e);
  startTouchX = pos.clientX;
  startTouchY = pos.clientY;

  startWidgetX = parseInt(widget.dataset.x || 0);
  startWidgetY = parseInt(widget.dataset.y || 0);

  widget.classList.add('sortable-chosen');
  widget.style.zIndex = '1000';
  widget.style.transition = 'none';

  document.addEventListener('mousemove', onDragMove);
  document.addEventListener('mouseup',   onDragEnd);
  document.addEventListener('touchmove', onDragMove, { passive: false });
  document.addEventListener('touchend',  onDragEnd, { passive: false });
  document.addEventListener('touchcancel', onDragEnd, { passive: false });
}

function onDragMove(e) {
  if (!draggedWidget) return;
  e.preventDefault();
  const pos = getEventXY(e);
  const dx = pos.clientX - startTouchX;
  const dy = pos.clientY - startTouchY;

  draggedWidget.style.transform = `translate3d(${dx}px, ${dy}px, 0) scale(1.03)`;
}

function onDragEnd(e) {
  if (!draggedWidget) return;
  e.preventDefault();

  const pos = getEventXY(e);
  const dx = pos.clientX - startTouchX;
  const dy = pos.clientY - startTouchY;

  const isMobile = window.innerWidth <= 768;
  const gridEl = document.getElementById('widget-grid');
  const gridRect = gridEl ? gridEl.getBoundingClientRect() : { width: 360 };
  
  const cols = isMobile ? 6 : 12;
  const rowHeight = isMobile ? 60 : 75;
  const gap = isMobile ? 8 : 12;
  const cellW = (gridRect.width - gap * (cols - 1)) / cols;

  const w = parseInt(draggedWidget.dataset.w || 6);
  const colSpan = isMobile ? (w >= 12 ? 6 : 3) : w;

  const deltaCols = Math.round(dx / (cellW + gap));
  const deltaRows = Math.round(dy / (rowHeight + gap));

  let newY = Math.max(0, startWidgetY + deltaRows);
  let newX = Math.max(0, Math.min(cols - colSpan, startWidgetX + deltaCols));

  if (isMobile) {
    if (colSpan >= 6) {
      newX = 0;
    } else {
      newX = (newX >= 2) ? 3 : 0;
    }
  }

  draggedWidget.dataset.x = newX;
  draggedWidget.dataset.y = newY;

  const wid = draggedWidget.dataset.id;
  const wObj = widgets.find(wItem => wItem.id == wid);
  if (wObj) {
    wObj.pos_x = newX;
    wObj.pos_y = newY;
  }

  draggedWidget.classList.remove('sortable-chosen');
  draggedWidget.style.transform = '';
  draggedWidget.style.zIndex = '';
  draggedWidget.style.transition = '';

  document.removeEventListener('mousemove', onDragMove);
  document.removeEventListener('mouseup',   onDragEnd);
  document.removeEventListener('touchmove', onDragMove);
  document.removeEventListener('touchend',  onDragEnd);
  document.removeEventListener('touchcancel', onDragEnd);
  draggedWidget = null;

  applyGridCoordinates();
  saveLayout(true);
}

// ============================================================
// RESIZE HANDLES (Drag-to-Resize on Corner Handle)
// ============================================================
function initResizeHandles() {
  document.querySelectorAll('.widget.edit-mode').forEach(widget => {
    const handle = widget.querySelector('.widget-resize-handle');
    if (!handle) return;

    handle.removeEventListener('mousedown', onResizeStart);
    handle.removeEventListener('touchstart', onResizeStart);
    handle.addEventListener('mousedown', onResizeStart);
    handle.addEventListener('touchstart', onResizeStart, { passive: false });
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
  if (window.getSelection) window.getSelection().removeAllRanges();

  resizingWidget = e.target.closest('.widget');
  if (!resizingWidget) return;

  const pos = getEventXY(e);
  resizeStartX = pos.clientX;
  resizeStartY = pos.clientY;

  const wid = resizingWidget.dataset.id;
  const w = widgets.find(wItem => wItem.id == wid);
  resizeStartW = parseInt(resizingWidget.dataset.w || w?.width  || 6);
  resizeStartH = parseInt(resizingWidget.dataset.h || w?.height || 2);

  document.addEventListener('mousemove', onResizeMove);
  document.addEventListener('mouseup',   onResizeEnd);
  document.addEventListener('touchmove', onResizeMove, { passive: false });
  document.addEventListener('touchend',  onResizeEnd, { passive: false });
  document.addEventListener('touchcancel', onResizeEnd, { passive: false });
}

function onResizeMove(e) {
  if (!resizingWidget) return;
  e.preventDefault();
  const pos = getEventXY(e);
  const dx = pos.clientX - resizeStartX;
  const dy = pos.clientY - resizeStartY;

  const isMobile = window.innerWidth <= 768;

  if (isMobile) {
    if (dx > 40) resizingWidget.dataset.w = 12;
    else if (dx < -40) resizingWidget.dataset.w = 6;

    const hSteps = Math.round(dy / 55);
    resizingWidget.dataset.h = Math.max(1, Math.min(8, resizeStartH + hSteps));
  } else {
    const wSteps = Math.round(dx / 80);
    const hSteps = Math.round(dy / 75);
    resizingWidget.dataset.w = Math.max(2, Math.min(12, resizeStartW + wSteps));
    resizingWidget.dataset.h = Math.max(1, Math.min(10, resizeStartH + hSteps));
  }

  applyGridCoordinates();
}

function onResizeEnd(e) {
  if (!resizingWidget) return;
  e.preventDefault();

  const wid = resizingWidget.dataset.id;
  const wObj = widgets.find(wItem => wItem.id == wid);
  if (wObj) {
    wObj.width  = parseInt(resizingWidget.dataset.w);
    wObj.height = parseInt(resizingWidget.dataset.h);
  }

  document.removeEventListener('mousemove', onResizeMove);
  document.removeEventListener('mouseup',   onResizeEnd);
  document.removeEventListener('touchmove', onResizeMove);
  document.removeEventListener('touchend',  onResizeEnd);
  document.removeEventListener('touchcancel', onResizeEnd);
  resizingWidget = null;

  applyGridCoordinates();
  setTimeout(initCharts, 150);
  saveLayout(true);
}

// ============================================================
// QUICK BUTTON CONTROLS (Width & Height Toggles)
// ============================================================
function toggleWidgetWidth(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentW = parseInt(wEl.dataset.w || wObj?.width || 6);

  // Cycle through sizes: 6 (50%) -> 12 (100%) -> 4 (33%) -> 6 (50%)
  let newW = 12;
  let label = 'Layar Penuh (100%)';
  if (currentW >= 12) {
    newW = 4; // 1/3 screen (fits 3 per row)
    label = '1/3 Layar (Muat 3 Sebaris)';
  } else if (currentW <= 4) {
    newW = 6; // 1/2 screen (fits 2 per row)
    label = 'Setengah Layar (50%)';
  } else {
    newW = 12; // Full width
    label = 'Layar Penuh (100%)';
  }
  
  wEl.dataset.w = newW;
  if (wObj) wObj.width = newW;

  applyGridCoordinates();
  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast('Lebar diubah: ' + label, 'success');
}

function increaseWidgetHeight(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentH = parseInt(wEl.dataset.h || wObj?.height || 2);
  const newH = Math.min(8, currentH + 1);

  wEl.dataset.h = newH;
  if (wObj) wObj.height = newH;

  applyGridCoordinates();
  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast(`Tinggi ditambah (${newH} baris)`, 'info');
}

function decreaseWidgetHeight(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentH = parseInt(wEl.dataset.h || wObj?.height || 2);
  const newH = Math.max(1, currentH - 1);

  wEl.dataset.h = newH;
  if (wObj) wObj.height = newH;

  applyGridCoordinates();
  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast(`Tinggi dikurangi (${newH} baris)`, 'info');
}

// ============================================================
// APPLY GRID COORDINATES (Universal Mobile & Desktop Positioner)
// ============================================================
function applyGridCoordinates() {
  const isMobile = window.innerWidth <= 768;
  document.querySelectorAll('.widget').forEach(wEl => {
    const wid = wEl.dataset.id;
    const wObj = widgets.find(w => w.id == wid);
    const x = parseInt(wEl.dataset.x ?? wObj?.pos_x ?? 0);
    const y = parseInt(wEl.dataset.y ?? wObj?.pos_y ?? 0);
    const w = parseInt(wEl.dataset.w ?? wObj?.width ?? 6);
    const h = parseInt(wEl.dataset.h ?? wObj?.height ?? 2);

    if (isMobile) {
      // Mobile 6-column grid:
      // w >= 10 -> span 6 (100% full width)
      // w <= 4  -> span 2 (33% one-third - fits 3 widgets per row!)
      // w in between -> span 3 (50% half width - fits 2 widgets per row)
      let colSpan = 3;
      if (w >= 10) colSpan = 6;
      else if (w <= 4) colSpan = 2;
      else colSpan = 3;

      let colX = Math.max(0, Math.min(6 - colSpan, x));
      if (colSpan === 6) {
        colX = 0;
      } else if (colSpan === 3) {
        colX = colX >= 2 ? 3 : 0;
      } else if (colSpan === 2) {
        if (colX <= 1) colX = 0;
        else if (colX <= 3) colX = 2;
        else colX = 4;
      }

      wEl.style.gridColumn = `${colX + 1} / span ${colSpan}`;
      wEl.style.gridRow = `${y + 1} / span ${h}`;
    } else {
      // Desktop 12-column grid:
      const colX = Math.max(0, Math.min(12 - w, x));
      wEl.style.gridColumn = `${colX + 1} / span ${w}`;
      wEl.style.gridRow = `${y + 1} / span ${h}`;
    }
  });
}

// ============================================================
// SAVE LAYOUT (Save Coordinates to Database)
// ============================================================
async function saveLayout(silent = false) {
  const btn = document.getElementById('btn-save-layout');
  if (btn && !silent) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Menyimpan...'; }

  const layout = [];
  document.querySelectorAll('.widget').forEach(el => {
    const wid = parseInt(el.dataset.id);
    const x = parseInt(el.dataset.x || 0);
    const y = parseInt(el.dataset.y || 0);
    const w = parseInt(el.dataset.w || 6);
    const h = parseInt(el.dataset.h || 2);

    layout.push({
      id:     wid,
      pos_x:  x,
      pos_y:  y,
      width:  w,
      height: h,
    });
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
      if (!silent) showToast('Tata letak berhasil disimpan!', 'success');
    } else {
      if (!silent) showToast(data.message || 'Gagal menyimpan.', 'danger');
    }
  } catch (err) {
    if (!silent) showToast('Error: ' + err.message, 'danger');
  } finally {
    if (btn && !silent) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Tata Letak'; }
  }
}

window.addEventListener('resize', () => {
  applyGridCoordinates();
  setTimeout(initCharts, 200);
});
