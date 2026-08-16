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
// ============================================================
// DRAG AND DROP (Unified Mouse & Touch for Mobile/Desktop)
// ============================================================
let draggedWidget = null;
let dragOffsetX   = 0;
let dragOffsetY   = 0;
let gridEl        = null;
let cellW         = 0;
let cellH         = 0;

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
  gridEl = document.getElementById('widget-grid');
  if (!gridEl) return;
  recalcGridDims();

  document.querySelectorAll('.widget.edit-mode').forEach(initWidgetDrag);
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

function recalcGridDims() {
  if (!gridEl) return;
  const rect = gridEl.getBoundingClientRect();
  const cols = window.innerWidth > 768 ? 12 : 2;
  const gap  = 10;
  cellW = (rect.width - gap * (cols - 1)) / cols;
  cellH = window.innerWidth > 768 ? 80 : 105;
}

function initWidgetDrag(widget) {
  const handle = widget.querySelector('.widget-drag-handle');
  if (!handle) return;

  handle.addEventListener('mousedown', onDragStart);
  handle.addEventListener('touchstart', onDragStart, { passive: false });
}

function onDragStart(e) {
  e.preventDefault();
  e.stopPropagation();
  if (window.getSelection) {
    window.getSelection().removeAllRanges();
  }

  const widget = e.target.closest('.widget');
  if (!widget || !editMode) return;

  draggedWidget = widget;
  const pos  = getEventXY(e);
  const rect = widget.getBoundingClientRect();
  dragOffsetX = pos.clientX - rect.left;
  dragOffsetY = pos.clientY - rect.top;

  widget.classList.add('is-dragging');
  widget.style.position      = 'fixed';
  widget.style.width         = rect.width + 'px';
  widget.style.height        = rect.height + 'px';
  widget.style.minHeight     = rect.height + 'px';
  widget.style.maxHeight     = rect.height + 'px';
  widget.style.left          = rect.left + 'px';
  widget.style.top           = rect.top + 'px';
  widget.style.zIndex        = '1000';
  widget.style.pointerEvents = 'none';

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
  draggedWidget.style.left = (pos.clientX - dragOffsetX) + 'px';
  draggedWidget.style.top  = (pos.clientY - dragOffsetY) + 'px';
}

function onDragEnd(e) {
  if (!draggedWidget || !gridEl) return;
  e.preventDefault();

  const pos  = getEventXY(e);
  const isMobile = window.innerWidth <= 768;

  // Restore dragged widget styles cleanly
  draggedWidget.classList.remove('is-dragging');
  draggedWidget.style.position      = '';
  draggedWidget.style.width         = '';
  draggedWidget.style.height        = '';
  draggedWidget.style.minHeight     = '';
  draggedWidget.style.maxHeight     = '';
  draggedWidget.style.left          = '';
  draggedWidget.style.top           = '';
  draggedWidget.style.zIndex        = '';
  draggedWidget.style.pointerEvents = '';

  if (isMobile) {
    // Mobile mode: drop-target DOM reorder
    const targetEl = document.elementFromPoint(pos.clientX, pos.clientY)?.closest('.widget');
    if (targetEl && targetEl !== draggedWidget && targetEl.parentElement === gridEl) {
      const allWidgets = Array.from(gridEl.querySelectorAll('.widget'));
      const draggedIdx = allWidgets.indexOf(draggedWidget);
      const targetIdx  = allWidgets.indexOf(targetEl);
      if (draggedIdx < targetIdx) {
        gridEl.insertBefore(draggedWidget, targetEl.nextSibling);
      } else {
        gridEl.insertBefore(draggedWidget, targetEl);
      }
    }
    reorderWidgetsFromDOM(true);
  } else {
    // Desktop mode: 12-column coordinate grid snap
    const gridRect = gridEl.getBoundingClientRect();
    const gap = 12;
    const relX = (pos.clientX - dragOffsetX) - gridRect.left;
    const relY = (pos.clientY - dragOffsetY) - gridRect.top;

    const col = Math.max(0, Math.min(11, Math.round(relX / (cellW + gap))));
    const row = Math.max(0, Math.round(relY / (cellH + gap)));

    const wid = draggedWidget.dataset.id;
    const w   = widgets.find(w => w.id == wid);
    const wWidth = w ? w.width : 4;
    const finalCol = Math.min(col, 12 - wWidth);

    draggedWidget.style.gridColumn = `${finalCol + 1} / span ${wWidth}`;
    draggedWidget.style.gridRow    = `${row + 1} / span ${w ? w.height : 2}`;

    if (w) {
      w.pos_x = finalCol;
      w.pos_y = row;
      draggedWidget.dataset.x = finalCol;
      draggedWidget.dataset.y = row;
    }
    saveLayout(true);
  }

  document.removeEventListener('mousemove', onDragMove);
  document.removeEventListener('mouseup',   onDragEnd);
  document.removeEventListener('touchmove', onDragMove);
  document.removeEventListener('touchend',  onDragEnd);
  document.removeEventListener('touchcancel', onDragEnd);
  draggedWidget = null;
}

