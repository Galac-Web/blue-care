<?php
declare(strict_types=1);

/**
 * Gestiune furnizori web: site, login, parolă, cont_id robot.
 */
function blu_render_gbg_suppliers_manager(array $suppliers, bool $compact = false): void
{
    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    ?>
    <style>
        .gbg-mgr { --gbg-teal: #308e87; }
        .gbg-mgr .gbg-supplier-card {
            border: 1px solid #dbe3ea; border-radius: 14px; padding: .85rem; cursor: pointer;
            transition: .15s ease; background: #fafbfc; height: 100%; position: relative;
        }
        .gbg-mgr .gbg-supplier-card:hover { border-color: var(--gbg-teal); }
        .gbg-mgr .gbg-supplier-card.is-active {
            border-color: var(--gbg-teal); background: rgba(48,142,135,.08);
            box-shadow: inset 0 0 0 1px var(--gbg-teal);
        }
        .gbg-mgr .gbg-cont { font-family: monospace; font-size: .72rem; color: #64748b; word-break: break-all; }
        .gbg-mgr .gbg-table td { font-size: .82rem; vertical-align: middle; }
        .gbg-mgr .gbg-pass-empty { color: #b45309; font-style: italic; }
    </style>
    <div class="gbg-mgr">
        <?php if (!$compact): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <p class="text-muted small mb-0">
                    Fiecare furnizor: <strong>site web</strong>, <strong>login</strong>, <strong>parolă</strong>, <code>cont_id</code> pentru robot.
                    <a href="?page=furnizori&amp;tab=pasi">Pași robot</a> · <a href="?page=robot-monitor">Monitor Robot</a>
                </p>
                <button type="button" class="btn btn-sm btn-outline-primary" id="gbgImportCatalog">
                    Importă / actualizează lista furnizori
                </button>
            </div>
        <?php endif; ?>

        <form id="gbgSupplierForm">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Denumire furnizor</label>
                    <input type="text" class="form-control form-control-sm" id="gbgName" placeholder="Ex: InterCars">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Site web (URL login / magazin)</label>
                    <input type="url" class="form-control form-control-sm" id="gbgSiteUrl" placeholder="https://eshop.exemplu.ro">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">cont_id robot</label>
                    <input type="text" class="form-control form-control-sm" id="gbgContId" placeholder="furnizor_autonet">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Login (user / email)</label>
                    <input type="text" class="form-control form-control-sm" id="gbgUser" placeholder="user sau email" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Parolă</label>
                    <input type="text" class="form-control form-control-sm" id="gbgPass" placeholder="(gol dacă necunoscută)" autocomplete="off">
                </div>
                <div class="col-md-9 d-flex align-items-end gap-2 flex-wrap">
                    <input type="hidden" id="gbgRid" value="">
                    <button type="submit" class="btn btn-sm btn-primary" id="gbgSaveBtn">Salvează furnizor</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="gbgResetForm()">Nou</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="gbgDeleteBtn" style="display:none;" onclick="gbgDeleteSupplier()">Șterge</button>
                </div>
            </div>
        </form>

        <?php if (!$compact && $suppliers): ?>
            <div class="table-responsive mt-4">
                <table class="table table-sm table-hover gbg-table mb-2">
                    <thead class="table-light">
                        <tr>
                            <th>Furnizor</th>
                            <th>Site web</th>
                            <th>Login</th>
                            <th>Parolă</th>
                            <th>cont_id</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['label'], ENT_QUOTES) ?></strong></td>
                                <td>
                                    <?php if (!empty($s['site_url'])): ?>
                                        <a href="<?= htmlspecialchars($s['site_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="small"><?= htmlspecialchars($s['site_url'], ENT_QUOTES) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($s['user'], ENT_QUOTES) ?></code></td>
                                <td>
                                    <?php if (($s['pass'] ?? '') !== ''): ?>
                                        <code><?= htmlspecialchars($s['pass'], ENT_QUOTES) ?></code>
                                    <?php else: ?>
                                        <span class="gbg-pass-empty">de completat</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($s['cont_id'], ENT_QUOTES) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="row g-2 mt-2" id="gbgSupplierCards">
            <?php if (!$suppliers): ?>
                <div class="col-12"><div class="text-muted small">Niciun furnizor. Apasă «Importă lista» sau adaugă manual.</div></div>
            <?php endif; ?>
            <?php foreach ($suppliers as $s): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="gbg-supplier-card"
                         data-id="<?= htmlspecialchars($s['id'], ENT_QUOTES) ?>"
                         data-name="<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>"
                         data-site="<?= htmlspecialchars($s['site_url'] ?? '', ENT_QUOTES) ?>"
                         data-cont-id="<?= htmlspecialchars($s['cont_id'], ENT_QUOTES) ?>"
                         data-user="<?= htmlspecialchars($s['user'], ENT_QUOTES) ?>"
                         data-pass="<?= htmlspecialchars($s['pass'], ENT_QUOTES) ?>"
                         onclick="gbgSelectSupplier(this)">
                        <div class="fw-bold small mb-1"><?= htmlspecialchars($s['label'], ENT_QUOTES) ?></div>
                        <div class="gbg-cont"><?= htmlspecialchars($s['site_url'] ?? '—', ENT_QUOTES) ?></div>
                        <div class="gbg-cont">login: <?= htmlspecialchars($s['user'], ENT_QUOTES) ?></div>
                        <div class="gbg-cont">cont: <?= htmlspecialchars($s['cont_id'], ENT_QUOTES) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function () {
        if (window.__gbgSupplierMgrInit) return;
        window.__gbgSupplierMgrInit = true;
        const API = <?= json_encode($wb . 'api/gbg_suppliers.php', JSON_UNESCAPED_SLASHES) ?>;

        window.gbgResetForm = function () {
            document.getElementById('gbgRid').value = '';
            document.getElementById('gbgName').value = '';
            document.getElementById('gbgSiteUrl').value = '';
            document.getElementById('gbgContId').value = '';
            document.getElementById('gbgUser').value = '';
            document.getElementById('gbgPass').value = '';
            const del = document.getElementById('gbgDeleteBtn');
            if (del) del.style.display = 'none';
            document.querySelectorAll('.gbg-supplier-card').forEach(c => c.classList.remove('is-active'));
            const save = document.getElementById('gbgSaveBtn');
            if (save) save.textContent = 'Salvează furnizor';
        };

        window.gbgSelectSupplier = function (el) {
            document.querySelectorAll('.gbg-supplier-card').forEach(c => c.classList.remove('is-active'));
            el.classList.add('is-active');
            document.getElementById('gbgRid').value = el.getAttribute('data-id') || '';
            document.getElementById('gbgName').value = el.getAttribute('data-name') || '';
            document.getElementById('gbgSiteUrl').value = el.getAttribute('data-site') || '';
            document.getElementById('gbgContId').value = el.getAttribute('data-cont-id') || '';
            document.getElementById('gbgUser').value = el.getAttribute('data-user') || '';
            document.getElementById('gbgPass').value = el.getAttribute('data-pass') || '';
            const del = document.getElementById('gbgDeleteBtn');
            if (del) del.style.display = 'inline-block';
            const save = document.getElementById('gbgSaveBtn');
            if (save) save.textContent = 'Salvează modificări';
        };

        const form = document.getElementById('gbgSupplierForm');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const payload = {
                    supplier_name: document.getElementById('gbgName').value.trim(),
                    site_url: document.getElementById('gbgSiteUrl').value.trim(),
                    cont_id: document.getElementById('gbgContId').value.trim(),
                    user: document.getElementById('gbgUser').value.trim(),
                    pas: document.getElementById('gbgPass').value,
                    ridusers: document.getElementById('gbgRid').value.trim(),
                };
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || 'Eroare salvare.');
                    return;
                }
                alert(json.message || 'Salvat.');
                location.reload();
            });
        }

        document.getElementById('gbgImportCatalog')?.addEventListener('click', async function () {
            if (!confirm('Importă / actualizează toți furnizorii din catalog (site, login, parolă)?')) return;
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'import_catalog' }),
            });
            const json = await res.json();
            alert(json.message || (json.success ? 'OK' : 'Eroare'));
            if (json.success) location.reload();
        });

        window.gbgDeleteSupplier = async function () {
            const rid = document.getElementById('gbgRid').value;
            if (!rid || !confirm('Ștergi acest furnizor?')) return;
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', randomn_id: rid }),
            });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            location.reload();
        };
    })();
    </script>
    <?php
}
