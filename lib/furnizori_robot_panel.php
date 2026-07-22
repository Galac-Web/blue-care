<?php
declare(strict_types=1);

require_once __DIR__ . '/robot_workflow.php';
require_once __DIR__ . '/gbg_suppliers.php';
require_once __DIR__ . '/robot_snapshots.php';

/**
 * Logică robot — un furnizor, pași clari, fără haos (fără tab logica/pasi duplicate).
 */
function blu_render_furnizori_robot_panel(string $csrf, array $suppliers, string $selectedCont): void
{
    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    $apiWf = $wb . 'api/robot_workflow.php';
    $apiSnap = $wb . 'api/robot_snapshots.php';

    if ($selectedCont === '' && $suppliers) {
        $selectedCont = (string)($suppliers[0]['cont_id'] ?? '');
    }

    $supplier = null;
    foreach ($suppliers as $s) {
        if (($s['cont_id'] ?? '') === $selectedCont) {
            $supplier = $s;
            break;
        }
    }

    $workflow = $selectedCont !== '' ? blu_load_robot_workflow($selectedCont) : blu_default_gbg_workflow();
    $steps = $workflow['steps'] ?? [];
    $snapshots = $selectedCont !== '' ? blu_list_robot_snapshots($selectedCont, 8) : [];
    $workflowJson = json_encode($workflow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $defaultWfJson = json_encode(blu_default_gbg_workflow(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stepTypeLabels = [
        'navigate' => 'Deschide pagină',
        'analyze_html' => 'Analizează HTML',
        'close_banners' => 'Închide banere',
        'fill' => 'Completează câmp',
        'click' => 'Click',
        'wait' => 'Pauză',
        'builtin' => 'Scanare automată',
    ];
    ?>
    <style>
        .fz-robot { --accent: #308e87; }
        .fz-robot-toolbar {
            display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;
            padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1.25rem;
        }
        .fz-robot-toolbar label { font-size: .75rem; font-weight: 700; color: #64748b; margin-bottom: .2rem; }
        .fz-step-timeline { list-style: none; padding: 0; margin: 0; }
        .fz-step-timeline > li {
            display: flex; gap: 1rem; padding: .85rem 0; border-bottom: 1px solid #eef2f7;
        }
        .fz-step-timeline > li:last-child { border-bottom: none; }
        .fz-step-num {
            flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%;
            background: var(--accent); color: #fff; font-weight: 800; font-size: .9rem;
            display: flex; align-items: center; justify-content: center;
        }
        .fz-step-body { flex: 1; min-width: 0; }
        .fz-step-type { font-size: .7rem; font-weight: 700; text-transform: uppercase; color: var(--accent); }
        .fz-step-label { font-weight: 600; color: #0f172a; }
        .fz-step-detail { font-size: .8rem; color: #64748b; margin-top: .2rem; }
        .fz-section { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; }
        .fz-section-head {
            padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #eef2f7;
            display: flex; justify-content: space-between; align-items: center; cursor: pointer;
        }
        .fz-section-head h6 { margin: 0; font-weight: 700; }
        .fz-section-body { padding: 1rem; background: #fafbfc; }
        .fz-section-body.collapsed { display: none; }
        .fz-actions-bar {
            position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e2e8f0;
            padding: .75rem 1rem; margin: 1rem -1rem -1rem; display: flex; gap: .5rem; flex-wrap: wrap; z-index: 5;
        }
        .fz-ro-step-edit {
            border: 1px solid #e2e8f0; border-radius: 10px; padding: .75rem; margin-bottom: .5rem; background: #fff;
        }
    </style>

    <div class="fz-robot">
        <div class="fz-robot-toolbar">
            <div class="flex-grow-1" style="min-width:200px;max-width:400px">
                <label>Furnizor activ</label>
                <select class="form-select form-select-sm" id="fzRobotCont" onchange="location.href='?page=furnizori&amp;tab=robot&amp;cont_id='+encodeURIComponent(this.value)">
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= htmlspecialchars($s['cont_id'], ENT_QUOTES) ?>" <?= $selectedCont === $s['cont_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($supplier): ?>
                <div class="small text-muted">
                    <div><strong>Site:</strong> <?= htmlspecialchars($supplier['site_url'] ?? '—', ENT_QUOTES) ?></div>
                    <div><strong>Login:</strong> <code><?= htmlspecialchars($supplier['user'], ENT_QUOTES) ?></code></div>
                </div>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="?page=furnizori&amp;tab=conturi&amp;cont_id=<?= rawurlencode($selectedCont) ?>">Editează cont</a>
            <a class="btn btn-sm btn-success" href="?page=robot-monitor&amp;cont_id=<?= rawurlencode($selectedCont) ?>">Lansează scanare</a>
        </div>

        <!-- Pas 1: Prezentare clară — ce face robotul -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <h5 class="mb-2">Ordinea acțiunilor robot</h5>
                <p class="text-muted small mb-3">Robotul execută pașii de sus în jos. Modifică lista în secțiunea «Editează pașii» de mai jos.</p>
                <ol class="fz-step-timeline">
                    <?php foreach ($steps as $step): ?>
                        <?php
                        $type = (string)($step['type'] ?? '');
                        $typeLabel = $stepTypeLabels[$type] ?? $type;
                        $detail = '';
                        if ($type === 'navigate') {
                            $detail = (string)($step['url'] ?? '');
                        } elseif ($type === 'fill') {
                            $detail = ($step['selector'] ?? '') . ' → ' . ($step['value'] ?? '');
                        } elseif ($type === 'click') {
                            $detail = (string)($step['selector'] ?? '');
                        } elseif ($type === 'wait') {
                            $detail = (string)($step['seconds'] ?? '3') . ' secunde';
                        } elseif ($type === 'builtin') {
                            $detail = (string)($step['action'] ?? 'gbg_catalog_scan');
                        } elseif ($type === 'analyze_html') {
                            $detail = (string)($step['url'] ?? $step['page_key'] ?? 'pagina curentă');
                        } elseif ($type === 'close_banners') {
                            $detail = !empty($step['use_global_banners']) ? 'Lista globală banere' : 'Selectori custom';
                        }
                        ?>
                        <li>
                            <span class="fz-step-num"><?= (int)($step['order'] ?? 0) ?></span>
                            <div class="fz-step-body">
                                <div class="fz-step-type"><?= htmlspecialchars($typeLabel, ENT_QUOTES) ?></div>
                                <div class="fz-step-label"><?= htmlspecialchars((string)($step['label'] ?? ''), ENT_QUOTES) ?></div>
                                <?php if ($detail !== ''): ?>
                                    <div class="fz-step-detail"><?= htmlspecialchars($detail, ENT_QUOTES) ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <!-- Pas 2: Editor simplificat (collapsible) -->
        <div class="fz-section">
            <div class="fz-section-head" data-toggle="fzEditSteps">
                <h6>Editează pașii robot</h6>
                <span class="text-muted small">click pentru deschidere</span>
            </div>
            <div class="fz-section-body collapsed" id="fzEditStepsBody">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">URL login</label>
                        <input type="url" class="form-control form-control-sm" id="fzWfLogin" value="<?= htmlspecialchars((string)($workflow['login_url'] ?? ''), ENT_QUOTES) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">URL catalog</label>
                        <input type="url" class="form-control form-control-sm" id="fzWfCatalog" value="<?= htmlspecialchars((string)($workflow['catalog_url'] ?? ''), ENT_QUOTES) ?>">
                    </div>
                </div>
                <div id="fzStepsEditor"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="fzAddStep">+ Adaugă pas</button>
                <div class="fz-actions-bar">
                    <button type="button" class="btn btn-success btn-sm" id="fzSaveWorkflow"><i class="fa-solid fa-floppy-disk"></i> Salvează logica</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="fzResetWf">Reset GBG implicit</button>
                </div>
            </div>
        </div>

        <!-- Avansat: banere + HTML -->
        <div class="fz-section">
            <div class="fz-section-head" data-toggle="fzAdvanced">
                <h6>Avansat (banere, pagini HTML)</h6>
                <span class="text-muted small">opțional</span>
            </div>
            <div class="fz-section-body collapsed" id="fzAdvancedBody">
                <p class="small text-muted">Doar dacă site-ul are popup-uri sau pagini speciale de verificat.</p>
                <div id="fzBannersEditor" class="mb-3"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="fzAddBanner">+ Banner</button>
                <div id="fzHtmlPagesEditor"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="fzAddHtmlPage">+ Pagină HTML</button>
            </div>
        </div>

        <?php if ($snapshots): ?>
        <div class="fz-section">
            <div class="fz-section-head" data-toggle="fzSnaps">
                <h6>Ultimele snapshot-uri HTML (<?= count($snapshots) ?>)</h6>
            </div>
            <div class="fz-section-body collapsed" id="fzSnapsBody">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($snapshots as $snap): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars((string)($snap['html_purpose'] ?? $snap['label'] ?? ''), ENT_QUOTES) ?> — <?= htmlspecialchars((string)($snap['saved_at'] ?? ''), ENT_QUOTES) ?></span>
                            <a href="<?= htmlspecialchars($apiSnap . '?cont_id=' . rawurlencode($selectedCont) . '&id=' . rawurlencode((string)($snap['id'] ?? '')) . '&view=html', ENT_QUOTES) ?>" target="_blank" rel="noopener">Vezi HTML</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const API = <?= json_encode($apiWf, JSON_UNESCAPED_SLASHES) ?>;
        const CONT = <?= json_encode($selectedCont, JSON_UNESCAPED_UNICODE) ?>;
        let workflow = <?= $workflowJson ?>;
        const defaultWf = <?= $defaultWfJson ?>;

        const STEP_TYPES = [
            ['navigate', 'Deschide pagină'],
            ['close_banners', 'Închide banere'],
            ['fill', 'Completează câmp'],
            ['click', 'Click'],
            ['wait', 'Pauză'],
            ['analyze_html', 'Analizează HTML'],
            ['builtin', 'Scanare automată'],
        ];

        document.querySelectorAll('[data-toggle]').forEach(head => {
            head.addEventListener('click', () => {
                const id = head.getAttribute('data-toggle') + 'Body';
                const body = document.getElementById(id);
                if (body) body.classList.toggle('collapsed');
            });
        });

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function renderStepsEditor() {
            const box = document.getElementById('fzStepsEditor');
            const steps = (workflow.steps || []).slice().sort((a, b) => (a.order || 0) - (b.order || 0));
            box.innerHTML = steps.map((step, idx) => {
                const opts = STEP_TYPES.map(([v, l]) => `<option value="${v}" ${step.type === v ? 'selected' : ''}>${l}</option>`).join('');
                return `<div class="fz-ro-step-edit" data-idx="${idx}">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto"><span class="badge bg-primary">${step.order || idx + 1}</span></div>
                        <div class="col-md-3"><select class="form-select form-select-sm fz-st-type">${opts}</select></div>
                        <div class="col-md-5"><input type="text" class="form-control form-control-sm fz-st-label" placeholder="Descriere pas" value="${esc(step.label || '')}"></div>
                        <div class="col-md-3"><input type="text" class="form-control form-control-sm fz-st-extra" placeholder="URL / selector / sec" value="${esc(step.url || step.selector || step.seconds || step.action || step.page_key || '')}"></div>
                        <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger fz-st-del">×</button></div>
                    </div>
                </div>`;
            }).join('');
            box.querySelectorAll('.fz-st-del').forEach(btn => {
                btn.onclick = () => { syncFromDom(); workflow.steps.splice(+btn.closest('.fz-ro-step-edit').dataset.idx, 1); renumber(); renderStepsEditor(); };
            });
            box.querySelectorAll('.fz-st-type').forEach(sel => {
                sel.onchange = () => { syncFromDom(); renderStepsEditor(); };
            });
        }

        function renumber() {
            (workflow.steps || []).forEach((s, i) => { s.order = i + 1; });
        }

        function syncFromDom() {
            workflow.login_url = document.getElementById('fzWfLogin').value.trim();
            workflow.catalog_url = document.getElementById('fzWfCatalog').value.trim();
            workflow.steps = [];
            document.querySelectorAll('.fz-ro-step-edit').forEach((row, i) => {
                const type = row.querySelector('.fz-st-type').value;
                const label = row.querySelector('.fz-st-label').value.trim();
                const extra = row.querySelector('.fz-st-extra').value.trim();
                const step = { order: i + 1, type, label: label || ('Pas ' + (i + 1)) };
                if (type === 'navigate' || type === 'analyze_html') step.url = extra;
                if (type === 'fill' || type === 'click') { step.selector = extra; if (type === 'fill') step.value = '{{user}}'; }
                if (type === 'wait') step.seconds = parseFloat(extra) || 3;
                if (type === 'builtin') step.action = extra || 'gbg_catalog_scan';
                if (type === 'close_banners') step.use_global_banners = true;
                if (type === 'analyze_html') { step.save_html = true; step.page_key = extra; }
                workflow.steps.push(step);
            });
        }

        document.getElementById('fzAddStep')?.addEventListener('click', () => {
            syncFromDom();
            workflow.steps = workflow.steps || [];
            workflow.steps.push({ order: workflow.steps.length + 1, type: 'wait', label: 'Pauză', seconds: 2 });
            renumber();
            renderStepsEditor();
            document.getElementById('fzEditStepsBody').classList.remove('collapsed');
        });

        document.getElementById('fzSaveWorkflow')?.addEventListener('click', async () => {
            syncFromDom();
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cont_id: CONT, workflow }),
            });
            const j = await res.json();
            alert(j.message || '');
            if (j.success) location.reload();
        });

        document.getElementById('fzResetWf')?.addEventListener('click', () => {
            if (!confirm('Resetezi la pașii impliciți GBG?')) return;
            workflow = JSON.parse(JSON.stringify(defaultWf));
            document.getElementById('fzWfLogin').value = workflow.login_url || '';
            document.getElementById('fzWfCatalog').value = workflow.catalog_url || '';
            renderStepsEditor();
        });

        renderStepsEditor();
    })();
    </script>
    <?php
}
