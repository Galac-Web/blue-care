<?php
declare(strict_types=1);

require_once __DIR__ . '/commerce_store.php';
require_once __DIR__ . '/admin_section_nav.php';

function blu_commerce_api_url(): string
{
    $base = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '../';
    return rtrim($base, '/') . '/api/admin-commerce.php';
}

function blu_commerce_badge_class(string $type, string $status): string
{
    return match ($type) {
        'order' => match ($status) {
            'livrat' => 'bg-success',
            'anulat' => 'bg-danger',
            'expediere' => 'bg-info',
            'procesare' => 'bg-warning text-dark',
            default => 'bg-secondary',
        },
        'invoice' => match ($status) {
            'achitata' => 'bg-success',
            'anulata', 'storno' => 'bg-danger',
            default => 'bg-warning text-dark',
        },
        'delivery' => match ($status) {
            'livrat' => 'bg-success',
            'in_tranzit', 'awb_generat' => 'bg-info',
            'retur', 'anulat' => 'bg-danger',
            default => 'bg-warning text-dark',
        },
        default => 'bg-secondary',
    };
}

function blu_commerce_shared_styles(): void
{
    ?>
    <style>
        .cm-panel { --cm: #308e87; }
        .cm-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .cm-stat { border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; background: #fff; }
        .cm-stat strong { display: block; font-size: 1.35rem; line-height: 1.2; }
        .cm-stat span { font-size: .75rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
        .cm-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: 1rem; }
        .cm-toolbar .form-control, .cm-toolbar .form-select { max-width: 280px; }
        .cm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .cm-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; padding: 1rem; height: 100%; display: flex; flex-direction: column; }
        .cm-card__title { font-weight: 600; margin-bottom: .25rem; }
        .cm-card__meta { font-size: .82rem; color: #64748b; }
        .cm-card__amount { font-weight: 700; margin-top: .5rem; color: var(--cm); }
        .cm-card__actions { margin-top: auto; padding-top: .75rem; display: flex; flex-wrap: wrap; gap: .35rem; }
        .cm-card__sent { font-size: .75rem; color: #64748b; margin-top: .35rem; }
        .cm-table-wrap { overflow-x: auto; }
        .cm-empty { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 2rem; text-align: center; color: #64748b; }
        .cm-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 10050; display: none; align-items: flex-start; justify-content: center; padding: 1.5rem; overflow-y: auto; }
        .cm-modal-backdrop.is-open { display: flex; }
        .cm-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 720px; box-shadow: 0 20px 50px rgba(15,23,42,.2); }
        .cm-modal__head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
        .cm-modal__body { padding: 1.25rem; }
        .cm-modal__foot { padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: .5rem; }
        .cm-toast { position: fixed; top: 1rem; right: 1rem; z-index: 10060; display: none; min-width: 220px; }
        .cm-toast.is-visible { display: block; }
    </style>
    <?php
}

function blu_commerce_shared_scripts(string $entity, array $config): void
{
    $api = blu_commerce_api_url();
    $cfg = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    ?>
    <div id="cmToast" class="alert cm-toast" role="alert"></div>
    <div id="cmModalBackdrop" class="cm-modal-backdrop" aria-hidden="true">
        <div class="cm-modal" role="dialog" aria-modal="true">
            <div class="cm-modal__head">
                <h5 class="mb-0" id="cmModalTitle">Editare</h5>
                <button type="button" class="btn-close" id="cmModalClose" aria-label="Închide"></button>
            </div>
            <form id="cmForm">
                <div class="cm-modal__body" id="cmFormFields"></div>
                <div class="cm-modal__foot">
                    <button type="button" class="btn btn-outline-secondary" id="cmModalCancel">Anulează</button>
                    <button type="submit" class="btn btn-primary">Salvează</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
        'use strict';
        const API = <?= json_encode($api, JSON_UNESCAPED_UNICODE) ?>;
        const CFG = <?= $cfg ?>;
        const entity = <?= json_encode($entity, JSON_UNESCAPED_UNICODE) ?>;
        const toast = document.getElementById('cmToast');
        const backdrop = document.getElementById('cmModalBackdrop');
        const form = document.getElementById('cmForm');
        const fieldsEl = document.getElementById('cmFormFields');
        const titleEl = document.getElementById('cmModalTitle');
        let rows = [];
        let stats = {};
        let statusFilter = '';

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        }

        function showToast(msg, isErr) {
            if (!toast) return;
            toast.textContent = msg;
            toast.className = 'alert cm-toast is-visible ' + (isErr ? 'alert-danger' : 'alert-success');
            setTimeout(() => toast.classList.remove('is-visible'), 3200);
        }

        async function api(action, payload) {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entity, action, ...payload })
            });
            const raw = await res.text();
            let json;
            try { json = JSON.parse(raw); } catch (e) { throw new Error('Răspuns invalid de la server.'); }
            if (!res.ok || !json.success) throw new Error(json.message || 'Eroare');
            return json.data;
        }

        function badgeClass(status) {
            const map = CFG.badgeMap || {};
            return map[status] || 'bg-secondary';
        }

        function statusLabel(status) {
            const map = CFG.statusLabels || {};
            return map[status] || status;
        }

        function filteredRows() {
            const q = (document.getElementById('cmSearch')?.value || '').trim().toLowerCase();
            return rows.filter((row) => {
                if (statusFilter && String(row[CFG.statusField] || '') !== statusFilter) return false;
                if (!q) return true;
                return (CFG.searchFields || []).some((f) => String(row[f] || '').toLowerCase().includes(q));
            });
        }

        function renderStats() {
            const el = document.getElementById('cmStats');
            if (!el || !CFG.statKeys) return;
            el.innerHTML = CFG.statKeys.map((key) => {
                const label = CFG.statLabels?.[key] || key;
                const val = key === 'total_amount' ? Number(stats[key] || 0).toFixed(2) + ' RON' : (stats[key] ?? 0);
                return `<div class="cm-stat"><strong>${esc(val)}</strong><span>${esc(label)}</span></div>`;
            }).join('');
        }

        function renderCards() {
            const grid = document.getElementById('cmGrid');
            if (!grid) return;
            const visible = filteredRows();
            if (!visible.length) {
                grid.innerHTML = '<div class="cm-empty">Nu există înregistrări.</div>';
                return;
            }
            grid.innerHTML = visible.map((row) => {
                const status = String(row[CFG.statusField] || '');
                const title = esc(row[CFG.titleField] || row[CFG.titleFallback] || '-');
                const sub = esc(row[CFG.subField] || '');
                const phone = esc(row.phone || '');
                const amount = CFG.amountField ? Number(row[CFG.amountField] || 0).toFixed(2) + ' RON' : '';
                let preview = '';
                if (CFG.previewField && row[CFG.previewField]) {
                    preview = esc(String(row[CFG.previewField]).split('\n').filter(Boolean).slice(0, 2).join(' · '));
                } else if (Array.isArray(row.items) && row.items.length) {
                    preview = esc(row.items.slice(0, 2).map((it) => {
                        const qty = Number(it.qty || 1);
                        const name = String(it.name || 'Produs');
                        return (qty > 1 ? (qty + '× ') : '') + name;
                    }).join(' · '));
                }
                const source = String(row.source || '');
                const sourceBadge = source === 'shop_checkout'
                    ? '<span class="badge bg-primary-subtle text-primary border ms-1">Magazin</span>'
                    : '';
                let invoiceActions = '';
                if (CFG.invoiceActions) {
                    const sentVia = String(row.last_sent_via || '');
                    const sentAt = String(row.last_sent_at || '');
                    const sentLabel = sentAt
                        ? `Trimisă ${sentVia === 'whatsapp' ? 'WhatsApp' : sentVia === 'email' ? 'email' : sentVia} · ${esc(sentAt)}`
                        : '';
                    invoiceActions = `
                        ${sentLabel ? `<div class="cm-card__sent">${sentLabel}</div>` : ''}
                        <a class="btn btn-sm btn-outline-secondary" href="invoice-view.php?id=${esc(row.id)}" target="_blank" rel="noopener">Vizualizează</a>
                        <button type="button" class="btn btn-sm btn-success cm-inv-wa" data-id="${esc(row.id)}">WhatsApp</button>
                        <button type="button" class="btn btn-sm btn-outline-primary cm-inv-mail" data-id="${esc(row.id)}">Email</button>`;
                }
                return `<div class="cm-card" data-id="${esc(row.id)}">
                    <div class="cm-card__title">${title}${sourceBadge}</div>
                    <div class="cm-card__meta">${sub}${phone ? ' · ' + phone : ''}</div>
                    ${preview ? `<div class="cm-card__meta mt-1" style="line-height:1.35">${preview}</div>` : ''}
                    <div class="mt-2"><span class="badge ${badgeClass(status)}">${esc(statusLabel(status))}</span></div>
                    ${amount ? `<div class="cm-card__amount">${esc(amount)}</div>` : ''}
                    <div class="cm-card__actions">
                        ${invoiceActions}
                        <button type="button" class="btn btn-sm btn-outline-primary cm-edit" data-row='${esc(JSON.stringify(row))}'>Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger cm-del" data-id="${esc(row.id)}">Șterge</button>
                    </div>
                </div>`;
            }).join('');
        }

        function buildFields(row) {
            const r = row || {};
            return (CFG.fields || []).map((f) => {
                if (f.type === 'custom') return '';
                const val = esc(r[f.name] ?? f.default ?? '');
                const req = f.required ? ' required' : '';
                if (f.type === 'select') {
                    const opts = (f.options || []).map((o) => {
                        const sel = String(r[f.name] ?? f.default ?? '') === o.value ? ' selected' : '';
                        return `<option value="${esc(o.value)}"${sel}>${esc(o.label)}</option>`;
                    }).join('');
                    return `<div class="mb-3"><label class="form-label">${esc(f.label)}</label><select class="form-select" name="${esc(f.name)}"${req}>${opts}</select></div>`;
                }
                if (f.type === 'textarea') {
                    return `<div class="mb-3"><label class="form-label">${esc(f.label)}</label><textarea class="form-control" name="${esc(f.name)}" rows="${f.rows || 3}"${req}>${val}</textarea></div>`;
                }
                if (f.type === 'hidden') {
                    return `<input type="hidden" name="${esc(f.name)}" value="${val}">`;
                }
                const type = f.type || 'text';
                const step = f.step ? ` step="${esc(f.step)}"` : '';
                return `<div class="mb-3"><label class="form-label">${esc(f.label)}</label><input class="form-control" type="${esc(type)}" name="${esc(f.name)}" value="${val}"${step}${req}></div>`;
            }).join('') + `<input type="hidden" name="id" value="${esc(r.id || '')}">`;
        }

        function injectOrderSelect() {
            if (!window.__cmOrders || !window.__cmOrders.length) return;
            if (document.getElementById('cmOrderSelect')) return;
            const wrap = document.createElement('div');
            wrap.className = 'mb-3';
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = 'Alege comanda';
            const sel = document.createElement('select');
            sel.className = 'form-select';
            sel.id = 'cmOrderSelect';
            sel.innerHTML = '<option value="">Selectează comanda</option>' +
                window.__cmOrders.map((o) =>
                    `<option value="${esc(o.id)}" data-order='${esc(JSON.stringify(o))}'>${esc(o.order_number)} — ${esc(o.client_name)}</option>`
                ).join('');
            wrap.appendChild(label);
            wrap.appendChild(sel);
            if (CFG.invoiceActions) {
                const quickBtn = document.createElement('button');
                quickBtn.type = 'button';
                quickBtn.className = 'btn btn-outline-primary btn-sm mt-2';
                quickBtn.textContent = 'Generează automat din comandă';
                quickBtn.addEventListener('click', async () => {
                    const orderId = sel.value;
                    if (!orderId) {
                        showToast('Selectează o comandă.', true);
                        return;
                    }
                    try {
                        const data = await api('create_from_order', { order_id: orderId });
                        showToast(data?.message || 'Factură generată.');
                        closeModal();
                        await reload();
                    } catch (err) { showToast(err.message, true); }
                });
                wrap.appendChild(quickBtn);
            }
            fieldsEl.prepend(wrap);
            sel.addEventListener('change', function () {
                const opt = sel.options[sel.selectedIndex];
                if (!opt || !opt.value) return;
                const data = JSON.parse(opt.getAttribute('data-order') || '{}');
                const map = entity === 'invoices'
                    ? { client_name: 'client_name', phone: 'phone', email: 'email', order_number: 'order_number', total_amount: 'amount' }
                    : { client_name: 'client_name', phone: 'phone', email: 'email', address: 'address', order_number: 'order_number', total_amount: 'total_amount' };
                Object.entries(map).forEach(([from, to]) => {
                    const el = form.elements.namedItem(to);
                    if (el && data[from] !== undefined) el.value = data[from];
                });
                const oid = form.elements.namedItem('order_id');
                if (oid) oid.value = data.id || '';
            });
        }

        function openModal(row) {
            titleEl.textContent = row ? CFG.editTitle : CFG.createTitle;
            fieldsEl.innerHTML = buildFields(row);
            if ((entity === 'invoices' || entity === 'deliveries') && !row) injectOrderSelect();
            backdrop.classList.add('is-open');
        }

        function closeModal() {
            backdrop.classList.remove('is-open');
            form.reset();
        }

        async function reload() {
            [rows, stats] = await Promise.all([
                api('list', { q: document.getElementById('cmSearch')?.value || '' }),
                api('stats', {})
            ]);
            renderStats();
            renderCards();
        }

        document.getElementById('cmSearch')?.addEventListener('input', () => renderCards());
        document.querySelectorAll('[data-cm-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                statusFilter = btn.getAttribute('data-cm-filter') || '';
                document.querySelectorAll('[data-cm-filter]').forEach((b) => b.classList.toggle('active', b === btn));
                renderCards();
            });
        });
        document.getElementById('cmCreate')?.addEventListener('click', () => openModal(null));
        document.getElementById('cmModalClose')?.addEventListener('click', closeModal);
        document.getElementById('cmModalCancel')?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });

        document.getElementById('cmGrid')?.addEventListener('click', async (e) => {
            const waBtn = e.target.closest('.cm-inv-wa');
            if (waBtn) {
                try {
                    const data = await api('whatsapp', { id: waBtn.getAttribute('data-id') });
                    if (data?.url) window.open(data.url, '_blank', 'noopener');
                    showToast('WhatsApp deschis cu mesajul facturii.');
                    await reload();
                } catch (err) { showToast(err.message, true); }
                return;
            }
            const mailBtn = e.target.closest('.cm-inv-mail');
            if (mailBtn) {
                if (!confirm('Trimitem factura pe email către client?')) return;
                try {
                    const data = await api('send_email', { id: mailBtn.getAttribute('data-id') });
                    showToast(data?.message || 'Email trimis.');
                    await reload();
                } catch (err) { showToast(err.message, true); }
                return;
            }
            const editBtn = e.target.closest('.cm-edit');
            if (editBtn) {
                openModal(JSON.parse(editBtn.getAttribute('data-row') || '{}'));
                return;
            }
            const delBtn = e.target.closest('.cm-del');
            if (delBtn) {
                if (!confirm('Ștergi înregistrarea?')) return;
                try {
                    await api('delete', { id: delBtn.getAttribute('data-id') });
                    showToast('Șters.');
                    await reload();
                } catch (err) { showToast(err.message, true); }
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {};
            new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') payload[k] = v; });
            try {
                await api('save', payload);
                showToast('Salvat.');
                closeModal();
                await reload();
            } catch (err) { showToast(err.message, true); }
        });

        document.getElementById('cmExtraAction')?.addEventListener('click', async () => {
            if (!CFG.extraAction) return;
            try {
                const data = await api(CFG.extraAction, {});
                showToast('Importate: ' + (data.imported ?? 0));
                await reload();
            } catch (err) { showToast(err.message, true); }
        });

        document.getElementById('cmOrderSelect')?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            const data = JSON.parse(opt.getAttribute('data-order') || '{}');
            const map = { client_name: 'client_name', phone: 'phone', email: 'email', order_number: 'order_number', total_amount: 'amount', amount: 'amount' };
            Object.entries(map).forEach(([from, to]) => {
                const el = form.elements.namedItem(to === 'amount' ? (entity === 'invoices' ? 'amount' : 'total_amount') : to);
                if (el && data[from] !== undefined) el.value = data[from];
            });
            const oid = form.elements.namedItem('order_id');
            if (oid) oid.value = data.id || '';
        });

        reload().catch((err) => showToast(err.message, true));
    })();
    </script>
    <?php
}

