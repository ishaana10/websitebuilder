/* ============================================================
   WEBCRAFT BUILDER CORE  v2.0  — Vanilla JS
   State → Render → Event loop
   ============================================================ */

const WCBuilder = (() => {

  // ── STATE ────────────────────────────────────────────────────
  let state = {
    selectedId: null,
    draggingType: null,
    schema: { version: 1, meta: { title: '', description: '', custom_css: '', custom_js: '' }, blocks: [] }
  };

  // ── INIT ─────────────────────────────────────────────────────
  function init(rawJson) {
    try {
      const parsed = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
      if (Array.isArray(parsed)) {
        state.schema.blocks = parsed;
      } else if (parsed && parsed.blocks) {
        state.schema = parsed;
      }
    } catch (e) {
      console.warn('[WCBuilder] Could not parse saved schema, starting fresh.', e);
    }
    const cssEl = document.getElementById('project-custom-css');
    const jsEl  = document.getElementById('project-custom-js');
    if (cssEl) cssEl.value = state.schema.meta.custom_css ?? '';
    if (jsEl)  jsEl.value  = state.schema.meta.custom_js  ?? '';
    renderCanvas();
    buildComponentShelf();
  }

  // ── CANVAS RENDERING ─────────────────────────────────────────
  function renderCanvas() {
    const canvas = document.getElementById('canvas-content');
    const empty  = document.getElementById('canvas-empty-state');
    canvas.innerHTML = '';
    if (state.schema.blocks.length === 0) {
      empty.style.display = 'flex';
      return;
    }
    empty.style.display = 'none';
    state.schema.blocks.forEach((block, index) => {
      canvas.appendChild(createBlockEl(block, index));
    });
  }

  function createBlockEl(block, index) {
    const def = WCComponents.get(block.type);
    if (!def) return document.createComment(`unknown block type: ${block.type}`);

    const wrapper = document.createElement('div');
    wrapper.dataset.blockId   = block.id;
    wrapper.dataset.blockType = block.type;
    wrapper.className = 'wc-block relative group cursor-pointer transition rounded-lg';
    wrapper.innerHTML = def.render(block.props);

    const controls = document.createElement('div');
    controls.className = 'wc-block-controls absolute top-2 right-2 hidden group-hover:flex gap-1 z-10';
    controls.innerHTML = `
      <button data-action="move-up"   title="Move Up"   class="wc-ctrl-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-1 rounded text-xs"><i class="fas fa-arrow-up"></i></button>
      <button data-action="move-down" title="Move Down" class="wc-ctrl-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-1 rounded text-xs"><i class="fas fa-arrow-down"></i></button>
      <button data-action="duplicate" title="Duplicate" class="wc-ctrl-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-1 rounded text-xs"><i class="fas fa-copy"></i></button>
      <button data-action="delete"    title="Delete"    class="wc-ctrl-btn bg-red-900/60 hover:bg-red-800 text-red-400 px-2 py-1 rounded text-xs"><i class="fas fa-trash"></i></button>
    `;
    wrapper.appendChild(controls);

    wrapper.setAttribute('draggable', true);
    wrapper.addEventListener('dragstart', e => onBlockDragStart(e, block.id));
    wrapper.addEventListener('dragover',  e => onBlockDragOver(e, block.id));
    wrapper.addEventListener('drop',      e => onBlockDrop(e, block.id));

    wrapper.addEventListener('click', e => {
      if (e.target.closest('[data-action]')) return;
      selectBlock(block.id);
    });

    controls.addEventListener('click', e => {
      const action = e.target.closest('[data-action]')?.dataset.action;
      if (!action) return;
      e.stopPropagation();
      if (action === 'delete')    deleteBlock(block.id);
      if (action === 'duplicate') duplicateBlock(block.id);
      if (action === 'move-up')   moveBlock(block.id, -1);
      if (action === 'move-down') moveBlock(block.id,  1);
    });

    if (state.selectedId === block.id) wrapper.classList.add('component-selected');
    return wrapper;
  }

  // ── COMPONENT SHELF ──────────────────────────────────────────
  function buildComponentShelf() {
    const shelf = document.getElementById('components-shelf');
    shelf.innerHTML = '';
    WCComponents.categories().forEach(cat => {
      const catEl = document.createElement('div');
      catEl.innerHTML = `<h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">${cat.label}</h3>`;
      const grid = document.createElement('div');
      grid.className = 'grid grid-cols-2 gap-2';
      cat.components.forEach(comp => {
        const item = document.createElement('div');
        item.className = 'wc-shelf-item bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-teal-500/50 rounded-lg p-3 text-center cursor-grab transition group';
        item.setAttribute('draggable', true);
        item.innerHTML = `
          <i class="${comp.icon} text-teal-400 text-lg mb-1.5 block group-hover:scale-110 transition-transform"></i>
          <span class="text-[11px] text-slate-300 font-medium">${comp.label}</span>
        `;
        item.addEventListener('dragstart', e => {
          state.draggingType = comp.type;
          e.dataTransfer.effectAllowed = 'copy';
        });
        item.addEventListener('dragend', () => { state.draggingType = null; });
        grid.appendChild(item);
      });
      catEl.appendChild(grid);
      shelf.appendChild(catEl);
    });
  }

  // ── CANVAS DROP ZONE ─────────────────────────────────────────
  function handleCanvasDrop(e) {
    e.preventDefault();
    document.getElementById('canvas-container').classList.remove('canvas-dragover');
    if (state.draggingType) {
      addBlock(state.draggingType);
      state.draggingType = null;
    }
  }

  let dragSrcId = null;
  function onBlockDragStart(e, id) { dragSrcId = id; e.dataTransfer.effectAllowed = 'move'; }
  function onBlockDragOver(e, id)  { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
  function onBlockDrop(e, targetId) {
    e.preventDefault(); e.stopPropagation();
    if (dragSrcId && dragSrcId !== targetId) reorderBlocks(dragSrcId, targetId);
    dragSrcId = null;
  }

  // ── BLOCK MUTATIONS ──────────────────────────────────────────
  function uid() { return 'blk_' + Math.random().toString(36).slice(2, 9); }

  function addBlock(type) {
    const def = WCComponents.get(type);
    if (!def) return;
    const block = { id: uid(), type, props: JSON.parse(JSON.stringify(def.defaultProps)) };
    state.schema.blocks.push(block);
    renderCanvas();
    selectBlock(block.id);
    autosave();
  }

  function deleteBlock(id) {
    state.schema.blocks = state.schema.blocks.filter(b => b.id !== id);
    if (state.selectedId === id) deselectBlock();
    renderCanvas();
    autosave();
  }

  function duplicateBlock(id) {
    const idx = state.schema.blocks.findIndex(b => b.id === id);
    if (idx === -1) return;
    const clone = JSON.parse(JSON.stringify(state.schema.blocks[idx]));
    clone.id = uid();
    state.schema.blocks.splice(idx + 1, 0, clone);
    renderCanvas();
    selectBlock(clone.id);
    autosave();
  }

  function moveBlock(id, dir) {
    const idx = state.schema.blocks.findIndex(b => b.id === id);
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= state.schema.blocks.length) return;
    [state.schema.blocks[idx], state.schema.blocks[newIdx]] = [state.schema.blocks[newIdx], state.schema.blocks[idx]];
    renderCanvas();
    selectBlock(id);
    autosave();
  }

  function reorderBlocks(srcId, targetId) {
    const arr = state.schema.blocks;
    const srcIdx    = arr.findIndex(b => b.id === srcId);
    const targetIdx = arr.findIndex(b => b.id === targetId);
    if (srcIdx === -1 || targetIdx === -1) return;
    const [removed] = arr.splice(srcIdx, 1);
    arr.splice(targetIdx, 0, removed);
    renderCanvas();
    autosave();
  }

  function updateBlockProp(id, key, value) {
    const block = state.schema.blocks.find(b => b.id === id);
    if (!block) return;
    setNestedProp(block.props, key, value);
    const el  = document.querySelector(`[data-block-id="${id}"]`);
    const def = WCComponents.get(block.type);
    if (el && def) {
      const newContent = document.createElement('div');
      newContent.innerHTML = def.render(block.props);
      const controls = el.querySelector('.wc-block-controls');
      el.innerHTML = '';
      while (newContent.firstChild) el.appendChild(newContent.firstChild);
      if (controls) el.appendChild(controls);
    }
    autosave();
  }

  function setNestedProp(obj, path, value) {
    const keys = path.split('.');
    keys.reduce((o, k, i) => {
      if (i === keys.length - 1) o[k] = value;
      else { o[k] = o[k] ?? {}; return o[k]; }
      return o[k];
    }, obj);
  }

  // ── SELECTION & PROPERTIES PANEL ─────────────────────────────
  function selectBlock(id) {
    document.querySelectorAll('.wc-block').forEach(el => el.classList.remove('component-selected'));
    const el = document.querySelector(`[data-block-id="${id}"]`);
    if (el) { el.classList.add('component-selected'); el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    state.selectedId = id;
    const block = state.schema.blocks.find(b => b.id === id);
    if (block) renderPropertiesPanel(block);
  }

  function deselectBlock() {
    document.querySelectorAll('.wc-block').forEach(el => el.classList.remove('component-selected'));
    state.selectedId = null;
    document.getElementById('no-selection-state').classList.remove('hidden');
    document.getElementById('selection-controls').classList.add('hidden');
  }

  function renderPropertiesPanel(block) {
    const def = WCComponents.get(block.type);
    if (!def) return;

    document.getElementById('no-selection-state').classList.add('hidden');
    document.getElementById('selection-controls').classList.remove('hidden');
    document.getElementById('selected-component-type').textContent = def.label;

    let fieldsEl = document.getElementById('dynamic-prop-fields');
    if (!fieldsEl) {
      fieldsEl = document.createElement('div');
      fieldsEl.id = 'dynamic-prop-fields';
      fieldsEl.className = 'space-y-3';
      const sc = document.getElementById('selection-controls');
      if (!sc) return;
      const firstHr = sc.querySelector('hr');
      if (firstHr) sc.insertBefore(fieldsEl, firstHr);
      else sc.prepend(fieldsEl);
    }
    fieldsEl.innerHTML = '';

    def.props.forEach(propDef => {
      const field = buildPropField(propDef, block.id, block.props[propDef.key]);
      if (field) fieldsEl.appendChild(field);
    });
  }

  // ── PROP FIELD BUILDER ───────────────────────────────────────
  function buildPropField(propDef, blockId, currentValue) {
    const wrap  = document.createElement('div');
    const label = `<label class="text-[11px] text-slate-400 block mb-1 font-semibold">${propDef.label}</label>`;
    const base  = `class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500"`;

    if (propDef.type === 'text') {
      wrap.innerHTML = `${label}<input type="text" ${base} value="${escHtml(currentValue ?? '')}">`;
      wrap.querySelector('input').addEventListener('input', e => updateBlockProp(blockId, propDef.key, e.target.value));

    } else if (propDef.type === 'textarea') {
      wrap.innerHTML = `${label}<textarea rows="3" ${base}>${escHtml(currentValue ?? '')}</textarea>`;
      wrap.querySelector('textarea').addEventListener('input', e => updateBlockProp(blockId, propDef.key, e.target.value));

    } else if (propDef.type === 'select') {
      const opts = (propDef.options ?? []).map(o =>
        `<option value="${o.value}" ${currentValue === o.value ? 'selected' : ''}>${o.label}</option>`
      ).join('');
      wrap.innerHTML = `${label}<select ${base}>${opts}</select>`;
      wrap.querySelector('select').addEventListener('change', e => updateBlockProp(blockId, propDef.key, e.target.value));

    } else if (propDef.type === 'color') {
      wrap.innerHTML = `${label}<input type="color" value="${escHtml(currentValue ?? '#000000')}" class="w-full h-9 rounded cursor-pointer border border-slate-800 bg-slate-950">`;
      wrap.querySelector('input').addEventListener('input', e => updateBlockProp(blockId, propDef.key, e.target.value));

    } else if (propDef.type === 'toggle') {
      const checked = currentValue ? 'checked' : '';
      wrap.innerHTML = `<label class="flex items-center justify-between text-[11px] text-slate-400 font-semibold cursor-pointer">
        <span>${propDef.label}</span>
        <input type="checkbox" class="w-4 h-4 accent-teal-500" ${checked}>
      </label>`;
      wrap.querySelector('input').addEventListener('change', e => updateBlockProp(blockId, propDef.key, e.target.checked));

    } else if (propDef.type === 'array') {
      return buildArrayField(propDef, blockId, currentValue ?? []);

    } else {
      return null;
    }

    return wrap;
  }

  // ── ARRAY / REPEATER FIELD ───────────────────────────────────
  function buildArrayField(propDef, blockId, items) {
    const container = document.createElement('div');
    container.className = 'space-y-2';

    const header = document.createElement('div');
    header.className = 'flex items-center justify-between mb-1';
    header.innerHTML = `
      <span class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">${propDef.label}</span>
      <button class="wc-add-item bg-teal-500/10 hover:bg-teal-500/20 text-teal-400 border border-teal-500/30 text-[10px] font-bold px-2 py-1 rounded transition flex items-center gap-1">
        <i class="fas fa-plus"></i> Add ${propDef.itemLabel ?? 'Item'}
      </button>
    `;
    container.appendChild(header);

    const listEl = document.createElement('div');
    listEl.className = 'space-y-2';
    container.appendChild(listEl);

    function renderItems() {
      listEl.innerHTML = '';
      items.forEach((item, idx) => {
        const itemWrap = document.createElement('div');
        itemWrap.className = 'bg-slate-950 border border-slate-800 rounded-lg overflow-hidden';

        const itemHeader = document.createElement('div');
        itemHeader.className = 'flex items-center justify-between px-3 py-2 cursor-pointer hover:bg-slate-800/50 transition';
        const previewText = item[propDef.fields?.[0]?.key] ?? `${propDef.itemLabel ?? 'Item'} ${idx + 1}`;
        itemHeader.innerHTML = `
          <span class="text-[11px] text-slate-300 font-semibold truncate">${escHtml(String(previewText))}</span>
          <div class="flex items-center gap-1 shrink-0 ml-2">
            <button data-move="up"   title="Move Up"   class="text-slate-500 hover:text-teal-400 px-1"><i class="fas fa-arrow-up text-[10px]"></i></button>
            <button data-move="down" title="Move Down" class="text-slate-500 hover:text-teal-400 px-1"><i class="fas fa-arrow-down text-[10px]"></i></button>
            <button data-remove title="Remove" class="text-slate-500 hover:text-red-400 px-1"><i class="fas fa-times text-[10px]"></i></button>
            <i class="fas fa-chevron-down text-slate-600 text-[10px] ml-1 toggle-chevron"></i>
          </div>
        `;

        const itemBody = document.createElement('div');
        itemBody.className = 'px-3 pb-3 space-y-2 hidden';

        (propDef.fields ?? []).forEach(fieldDef => {
          const fWrap = buildPropField(
            { ...fieldDef, label: fieldDef.label },
            blockId,
            item[fieldDef.key]
          );
          if (!fWrap) return;
          const input = fWrap.querySelector('input,textarea,select');
          if (input) {
            const fresh = input.cloneNode(true);
            input.replaceWith(fresh);
            const evtName = fresh.tagName === 'SELECT' ? 'change' : (fresh.type === 'checkbox' ? 'change' : 'input');
            fresh.addEventListener(evtName, ev => {
              items[idx][fieldDef.key] = fresh.type === 'checkbox' ? fresh.checked : fresh.value;
              updateBlockProp(blockId, propDef.key, items);
              const preview = itemHeader.querySelector('span');
              if (preview) preview.textContent = escHtml(String(items[idx][propDef.fields?.[0]?.key] ?? `${propDef.itemLabel} ${idx + 1}`));
            });
          }
          itemBody.appendChild(fWrap);
        });

        itemHeader.addEventListener('click', e => {
          if (e.target.closest('[data-remove],[data-move]')) return;
          itemBody.classList.toggle('hidden');
          itemHeader.querySelector('.toggle-chevron').classList.toggle('fa-chevron-down');
          itemHeader.querySelector('.toggle-chevron').classList.toggle('fa-chevron-up');
        });

        itemHeader.querySelector('[data-remove]').addEventListener('click', e => {
          e.stopPropagation();
          items.splice(idx, 1);
          updateBlockProp(blockId, propDef.key, items);
          renderItems();
        });

        itemHeader.querySelector('[data-move="up"]').addEventListener('click', e => {
          e.stopPropagation();
          if (idx === 0) return;
          [items[idx - 1], items[idx]] = [items[idx], items[idx - 1]];
          updateBlockProp(blockId, propDef.key, items);
          renderItems();
        });

        itemHeader.querySelector('[data-move="down"]').addEventListener('click', e => {
          e.stopPropagation();
          if (idx >= items.length - 1) return;
          [items[idx], items[idx + 1]] = [items[idx + 1], items[idx]];
          updateBlockProp(blockId, propDef.key, items);
          renderItems();
        });

        itemWrap.appendChild(itemHeader);
        itemWrap.appendChild(itemBody);
        listEl.appendChild(itemWrap);
      });
    }

    header.querySelector('.wc-add-item').addEventListener('click', () => {
      items.push(JSON.parse(JSON.stringify(propDef.itemDefault ?? {})));
      updateBlockProp(blockId, propDef.key, items);
      renderItems();
      const lastItem = listEl.lastElementChild;
      if (lastItem) {
        const body = lastItem.querySelector('div.hidden');
        if (body) body.classList.remove('hidden');
      }
    });

    renderItems();
    return container;
  }

  // ── SAVE / PUBLISH ───────────────────────────────────────────
  let autosaveTimer = null;
  function autosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => saveProject(false, true), 2000);
  }

  async function saveProject(publish = false, silent = false) {
    if (!silent) showToast('Saving…', 'Sending to server…', 'info');
    state.schema.meta.custom_css = document.getElementById('project-custom-css')?.value ?? '';
    state.schema.meta.custom_js  = document.getElementById('project-custom-js')?.value  ?? '';

    try {
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:     publish ? 'publish_project' : 'save_project',
          project_id: PROJECT_ID,
          csrf_token: CSRF_TOKEN,
          schema:     state.schema
        })
      });
      const data = await res.json();
      if (data.success) {
        if (!silent) showToast(publish ? 'Published! 🚀' : 'Draft Saved ✓', data.message ?? 'Project updated.', 'success');
        if (publish) {
          const badge = document.getElementById('status-badge');
          if (badge) { badge.textContent = 'Published'; badge.className = 'font-semibold uppercase text-teal-400'; }
        }
      } else {
        showToast('Save Failed', data.error ?? 'An error occurred.', 'error');
      }
    } catch (err) {
      showToast('Network Error', err.message, 'error');
    }
  }

  async function publishProject()   { await saveProject(true); }
  async function exportProjectZip() { window.location.href = `api.php?action=export_zip&project_id=${PROJECT_ID}&csrf_token=${CSRF_TOKEN}`; }

  // ── CANVAS VIEW ──────────────────────────────────────────────
  function setCanvasView(mode) {
    const sizes = { desktop: 'w-full', tablet: 'max-w-[768px] mx-auto', mobile: 'max-w-[390px] mx-auto' };
    const container = document.getElementById('canvas-container');
    ['w-full', 'max-w-[768px]', 'max-w-[390px]', 'mx-auto'].forEach(c => container.classList.remove(c));
    sizes[mode].split(' ').forEach(c => container.classList.add(c));
    ['desktop', 'tablet', 'mobile'].forEach(m => {
      const btn = document.getElementById(`view-${m}`);
      if (!btn) return;
      btn.classList.toggle('bg-slate-800',  m === mode);
      btn.classList.toggle('text-teal-400', m === mode);
      btn.classList.toggle('text-slate-400', m !== mode);
    });
  }

  // ── UI HELPERS ───────────────────────────────────────────────
  function switchControlPanelTab(tab) {
    document.getElementById('property-panel').classList.toggle('hidden', tab !== 'properties');
    document.getElementById('settings-panel').classList.toggle('hidden', tab !== 'settings');
    ['properties', 'settings'].forEach(t => {
      const btn = document.getElementById(`control-tab-btn-${t}`);
      if (!btn) return;
      btn.classList.toggle('border-teal-500',    t === tab);
      btn.classList.toggle('text-teal-400',      t === tab);
      btn.classList.toggle('border-transparent', t !== tab);
      btn.classList.toggle('text-slate-400',     t !== tab);
    });
  }

  function deleteSelectedComponent() { if (state.selectedId) deleteBlock(state.selectedId); }

  function showToast(title, desc, type = 'info') {
    const toast = document.getElementById('notification-toast');
    if (!toast) return;
    document.getElementById('notification-title').textContent = title;
    document.getElementById('notification-desc').textContent  = desc;
    const icon = toast.querySelector('div > div:first-child');
    if (icon) {
      icon.className = 'p-2 rounded-full text-sm ' + {
        success: 'bg-teal-500/20 text-teal-400',
        error:   'bg-red-500/20 text-red-400',
        info:    'bg-slate-700 text-slate-300'
      }[type];
    }
    toast.classList.remove('translate-y-24', 'opacity-0');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.add('translate-y-24', 'opacity-0'), 3500);
  }

  function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  // ── PUBLIC API ───────────────────────────────────────────────
  return {
    init,
    handleCanvasDrop,
    setCanvasView,
    switchControlPanelTab,
    saveProject,
    publishProject,
    exportProjectZip,
    deleteSelectedComponent
  };

})();

// Bootstrap
window.addEventListener('DOMContentLoaded', () => WCBuilder.init(LOADED_CONTENT_STATE));

// Expose globals for onclick= handlers in builder.php
window.handleCanvasDrop        = WCBuilder.handleCanvasDrop;
window.setCanvasView           = WCBuilder.setCanvasView;
window.switchControlPanelTab   = WCBuilder.switchControlPanelTab;
window.saveProject             = WCBuilder.saveProject;
window.publishProject          = WCBuilder.publishProject;
window.exportProjectZip        = WCBuilder.exportProjectZip;
window.deleteSelectedComponent = WCBuilder.deleteSelectedComponent;
