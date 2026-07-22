<?php
declare(strict_types=1);

$page = blu_cms_page_slug();
if ($page === '') {
  $page = trim((string) ($_GET['blu_cms_page'] ?? 'despre'));
}
require_once dirname(__DIR__, 2) . '/lib/website_builder.php';
$builderConfig = blu_builder_export_js_config($page);
$apiUrl = 'api/admin-website.php';
?>
<link rel="stylesheet" href="assets/shop/cms-edit.css?v=2">
<link rel="stylesheet" href="assets/shop/cms-builder.css?v=1">
<div id="bluCmsToolbar" class="blu-cms-toolbar" role="toolbar" aria-label="Editor conținut">
  <span class="blu-cms-toolbar-badge"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Constructor site</span>
  <span class="blu-cms-toolbar-hint">Click pe text · panoul din dreapta pentru blocuri noi</span>
  <span id="bluCmsStatus" class="blu-cms-toolbar-status" aria-live="polite"></span>
  <button type="button" class="blu-cms-btn blu-cms-btn-ghost" id="bluCmsPreview">
    <i class="fa-solid fa-eye" aria-hidden="true"></i> Previzualizare
  </button>
  <button type="button" class="blu-cms-btn blu-cms-btn-primary" id="bluCmsSave">
    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvează tot
  </button>
</div>
<script>window.__bluBuilderConfig = <?= json_encode($builderConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;</script>
<script>
(function () {
  'use strict';
  const PAGE = <?= json_encode($page, JSON_UNESCAPED_UNICODE) ?>;
  const API = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE) ?>;
  const statusEl = document.getElementById('bluCmsStatus');
  const saveBtn = document.getElementById('bluCmsSave');
  const previewBtn = document.getElementById('bluCmsPreview');
  let dirty = false;

  document.body.classList.add('blu-cms-editing');

  window.__bluCmsMarkDirty = function () {
    dirty = true;
    setStatus('Modificări nesalvate…');
  };

  function setStatus(msg, isErr) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'blu-cms-toolbar-status' + (isErr ? ' is-error' : msg ? ' is-ok' : '');
  }

  function collectFields() {
    const fields = {};
    document.querySelectorAll('[data-cms]').forEach((el) => {
      const key = el.getAttribute('data-cms') || '';
      const dot = key.indexOf('.');
      if (dot < 0) return;
      const fieldKey = key.slice(dot + 1);
      const isHtml = el.getAttribute('data-cms-html') === '1';
      fields[fieldKey] = isHtml ? el.innerHTML.trim() : (el.textContent || '').trim();
    });
    return fields;
  }

  document.querySelectorAll('[data-cms]').forEach((el) => {
    el.addEventListener('input', () => window.__bluCmsMarkDirty());
    el.addEventListener('focus', () => el.classList.add('is-focused'));
    el.addEventListener('blur', () => el.classList.remove('is-focused'));
  });

  async function save() {
    saveBtn.disabled = true;
    setStatus('Se salvează…');
    const blocks = typeof window.__bluBuilderGetBlocks === 'function' ? window.__bluBuilderGetBlocks() : [];
    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ page: PAGE, fields: collectFields(), blocks })
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Eroare la salvare');
      dirty = false;
      window.__bluBuilderDirty = false;
      setStatus(json.message || 'Salvat.');
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'bluCmsSaved', page: PAGE }, '*');
      }
    } catch (err) {
      setStatus(err.message || 'Eroare', true);
    } finally {
      saveBtn.disabled = false;
    }
  }

  saveBtn?.addEventListener('click', save);
  previewBtn?.addEventListener('click', () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('blu_cms_edit');
    window.open(url.toString(), '_blank', 'noopener');
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      save();
    }
  });

  window.addEventListener('beforeunload', (e) => {
    if (dirty || window.__bluBuilderDirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
})();
</script>
<script src="assets/shop/cms-builder.js?v=1"></script>
<script src="assets/shop/shop.js?v=31"></script>