function blu_render_orders_admin_panel(): void
{
    blu_commerce_shared_styles();
    blu_render_admin_section_nav('comenzi', 'orders');
    $orders = blu_orders_list();
    ?>
    <div class="cm-panel">
        <div class="card">
            <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-1">Toate comenzile</h4>
                    <p class="text-muted small mb-0">Comenzi din checkout magazin și adăugate manual.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cmExtraAction">Importă cereri clienți</button>
                    <button type="button" class="btn btn-primary btn-sm" id="cmCreate">Comandă nouă</button>
                </div>
            </div>
            <div class="card-body">
                <div class="cm-toolbar">
                    <input type="search" class="form-control" id="cmSearch" placeholder="Caută comandă, client, telefon…">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary active" data-cm-filter="">Toate</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="comandat">Comandat</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="procesare">Procesare</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="expediere">Expediere</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="livrat">Livrat</button>
                    </div>
                </div>
                <div class="cm-stats" id="cmStats"></div>
                <div class="cm-grid" id="cmGrid"></div>
                <?php if ($orders === []): ?>
                    <p class="text-muted small mt-3 mb-0">Nicio comandă încă. Checkout-ul din magazin creează automat comenzi.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    blu_commerce_shared_scripts('orders', [
        'createTitle' => 'Comandă nouă',
        'editTitle' => 'Editează comanda',
        'titleField' => 'order_number',
        'subField' => 'client_name',
        'previewField' => 'items_text',
        'amountField' => 'total_amount',
        'statusField' => 'status',
        'searchFields' => ['order_number', 'client_name', 'phone', 'email'],
        'statKeys' => ['all', 'comandat', 'procesare', 'expediere', 'livrat', 'total_amount'],
        'statLabels' => [
            'all' => 'Total',
            'comandat' => 'Comandate',
            'procesare' => 'Procesare',
            'expediere' => 'Expediere',
            'livrat' => 'Livrate',
            'total_amount' => 'Valoare',
        ],
        'badgeMap' => [
            'comandat' => 'bg-secondary',
            'procesare' => 'bg-warning text-dark',
            'expediere' => 'bg-info',
            'livrat' => 'bg-success',
            'anulat' => 'bg-danger',
        ],
        'statusLabels' => [
            'comandat' => 'Comandat',
            'procesare' => 'În procesare',
            'expediere' => 'Expediere',
            'livrat' => 'Livrat',
            'anulat' => 'Anulat',
        ],
        'extraAction' => 'import_leads',
        'fields' => [
            ['name' => 'order_number', 'label' => 'Nr. comandă', 'type' => 'text'],
            ['name' => 'client_name', 'label' => 'Client', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'address', 'label' => 'Adresă livrare', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'default' => 'comandat', 'options' => [
                ['value' => 'comandat', 'label' => 'Comandat'],
                ['value' => 'procesare', 'label' => 'În procesare'],
                ['value' => 'expediere', 'label' => 'Expediere'],
                ['value' => 'livrat', 'label' => 'Livrat'],
                ['value' => 'anulat', 'label' => 'Anulat'],
            ]],
            ['name' => 'payment_method', 'label' => 'Plată', 'type' => 'select', 'default' => 'ramburs', 'options' => [
                ['value' => 'ramburs', 'label' => 'Ramburs'],
                ['value' => 'card_online', 'label' => 'Card online'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'cash', 'label' => 'Cash'],
            ]],
            ['name' => 'total_amount', 'label' => 'Total (RON)', 'type' => 'number', 'step' => '0.01', 'default' => '0'],
            ['name' => 'items_text', 'label' => 'Produse', 'type' => 'textarea', 'rows' => 4],
            ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
        ],
    ]);
}

