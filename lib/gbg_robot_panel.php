<?php
declare(strict_types=1);

/**
 * Panou lansare robot GBG-eshop — cartele furnizor + scanare completă din admin.
 */
function blu_render_gbg_robot_panel(array $suppliers): void
{
    require_once __DIR__ . '/robot_config.php';

    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    $robotBase = blu_robot_furnizori_effective_url();
    $robotJs = blu_robot_admin_js_config();
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .gbg-panel { --gbg-teal: #308e87; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; margin-bottom: 1.25rem; overflow: hidden; }
        .gbg-panel-head { padding: .9rem 1.1rem; background: linear-gradient(90deg, rgba(48,142,135,.08), #fff); border-bottom: 1px solid #eef2f7; }
        .gbg-panel-body { padding: 1rem 1.1rem; }
        .gbg-supplier-card { border: 1px solid #dbe3ea; border-radius: 14px; padding: .85rem; cursor: pointer; transition: .15s ease; background: #fafbfc; height: 100%; }
        .gbg-supplier-card:hover { border-color: var(--gbg-teal); }
        .gbg-supplier-card.is-active { border-color: var(--gbg-teal); background: rgba(48,142,135,.08); box-shadow: inset 0 0 0 1px var(--gbg-teal); }
        .gbg-supplier-card.is-scanning { box-shadow: 0 0 0 2px rgba(48,142,135,.35); }
        .gbg-supplier-card.is-scanning::after {
            content: 'SCAN';
            position: absolute; top: 8px; right: 8px;
            font-size: .62rem; font-weight: 800; color: #15803d;
            background: #dcfce7; padding: .1rem .35rem; border-radius: 999px;
        }
        .gbg-supplier-card { position: relative; }
        .gbg-supplier-card .gbg-cont { font-family: monospace; font-size: .78rem; color: #64748b; }
        .gbg-launch-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0; }
        .gbg-status-box { font-family: Consolas, monospace; font-size: .78rem; background: #0b1220; color: #86efac; border-radius: 10px; padding: .75rem; min-height: 220px; max-height: 420px; overflow-y: auto; }
        .gbg-log-line { margin-bottom: 4px; line-height: 1.35; }
        .gbg-log-line.info { color: #86efac; }
        .gbg-log-line.ok { color: #4ade80; }
        .gbg-log-line.warn { color: #fbbf24; }
        .gbg-log-line.err { color: #f87171; }
        .gbg-scan-badge { font-size: .72rem; font-weight: 800; padding: .25rem .6rem; border-radius: 999px; }
        .gbg-scan-badge.active { background: #dcfce7; color: #15803d; }
        .gbg-scan-badge.idle { background: #f1f5f9; color: #64748b; }
        .gbg-scan-badge.stopped { background: #fee2e2; color: #b91c1c; }
        .gbg-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .gbg-pill.offline { background: #fee2e2; color: #b91c1c; }
        .gbg-pill.online { background: #dcfce7; color: #15803d; }
    </style>

    <div class="gbg-panel">
        <div class="gbg-panel-head">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h4 class="h5 mb-1 fw-bold">Robot GBG — Cartelă furnizor</h4>
                    <p class="text-muted small mb-0">Salvează login GBG, apoi lansează scanarea completă direct din admin (fără script Python manual).</p>
                </div>
                <div class="text-end">
                    <span class="gbg-pill offline" id="gbgRobotPill">Robot: verificare...</span>
                    <div class="small text-muted mt-1">Furnizori GBG · <code class="text-truncate d-inline-block" style="max-width:min(100%, 280px); vertical-align:bottom;" title="<?= htmlspecialchars($robotBase, ENT_QUOTES) ?>"><?= htmlspecialchars($robotBase, ENT_QUOTES) ?></code></div>
                    <?php if ($robotJs['hint'] !== ''): ?>
                    <div class="small text-warning mt-1" id="gbgRobotHint"><?= htmlspecialchars($robotJs['hint'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="gbg-panel-body">
            <form id="gbgSupplierForm">
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <label class="form-label small fw-bold text-muted">Furnizor</label>
                        <input type="text" class="form-control form-control-sm" id="gbgName" placeholder="InterCars">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-8 col-xl-4">
                        <label class="form-label small fw-bold text-muted">Site web</label>
                        <input type="url" class="form-control form-control-sm" id="gbgSiteUrl" placeholder="https://...">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                        <label class="form-label small fw-bold text-muted">cont_id</label>
                        <input type="text" class="form-control form-control-sm" id="gbgContId" placeholder="furnizor_...">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                        <label class="form-label small fw-bold text-muted">Login</label>
                        <input type="text" class="form-control form-control-sm" id="gbgUser" placeholder="user / email">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                        <label class="form-label small fw-bold text-muted">Parolă</label>
                        <input type="text" class="form-control form-control-sm" id="gbgPass">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-1 d-flex align-items-end gap-1">
                        <input type="hidden" id="gbgRid" value="">
                        <button type="submit" class="btn btn-sm btn-primary" id="gbgSaveBtn" title="Salvează">💾</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="gbgResetForm()" title="Nou">+</button>
                    </div>
                </div>
            </form>

            <div class="row g-2 mt-3" id="gbgSupplierCards">
                <?php if (!$suppliers): ?>
                    <div class="col-12"><div class="text-muted small">Niciun furnizor salvat. Completează formularul de sus.</div></div>
                <?php endif; ?>
                <?php foreach ($suppliers as $s): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-2 mt-3 align-items-end" style="border-top:1px dashed #e2e8f0; padding-top:1rem;">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted">De la produsul nr.</label>
                    <input type="number" min="1" class="form-control form-control-sm" id="gbgScanFrom" value="1">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted">Până la produsul nr.</label>
                    <input type="number" min="0" class="form-control form-control-sm" id="gbgScanTo" value="0" placeholder="0 = până la final">
                </div>
                <div class="col-12 col-md-4 pt-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gbgSkipDup" checked>
                        <label class="form-check-label small" for="gbgSkipDup">Sări peste produsele deja scanate (fără dubluri)</label>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="gbgAutoPieseauto" checked>
                        <label class="form-check-label small" for="gbgAutoPieseauto">Publică automat pe PieseAuto după import</label>
                    </div>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-center justify-content-md-end gap-2">
                    <span class="small text-muted">Deja scanate: <strong id="gbgScanCount">0</strong></span>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="gbgResetScanate()">Resetează lista scanate</button>
                    <button type="button" class="btn btn-warning btn-sm fw-bold" onclick="gbgStartFromBeginning()" title="Golește lista scanate + de la produsul 1 + lansează scanarea">
                        Începe de la început
                    </button>
                </div>
                <div class="col-12">
                    <div class="small text-muted">Lasă „De la = 1" și „Până la = 0" ca să scanezi tot. Ex.: ca să iei doar produsele 7–8, pune „De la = 7" și „Până la = 8".</div>
                </div>
            </div>

            <div class="gbg-launch-bar">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted">Pași robot — jurnal live</span>
                        <span class="gbg-scan-badge idle" id="gbgScanBadge">INACTIV</span>
                    </div>
                    <div class="gbg-status-box" id="gbgStatusBox">
                        <div class="gbg-log-line info">Selectează un furnizor și apasă lansare.</div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="gbgDeleteBtn" style="display:none;" onclick="gbgDeleteSupplier()">Șterge furnizor</button>
                    <button type="button" class="btn btn-danger fw-bold px-4" id="gbgStopBtn" onclick="gbgStopScan()" disabled>
                        <i class="bi bi-stop-fill"></i> Oprește robot
                    </button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="gbgLaunchBtn" onclick="gbgLaunchFullScan()">
                        <i class="bi bi-play-fill"></i> Lansează scanare
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const PROXY = <?= json_encode($wb . 'robot_proxy.php', JSON_UNESCAPED_SLASHES) ?>;
        const PIESEAUTO_AUTO_URL = <?= json_encode($wb . 'api/pieseauto_auto.php', JSON_UNESCAPED_SLASHES) ?>;
        const ROBOT_LAUNCHER_URL = <?= json_encode($wb . 'api/robot_launcher.php', JSON_UNESCAPED_SLASHES) ?>;
        const ROBOT_CFG = <?= json_encode($robotJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const NGROK_HEADERS = { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': '69420' };
        const GBG_SESSION_KEY = 'gbg_robot_scan_session';
        let gbgPollTimer = null;
        let gbgReconnectTimer = null;
        let gbgActiveContId = '';
        let gbgLastJournalKey = '';
        let gbgLaunchBusy = false;
        let gbgStalePolls = 0;
        let gbgLastJournalPollKey = '';

        function gbgLogStatus(msg, level) {
            const box = document.getElementById('gbgStatusBox');
            if (!box) return;
            const lvl = level || 'info';
            box.innerHTML += `<div class="gbg-log-line ${gbgEsc(lvl)}">[${new Date().toLocaleTimeString()}] ${gbgEsc(msg)}</div>`;
            box.scrollTop = box.scrollHeight;
        }

        function gbgSetLaunchBusy(busy) {
            gbgLaunchBusy = busy;
            const btn = document.getElementById('gbgLaunchBtn');
            if (!btn) return;
            btn.disabled = busy;
            btn.innerHTML = busy
                ? '<span class="spinner-border spinner-border-sm me-1"></span> Se lansează...'
                : '<i class="bi bi-play-fill"></i> Lansează scanare';
        }

        async function gbgRobotRequest(path, options = {}) {
            const headers = Object.assign({}, NGROK_HEADERS, options.headers || {});
            const timeoutMs = options.timeoutMs || 8000;
            const opts = Object.assign({}, options, { headers });
            delete opts.timeoutMs;

            const bases = [];
            if (ROBOT_CFG.direct_furnizori) bases.push(ROBOT_CFG.direct_furnizori);
            if (ROBOT_CFG.local_direct && bases.indexOf(ROBOT_CFG.local_direct) === -1) {
                bases.push(ROBOT_CFG.local_direct);
            }

            for (const base of bases) {
                try {
                    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                    const timer = ctrl ? setTimeout(function () { ctrl.abort(); }, timeoutMs) : null;
                    const res = await fetch(base + path, Object.assign({}, opts, ctrl ? { signal: ctrl.signal } : {}));
                    if (timer) clearTimeout(timer);
                    const text = await res.text();
                    let data = null;
                    try { data = JSON.parse(text); } catch (e) {}
                    if (data !== null && res.ok) {
                        return { ok: true, status: res.status, data, via: base === ROBOT_CFG.local_direct ? 'local' : 'direct' };
                    }
                } catch (e) { /* urmatorul endpoint */ }
            }

            try {
                const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                const timer = ctrl ? setTimeout(function () { ctrl.abort(); }, timeoutMs) : null;
                const res = await fetch(PROXY + '?path=' + encodeURIComponent(path), Object.assign({}, opts, ctrl ? { signal: ctrl.signal } : {}));
                if (timer) clearTimeout(timer);
                const text = await res.text();
                let data = null;
                try { data = JSON.parse(text); } catch (e) {}
                return {
                    ok: res.ok && data !== null,
                    status: res.status,
                    data,
                    via: 'proxy',
                    raw: text,
                };
            } catch (e) {
                return { ok: false, status: 0, data: null, error: String(e) };
            }
        }

        async function gbgFetchJson(path, options = {}) {
            return gbgRobotRequest(path, options);
        }

        function gbgFetch(path, options = {}) {
            return gbgFetchJson(path, options).then(r => ({
                ok: r.ok,
                json: async () => r.data,
            }));
        }

        function gbgEsc(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function gbgJournalKey(jurnal) {
            if (!jurnal || !jurnal.length) return '';
            const last = jurnal[jurnal.length - 1];
            return jurnal.length + '|' + (last.t || '') + '|' + (last.msg || '') + '|' + (last.level || '');
        }

        function gbgSaveSession(contId, supplierId) {
            try {
                localStorage.setItem(GBG_SESSION_KEY, JSON.stringify({
                    contId: contId || '',
                    supplierId: supplierId || '',
                    ts: Date.now(),
                }));
            } catch (e) {}
        }

        function gbgLoadSession() {
            try {
                const raw = localStorage.getItem(GBG_SESSION_KEY);
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        function gbgResolveContId(data) {
            if (!data) return '';
            const runningMap = data.running || {};
            if (data.active_cont_id && runningMap[data.active_cont_id]) {
                return data.active_cont_id;
            }
            for (const [cid, isRun] of Object.entries(runningMap)) {
                if (isRun) return cid;
            }
            const saved = gbgLoadSession();
            if (saved && saved.contId && runningMap[saved.contId]) {
                return saved.contId;
            }
            return '';
        }

        function gbgAttachToScan(contId, data) {
            if (!contId) return false;
            const saved = gbgLoadSession();
            const jurnalMap = data.jurnal_clienti || {};
            const runningMap = data.running || {};
            const jurnal = jurnalMap[contId] || [];
            const running = !!(runningMap[contId] || (data.active_cont_id === contId && data.active_running));

            gbgSelectSupplierByContId(contId, saved ? saved.supplierId : '');
            gbgActiveContId = contId;

            if (running) {
                gbgSaveSession(contId, document.getElementById('gbgRid').value.trim());
                gbgStartPoll(contId, jurnal, true);
                return true;
            }

            if (jurnal.length) {
                gbgRenderJournal(jurnal);
                gbgApplyScanState(false, jurnal);
            }
            return false;
        }

        function gbgRenderJournal(jurnal) {
            const box = document.getElementById('gbgStatusBox');
            if (!jurnal || !jurnal.length) {
                if (!gbgActiveContId) {
                    box.innerHTML = '<div class="gbg-log-line info">Selectează un furnizor și apasă lansare.</div>';
                } else {
                    box.innerHTML = '<div class="gbg-log-line info">Aștept pași de la robot...</div>';
                }
                return;
            }
            box.innerHTML = jurnal.map(line => {
                const lvl = line.level || 'info';
                return `<div class="gbg-log-line ${gbgEsc(lvl)}">[${gbgEsc(line.t || '')}] ${gbgEsc(line.msg || '')}</div>`;
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        function gbgApplyScanState(running, jurnal) {
            if (running) {
                gbgSetScanBadge('active');
            } else if (jurnal && jurnal.length) {
                const last = (jurnal[jurnal.length - 1].msg || '').toLowerCase();
                if (last.includes('oprit')) {
                    gbgSetScanBadge('stopped');
                } else if (last.includes('finalizata') || last.includes('terminata')) {
                    gbgSetScanBadge('idle');
                } else {
                    gbgSetScanBadge('active');
                }
            }
        }

        window.gbgSelectSupplierByContId = function (contId, supplierId) {
            if (supplierId) {
                const byId = document.querySelector('.gbg-supplier-card[data-id="' + supplierId + '"]');
                if (byId) {
                    gbgSelectSupplier(byId);
                    return;
                }
            }
            document.querySelectorAll('.gbg-supplier-card').forEach(card => {
                if ((card.getAttribute('data-cont-id') || '') === contId) {
                    gbgSelectSupplier(card);
                }
            });
        };

        function gbgMarkSupplierScanning(contId, active) {
            document.querySelectorAll('.gbg-supplier-card').forEach(card => {
                card.classList.toggle('is-scanning', active && (card.getAttribute('data-cont-id') || '') === contId);
            });
        }

        function gbgSetScanBadge(state) {
            const badge = document.getElementById('gbgScanBadge');
            const stopBtn = document.getElementById('gbgStopBtn');
            const launchBtn = document.getElementById('gbgLaunchBtn');
            if (!badge) return;
            badge.className = 'gbg-scan-badge ' + state;
            if (state === 'active') {
                badge.textContent = 'SCANARE ACTIVĂ';
                if (stopBtn) stopBtn.disabled = false;
                if (launchBtn) {
                    launchBtn.disabled = false;
                    launchBtn.title = 'Relansează scanarea (oprește sesiunea curentă dacă e blocată)';
                }
                gbgMarkSupplierScanning(gbgActiveContId, true);
            } else if (state === 'stopped') {
                badge.textContent = 'OPRIT';
                if (stopBtn) stopBtn.disabled = true;
                if (launchBtn) launchBtn.disabled = false;
                gbgMarkSupplierScanning(gbgActiveContId, false);
            } else {
                badge.textContent = 'INACTIV';
                if (stopBtn) stopBtn.disabled = true;
                if (launchBtn) launchBtn.disabled = false;
                gbgMarkSupplierScanning(gbgActiveContId, false);
            }
        }

        window.gbgResetForm = function () {
            document.getElementById('gbgRid').value = '';
            document.getElementById('gbgName').value = '';
            document.getElementById('gbgSiteUrl').value = '';
            document.getElementById('gbgContId').value = '';
            document.getElementById('gbgUser').value = '';
            document.getElementById('gbgPass').value = '';
            document.getElementById('gbgDeleteBtn').style.display = 'none';
            document.querySelectorAll('.gbg-supplier-card').forEach(c => c.classList.remove('is-active'));
            gbgActiveContId = '';
        };

        window.gbgSelectSupplier = function (el) {
            document.querySelectorAll('.gbg-supplier-card').forEach(c => c.classList.remove('is-active'));
            el.classList.add('is-active');
            document.getElementById('gbgRid').value = el.getAttribute('data-id') || '';
            document.getElementById('gbgName').value = el.getAttribute('data-name') || '';
            const siteEl = document.getElementById('gbgSiteUrl');
            if (siteEl) siteEl.value = el.getAttribute('data-site') || '';
            document.getElementById('gbgContId').value = el.getAttribute('data-cont-id') || '';
            document.getElementById('gbgUser').value = el.getAttribute('data-user') || '';
            document.getElementById('gbgPass').value = el.getAttribute('data-pass') || '';
            document.getElementById('gbgDeleteBtn').style.display = 'inline-block';
            gbgActiveContId = el.getAttribute('data-cont-id') || '';
            document.getElementById('gbgSaveBtn').textContent = 'Salvează modificări';
            gbgSaveSession(gbgActiveContId, el.getAttribute('data-id') || '');
        };

        document.getElementById('gbgSupplierForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const payload = {
                supplier_name: document.getElementById('gbgName').value.trim(),
                site_url: (document.getElementById('gbgSiteUrl') || {}).value ? document.getElementById('gbgSiteUrl').value.trim() : '',
                cont_id: document.getElementById('gbgContId').value.trim(),
                user: document.getElementById('gbgUser').value.trim(),
                pas: document.getElementById('gbgPass').value,
                ridusers: document.getElementById('gbgRid').value.trim(),
            };
            const res = await fetch(<?= json_encode($wb . 'api/gbg_suppliers.php', JSON_UNESCAPED_SLASHES) ?>, {
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

        window.gbgDeleteSupplier = async function () {
            const rid = document.getElementById('gbgRid').value;
            if (!rid || !confirm('Ștergi acest furnizor?')) return;
            const res = await fetch(<?= json_encode($wb . 'api/gbg_suppliers.php', JSON_UNESCAPED_SLASHES) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', randomn_id: rid }),
            });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            location.reload();
        };

        async function gbgCheckRobot() {
            const pill = document.getElementById('gbgRobotPill');
            try {
                const r = await gbgFetchJson('/status');
                if (!r.ok || !r.data) throw new Error('offline');
                pill.className = 'gbg-pill online';
                pill.textContent = 'Robot: ONLINE' + (r.via === 'local' ? ' (local)' : r.via === 'direct' ? ' (ngrok)' : '');
                return true;
            } catch (e) {
                pill.className = 'gbg-pill offline';
                pill.textContent = 'Robot: OFFLINE';
                return false;
            }
        }

        function gbgRobotOfflineHelp() {
            let msg = 'Robotul GBG nu răspunde.\n\n';
            if (ROBOT_CFG.is_local_admin) {
                msg += '1. Pe PC: rulează robot\\start_robot_hidden.vbs sau robot\\install_autostart.bat\n';
                msg += '2. Verifică în .env: ROBOT_FURNIZORI_URL=' + (ROBOT_CFG.configured_furnizori || 'http://127.0.0.1:5000') + '\n';
                msg += '3. Deschide în browser: ' + (ROBOT_CFG.configured_furnizori || 'http://127.0.0.1:5000') + '/status\n';
            } else {
                msg += '1. Pe PC-ul cu robotul: rulează robot\\install_autostart.bat\n';
                msg += '2. Admin pe blu-car.ro: ngrok http 5000 → pune în .env pe server:\n';
                msg += '   ROBOT_FURNIZORI_TUNNEL_URL=https://xxxx.ngrok-free.app\n';
                msg += '3. Sau folosește admin LOCAL (recomandat): http://blu-car.test/admin/?page=robot-monitor\n';
            }
            if (ROBOT_CFG.hint) msg += '\n\n' + ROBOT_CFG.hint;
            return msg;
        }

        async function gbgEnsureRobotRunning(maxWaitSec = 20) {
            const pill = document.getElementById('gbgRobotPill');
            if (await gbgCheckRobot()) return true;

            if (!ROBOT_CFG.is_local_admin && !ROBOT_CFG.direct_furnizori) {
                pill.className = 'gbg-pill offline';
                pill.textContent = 'Robot: OFFLINE (folosește admin local sau ngrok)';
                return false;
            }

            pill.textContent = 'Robot: pornesc automat...';
            try {
                await fetch(<?= json_encode($wb . 'api/robot_launcher.php', JSON_UNESCAPED_SLASHES) ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'start', robot: 'furnizori' }),
                    signal: typeof AbortSignal !== 'undefined' && AbortSignal.timeout
                        ? AbortSignal.timeout(10000) : undefined,
                });
            } catch (e) {}

            const started = Date.now();
            while ((Date.now() - started) / 1000 < maxWaitSec) {
                await new Promise(r => setTimeout(r, 1500));
                if (await gbgCheckRobot()) return true;
            }
            pill.className = 'gbg-pill offline';
            pill.textContent = 'Robot: OFFLINE — rulează robot\\start_robot_hidden.vbs';
            return false;
        }

        function gbgStartPoll(contId, initialJurnal, isRunning) {
            if (gbgPollTimer) clearInterval(gbgPollTimer);
            gbgActiveContId = contId;
            gbgLastJournalKey = '';
            gbgSaveSession(contId, document.getElementById('gbgRid').value.trim());
            if (initialJurnal && initialJurnal.length) {
                gbgRenderJournal(initialJurnal);
                gbgLastJournalKey = gbgJournalKey(initialJurnal);
                gbgApplyScanState(!!isRunning, initialJurnal);
            } else if (isRunning) {
                gbgSetScanBadge('active');
            }

            const poll = async () => {
                const r = await gbgFetchJson('/status?cont_id=' + encodeURIComponent(contId));
                if (!r.ok || !r.data) return;
                const data = r.data;
                const jurnal = (data.jurnal_clienti && data.jurnal_clienti[contId])
                    ? data.jurnal_clienti[contId]
                    : (data.jurnal || []);
                const running = (data.running && data.running[contId]) || data.is_running || data.active_running;
                const key = gbgJournalKey(jurnal);
                if (key !== gbgLastJournalKey) {
                    gbgLastJournalKey = key;
                    gbgRenderJournal(jurnal);
                    gbgStalePolls = 0;
                    gbgLastJournalPollKey = key;
                } else if (running) {
                    gbgStalePolls += 1;
                }
                if (data.deja_scanate !== undefined) gbgUpdateScanCount(data.deja_scanate);
                if (running && gbgStalePolls >= 25 && jurnal.length > 0 && jurnal.length < 8) {
                    const badge = document.getElementById('gbgScanBadge');
                    const launchBtn = document.getElementById('gbgLaunchBtn');
                    if (badge) {
                        badge.textContent = 'POSIBIL BLOCAT';
                        badge.className = 'gbg-scan-badge stopped';
                    }
                    if (launchBtn) launchBtn.title = 'Robot blocat — apasă Relansează sau Oprește';
                }
                gbgApplyScanState(running, jurnal);
                if (!running && jurnal.length) {
                    const last = (jurnal[jurnal.length - 1].msg || '').toLowerCase();
                    if (last.includes('finalizata') || last.includes('oprit') || last.includes('terminata')) {
                        clearInterval(gbgPollTimer);
                        gbgPollTimer = null;
                    }
                }
            };
            poll();
            gbgPollTimer = setInterval(poll, 1200);
        }

        async function gbgReconnectActiveScan() {
            const r = await gbgFetchJson('/status');
            if (!r.ok || !r.data) return false;
            const contId = gbgResolveContId(r.data);
            if (!contId) return false;
            return gbgAttachToScan(contId, r.data);
        }

        function gbgStartReconnectWatcher() {
            if (gbgReconnectTimer) clearInterval(gbgReconnectTimer);
            gbgReconnectTimer = setInterval(async () => {
                if (gbgPollTimer) return;
                await gbgReconnectActiveScan();
            }, 5000);
        }

        window.gbgStopScan = async function () {
            const contId = gbgActiveContId || document.getElementById('gbgContId').value.trim();
            if (!contId) return alert('Niciun cont activ.');
            if (!confirm('Oprești robotul și închizi Chrome?')) return;
            try {
                if (gbgPollTimer) {
                    clearInterval(gbgPollTimer);
                    gbgPollTimer = null;
                }
                const res = await gbgFetch('/stop', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cont_id: contId }),
                });
                const data = await res.json();
                gbgMarkSupplierScanning(contId, false);
                gbgSetScanBadge('stopped');
                const box = document.getElementById('gbgStatusBox');
                box.innerHTML += `<div class="gbg-log-line warn">[${new Date().toLocaleTimeString()}] ${gbgEsc(data.mesaj || 'Oprit')}</div>`;
                box.scrollTop = box.scrollHeight;
            } catch (e) {
                alert('Nu am putut opri robotul.');
            }
        };

        function gbgUpdateScanCount(n) {
            if (n === undefined || n === null) return;
            const el = document.getElementById('gbgScanCount');
            if (el) el.textContent = n;
        }

        async function gbgResetScanateInternal(contId, logMsg) {
            const res = await gbgFetch('/reset_scanate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cont_id: contId }),
            });
            const data = await res.json();
            gbgUpdateScanCount(0);
            const box = document.getElementById('gbgStatusBox');
            const line = logMsg || (data && data.mesaj) || 'Lista scanate golită.';
            box.innerHTML += `<div class="gbg-log-line warn">[${new Date().toLocaleTimeString()}] ${gbgEsc(line)}</div>`;
            box.scrollTop = box.scrollHeight;
            return data;
        }

        window.gbgResetScanate = async function () {
            const contId = gbgActiveContId || document.getElementById('gbgContId').value.trim();
            if (!contId) return alert('Selectează un furnizor / completează cont_id.');
            if (!confirm('Golești lista produselor deja scanate pentru „' + contId + '"?\nUrmătoarea scanare le va include din nou.')) return;
            try {
                await gbgResetScanateInternal(contId);
            } catch (e) {
                alert('Nu am putut reseta lista scanate.');
            }
        };

        /** Oprește robotul, golește lista scanate, setează de la produsul 1 și relansează scanarea. */
        window.gbgStartFromBeginning = async function () {
            const contId = gbgActiveContId || document.getElementById('gbgContId').value.trim();
            const user = document.getElementById('gbgUser').value.trim();
            const pass = document.getElementById('gbgPass').value;
            if (!contId || !user || !pass) {
                alert('Selectează furnizorul GBG (cont, user, parolă).');
                return;
            }
            if (!confirm(
                'Începi scanarea DE LA ÎNCEPUT?\n\n' +
                '• Se oprește robotul curent (dacă rulează)\n' +
                '• Se golește lista „deja scanate” (' + contId + ')\n' +
                '• Se setează: de la produsul 1, până la final\n\n' +
                'Produsele #1, #2, … vor fi scanate din nou (nu mai „sar peste”).'
            )) return;

            document.getElementById('gbgScanFrom').value = '1';
            document.getElementById('gbgScanTo').value = '0';

            try {
                await gbgFetch('/stop', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cont_id: contId }),
                });
            } catch (e) { /* robot poate fi deja oprit */ }

            try {
                await gbgResetScanateInternal(contId, 'Listă scanate golită — următoarea scanare începe de la produsul #1.');
            } catch (e) {
                alert('Nu am putut reseta lista scanate.');
                return;
            }

            await gbgLaunchFullScan();
        };

        window.gbgLaunchFullScan = async function () {
            if (gbgLaunchBusy) return;
            const contId = document.getElementById('gbgContId').value.trim();
            const user = document.getElementById('gbgUser').value.trim();
            const pass = document.getElementById('gbgPass').value;
            if (!contId || !user || !pass) {
                alert('Completează cont_id, user și parola GBG (sau selectează o cartelă furnizor).');
                return;
            }

            gbgSetLaunchBusy(true);
            gbgActiveContId = contId;
            document.getElementById('gbgStatusBox').innerHTML = '<div class="gbg-log-line info">Verific robot GBG...</div>';

            try {
                if (!(await gbgEnsureRobotRunning())) {
                    const help = gbgRobotOfflineHelp();
                    gbgLogStatus('Robot OFFLINE — vezi mesajul de mai jos.', 'err');
                    alert(help);
                    return;
                }

                const autoPieseauto = document.getElementById('gbgAutoPieseauto')?.checked !== false;
                if (autoPieseauto) {
                    gbgLogStatus('Verific serviciul PieseAuto (fără browser suplimentar)...', 'info');
                    fetch(ROBOT_LAUNCHER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'start', robot: 'pieseauto' }),
                    }).catch(() => {});
                    fetch(PIESEAUTO_AUTO_URL + '?action=prepare&wait_browser_sec=0&open_browser=0', { method: 'POST' })
                        .then(r => r.json())
                        .then(prep => {
                            const prepMsg = prep.message || 'PieseAuto pornit.';
                            gbgLogStatus('PieseAuto: ' + prepMsg, prep.success ? 'ok' : 'warn');
                        })
                        .catch(() => gbgLogStatus('PieseAuto: nu am putut porni (continuăm scanarea GBG).', 'warn'));
                }

                if (gbgPollTimer) {
                    clearInterval(gbgPollTimer);
                    gbgPollTimer = null;
                }

                const scanFrom = parseInt(document.getElementById('gbgScanFrom').value, 10) || 1;
                const scanTo = parseInt(document.getElementById('gbgScanTo').value, 10) || 0;
                const skipDup = document.getElementById('gbgSkipDup').checked;

                gbgLogStatus('Încarc pașii robot pentru furnizor...', 'info');
                let workflowPayload = null;
                try {
                    const wfRes = await fetch(<?= json_encode($wb . 'api/robot_workflow.php', JSON_UNESCAPED_SLASHES) ?> + '?cont_id=' + encodeURIComponent(contId));
                    const wfJson = await wfRes.json();
                    if (wfJson.success && wfJson.workflow) workflowPayload = wfJson.workflow;
                } catch (e) {}

                gbgLogStatus('Trimit comanda /comanda către robot...', 'info');
                const r = await gbgFetchJson('/comanda', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cont_id: contId, user: user, pass: pass,
                        site_url: (document.getElementById('gbgSiteUrl') || {}).value ? document.getElementById('gbgSiteUrl').value.trim() : '',
                        scan_from: scanFrom, scan_to: scanTo, skip_duplicate: skipDup,
                        workflow: workflowPayload,
                    }),
                });

                if (!r.ok || !r.data) {
                    const errMsg = (r.data && (r.data.mesaj || r.data.message))
                        || (r.status === 502 ? 'Proxy nu ajunge la robot (127.0.0.1 pe server).' : 'Răspuns invalid de la robot.');
                    gbgLogStatus('Eroare: ' + errMsg, 'err');
                    if (r.status === 502 || ROBOT_CFG.is_localhost_config) {
                        alert(gbgRobotOfflineHelp());
                    } else {
                        alert('Eroare la lansare: ' + errMsg);
                    }
                    return;
                }

                const data = r.data;
                if ((data.status || '').toLowerCase() === 'error') {
                    const errMsg = data.mesaj || data.message || 'Eroare robot';
                    gbgLogStatus('Eroare: ' + errMsg, 'err');
                    alert('Eroare la lansare: ' + errMsg);
                    return;
                }
                gbgStartPoll(contId);
                gbgSaveSession(contId, document.getElementById('gbgRid').value.trim());
                const intervalTxt = scanTo ? ('produsele ' + scanFrom + '–' + scanTo) : ('de la produsul ' + scanFrom + ' până la final');
                gbgRenderJournal([{
                    t: new Date().toLocaleTimeString(),
                    msg: 'Pas 0: Lansat scanare (' + intervalTxt + (skipDup ? ', fără dubluri' : '') + ') → ' + (data.site_api || data.mesaj || 'robot'),
                    level: 'ok',
                }]);
                gbgSetScanBadge('active');
            } catch (e) {
                gbgLogStatus('Eroare conexiune: ' + String(e), 'err');
                alert('Eroare la trimiterea comenzii.\n\n' + gbgRobotOfflineHelp());
            } finally {
                gbgSetLaunchBusy(false);
            }
        };

        async function gbgInitPage() {
            const reconnected = await gbgReconnectActiveScan();
            if (!reconnected) {
                await gbgEnsureRobotRunning();
                await gbgReconnectActiveScan();
            } else {
                gbgCheckRobot();
            }

            const saved = gbgLoadSession();
            if (saved && !document.querySelector('.gbg-supplier-card.is-active')) {
                gbgSelectSupplierByContId(saved.contId, saved.supplierId || '');
            }

            gbgStartReconnectWatcher();
        }

        gbgInitPage();
        setInterval(gbgCheckRobot, 15000);
    })();
    </script>
    <?php
}
