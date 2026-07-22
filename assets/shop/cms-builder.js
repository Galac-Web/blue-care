(function () {
  'use strict';

  const cfg = window.__bluBuilderConfig;
  if (!cfg || !cfg.page) return;

  const state = {
    blocks: (cfg.blocks || []).map(normalizeBlock),
    selectedId: null,
    pendingZone: cfg.zones[0] || 'after_hero',
  };

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  }

  function normalizeBlock(b) {
    const type = b.type || 'text';
    const defs = (cfg.types[type] && cfg.types[type].defaults) || {};
    return {
      id: b.id || newId(),
      type,
      zone: b.zone || state.pendingZone,
      props: Object.assign({}, defs, b.props || {}),
    };
  }

  function newId() {
    return 'blk_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 6);
  }

  function blockLabel(block) {
    const t = cfg.types[block.type];
    const name = t ? t.label : block.type;
    const preview = block.props.text || block.props.title || block.props.label || block.props.html || '';
    const short = String(preview).replace(/<[^>]+>/g, '').slice(0, 28);
    return name + (short ? ': ' + short : '');
  }

  function renderBlockInner(block) {
    const p = block.props;
    switch (block.type) {
      case 'heading': {
        const lvl = p.level === 'h3' ? 'h3' : 'h2';
        return `<div class="blu-block-heading shop-woo-section reveal"><${lvl} class="blu-block-heading__text">${esc(p.text)}</${lvl}></div>`;
      }
      case 'text':
        return `<div class="blu-block-text shop-woo-prose shop-woo-section reveal">${p.html || ''}</div>`;
      case 'message':
        return `<div class="blu-block-message shop-woo-section reveal"><div class="blu-block-alert blu-block-alert--${esc(p.variant || 'info')}"><strong class="blu-block-alert__title">${esc(p.title)}</strong><p class="blu-block-alert__text">${esc(p.text).replace(/\n/g, '<br>')}</p></div></div>`;
      case 'image': {
        if (!p.url) {
          return '<div class="blu-block-image blu-block-image--placeholder shop-woo-section reveal"><i class="fa-solid fa-image"></i> Adaugă URL imagine</div>';
        }
        const img = `<img src="${esc(p.url)}" alt="${esc(p.alt)}" loading="lazy">`;
        const inner = p.link ? `<a href="${esc(p.link)}">${img}</a>` : img;
        const cap = p.caption ? `<figcaption>${esc(p.caption)}</figcaption>` : '';
        return `<figure class="blu-block-image shop-woo-section reveal">${inner}${cap}</figure>`;
      }
      case 'button': {
        const cls = p.style === 'ghost' ? 'shop-btn-ghost' : (p.style === 'glow' ? 'shop-btn-glow' : 'shop-btn-accent');
        return `<div class="blu-block-button shop-woo-section reveal"><a class="shop-btn ${cls}" href="${esc(p.url || '#')}">${esc(p.label)}</a></div>`;
      }
      case 'columns':
        return `<div class="blu-block-columns shop-woo-section reveal"><div class="blu-block-columns__grid"><div class="blu-block-columns__col shop-woo-prose">${p.left || ''}</div><div class="blu-block-columns__col shop-woo-prose">${p.right || ''}</div></div></div>`;
      case 'spacer':
        return `<div class="blu-block-spacer blu-block-spacer--${esc(p.size || 'md')}" aria-hidden="true"></div>`;
      case 'divider':
        return '<hr class="blu-block-divider shop-woo-section reveal">';
      default:
        return '';
    }
  }

  function renderBlockEl(block) {
    const wrap = document.createElement('div');
    wrap.className = 'blu-block';
    wrap.dataset.blockId = block.id;
    wrap.dataset.blockType = block.type;
    wrap.dataset.blockProps = JSON.stringify(block.props);
    wrap.innerHTML = renderBlockInner(block) + `
      <div class="blu-block-controls" aria-hidden="true">
        <button type="button" class="blu-block-ctrl" data-block-act="up" title="Mută sus"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="blu-block-ctrl" data-block-act="down" title="Mută jos"><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="blu-block-ctrl" data-block-act="edit" title="Editează"><i class="fa-solid fa-pen"></i></button>
        <button type="button" class="blu-block-ctrl blu-block-ctrl--danger" data-block-act="delete" title="Șterge"><i class="fa-solid fa-trash"></i></button>
      </div>`;
    return wrap;
  }

  function renderZone(zone) {
    const zoneEl = document.querySelector(`[data-builder-zone="${zone}"]`);
    if (!zoneEl) return;
    zoneEl.querySelectorAll('.blu-block').forEach((el) => el.remove());
    const empty = zoneEl.querySelector('.blu-builder-zone-empty');
    const blocks = state.blocks.filter((b) => b.zone === zone);
    if (empty) empty.style.display = blocks.length ? 'none' : '';
    const addBtn = zoneEl.querySelector('.blu-builder-zone-add');
    blocks.forEach((b) => {
      zoneEl.insertBefore(renderBlockEl(b), addBtn);
    });
  }

  function renderAllZones() {
    (cfg.zones || []).forEach(renderZone);
  }

  function selectBlock(id) {
    state.selectedId = id;
    document.querySelectorAll('.blu-block').forEach((el) => {
      el.classList.toggle('is-selected', el.dataset.blockId === id);
    });
    document.querySelectorAll('.blu-builder-block-list li').forEach((li) => {
      li.classList.toggle('is-active', li.dataset.blockId === id);
    });
    renderPropForm();
    const el = document.querySelector(`[data-block-id="${id}"]`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function getSelected() {
    return state.blocks.find((b) => b.id === state.selectedId) || null;
  }

  function renderPropForm() {
    const formEl = document.getElementById('bluBuilderForm');
    if (!formEl) return;
    const block = getSelected();
    if (!block) {
      formEl.innerHTML = '<div class="blu-builder-empty-form">Selectează un bloc sau adaugă unul nou.</div>';
      return;
    }
    const typeDef = cfg.types[block.type];
    if (!typeDef || !typeDef.fields) {
      formEl.innerHTML = '';
      return;
    }
    let html = `<div class="blu-builder-form"><strong style="font-size:.8rem">${esc(typeDef.label)}</strong>`;
    typeDef.fields.forEach((f) => {
      const val = block.props[f.key] ?? '';
      html += `<label>${esc(f.label)}</label>`;
      if (f.type === 'select') {
        html += `<select data-prop="${esc(f.key)}">`;
        Object.entries(f.options || {}).forEach(([k, lbl]) => {
          html += `<option value="${esc(k)}"${val === k ? ' selected' : ''}>${esc(lbl)}</option>`;
        });
        html += '</select>';
      } else if (f.type === 'textarea' || f.type === 'html') {
        html += `<textarea data-prop="${esc(f.key)}" rows="${f.type === 'html' ? 5 : 3}">${esc(val)}</textarea>`;
      } else {
        html += `<input type="text" data-prop="${esc(f.key)}" value="${esc(val)}">`;
      }
    });
    html += '</div>';
    formEl.innerHTML = html;
    formEl.querySelectorAll('[data-prop]').forEach((input) => {
      input.addEventListener('input', () => {
        const key = input.getAttribute('data-prop');
        block.props[key] = input.value;
        refreshBlock(block.id);
        markDirty();
        updateBlockList();
      });
    });
  }

  function refreshBlock(id) {
    const block = state.blocks.find((b) => b.id === id);
    const el = document.querySelector(`[data-block-id="${id}"]`);
    if (!block || !el) return;
    el.dataset.blockProps = JSON.stringify(block.props);
    const controls = el.querySelector('.blu-block-controls');
    el.innerHTML = renderBlockInner(block);
    if (controls) el.appendChild(controls);
    else {
      el.innerHTML += `<div class="blu-block-controls" aria-hidden="true">
        <button type="button" class="blu-block-ctrl" data-block-act="up"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="blu-block-ctrl" data-block-act="down"><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="blu-block-ctrl" data-block-act="edit"><i class="fa-solid fa-pen"></i></button>
        <button type="button" class="blu-block-ctrl blu-block-ctrl--danger" data-block-act="delete"><i class="fa-solid fa-trash"></i></button>
      </div>`;
    }
  }

  function addBlock(type, zone) {
    const typeDef = cfg.types[type];
    if (!typeDef) return;
    const block = normalizeBlock({
      id: newId(),
      type,
      zone: zone || state.pendingZone,
      props: Object.assign({}, typeDef.defaults || {}),
    });
    state.blocks.push(block);
    renderZone(block.zone);
    selectBlock(block.id);
    markDirty();
    updateBlockList();
  }

  function deleteBlock(id) {
    state.blocks = state.blocks.filter((b) => b.id !== id);
    if (state.selectedId === id) state.selectedId = null;
    renderAllZones();
    renderPropForm();
    updateBlockList();
    markDirty();
  }

  function moveBlock(id, dir) {
    const block = state.blocks.find((b) => b.id === id);
    if (!block) return;
    const zoneBlocks = state.blocks.filter((b) => b.zone === block.zone);
    const idx = zoneBlocks.findIndex((b) => b.id === id);
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= zoneBlocks.length) return;
    const allIdx = state.blocks.indexOf(block);
    const swap = zoneBlocks[newIdx];
    const swapIdx = state.blocks.indexOf(swap);
    state.blocks[allIdx] = swap;
    state.blocks[swapIdx] = block;
    renderZone(block.zone);
    selectBlock(id);
    markDirty();
    updateBlockList();
  }

  function updateBlockList() {
    const list = document.getElementById('bluBuilderBlockList');
    if (!list) return;
    if (!state.blocks.length) {
      list.innerHTML = '<li style="cursor:default;color:#94a3b8">Niciun bloc încă</li>';
      return;
    }
    list.innerHTML = state.blocks.map((b) =>
      `<li data-block-id="${esc(b.id)}" class="${b.id === state.selectedId ? 'is-active' : ''}"><i class="fa-solid ${esc((cfg.types[b.type] || {}).icon || 'fa-cube')}"></i><span>${esc(blockLabel(b))}</span></li>`
    ).join('');
    list.querySelectorAll('li[data-block-id]').forEach((li) => {
      li.addEventListener('click', () => selectBlock(li.dataset.blockId));
    });
  }

  function buildSidebar() {
    const sidebar = document.createElement('aside');
    sidebar.className = 'blu-builder-sidebar';
    sidebar.id = 'bluBuilderSidebar';
    sidebar.innerHTML = `
      <div class="blu-builder-sidebar__head">
        <h3><i class="fa-solid fa-cubes"></i> Constructor site</h3>
        <p>Adaugă blocuri: mesaj, imagine, text, buton…</p>
      </div>
      <div class="blu-builder-sidebar__body">
        <label style="font-size:.75rem;font-weight:600;color:#475569">Zonă inserare</label>
        <select id="bluBuilderZoneSelect" style="width:100%;margin-bottom:.75rem;border:1px solid #e2e8f0;border-radius:8px;padding:.4rem;font-size:.82rem">
          ${(cfg.zones || []).map((z) => `<option value="${esc(z)}">${esc((cfg.zoneLabels || {})[z] || z)}</option>`).join('')}
        </select>
        <div class="blu-builder-palette" id="bluBuilderPalette"></div>
        <strong style="font-size:.78rem;color:#475569;display:block;margin-bottom:.35rem">Blocuri pe pagină</strong>
        <ul class="blu-builder-block-list" id="bluBuilderBlockList"></ul>
        <div id="bluBuilderForm"></div>
      </div>`;
    document.body.appendChild(sidebar);

    const palette = sidebar.querySelector('#bluBuilderPalette');
    Object.entries(cfg.types || {}).forEach(([type, def]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'blu-builder-palette-btn';
      btn.innerHTML = `<i class="fa-solid ${esc(def.icon || 'fa-cube')}"></i>${esc(def.label)}`;
      btn.title = def.desc || '';
      btn.addEventListener('click', () => {
        const zone = document.getElementById('bluBuilderZoneSelect')?.value || state.pendingZone;
        addBlock(type, zone);
      });
      palette.appendChild(btn);
    });

    document.getElementById('bluBuilderZoneSelect')?.addEventListener('change', (e) => {
      state.pendingZone = e.target.value;
    });

    updateBlockList();
    renderPropForm();
  }

  function markDirty() {
    window.__bluBuilderDirty = true;
    if (typeof window.__bluCmsMarkDirty === 'function') window.__bluCmsMarkDirty();
  }

  document.addEventListener('click', (e) => {
    const actBtn = e.target.closest('[data-block-act]');
    if (actBtn) {
      e.preventDefault();
      const blockEl = actBtn.closest('.blu-block');
      const id = blockEl?.dataset?.blockId;
      if (!id) return;
      const act = actBtn.getAttribute('data-block-act');
      if (act === 'delete' && confirm('Ștergi acest bloc?')) deleteBlock(id);
      else if (act === 'edit') selectBlock(id);
      else if (act === 'up') moveBlock(id, -1);
      else if (act === 'down') moveBlock(id, 1);
      return;
    }
    const zoneAdd = e.target.closest('[data-zone-add]');
    if (zoneAdd) {
      state.pendingZone = zoneAdd.getAttribute('data-zone-add');
      const sel = document.getElementById('bluBuilderZoneSelect');
      if (sel) sel.value = state.pendingZone;
      document.getElementById('bluBuilderSidebar')?.scrollIntoView({ behavior: 'smooth' });
    }
    const blockEl = e.target.closest('.blu-block');
    if (blockEl && !e.target.closest('.blu-block-controls')) {
      selectBlock(blockEl.dataset.blockId);
    }
  });

  window.__bluBuilderGetBlocks = () => state.blocks.map((b) => ({
    id: b.id,
    type: b.type,
    zone: b.zone,
    props: b.props,
  }));

  buildSidebar();
  renderAllZones();
})();