function blu_render_facturi_admin_panel(): void
{
    blu_commerce_shared_styles();
    blu_render_admin_section_nav('comenzi', 'facturi');
    $orders = blu_orders_list();
    ?>
    <div class="cm-panel">
        <div class="card">
            <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-1">Facturi</h4>
                    <p class="text-muted small mb-0">Generează factura din comandă, vizualizează, trimite pe WhatsApp sau email.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="cmCreate">Generează factură</button>
            </div>
            <div class="card-body">
                <div class="cm-toolbar">
                    <input type="search" class="form-control" id="cmSearch" placeholder="Caută factură, client, comandă…">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary active" data-cm-filter="">Toate</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="achitata">Achitate</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="neachitata">Neachitate</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="anulata">Anulate</button>
                    </div>
                </div>
                <div class="cm-stats" id="cmStats"></div>
                <div class="cm-grid" id="cmGrid"></div>
            </div>
        </div>
    </div>
    <?php
    $orderOptions = array_map(static fn(array $o): array => [
        'id' => $o['id'] ?? '',
        'order_number' => $o['order_number'] ?? '',
        'client_name' => $o['client_name'] ?? '',
        'phone' => $o['phone'] ?? '',
        'email' => $o['email'] ?? '',
        'total_amount' => $o['total_amount'] ?? 0,
    ], $orders);
    ?>
    <script>window.__cmOrders = <?= json_encode($orderOptions, JSON_UNESCAPED_UNICODE) ?>;</script>
    <?php
    blu_commerce_shared_scripts('invoices', [
        'invoiceActions' => true,
        'createTitle' => 'Factură nouă',
        'editTitle' => 'Editează factura',
        'titleField' => 'invoice_number',
        'subField' => 'client_name',
        'amountField' => 'amount',
        'statusField' => 'invoice_status',
        'searchFields' => ['invoice_number', 'order_number', 'client_name', 'phone'],
        'statKeys' => ['all', 'achitata', 'neachitata', 'anulata', 'total_amount'],
        'statLabels' => [
            'all' => 'Total',
            'achitata' => 'Achitate',
            'neachitata' => 'Neachitate',
            'anulata' => 'Anulate',
            'total_amount' => 'Facturat',
        ],
        'badgeMap' => [
            'achitata' => 'bg-success',
            'neachitata' => 'bg-warning text-dark',
            'anulata' => 'bg-danger',
            'storno' => 'bg-danger',
        ],
        'statusLabels' => [
            'neachitata' => 'Neachitată',
            'achitata' => 'Achitată',
            'anulata' => 'Anulată',
            'storno' => 'Storno',
        ],
        'fields' => [
            ['name' => 'invoice_title', 'label' => 'Titlu factură', 'type' => 'text', 'default' => 'Factură fiscală'],
            ['name' => 'invoice_number', 'label' => 'Nr. factură', 'type' => 'text'],
            ['name' => 'order_id', 'label' => 'ID comandă', 'type' => 'hidden'],
            ['name' => 'order_number', 'label' => 'Nr. comandă', 'type' => 'text'],
            ['name' => 'client_name', 'label' => 'Client', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'due_date', 'label' => 'Scadență', 'type' => 'date'],
            ['name' => 'payment_method', 'label' => 'Plată', 'type' => 'select', 'default' => 'ramburs', 'options' => [
                ['value' => 'ramburs', 'label' => 'Ramburs'],
                ['value' => 'card_online', 'label' => 'Card online'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'cash', 'label' => 'Cash'],
            ]],
            ['name' => 'invoice_status', 'label' => 'Status', 'type' => 'select', 'default' => 'neachitata', 'options' => [
                ['value' => 'neachitata', 'label' => 'Neachitată'],
                ['value' => 'achitata', 'label' => 'Achitată'],
                ['value' => 'anulata', 'label' => 'Anulată'],
                ['value' => 'storno', 'label' => 'Storno'],
            ]],
            ['name' => 'amount', 'label' => 'Sumă (RON)', 'type' => 'number', 'step' => '0.01', 'default' => '0'],
            ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
        ],
    ]);
}

