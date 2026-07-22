<?php

declare(strict_types=1);



/**

 * Furnizori — grid de cartele + popup modal (edit / șterge / progres scanare).

 */

function blu_render_furnizori_suppliers_list(array $suppliers, string $selectedCont = ''): void

{

    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';

    $stats = blu_read_json_file(blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_stats.json', []);

    $globalTotal = is_array($stats) ? (int)($stats['total'] ?? 0) : 0;

    $globalFound = is_array($stats) ? (int)($stats['found'] ?? 0) : 0;

    ?>

    <style>

        .fz-cards { --fz: #308e87; --fz-bg: #f0fdfa; }

        .fz-cards-head { margin-bottom: 1.25rem; }

        .fz-cards-grid {

            display: grid;

            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));

            gap: 1rem;

        }

        .fz-card {

            border: 1px solid #e2e8f0;

            border-radius: 14px;

            background: #fff;

            padding: 1.1rem 1.15rem;

            cursor: pointer;

            transition: box-shadow .2s, border-color .2s, transform .15s;

            height: 100%;

            display: flex;

            flex-direction: column;

        }

        .fz-card:hover {

            border-color: rgba(48, 142, 135, .45);

            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);

            transform: translateY(-2px);

        }

        .fz-card__top {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: .5rem;

            margin-bottom: .65rem;

        }

        .fz-card__title {

            font-weight: 700;

            font-size: 1rem;

            line-height: 1.3;

            color: #0f172a;

            margin: 0;

        }

        .fz-card__badge {

            font-size: .68rem;

            font-weight: 700;

            padding: .2rem .55rem;

            border-radius: 999px;

            background: var(--fz-bg);

            color: var(--fz);

            white-space: nowrap;

        }

        .fz-card__meta {

            font-size: .82rem;

            color: #64748b;

            margin-bottom: .35rem;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

        .fz-card__meta code { font-size: .78rem; color: #475569; }

        .fz-card__progress {

            margin-top: auto;

            padding-top: .85rem;

            border-top: 1px dashed #e2e8f0;

        }

        .fz-card__stat {

            display: flex;

            align-items: center;

            justify-content: space-between;

            font-size: .8rem;

        }

        .fz-card__stat strong { color: var(--fz); font-size: 1.05rem; }

        .fz-card__stat-label { color: #64748b; }

        .fz-card__status {

            margin-top: .45rem;

            font-size: .75rem;

            color: #94a3b8;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }

        .fz-card--warn { border-color: #fcd34d; }

        .fz-card--warn .fz-card__badge { background: #fef3c7; color: #b45309; }

        .fz-modal-progress {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 12px;

            padding: 1rem;

        }

        .fz-modal-progress dl {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: .65rem 1rem;

            margin: 0;

        }

        .fz-modal-progress dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin: 0; }

        .fz-modal-progress dd { font-size: .92rem; font-weight: 600; margin: 0; color: #0f172a; }

        .fz-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
            border: 2px dashed #e2e8f0;
            border-radius: 14px;
        }
        #fzSupplierModal.show { display: block !important; }
        body.blu-admin-neon #fzSupplierModal {
            position: fixed;
            inset: 0;
            z-index: 10060 !important;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 0 1rem;
        }
        body.blu-admin-neon #fzSupplierModal .modal-dialog {
            margin: 1.75rem auto;
            max-width: 820px;
            position: relative;
            z-index: 2;
            pointer-events: auto;
        }
        body.blu-admin-neon #fzSupplierModal .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 28px 64px rgba(15, 23, 42, 0.28);
        }
        body.blu-admin-neon .modal-backdrop.show,
        body.blu-admin-neon .fz-modal-backdrop {
            z-index: 10050 !important;
            background: rgba(15, 23, 42, 0.38) !important;
        }
        .fz-modal-backdrop {
            position: fixed;
            inset: 0;
        }
    </style>



    <div class="fz-cards">

        <div class="fz-cards-head d-flex flex-wrap justify-content-between align-items-start gap-3">

            <div>

                <h4 class="mb-1">Furnizori</h4>

                <p class="text-muted small mb-0">

                    Apasă pe o cartelă pentru detalii, editare și progres scanare.

                    <?php if ($globalTotal > 0): ?>

                        · Total procesate: <strong><?= (int)$globalFound ?></strong> / <?= (int)$globalTotal ?>

                    <?php endif; ?>

                </p>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <button type="button" class="btn btn-sm btn-outline-primary" id="fzImportCatalog">

                    <i class="fa-solid fa-file-import"></i> Importă catalog

                </button>

                <button type="button" class="btn btn-sm btn-primary" id="fzNewSupplier">

                    <i class="fa-solid fa-plus"></i> Furnizor nou

                </button>

            </div>

        </div>



        <div class="fz-cards-grid" id="fzCardsGrid">

            <?php if (!$suppliers): ?>

                <div class="fz-empty">

                    <p class="mb-2">Niciun furnizor configurat.</p>

                    <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('fzNewSupplier')?.click()">+ Adaugă primul furnizor</button>

                </div>

            <?php endif; ?>

            <?php foreach ($suppliers as $s): ?>

                <?php

                $cid = (string)($s['cont_id'] ?? '');

                $scan = is_array($s['scan'] ?? null) ? $s['scan'] : blu_supplier_scan_progress($cid);

                $scanned = (int)($scan['scanned_count'] ?? 0);

                $passOk = !empty($s['has_password']);

                $host = parse_url((string)($s['site_url'] ?? ''), PHP_URL_HOST) ?: (string)($s['site_url'] ?? '');

                $statusLabel = match ((string)($scan['last_status'] ?? '')) {

                    'imported' => 'Ultimul: importat OK',

                    'processing' => 'În scanare…',

                    'empty' => 'Ultimul: fără rezultat',

                    'error' => 'Ultimul: eroare',

                    'no_oem' => 'Ultimul: fără OEM',

                    default => $scanned > 0 ? 'Scanare activă' : 'Neînceput',

                };

                ?>

                <article class="fz-card<?= $passOk ? '' : ' fz-card--warn' ?>"

                         role="button"

                         tabindex="0"

                         data-id="<?= htmlspecialchars($s['id'], ENT_QUOTES) ?>"

                         data-cont-id="<?= htmlspecialchars($cid, ENT_QUOTES) ?>"

                         data-name="<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>"

                         data-site="<?= htmlspecialchars($s['site_url'] ?? '', ENT_QUOTES) ?>"

                         data-user="<?= htmlspecialchars($s['user'], ENT_QUOTES) ?>"

                         data-pass="<?= htmlspecialchars($s['pass'] ?? '', ENT_QUOTES) ?>"

                         data-scanned="<?= $scanned ?>"

                         data-scan-updated="<?= htmlspecialchars((string)($scan['updated_at'] ?? ''), ENT_QUOTES) ?>"

                         data-last-status="<?= htmlspecialchars((string)($scan['last_status'] ?? ''), ENT_QUOTES) ?>"

                         data-last-message="<?= htmlspecialchars((string)($scan['last_message'] ?? ''), ENT_QUOTES) ?>"

                         data-last-at="<?= htmlspecialchars((string)($scan['last_at'] ?? ''), ENT_QUOTES) ?>">

                    <div class="fz-card__top">

                        <h3 class="fz-card__title"><?= htmlspecialchars($s['label'], ENT_QUOTES) ?></h3>

                        <span class="fz-card__badge"><?= htmlspecialchars($cid, ENT_QUOTES) ?></span>

                    </div>

                    <div class="fz-card__meta" title="<?= htmlspecialchars($s['site_url'] ?? '', ENT_QUOTES) ?>">

                        <i class="fa-solid fa-globe fa-fw"></i> <?= htmlspecialchars($host, ENT_QUOTES) ?>

                    </div>

                    <div class="fz-card__meta">

                        <i class="fa-solid fa-user fa-fw"></i> <code><?= htmlspecialchars($s['user'], ENT_QUOTES) ?></code>

                        <?php if (!$passOk): ?>

                            <span class="text-warning ms-1">· parolă lipsă</span>

                        <?php endif; ?>

                    </div>

                    <div class="fz-card__progress">

                        <div class="fz-card__stat">

                            <span class="fz-card__stat-label">Produse scanate</span>

                            <strong><?= $scanned ?></strong>

                        </div>

                        <div class="fz-card__status"><?= htmlspecialchars($statusLabel, ENT_QUOTES) ?></div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </div>



    <div class="modal fade" id="fzSupplierModal" tabindex="-1" aria-labelledby="fzSupplierModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title" id="fzSupplierModalLabel">Furnizor</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>

                </div>

                <div class="modal-body">

                    <form id="fzSupplierForm">

                        <input type="hidden" id="fzRid" value="">

                        <input type="hidden" id="fzMode" value="edit">



                        <div class="fz-modal-progress mb-4" id="fzProgressBox">

                            <h6 class="mb-3"><i class="fa-solid fa-chart-line"></i> Progres scanare</h6>

                            <dl>

                                <div><dt>Produse scanate</dt><dd id="fzProgScanned">0</dd></div>

                                <div><dt>URL-uri unice</dt><dd id="fzProgUrls">0</dd></div>

                                <div><dt>Ultima actualizare</dt><dd id="fzProgUpdated">—</dd></div>

                                <div><dt>Ultimul status</dt><dd id="fzProgStatus">—</dd></div>

                            </dl>

                            <p class="small text-muted mb-2 mt-2" id="fzProgMessage"></p>

                            <div class="d-flex flex-wrap gap-2">

                                <a class="btn btn-sm btn-success" id="fzGoScan" href="#"><i class="fa-solid fa-play"></i> Monitor scanare</a>

                                <a class="btn btn-sm btn-outline-secondary" id="fzGoRobot" href="#"><i class="fa-solid fa-robot"></i> Logică robot</a>

                            </div>

                        </div>



                        <div class="row g-3">

                            <div class="col-md-6">

                                <label class="form-label fw-semibold">Denumire furnizor</label>

                                <input type="text" class="form-control" id="fzName" required>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label fw-semibold">cont_id robot</label>

                                <input type="text" class="form-control" id="fzContId" required pattern="[a-zA-Z0-9_-]+">

                                <div class="form-text">Identificator unic — nu se schimbă după creare.</div>

                            </div>

                            <div class="col-12">

                                <label class="form-label fw-semibold">Site web (URL login)</label>

                                <input type="url" class="form-control" id="fzSiteUrl" required>

                            </div>

                            <div class="col-md-6">

                                <label class="form-label fw-semibold">Login</label>

                                <input type="text" class="form-control" id="fzUser" required autocomplete="off">

                            </div>

                            <div class="col-md-6">

                                <label class="form-label fw-semibold">Parolă</label>

                                <input type="text" class="form-control" id="fzPass" autocomplete="off" placeholder="Lasă gol dacă nu se schimbă">

                            </div>

                        </div>

                    </form>

                </div>

                <div class="modal-footer justify-content-between">

                    <button type="button" class="btn btn-outline-danger" id="fzDeleteBtn" hidden>

                        <i class="fa-solid fa-trash"></i> Șterge

                    </button>

                    <div class="d-flex gap-2 ms-auto">

                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anulează</button>

                        <button type="submit" form="fzSupplierForm" class="btn btn-primary">

                            <i class="fa-solid fa-floppy-disk"></i> Salvează

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <script>
    (function () {
        const API = <?= json_encode($wb . 'api/gbg_suppliers.php', JSON_UNESCAPED_SLASHES) ?>;
        let modalEl = null;
        let bsModal = null;
        let backdropEl = null;

        function statusLabel(code) {
            const map = {
                imported: 'Importat OK',
                processing: 'În scanare',
                empty: 'Fără rezultat',
                error: 'Eroare',
                no_oem: 'Fără OEM',
            };
            return map[code] || (code || '—');
        }

        function mountModalOnBody() {
            modalEl = document.getElementById('fzSupplierModal');
            if (!modalEl) return;
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            bsModal = null;
        }

        function getBsModal() {
            mountModalOnBody();
            if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return null;
            }
            if (!bsModal) {
                bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true });
            }
            return bsModal;
        }

        function ensureBackdrop() {
            if (!backdropEl) {
                backdropEl = document.createElement('div');
                backdropEl.className = 'modal-backdrop fade show fz-modal-backdrop';
                backdropEl.addEventListener('click', hideModal);
            }
            return backdropEl;
        }

        function showModalUi() {
            if (!modalEl) return;
            const bs = getBsModal();
            if (bs) {
                bs.show();
                return;
            }
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            if (!document.body.contains(ensureBackdrop())) {
                document.body.appendChild(backdropEl);
            }
        }

        function hideModal() {
            const bs = getBsModal();
            if (bs) {
                bs.hide();
                return;
            }
            if (!modalEl) return;
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            backdropEl?.remove();
        }

        function fillProgress(card) {
            if (!card) return;
            const scanned = card.dataset.scanned || '0';
            document.getElementById('fzProgScanned').textContent = scanned;
            document.getElementById('fzProgUrls').textContent = scanned;
            document.getElementById('fzProgUpdated').textContent = card.dataset.scanUpdated || '—';
            document.getElementById('fzProgStatus').textContent = statusLabel(card.dataset.lastStatus || '');
            const msg = card.dataset.lastMessage || '';
            const at = card.dataset.lastAt || '';
            document.getElementById('fzProgMessage').textContent = msg
                ? (at ? ('[' + at + '] ' + msg) : msg)
                : (parseInt(scanned, 10) > 0 ? 'Produse marcate în lista „deja scanate”.' : 'Nicio scanare înregistrată încă.');
            const contId = encodeURIComponent(card.dataset.contId || '');
            document.getElementById('fzGoScan').href = '?page=robot-monitor&cont_id=' + contId;
            document.getElementById('fzGoRobot').href = '?page=furnizori&tab=robot&cont_id=' + contId;
        }

        function openModal(mode, card) {
            const isNew = mode === 'new';
            document.getElementById('fzMode').value = mode;
            document.getElementById('fzSupplierModalLabel').textContent = isNew
                ? 'Furnizor nou'
                : ('Furnizor: ' + (card?.dataset?.name || ''));
            document.getElementById('fzRid').value = isNew ? '' : (card?.dataset?.id || '');
            document.getElementById('fzName').value = isNew ? '' : (card?.dataset?.name || '');
            document.getElementById('fzSiteUrl').value = isNew ? '' : (card?.dataset?.site || '');
            document.getElementById('fzContId').value = isNew ? '' : (card?.dataset?.contId || '');
            document.getElementById('fzContId').readOnly = !isNew;
            document.getElementById('fzUser').value = isNew ? '' : (card?.dataset?.user || '');
            document.getElementById('fzPass').value = isNew ? '' : (card?.dataset?.pass || '');
            document.getElementById('fzDeleteBtn').hidden = isNew;
            document.getElementById('fzProgressBox').hidden = isNew;
            if (!isNew) fillProgress(card);
            showModalUi();
        }

        function bindEvents() {
            mountModalOnBody();
            const form = document.getElementById('fzSupplierForm');

            document.querySelectorAll('.fz-card[data-cont-id]').forEach(card => {
                card.addEventListener('click', () => openModal('edit', card));
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openModal('edit', card);
                    }
                });
            });

            document.getElementById('fzNewSupplier')?.addEventListener('click', () => openModal('new', null));

            modalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    hideModal();
                });
            });

            document.getElementById('fzImportCatalog')?.addEventListener('click', async () => {
                if (!confirm('Actualizează lista de furnizori din catalog?')) return;
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'import_catalog' }),
                });
                const j = await res.json();
                alert(j.message || '');
                if (j.success) location.reload();
            });

            form?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        supplier_name: document.getElementById('fzName').value.trim(),
                        site_url: document.getElementById('fzSiteUrl').value.trim(),
                        cont_id: document.getElementById('fzContId').value.trim(),
                        user: document.getElementById('fzUser').value.trim(),
                        pas: document.getElementById('fzPass').value,
                        ridusers: document.getElementById('fzRid').value.trim(),
                    }),
                });
                const j = await res.json();
                alert(j.message || '');
                if (j.success) location.reload();
            });

            document.getElementById('fzDeleteBtn')?.addEventListener('click', async () => {
                const rid = document.getElementById('fzRid').value;
                const name = document.getElementById('fzName').value;
                if (!rid || !confirm('Ștergi furnizorul «' + name + '»?\n\nAcțiunea nu poate fi anulată.')) return;
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', randomn_id: rid }),
                });
                const j = await res.json();
                if (j.success) location.reload();
                else alert(j.message || 'Eroare');
            });

            <?php if ($selectedCont !== ''): ?>
            const pre = document.querySelector('.fz-card[data-cont-id="<?= htmlspecialchars($selectedCont, ENT_QUOTES) ?>"]');
            if (pre) openModal('edit', pre);
            <?php endif; ?>
        }

        if (document.readyState === 'complete') {
            bindEvents();
        } else {
            window.addEventListener('load', bindEvents);
        }
    })();
    </script>

    <?php

}

