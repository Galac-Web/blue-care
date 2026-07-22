<?php
declare(strict_types=1);

require_once __DIR__ . '/robot_workflow.php';
require_once __DIR__ . '/gbg_suppliers.php';

/**
 * Editor pași robot per furnizor (admin).
 */
function blu_render_robot_workflow_editor(string $csrf, array $gbgSuppliers): void
{
    $suppliers = blu_gbg_suppliers_for_ui();
    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    $apiUrl = $wb . 'api/robot_workflow.php';
    $selectedCont = trim((string)($_GET['cont_id'] ?? ''));
    if ($selectedCont === '' && $suppliers) {
        $selectedCont = (string)($suppliers[0]['cont_id'] ?? '');
    }
    $workflow = $selectedCont !== '' ? blu_load_robot_workflow($selectedCont) : blu_default_gbg_workflow();
    $workflowJson = json_encode($workflow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $suppliersJson = json_encode($suppliers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    <style>
        .rw-editor { --rw-accent: #308e87; }
        .rw-step {
            border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-bottom: .75rem;
            background: #fafbfc;
        }
        .rw-step-head { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .75rem; }
        .rw-step-num {
            width: 32px; height: 32px; border-radius: 8px; background: var(--rw-accent); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .85rem;
        }
        .rw-banner-row { border: 1px dashed #cbd5e1; border-radius: 10px; padding: .75rem; margin-bottom: .5rem; background: #fff; }
        .rw-help { font-size: .78rem; color: #64748b; }
        .rw-type-hint { display: none; }
        .rw-step[data-type] .rw-type-hint { display: none; }
        .rw-step[data-type="navigate"] .rw-hint-navigate,
        .rw-step[data-type="close_banners"] .rw-hint-banners,
        .rw-step[data-type="fill"] .rw-hint-fill,
        .rw-step[data-type="click"] .rw-hint-click,
        .rw-step[data-type="wait"] .rw-hint-wait,
        .rw-step[data-type="builtin"] .rw-hint-builtin,
        .rw-step[data-type="analyze_html"] .rw-hint-analyze { display: block; }
        .rw-html-page { border: 1px solid #cbd5e1; border-radius: 10px; padding: .75rem; margin-bottom: .5rem; background: #fff; }
    </style>

    <div class="card rw-editor">
        <div class="card-header card-no-border pb-0">
            <h4 class="mb-1">Logică robot — pași per furnizor</h4>
            <p class="text-muted small mb-0">
                Fiecare furnizor are propriul workflow (salvat în <code>data/robot_workflows/</code> + copie pe PC robot).
                Definește clar: unde navighează, ce face la fiecare pas, ce banere închide.
            </p>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Furnizor (cont_id)</label>
                    <select class="form-select" id="rwContId">
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= htmlspecialchars($s['cont_id'], ENT_QUOTES) ?>"
                                <?= $selectedCont === $s['cont_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label'] . ' — ' . $s['cont_id'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7 d-flex align-items-end gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rwReload">Reîncarcă</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="rwResetDefault">Reset pași GBG impliciți</button>
                    <button type="button" class="btn btn-success btn-sm" id="rwSave"><i class="fa-solid fa-floppy-disk"></i> Salvează pașii</button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">URL login</label>
                    <input type="url" class="form-control form-control-sm" id="rwLoginUrl" value="<?= htmlspecialchars((string)($workflow['login_url'] ?? ''), ENT_QUOTES) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">URL catalog / aplicație</label>
                    <input type="url" class="form-control form-control-sm" id="rwCatalogUrl" value="<?= htmlspecialchars((string)($workflow['catalog_url'] ?? ''), ENT_QUOTES) ?>">
                </div>
            </div>

            <h5 class="mb-2">Pagini HTML — ce trebuie deschise și analizate</h5>
            <p class="rw-help mb-2">
                Documentează fiecare pagină HTML: URL, ce extrage robotul, selectori de verificare.
                La scanare, robotul salvează snapshot în <code>data/robot_snapshots/</code> — vezi tab <a href="?page=furnizori&amp;tab=logica&amp;cont_id=<?= htmlspecialchars($selectedCont, ENT_QUOTES) ?>">Logica + HTML</a>.
            </p>
            <div id="rwHtmlPagesList"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="rwAddHtmlPage">+ Pagină HTML</button>

            <h5 class="mb-2">Banere de închis (global pentru acest furnizor)</h5>
            <p class="rw-help mb-2">La pașii «Închid banere» cu «folosește lista globală», robotul încearcă fiecare selector de mai jos.</p>
            <div id="rwBannersList"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="rwAddBanner">+ Banner</button>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Pași de lucru (1, 2, 3…)</h5>
                <button type="button" class="btn btn-sm btn-primary" id="rwAddStep">+ Pas nou</button>
            </div>
            <div id="rwStepsList"></div>

            <div class="alert alert-light border mt-3 mb-0 small">
                <strong>Variabile în URL / câmpuri:</strong> <code>{{user}}</code>, <code>{{pass}}</code>, <code>{{login_url}}</code>, <code>{{catalog_url}}</code>, <code>{{cont_id}}</code><br>
                <strong>Tipuri pas:</strong> navigate, <strong>analyze_html</strong> (deschide + salvează HTML + verifică selectori), close_banners, fill, click, wait, builtin<br>
                La <strong>navigate</strong> poți bifa «salvează HTML» — analiză automată după încărcare.
            </div>
        </div>
    </div>

    <script>
    (function () {
        const API = <?= json_encode($apiUrl, JSON_UNESCAPED_SLASHES) ?>;
        const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
        let workflow = <?= $workflowJson ?>;
        const defaultWf = <?= json_encode(blu_default_gbg_workflow(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        const STEP_TYPES = [
            ['navigate', '1) Navigare — deschide URL'],
            ['analyze_html', 'Analizez HTML (snapshot + verificare)'],
            ['close_banners', 'Închid banere / popup'],
            ['fill', 'Completez câmp (user/parolă)'],
            ['click', 'Click pe buton/link'],
            ['wait', 'Pauză (secunde)'],
            ['builtin', 'Acțiune integrată (scanare catalog)'],
        ];

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function renderHtmlPages() {
            const box = document.getElementById('rwHtmlPagesList');
            const pages = workflow.html_pages || [];
            box.innerHTML = pages.map((p, i) => `
                <div class="rw-html-page" data-idx="${i}">
                    <div class="row g-2">
                        <div class="col-md-2"><label class="small fw-bold">Cheie</label><input type="text" class="form-control form-control-sm rw-hp-key" value="${esc(p.key || '')}" placeholder="login"></div>
                        <div class="col-md-4"><label class="small fw-bold">Nume pagină</label><input type="text" class="form-control form-control-sm rw-hp-name" value="${esc(p.name || '')}"></div>
                        <div class="col-md-6"><label class="small fw-bold">URL</label><input type="text" class="form-control form-control-sm rw-hp-url" value="${esc(p.url || '')}"></div>
                        <div class="col-12"><label class="small fw-bold">Ce scanează / extrage din HTML</label><textarea class="form-control form-control-sm rw-hp-what" rows="2">${esc(p.what_scans || '')}</textarea></div>
                        <div class="col-md-8"><label class="small fw-bold">Selectori verificare (unul pe linie)</label><textarea class="form-control form-control-sm rw-hp-sels" rows="2">${esc((p.verify_selectors || []).join('\n'))}</textarea></div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <div class="form-check"><input class="form-check-input rw-hp-save" type="checkbox" ${p.save_html !== false ? 'checked' : ''}><label class="form-check-label small">Salvează HTML</label></div>
                            <button type="button" class="btn btn-sm btn-outline-danger rw-hp-del">Șterge</button>
                        </div>
                    </div>
                </div>
            `).join('');
            box.querySelectorAll('.rw-hp-del').forEach(btn => {
                btn.onclick = () => {
                    workflow.html_pages.splice(parseInt(btn.closest('.rw-html-page').dataset.idx, 10), 1);
                    renderHtmlPages();
                };
            });
        }

        function renderBanners() {
            const box = document.getElementById('rwBannersList');
            const banners = workflow.banners || [];
            box.innerHTML = banners.map((b, i) => `
                <div class="rw-banner-row" data-idx="${i}">
                    <div class="d-flex gap-2 mb-2">
                        <input type="text" class="form-control form-control-sm rw-ban-name" placeholder="Nume banner (ex: Cookie GDPR)" value="${esc(b.name || '')}">
                        <button type="button" class="btn btn-sm btn-outline-danger rw-ban-del">Șterge</button>
                    </div>
                    <textarea class="form-control form-control-sm rw-ban-sels" rows="3" placeholder="Un selector XPath/CSS pe linie">${esc((b.selectors || []).join('\n'))}</textarea>
                </div>
            `).join('');
            box.querySelectorAll('.rw-ban-del').forEach(btn => {
                btn.onclick = () => {
                    workflow.banners.splice(parseInt(btn.closest('.rw-banner-row').dataset.idx, 10), 1);
                    renderBanners();
                };
            });
        }

        function stepFieldsHtml(step) {
            const t = step.type || 'wait';
            return `
                <div class="rw-hint-navigate rw-type-hint rw-help">URL: unde navighează robotul. Poți folosi {{login_url}}. Bifează salvare HTML pentru analiză automată.</div>
                <div class="rw-hint-analyze rw-type-hint rw-help">Deschide URL (sau pagina curentă), verifică selectori, salvează HTML complet + jurnal detaliat.</div>
                <div class="rw-hint-banners rw-type-hint rw-help">Bifează «lista globală» sau pune selectori doar pentru acest pas (câte unul pe linie).</div>
                <div class="rw-hint-fill rw-type-hint rw-help">Selector CSS (#id) sau XPath. Valoare: {{user}} sau {{pass}}.</div>
                <div class="rw-hint-click rw-type-hint rw-help">Selector buton de apăsat după completare câmpuri.</div>
                <div class="rw-hint-wait rw-type-hint rw-help">Secunde de așteptare după login sau încărcare pagină.</div>
                <div class="rw-hint-builtin rw-type-hint rw-help">gbg_catalog_scan = logica de scanare marci/modele/piese (robot existent).</div>
                <div class="row g-2 mt-1 rw-f-url" style="display:${t === 'navigate' ? '' : 'none'}">
                    <div class="col-12"><label class="small fw-bold">URL</label><input type="text" class="form-control form-control-sm rw-in-url" value="${esc(step.url || '')}"></div>
                    <div class="col-12"><label class="small fw-bold">Scop pagină HTML</label><input type="text" class="form-control form-control-sm rw-in-html-purpose" value="${esc(step.html_purpose || '')}"></div>
                    <div class="col-12"><label class="small fw-bold">Ce scanează pe această pagină</label><textarea class="form-control form-control-sm rw-in-what-scans" rows="2">${esc(step.what_scans || '')}</textarea></div>
                    <div class="col-12"><label class="small fw-bold">Selectori verificare</label><textarea class="form-control form-control-sm rw-in-verify-sels" rows="2">${esc((step.verify_selectors || []).join('\n'))}</textarea></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input rw-in-save-html" type="checkbox" ${step.save_html ? 'checked' : ''}><label class="form-check-label small">Salvează snapshot HTML după încărcare</label></div></div>
                </div>
                <div class="row g-2 mt-1 rw-f-analyze" style="display:${t === 'analyze_html' ? '' : 'none'}">
                    <div class="col-md-4"><label class="small fw-bold">Cheie pagină (din lista de mai sus)</label><input type="text" class="form-control form-control-sm rw-in-page-key" value="${esc(step.page_key || '')}" placeholder="login"></div>
                    <div class="col-md-8"><label class="small fw-bold">URL (gol = pagina curentă)</label><input type="text" class="form-control form-control-sm rw-in-url" value="${esc(step.url || '')}"></div>
                    <div class="col-12"><label class="small fw-bold">Ce scanează / extrage</label><textarea class="form-control form-control-sm rw-in-what-scans" rows="2">${esc(step.what_scans || '')}</textarea></div>
                    <div class="col-12"><label class="small fw-bold">Selectori verificare</label><textarea class="form-control form-control-sm rw-in-verify-sels" rows="2">${esc((step.verify_selectors || []).join('\n'))}</textarea></div>
                </div>
                <div class="row g-2 mt-1 rw-f-banners" style="display:${t === 'close_banners' ? '' : 'none'}">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input rw-in-use-global" type="checkbox" ${step.use_global_banners !== false ? 'checked' : ''}>
                            <label class="form-check-label small">Folosește lista globală de banere de mai sus</label>
                        </div>
                        <textarea class="form-control form-control-sm rw-in-step-sels mt-1" rows="2" placeholder="Selectori doar pentru acest pas (opțional)">${esc((step.selectors || []).join('\n'))}</textarea>
                    </div>
                </div>
                <div class="row g-2 mt-1 rw-f-fill" style="display:${t === 'fill' ? '' : 'none'}">
                    <div class="col-md-6"><label class="small fw-bold">Selector</label><input type="text" class="form-control form-control-sm rw-in-sel" value="${esc(step.selector || '')}"></div>
                    <div class="col-md-6"><label class="small fw-bold">Valoare</label><input type="text" class="form-control form-control-sm rw-in-val" value="${esc(step.value || '')}"></div>
                </div>
                <div class="row g-2 mt-1 rw-f-click" style="display:${t === 'click' ? '' : 'none'}">
                    <div class="col-12"><label class="small fw-bold">Selector click</label><input type="text" class="form-control form-control-sm rw-in-sel" value="${esc(step.selector || '')}"></div>
                </div>
                <div class="row g-2 mt-1 rw-f-wait" style="display:${t === 'wait' ? '' : 'none'}">
                    <div class="col-md-4"><label class="small fw-bold">Secunde</label><input type="number" min="0" step="0.5" class="form-control form-control-sm rw-in-sec" value="${esc(String(step.seconds ?? 3))}"></div>
                </div>
                <div class="row g-2 mt-1 rw-f-builtin" style="display:${t === 'builtin' ? '' : 'none'}">
                    <div class="col-md-6"><label class="small fw-bold">Acțiune</label>
                        <select class="form-select form-select-sm rw-in-action">
                            <option value="gbg_catalog_scan" ${step.action === 'gbg_catalog_scan' ? 'selected' : ''}>gbg_catalog_scan</option>
                        </select>
                    </div>
                </div>
            `;
        }

        function renderSteps() {
            const box = document.getElementById('rwStepsList');
            const steps = (workflow.steps || []).slice().sort((a, b) => (a.order || 0) - (b.order || 0));
            box.innerHTML = steps.map((step, idx) => {
                const types = STEP_TYPES.map(([v, l]) =>
                    `<option value="${v}" ${step.type === v ? 'selected' : ''}>${esc(l)}</option>`
                ).join('');
                return `
                <div class="rw-step" data-idx="${idx}" data-type="${esc(step.type || 'wait')}">
                    <div class="rw-step-head">
                        <span class="rw-step-num">${step.order || idx + 1}</span>
                        <input type="text" class="form-control form-control-sm flex-grow-1 rw-in-label" placeholder="Descriere pas (apare în jurnal robot)" value="${esc(step.label || '')}">
                        <select class="form-select form-select-sm rw-in-type" style="max-width:220px">${types}</select>
                        <button type="button" class="btn btn-sm btn-outline-secondary rw-step-up" title="Sus">↑</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rw-step-down" title="Jos">↓</button>
                        <button type="button" class="btn btn-sm btn-outline-danger rw-step-del">Șterge</button>
                    </div>
                    ${stepFieldsHtml(step)}
                </div>`;
            }).join('');

            box.querySelectorAll('.rw-in-type').forEach(sel => {
                sel.onchange = () => {
                    const row = sel.closest('.rw-step');
                    row.dataset.type = sel.value;
                    syncStepFromDom();
                    renderSteps();
                };
            });
            box.querySelectorAll('.rw-step-del').forEach(btn => {
                btn.onclick = () => {
                    syncStepFromDom();
                    workflow.steps.splice(parseInt(btn.closest('.rw-step').dataset.idx, 10), 1);
                    renumberSteps();
                    renderSteps();
                };
            });
            box.querySelectorAll('.rw-step-up').forEach(btn => {
                btn.onclick = () => moveStep(parseInt(btn.closest('.rw-step').dataset.idx, 10), -1);
            });
            box.querySelectorAll('.rw-step-down').forEach(btn => {
                btn.onclick = () => moveStep(parseInt(btn.closest('.rw-step').dataset.idx, 10), 1);
            });
        }

        function moveStep(idx, dir) {
            syncStepFromDom();
            const j = idx + dir;
            if (j < 0 || j >= workflow.steps.length) return;
            const t = workflow.steps[idx];
            workflow.steps[idx] = workflow.steps[j];
            workflow.steps[j] = t;
            renumberSteps();
            renderSteps();
        }

        function renumberSteps() {
            workflow.steps.forEach((s, i) => { s.order = i + 1; });
        }

        function syncStepFromDom() {
            workflow.login_url = document.getElementById('rwLoginUrl').value.trim();
            workflow.catalog_url = document.getElementById('rwCatalogUrl').value.trim();
            workflow.html_pages = [];
            document.querySelectorAll('.rw-html-page').forEach(row => {
                workflow.html_pages.push({
                    key: row.querySelector('.rw-hp-key')?.value.trim() || '',
                    name: row.querySelector('.rw-hp-name')?.value.trim() || '',
                    url: row.querySelector('.rw-hp-url')?.value.trim() || '',
                    what_scans: row.querySelector('.rw-hp-what')?.value.trim() || '',
                    verify_selectors: (row.querySelector('.rw-hp-sels')?.value || '').split('\n').map(s => s.trim()).filter(Boolean),
                    save_html: row.querySelector('.rw-hp-save')?.checked !== false,
                });
            });
            workflow.banners = [];
            document.querySelectorAll('.rw-banner-row').forEach(row => {
                const name = row.querySelector('.rw-ban-name').value.trim();
                const sels = row.querySelector('.rw-ban-sels').value.split('\n').map(s => s.trim()).filter(Boolean);
                if (name || sels.length) workflow.banners.push({ name: name || 'Banner', selectors: sels });
            });
            workflow.steps = [];
            document.querySelectorAll('.rw-step').forEach((row, i) => {
                const type = row.querySelector('.rw-in-type').value;
                const step = {
                    order: i + 1,
                    type: type,
                    label: row.querySelector('.rw-in-label').value.trim() || ('Pas ' + (i + 1)),
                };
                if (type === 'navigate') {
                    step.url = row.querySelector('.rw-in-url')?.value.trim() || '';
                    step.html_purpose = row.querySelector('.rw-in-html-purpose')?.value.trim() || '';
                    step.what_scans = row.querySelector('.rw-in-what-scans')?.value.trim() || '';
                    step.verify_selectors = (row.querySelector('.rw-in-verify-sels')?.value || '').split('\n').map(s => s.trim()).filter(Boolean);
                    step.save_html = !!row.querySelector('.rw-in-save-html')?.checked;
                }
                if (type === 'analyze_html') {
                    step.page_key = row.querySelector('.rw-in-page-key')?.value.trim() || '';
                    step.url = row.querySelector('.rw-in-url')?.value.trim() || '';
                    step.what_scans = row.querySelector('.rw-in-what-scans')?.value.trim() || '';
                    step.verify_selectors = (row.querySelector('.rw-in-verify-sels')?.value || '').split('\n').map(s => s.trim()).filter(Boolean);
                    step.save_html = true;
                }
                if (type === 'close_banners') {
                    step.use_global_banners = row.querySelector('.rw-in-use-global')?.checked !== false;
                    step.selectors = (row.querySelector('.rw-in-step-sels')?.value || '').split('\n').map(s => s.trim()).filter(Boolean);
                }
                if (type === 'fill' || type === 'click') {
                    step.selector = row.querySelector('.rw-in-sel')?.value.trim() || '';
                    step.selector_type = 'css';
                    if (type === 'fill') step.value = row.querySelector('.rw-in-val')?.value.trim() || '';
                }
                if (type === 'wait') step.seconds = parseFloat(row.querySelector('.rw-in-sec')?.value || '3') || 3;
                if (type === 'builtin') step.action = row.querySelector('.rw-in-action')?.value || 'gbg_catalog_scan';
                workflow.steps.push(step);
            });
        }

        function collectWorkflow() {
            syncStepFromDom();
            return workflow;
        }

        async function loadForCont(contId) {
            const res = await fetch(API + '?cont_id=' + encodeURIComponent(contId));
            const data = await res.json();
            if (data.success && data.workflow) {
                workflow = data.workflow;
                document.getElementById('rwLoginUrl').value = workflow.login_url || '';
                document.getElementById('rwCatalogUrl').value = workflow.catalog_url || '';
                if (!workflow.html_pages || !workflow.html_pages.length) {
                    workflow.html_pages = (defaultWf.html_pages || []).slice();
                }
                renderHtmlPages();
                renderBanners();
                renderSteps();
            }
        }

        document.getElementById('rwContId').onchange = function () {
            const u = new URL(location.href);
            u.searchParams.set('page', 'furnizori');
            u.searchParams.set('tab', 'pasi');
            u.searchParams.set('cont_id', this.value);
            location.href = u.toString();
        };

        document.getElementById('rwAddHtmlPage').onclick = () => {
            syncStepFromDom();
            workflow.html_pages = workflow.html_pages || [];
            workflow.html_pages.push({
                key: 'pagina_' + (workflow.html_pages.length + 1),
                name: 'Pagină HTML nouă',
                url: '',
                what_scans: '',
                verify_selectors: [],
                save_html: true,
            });
            renderHtmlPages();
        };

        document.getElementById('rwAddBanner').onclick = () => {
            workflow.banners = workflow.banners || [];
            workflow.banners.push({ name: 'Banner nou', selectors: [] });
            renderBanners();
        };

        document.getElementById('rwAddStep').onclick = () => {
            syncStepFromDom();
            workflow.steps = workflow.steps || [];
            workflow.steps.push({
                order: workflow.steps.length + 1,
                type: 'wait',
                label: 'Pas ' + (workflow.steps.length + 1),
                seconds: 2,
            });
            renderSteps();
        };

        document.getElementById('rwResetDefault').onclick = () => {
            if (!confirm('Resetezi la pașii impliciți GBG pentru acest furnizor?')) return;
            workflow = JSON.parse(JSON.stringify(defaultWf));
            document.getElementById('rwLoginUrl').value = workflow.login_url || '';
            document.getElementById('rwCatalogUrl').value = workflow.catalog_url || '';
            renderHtmlPages();
            renderBanners();
            renderSteps();
        };

        document.getElementById('rwReload').onclick = () => loadForCont(document.getElementById('rwContId').value);

        document.getElementById('rwSave').onclick = async () => {
            const contId = document.getElementById('rwContId').value;
            const body = { cont_id: contId, workflow: collectWorkflow() };
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            alert(data.message || (data.success ? 'Salvat.' : 'Eroare.'));
            if (data.success) loadForCont(contId);
        };

        if (!workflow.html_pages || !workflow.html_pages.length) {
            workflow.html_pages = (defaultWf.html_pages || []).slice();
        }
        renderHtmlPages();
        renderBanners();
        renderSteps();
    })();
    </script>
    <?php
}