function blu_render_livrare_admin_panel(): void
{
    blu_commerce_shared_styles();
    blu_render_admin_section_nav('comenzi', 'livrare');
    ?>
    <div class="cm-panel">
        <div class="card">
            <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-1">Livrare / AWB</h4>
                    <p class="text-muted small mb-0">AWB curier, status livrare — Fan Courier, Cargus, Sameday etc.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="cmCreate">AWB nou</button>
            </div>
            <div class="card-body">
                <div class="cm-toolbar">
                    <input type="search" class="form-control" id="cmSearch" placeholder="Caută AWB, client, comandă…">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary active" data-cm-filter="">Toate</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="pregatire">Pregătire</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="awb_generat">AWB generat</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="in_tranzit">În tranzit</button>
                        <button type="button" class="btn btn-outline-secondary" data-cm-filter="livrat">Livrat</button>
                    </div>
                </div>
                <div class="cm-stats" id="cmStats"></div>
                <div class="cm-grid" id="cmGrid"></div>
            </div>
        </div>
    </div>
    <?php
    $orders = blu_orders_list();
    $orderOptions = array_map(static fn(array $o): array => [
        'id' => $o['id'] ?? '',
        'order_number' => $o['order_number'] ?? '',
        'client_name' => $o['client_name'] ?? '',
        'phone' => $o['phone'] ?? '',
        'email' => $o['email'] ?? '',
        'address' => $o['address'] ?? '',
        'total_amount' => $o['total_amount'] ?? 0,
    ], $orders);
    ?>
    <script>window.__cmOrders = <?= json_encode($orderOptions, JSON_UNESCAPED_UNICODE) ?>;</script>
    <?php
    blu_commerce_shared_scripts('deliveries', [
        'createTitle' => 'AWB nou',
        'editTitle' => 'Editează livrarea',
        'titleField' => 'awb',
        'titleFallback' => 'order_number',
        'subField' => 'client_name',
        'amountField' => 'total_amount',
        'statusField' => 'delivery_status',
        'searchFields' => ['awb', 'order_number', 'client_name', 'phone', 'courier'],
        'statKeys' => ['all', 'pregatire', 'awb_generat', 'in_tranzit', 'livrat'],
        'statLabels' => [
            'all' => 'Total',
            'pregatire' => 'Pregătire',
            'awb_generat' => 'AWB generat',
            'in_tranzit' => 'În tranzit',
            'livrat' => 'Livrat',
        ],
        'badgeMap' => [
            'pregatire' => 'bg-warning text-dark',
            'awb_generat' => 'bg-info',
            'in_tranzit' => 'bg-primary',
            'livrat' => 'bg-success',
            'retur' => 'bg-danger',
            'anulat' => 'bg-danger',
        ],
        'statusLabels' => [
            'pregatire' => 'Pregătire colet',
            'awb_generat' => 'AWB generat',
            'in_tranzit' => 'În trânzit',
            'livrat' => 'Livrat',
            'retur' => 'Retur',
            'anulat' => 'Anulat',
        ],
        'fields' => [
            ['name' => 'delivery_title', 'label' => 'Titlu', 'type' => 'text', 'default' => 'Livrare curier'],
            ['name' => 'awb', 'label' => 'Număr AWB', 'type' => 'text'],
            ['name' => 'order_id', 'label' => 'ID comandă', 'type' => 'hidden'],
            ['name' => 'order_number', 'label' => 'Nr. comandă', 'type' => 'text'],
            ['name' => 'client_name', 'label' => 'Client', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'address', 'label' => 'Adresă livrare', 'type' => 'textarea'],
            ['name' => 'courier', 'label' => 'Curier', 'type' => 'select', 'default' => 'Fan Courier', 'options' => [
                ['value' => 'Fan Courier', 'label' => 'Fan Courier'],
                ['value' => 'Cargus', 'label' => 'Cargus'],
                ['value' => 'Sameday', 'label' => 'Sameday'],
                ['value' => 'DPD', 'label' => 'DPD'],
                ['value' => 'GLS', 'label' => 'GLS'],
                ['value' => 'Ridicare magazin', 'label' => 'Ridicare magazin'],
            ]],
            ['name' => 'delivery_status', 'label' => 'Status livrare', 'type' => 'select', 'default' => 'pregatire', 'options' => [
                ['value' => 'pregatire', 'label' => 'Pregătire colet'],
                ['value' => 'awb_generat', 'label' => 'AWB generat'],
                ['value' => 'in_tranzit', 'label' => 'În trânzit'],
                ['value' => 'livrat', 'label' => 'Livrat'],
                ['value' => 'retur', 'label' => 'Retur'],
                ['value' => 'anulat', 'label' => 'Anulat'],
            ]],
            ['name' => 'total_amount', 'label' => 'Valoare colet (RON)', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'delivery_date', 'label' => 'Data livrare', 'type' => 'date'],
            ['name' => 'delivery_time', 'label' => 'Interval orar', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
        ],
    ]);
}

function blu_render_website_admin_panel(): void
{
    require_once __DIR__ . '/website_cms.php';
    $slug = trim((string) ($_GET['slug'] ?? ''));
    $mode = trim((string) ($_GET['mode'] ?? ''));
    if ($slug !== '' && $mode === 'live') {
        blu_render_website_live_editor($slug);
        return;
    }

    blu_render_admin_section_nav('website', 'website');
    $pages = blu_website_cms_pages_registry();
    $webBase = function_exists('blu_admin_web_base') ? rtrim(blu_admin_web_base(), '/') : '..';
    ?>
    <div class="card">
        <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">Pagini site</h4>
                <p class="text-muted small mb-0">Constructor site: editează text live + adaugă blocuri (mesaj, imagine, buton, coloane).</p>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($pages as $pageSlug => $meta): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary p-2 rounded-circle"><i class="fa-solid <?= htmlspecialchars((string) ($meta['icon'] ?? 'fa-file'), ENT_QUOTES) ?>"></i></span>
                                <div>
                                    <strong><?= htmlspecialchars((string) ($meta['label'] ?? $pageSlug), ENT_QUOTES) ?></strong>
                                    <div class="text-muted small"><?= htmlspecialchars((string) ($meta['file'] ?? ''), ENT_QUOTES) ?></div>
                                </div>
                            </div>
                            <div class="mt-auto d-flex flex-wrap gap-2 pt-2">
                                <a class="btn btn-primary btn-sm" href="?page=website&amp;slug=<?= rawurlencode($pageSlug) ?>&amp;mode=live">
                                    <i class="fa-solid fa-pen-to-square"></i> Editează live
                                </a>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($webBase . '/' . ($meta['file'] ?? ''), ENT_QUOTES) ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Vezi site
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function blu_render_website_live_editor(string $slug): void
{
    require_once __DIR__ . '/website_cms.php';
    $pages = blu_website_cms_pages_registry();
    if (!isset($pages[$slug])) {
        echo '<div class="alert alert-danger">Pagina nu există.</div>';
        return;
    }
    $meta = $pages[$slug];
    $webBase = function_exists('blu_admin_web_base') ? rtrim(blu_admin_web_base(), '/') : '..';
    $frameSrc = $webBase . '/' . ($meta['file'] ?? 'index.php') . '?blu_cms_edit=1&amp;blu_cms_page=' . rawurlencode($slug);
    blu_render_admin_section_nav('website', 'website');
    ?>
    <style>
        .ws-live-wrap { display: flex; flex-direction: column; gap: .75rem; min-height: calc(100vh - 200px); }
        .ws-live-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .75rem 1rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
        .ws-live-frame-wrap { flex: 1; min-height: 520px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #f8fafc; }
        .ws-live-frame { width: 100%; height: 100%; min-height: 520px; border: 0; display: block; background: #fff; }
        .ws-live-status { margin-left: auto; font-size: .85rem; color: #64748b; }
        .ws-live-status.is-ok { color: #16a34a; }
    </style>
    <div class="ws-live-wrap">
        <div class="ws-live-toolbar">
            <a class="btn btn-outline-secondary btn-sm" href="?page=website"><i class="fa-solid fa-arrow-left"></i> Înapoi la pagini</a>
            <strong><?= htmlspecialchars((string) ($meta['label'] ?? $slug), ENT_QUOTES) ?></strong>
            <select class="form-select form-select-sm" id="wsPageSelect" style="max-width:220px">
                <?php foreach ($pages as $pageSlug => $pageMeta): ?>
                    <option value="<?= htmlspecialchars($pageSlug, ENT_QUOTES) ?>"<?= $pageSlug === $slug ? ' selected' : '' ?>><?= htmlspecialchars((string) ($pageMeta['label'] ?? $pageSlug), ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-primary btn-sm" id="wsSaveTrigger"><i class="fa-solid fa-floppy-disk"></i> Salvează</button>
            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($frameSrc, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> Deschide în tab nou</a>
            <span class="ws-live-status" id="wsLiveStatus">Constructor: panou dreapta pentru blocuri noi · Ctrl+S salvează</span>
        </div>
        <div class="ws-live-frame-wrap">
            <iframe id="wsLiveFrame" class="ws-live-frame" title="Editor live <?= htmlspecialchars($slug, ENT_QUOTES) ?>" src="<?= htmlspecialchars($frameSrc, ENT_QUOTES) ?>"></iframe>
        </div>
    </div>
    <script>
    (function () {
        'use strict';
        const frame = document.getElementById('wsLiveFrame');
        const statusEl = document.getElementById('wsLiveStatus');
        const selectEl = document.getElementById('wsPageSelect');
        const saveBtn = document.getElementById('wsSaveTrigger');
        const webBase = <?= json_encode($webBase, JSON_UNESCAPED_UNICODE) ?>;

        selectEl?.addEventListener('change', function () {
            window.location.href = '?page=website&slug=' + encodeURIComponent(selectEl.value) + '&mode=live';
        });

        saveBtn?.addEventListener('click', function () {
            try {
                frame?.contentWindow?.document.getElementById('bluCmsSave')?.click();
            } catch (e) {
                if (statusEl) {
                    statusEl.textContent = 'Salvează din bara de sus a paginii.';
                    statusEl.className = 'ws-live-status';
                }
            }
        });

        window.addEventListener('message', function (e) {
            if (!e.data || e.data.type !== 'bluCmsSaved') return;
            if (statusEl) {
                statusEl.textContent = 'Conținut salvat.';
                statusEl.className = 'ws-live-status is-ok';
            }
        });
    })();
    </script>
    <?php
}

function blu_render_blog_admin_panel(): void
{
    blu_render_admin_section_nav('website', 'blog');
    ?>
    <div class="card">
        <div class="card-header card-no-border pb-0"><h4>Blog</h4></div>
        <div class="card-body">
            <p class="text-muted mb-3">Modul blog (ca Besoiu) — pregătit pentru articole. Adaugă fișiere în <code>data/blog.json</code> când activezi conținutul.</p>
            <div class="alert alert-light border">Nu există articole publicate. Poți adăuga manual în <code>data/blog.json</code> sau extinde modulul ulterior.</div>
        </div>
    </div>
    <?php
}

function blu_render_alerts_admin_panel(): void
{
    blu_render_admin_section_nav('sistem', 'alerts');
    require_once __DIR__ . '/robot_feed.php';
    $feed = blu_robot_get_feed(30);
    ?>
    <div class="card">
        <div class="card-header card-no-border pb-0"><h4>Alerte sistem</h4></div>
        <div class="card-body">
            <p class="text-muted small">Evenimente recente robot / import (echivalent Alerte Besoiu).</p>
            <ul class="list-group list-group-flush">
                <?php foreach ($feed as $item): ?>
                    <li class="list-group-item px-0">
                        <span class="badge bg-light text-dark me-2"><?= htmlspecialchars((string)($item['time'] ?? ''), ENT_QUOTES) ?></span>
                        <?= htmlspecialchars((string)($item['message'] ?? $item['text'] ?? '-'), ENT_QUOTES) ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($feed === []): ?>
                    <li class="list-group-item px-0 text-muted">Nicio alertă recentă.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
}

function blu_render_backup_admin_panel(string $csrf): void
{
    blu_render_admin_section_nav('sistem', 'backup');
    $webBase = function_exists('blu_admin_web_base') ? rtrim(blu_admin_web_base(), '/') : '..';
    $files = [
        'Produse' => $webBase . '/data/products.json',
        'Comenzi' => $webBase . '/data/orders.json',
        'Facturi' => $webBase . '/data/invoices.json',
        'Livrări AWB' => $webBase . '/data/deliveries.json',
        'Furnizori GBG' => $webBase . '/data/gbg_suppliers.json',
        'Leads' => $webBase . '/leads.json',
    ];
    $disk = [
        'Produse' => blu_products_json_file(),
        'Comenzi' => blu_orders_file(),
        'Facturi' => blu_invoices_file(),
        'Livrări AWB' => blu_deliveries_file(),
        'Furnizori GBG' => blu_data_dir() . DIRECTORY_SEPARATOR . 'gbg_suppliers.json',
        'Leads' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'leads.json',
    ];
    ?>
    <div class="card">
        <div class="card-header card-no-border pb-0"><h4>Backup date</h4></div>
        <div class="card-body">
            <p class="text-muted">Export JSON pentru backup (ca secțiunea Backup Besoiu).</p>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Dataset</th><th>Fișier</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($files as $label => $href): ?>
                        <tr>
                            <td><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
                            <td><code><?= htmlspecialchars($href, ENT_QUOTES) ?></code></td>
                            <td>
                                <?php if (is_file($disk[$label] ?? '')): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" download>Descarcă</a>
                                <?php else: ?>
                                    <span class="text-muted small">Lipsă</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="mt-3">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="commerce_export_zip">
                <button type="submit" class="btn btn-primary">Export arhivă JSON (toate)</button>
            </form>
        </div>
    </div>
    <?php
}

function blu_commerce_export_zip(string $projectRoot): array
{
    $dataDir = blu_data_dir();
    $names = [
        'orders.json' => blu_orders_file(),
        'invoices.json' => blu_invoices_file(),
        'deliveries.json' => blu_deliveries_file(),
        'products.json' => blu_products_json_file(),
        'leads.json' => $projectRoot . DIRECTORY_SEPARATOR . 'leads.json',
    ];
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'Extensia ZipArchive nu este disponibilă.'];
    }
    $zipPath = $dataDir . DIRECTORY_SEPARATOR . 'backup-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return ['ok' => false, 'message' => 'Nu am putut crea arhiva.'];
    }
    foreach ($names as $name => $path) {
        if (is_file($path)) {
            $zip->addFile($path, $name);
        }
    }
    $zip->close();
    return ['ok' => true, 'path' => $zipPath, 'message' => 'Backup creat: ' . basename($zipPath)];
}