// ============================================================
// RESIZE HANDLES (Drag-to-Resize on Mobile and Desktop)
// ============================================================
function initResizeHandles() {
  document.querySelectorAll('.widget.edit-mode').forEach(widget => {
    const handle = widget.querySelector('.widget-resize-handle');
    if (!handle) return;

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
  if (window.getSelection) {
    window.getSelection().removeAllRanges();
  }

  resizingWidget = e.target.closest('.widget');
  if (!resizingWidget) return;

  const pos = getEventXY(e);
  resizeStartX = pos.clientX;
  resizeStartY = pos.clientY;

  const wid = resizingWidget.dataset.id;
  const w   = widgets.find(w => w.id == wid);
  resizeStartW = parseInt(resizingWidget.dataset.w || w?.width  || 6);
  resizeStartH = parseInt(resizingWidget.dataset.h || w?.height || 2);

  document.addEventListener('mousemove', onResizeMove);
  document.addEventListener('mouseup',   onResizeEnd);
  document.addEventListener('touchmove', onResizeMove, { passive: false });
  document.addEventListener('touchend',  onResizeEnd, { passive: false });
  document.addEventListener('touchcancel', onResizeEnd, { passive: false });
}

function onResizeMove(e) {
  if (!resizingWidget || !gridEl) return;
  e.preventDefault();
  const pos = getEventXY(e);
  const dx = pos.clientX - resizeStartX;
  const dy = pos.clientY - resizeStartY;

  if (window.innerWidth <= 768) {
    // Mobile Resize: horizontal drag toggles width, vertical drag changes height tier
    if (dx > 40) {
      resizingWidget.dataset.w = 12;
      resizingWidget.classList.add('w-full');
      resizingWidget.classList.remove('w-half');
    } else if (dx < -40) {
      resizingWidget.dataset.w = 6;
      resizingWidget.classList.add('w-half');
      resizingWidget.classList.remove('w-full');
    }

    const hSteps = Math.round(dy / 45);
    const newH = Math.max(1, Math.min(5, resizeStartH + hSteps));
    resizingWidget.dataset.h = newH;
  } else {
    // Desktop Resize: 12-column grid
    const gap = 12;
    const newW = Math.max(1, Math.min(12, resizeStartW + Math.round(dx / (cellW + gap))));
    const newH = Math.max(1, resizeStartH + Math.round(dy / (cellH + gap)));
    resizingWidget.style.gridColumn = (resizingWidget.style.gridColumn || '1').split('/')[0] + `/ span ${newW}`;
    resizingWidget.style.gridRow    = (resizingWidget.style.gridRow || '1').split('/')[0]    + `/ span ${newH}`;
    resizingWidget.dataset.w = newW;
    resizingWidget.dataset.h = newH;
  }
}

function onResizeEnd(e) {
  if (!resizingWidget) return;
  e.preventDefault();
  const pos = getEventXY(e);
  const dx = pos.clientX - resizeStartX;
  const dy = pos.clientY - resizeStartY;

  const wid = resizingWidget.dataset.id;
  const w   = widgets.find(w => w.id == wid);

  if (window.innerWidth <= 768) {
    const finalW = parseInt(resizingWidget.dataset.w || 6);
    const finalH = parseInt(resizingWidget.dataset.h || 2);
    if (w) {
      w.width  = finalW;
      w.height = finalH;
    }
  } else {
    const gap = 12;
    const finalW = Math.max(1, Math.min(12, resizeStartW + Math.round(dx / (cellW + gap))));
    const finalH = Math.max(1, resizeStartH + Math.round(dy / (cellH + gap)));
    if (w) {
      w.width  = finalW;
      w.height = finalH;
      resizingWidget.dataset.w = finalW;
      resizingWidget.dataset.h = finalH;
    }
  }

  document.removeEventListener('mousemove', onResizeMove);
  document.removeEventListener('mouseup',   onResizeEnd);
  document.removeEventListener('touchmove', onResizeMove);
  document.removeEventListener('touchend',  onResizeEnd);
  document.removeEventListener('touchcancel', onResizeEnd);
  resizingWidget = null;

  setTimeout(initCharts, 150);
  saveLayout(true);
}

// ============================================================
// QUICK REORDER & SIZE TOGGLE (Blynk-Style Mobile Controls)
// ============================================================
function moveWidgetUp(id) {
  const el = document.getElementById('widget-' + id);
  if (!el) return;
  const prev = el.previousElementSibling;
  if (!prev || !prev.classList.contains('widget')) return;
  el.parentNode.insertBefore(el, prev);
  reorderWidgetsFromDOM(true);
}

function moveWidgetDown(id) {
  const el = document.getElementById('widget-' + id);
  if (!el) return;
  const next = el.nextElementSibling;
  if (!next || !next.classList.contains('widget')) return;
  el.parentNode.insertBefore(next, el);
  reorderWidgetsFromDOM(true);
}

function toggleWidgetWidth(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentW = parseInt(wEl.dataset.w || wObj?.width || 6);

  // Toggle between 12 (100% full width) and 6 (50% half width)
  const newW = currentW >= 12 ? 6 : 12;
  
  wEl.dataset.w = newW;
  if (wObj) wObj.width = newW;

  if (newW >= 12) {
    wEl.classList.add('w-full');
    wEl.classList.remove('w-half');
  } else {
    wEl.classList.add('w-half');
    wEl.classList.remove('w-full');
  }

  if (window.innerWidth > 768) {
    const x = parseInt(wEl.dataset.x || 0);
    const y = parseInt(wEl.dataset.y || 0);
    const h = parseInt(wEl.dataset.h || 2);
    wEl.style.gridColumn = `${x + 1} / span ${newW}`;
    wEl.style.gridRow = `${y + 1} / span ${h}`;
  } else {
    wEl.style.gridColumn = '';
    wEl.style.gridRow = '';
  }

  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast(newW >= 12 ? 'Lebar diubah ke Layar Penuh (100%)' : 'Lebar diubah ke Setengah Layar (50%)', 'success');
}

function increaseWidgetHeight(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentH = parseInt(wEl.dataset.h || wObj?.height || 2);
  const newH = Math.min(5, currentH + 1);

  wEl.dataset.h = newH;
  if (wObj) wObj.height = newH;

  if (window.innerWidth > 768) {
    const y = parseInt(wEl.dataset.y || 0);
    wEl.style.gridRow = `${y + 1} / span ${newH}`;
  }

  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast(`Tinggi diatur: ${newH}x`, 'info');
}

function decreaseWidgetHeight(id) {
  const wEl = document.getElementById('widget-' + id);
  if (!wEl) return;
  const wObj = widgets.find(w => w.id == id);
  const currentH = parseInt(wEl.dataset.h || wObj?.height || 2);
  const newH = Math.max(1, currentH - 1);

  wEl.dataset.h = newH;
  if (wObj) wObj.height = newH;

  if (window.innerWidth > 768) {
    const y = parseInt(wEl.dataset.y || 0);
    wEl.style.gridRow = `${y + 1} / span ${newH}`;
  }

  setTimeout(initCharts, 150);
  saveLayout(true);
  showToast(`Tinggi diatur: ${newH}x`, 'info');
}

function toggleWidgetSize(id) {
  toggleWidgetWidth(id);
}

function reorderWidgetsFromDOM(autoSave = false) {
  const grid = document.getElementById('widget-grid');
  if (!grid) return;
  const domWidgets = Array.from(grid.querySelectorAll('.widget'));
  
  if (window.innerWidth > 768) {
    let curX = 0, curY = 0, maxRowH = 0;
    domWidgets.forEach(wEl => {
      const wid = wEl.dataset.id;
      const wObj = widgets.find(w => w.id == wid);
      const w = parseInt(wEl.dataset.w || wObj?.width || 4);
      const h = parseInt(wEl.dataset.h || wObj?.height || 2);

      if (curX + w > 12) {
        curX = 0;
        curY += (maxRowH || 2);
        maxRowH = h;
      } else {
        maxRowH = Math.max(maxRowH, h);
      }

      wEl.dataset.x = curX;
      wEl.dataset.y = curY;
      wEl.style.gridColumn = `${curX + 1} / span ${w}`;
      wEl.style.gridRow = `${curY + 1} / span ${h}`;
      if (wObj) { wObj.pos_x = curX; wObj.pos_y = curY; }
      curX += w;
    });
  } else {
    // Mobile mode: clear inline styles to prevent height/width stretching
    domWidgets.forEach((wEl, idx) => {
      wEl.dataset.y = idx;
      wEl.dataset.x = 0;
      wEl.style.gridColumn = '';
      wEl.style.gridRow = '';
      wEl.style.height = '';
      wEl.style.width = '';
      const wObj = widgets.find(w => w.id == wEl.dataset.id);
      if (wObj) { wObj.pos_y = idx; wObj.pos_x = 0; }
    });
  }

  if (autoSave) {
    saveLayout(true);
  }
}

// ============================================================
// SAVE LAYOUT
// ============================================================
async function saveLayout(silent = false) {
  const btn = document.getElementById('btn-save-layout');
  if (btn && !silent) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Menyimpan...'; }

  // Collect positions from DOM
  const layout = [];
  document.querySelectorAll('.widget').forEach((el, idx) => {
    const wid = el.dataset.id;
    const style = el.style;
    const colMatch = (style.gridColumn || '').match(/(\d+)\s*\/\s*span\s*(\d+)/);
    const rowMatch = (style.gridRow || '').match(/(\d+)\s*\/\s*span\s*(\d+)/);
    if (colMatch && rowMatch && window.innerWidth > 768) {
      layout.push({
        id:    parseInt(wid),
        pos_x: parseInt(colMatch[1]) - 1,
        pos_y: parseInt(rowMatch[1]) - 1,
        width: parseInt(colMatch[2]),
        height:parseInt(rowMatch[2]),
      });
    } else {
      layout.push({
        id:    parseInt(wid),
        pos_x: 0,
        pos_y: idx,
        width: parseInt(el.dataset.w || 4),
        height:parseInt(el.dataset.h || 2),
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

// ============================================================
// RESPONSIVE COORDINATE APPLIER
// ============================================================
function applyDesktopCoordinates() {
  if (window.innerWidth > 768) {
    document.querySelectorAll('.widget').forEach(w => {
      const x = parseInt(w.dataset.x || 0);
      const y = parseInt(w.dataset.y || 0);
      const width = parseInt(w.dataset.w || 4);
      const height = parseInt(w.dataset.h || 2);
      w.style.gridColumn = `${x + 1} / span ${width}`;
      w.style.gridRow = `${y + 1} / span ${height}`;
    });
  } else {
    document.querySelectorAll('.widget').forEach(w => {
      w.style.gridColumn = '';
      w.style.gridRow = '';
    });
  }
}

window.addEventListener('DOMContentLoaded', () => {
  applyDesktopCoordinates();
  recalcGridDims();
});

window.addEventListener('resize', () => {
  applyDesktopCoordinates();
  recalcGridDims();
  setTimeout(initCharts, 200);
});
