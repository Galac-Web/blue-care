<?php
declare(strict_types=1);

/**
 * Panou Atelier Publicare PieseAuto — layout 3 stații + panou de bord.
 */
function blu_render_pieseauto_panel(array $accounts): void
{
    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    require_once __DIR__ . '/scanned_products.php';
    require_once __DIR__ . '/robot_config.php';
    // Optimizare încărcare pagină:
    // blu_pieseauto_scanned_items() face clasificare + sortare și poate fi costisitor.
    // Lista completă o încărcăm asincron în JS prin `api/scanned_products.php`,
    // ca pagina să se afișeze rapid (nu blocăm renderul PHP).
    $scannedProducts = [];
    $scannedCount = 0;
    $robotPaUrl = blu_robot_pieseauto_base_url();
    $robotPaCfg = blu_robot_admin_js_config();
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .pa-atelier {
            --pa-teal: #308e87;
            --pa-teal-soft: rgba(48,142,135,.12);
            --pa-bg: #f4f7f6;
            --pa-ink: #1e293b;
            --pa-muted: #64748b;
            max-width: 100%;
            overflow-x: clip;
        }
        .pa-dash { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
        .pa-dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .85rem 1rem;
            align-items: end;
        }
        .pa-dash-actions {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .pa-dash-url {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .72rem;
        }
        .pa-dash-label { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--pa-muted); margin-bottom: .35rem; }
        .pa-dash-value { font-size: .95rem; font-weight: 700; color: var(--pa-ink); word-break: break-word; }
        .pa-flow { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eef2f7; }
        .pa-flow-step { font-size: .75rem; font-weight: 700; padding: .35rem .75rem; border-radius: 999px; background: #f1f5f9; color: var(--pa-muted); white-space: nowrap; }
        .pa-flow-step.is-done { background: var(--pa-teal-soft); color: var(--pa-teal); }
        .pa-flow-arrow { color: #cbd5e1; font-size: .8rem; }
        .pa-station { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; margin-bottom: 1rem; max-width: 100%; }
        .pa-station-head { padding: .85rem 1rem; background: #fafbfc; border-bottom: 1px solid #eef2f7; border-left: 4px solid var(--pa-teal); }
        .pa-station-title { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--pa-muted); margin: 0; }
        .pa-station-sub { font-size: .78rem; color: #94a3b8; margin: .15rem 0 0; }
        .pa-station-body { padding: 1rem; }
        .pa-station--wide .pa-station-head { border-left-width: 4px; }
        .pa-btn-teal { background: var(--pa-teal); border-color: var(--pa-teal); color: #fff; font-weight: 700; }
        .pa-btn-teal:hover { background: #267a74; border-color: #267a74; color: #fff; }
        .pa-btn-outline-teal { border: 1px solid var(--pa-teal); color: var(--pa-teal); background: #fff; font-weight: 700; }
        .pa-btn-outline-teal:hover { background: var(--pa-teal-soft); color: var(--pa-teal); }
        .pa-account-pills { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
        .pa-pill { border: 1px solid #dbe3ea; background: #fff; border-radius: 999px; padding: .35rem .85rem; font-size: .78rem; font-weight: 700; color: var(--pa-ink); cursor: pointer; transition: .15s ease; max-width: 100%; overflow: hidden; text-overflow: ellipsis; }
        .pa-pill:hover { border-color: var(--pa-teal); color: var(--pa-teal); }
        .pa-pill.is-active { background: var(--pa-teal-soft); border-color: var(--pa-teal); color: var(--pa-teal); }
        .pa-pill--new { border-style: dashed; color: var(--pa-muted); }
        #consoleBox { background: #0b1220; border-radius: 12px; padding: 14px; min-height: 140px; max-height: min(200px, 28vh); overflow-y: auto; border: 1px solid #1e293b; }
        .console-line { font-family: 'Consolas', 'Fira Code', monospace; font-size: .78rem; margin-bottom: 5px; border-left: 2px solid var(--pa-teal); padding-left: 8px; color: #86efac; word-break: break-word; }
        .pulse-online { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; animation: pa-pulse 2s infinite; flex-shrink: 0; }
        @keyframes pa-pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.7); } 70% { box-shadow: 0 0 0 7px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .pa-inventory { max-height: min(320px, 38vh); overflow-y: auto; padding-right: 4px; }
        .scan-item { transition: .15s ease; cursor: pointer; border-left: 3px solid transparent !important; }
        .scan-item:hover { background: var(--pa-teal-soft) !important; border-color: #dbe3ea !important; }
        .scan-selected { background: var(--pa-teal-soft) !important; border-color: var(--pa-teal) !important; border-left-color: var(--pa-teal) !important; }
        .pa-preview-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; background: #fafbfc; margin-bottom: 1rem; }
        .pa-preview-img { width: 72px; height: 72px; object-fit: contain; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; flex-shrink: 0; }
        .pa-preview-img-empty { width: 72px; height: 72px; border-radius: 10px; border: 1px dashed #cbd5e1; background: #fff; display: flex; align-items: center; justify-content: center; font-size: .7rem; color: #94a3b8; flex-shrink: 0; }
        .pa-form-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pa-muted); margin-bottom: .3rem; }
        .pa-history-box { max-height: min(120px, 18vh); overflow-y: auto; border: 1px solid #eef2f7; border-radius: 10px; padding: .5rem; background: #fafbfc; }
        .pa-split { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 1rem; align-items: start; }
        .pa-split > div { min-width: 0; }
        .pa-form-row-price .col-4 { min-width: 0; }
        @media (max-width: 1599px) {
            .pa-split { grid-template-columns: 1fr; }
            .pa-inventory { max-height: min(260px, 32vh); }
        }
        @media (max-width: 991px) {
            .pa-dash-grid { grid-template-columns: 1fr 1fr; }
            .pa-dash-actions { grid-column: 1 / -1; justify-content: stretch; }
            .pa-dash-actions .btn { flex: 1 1 auto; }
            .pa-flow .ms-auto { margin-left: 0 !important; width: 100%; text-align: center; }
        }
        @media (max-width: 575px) {
            .pa-dash-grid { grid-template-columns: 1fr; }
            .pa-station-body { padding: .75rem; }
            .pa-form-row-price > [class*="col-"] { flex: 0 0 100%; max-width: 100%; }
        }

        /* ── Ovoko popup progress ── */
        .pa-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.55);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .pa-modal-overlay.is-open { display: flex; }
        .pa-modal {
            width: min(520px, 100%);
            border-radius: 16px;
            background: #0b1220;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 20px 70px rgba(0,0,0,0.35);
            overflow: hidden;
        }
        .pa-modal__head {
            padding: .9rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: rgba(37, 99, 235, 0.12);
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }
        .pa-modal__title {
            color: #e2e8f0;
            font-weight: 800;
            margin: 0;
            font-size: .95rem;
        }
        .pa-modal__body { padding: 1rem; }
        .pa-modal__msg {
            color: rgba(226,232,240,0.92);
            font-size: .86rem;
            font-weight: 650;
            margin-bottom: .75rem;
        }
        .pa-modal__msg.is-error { color: #fb7185; }
        .pa-modal__msg.is-ok { color: #4ade80; }
        .pa-progress {
            height: 12px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.14);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }
        .pa-progress__bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #2563eb 0%, #0891b2 100%);
            transition: width .25s ease;
        }
        .pa-modal__foot {
            display: flex;
            justify-content: flex-end;
            padding: .8rem 1rem;
            gap: .5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(255,255,255,0.02);
        }
        .pa-modal__btn {
            border-radius: 10px;
            font-weight: 800;
        }
        @media (max-width: 575px) {
            .pa-modal__head { padding: .75rem .85rem; }
            .pa-modal__body { padding: .85rem; }
        }

        /* Buton “Închide” cu text negru (override peste Bootstrap outline-light) */
        #ovokoProgressCloseBtn {
            color: #0b1220 !important;
            background: #ffffff !important;
            border-color: rgba(148, 163, 184, 0.55) !important;
        }
    </style>

    <div class="pa-atelier">
        <!-- Ovoko popup modal -->
        <div class="pa-modal-overlay" id="ovokoProgressModal" aria-hidden="true">
            <div class="pa-modal" role="dialog" aria-modal="true" aria-labelledby="ovokoProgressTitle">
                <div class="pa-modal__head">
                    <h3 class="pa-modal__title" id="ovokoProgressTitle">Autodoc — completare din sursă</h3>
                </div>
                <div class="pa-modal__body">
                    <div class="pa-modal__msg" id="ovokoProgressMsg">Se pregătește...</div>
                    <div class="pa-progress" aria-label="Progres">
                        <div class="pa-progress__bar" id="ovokoProgressBar"></div>
                    </div>
                </div>
                <div class="pa-modal__foot">
                    <button type="button" class="btn btn-sm btn-warning pa-modal__btn" id="ovokoRescanBtn" onclick="completePieseFromOvoko(true)" hidden>
                        Scanează din nou
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light pa-modal__btn" id="ovokoProgressCloseBtn" onclick="hideOvokoProgressModal()">
                        Închide
                    </button>
                </div>
            </div>
        </div>

        <!-- Stația 0: Panou de bord -->
        <div class="pa-dash">
            <div class="pa-dash-grid">
                <div>
                    <div class="pa-dash-label">Robot Python</div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="server-status-dot" class="pulse-online"></span>
                        <span class="pa-dash-value" id="globalStatus">Verificare...</span>
                    </div>
                    <div class="small text-muted mt-1">PieseAuto · <code class="pa-dash-url" title="<?= htmlspecialchars($robotPaUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($robotPaUrl, ENT_QUOTES) ?></code></div>
                </div>
                <div>
                    <div class="pa-dash-label">Cont activ</div>
                    <div class="pa-dash-value" id="dashActiveAccount">—</div>
                </div>
                <div>
                    <div class="pa-dash-label">Target</div>
                    <div class="pa-dash-value" id="dashTarget">bluecar</div>
                </div>
                <div>
                    <div class="pa-dash-label">Magazie</div>
                    <div class="pa-dash-value"><span id="scanate_count"><?= (int)$scannedCount ?></span> piese</div>
                </div>
                <div class="pa-dash-actions">
                    <button class="btn btn-sm pa-btn-outline-teal" type="button" onclick="startRobot()">Pornește</button>
                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="stopTotalRobot()">Stop</button>
                </div>
            </div>
            <div class="pa-flow">
                <span class="pa-flow-step" id="flowStep1">1. Poarta cont</span>
                <span class="pa-flow-arrow">→</span>
                <span class="pa-flow-step" id="flowStep2">2. Robot browser</span>
                <span class="pa-flow-arrow">→</span>
                <span class="pa-flow-step" id="flowStep3">3. Anunț publicat</span>
                <span class="ms-auto badge bg-secondary" id="bot-status-label">INACTIV</span>
            </div>
        </div>

        <div class="row g-3">
            <!-- Stânga: Stația 1 + 2 -->
            <div class="col-12 col-xxl-4">
                <div class="pa-station">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 1 · Poarta cont</p>
                        <p class="pa-station-sub">Login PieseAuto și conturi salvate</p>
                    </div>
                    <div class="pa-station-body">
                        <form id="addpieseauto" data-endpoint="<?= htmlspecialchars($wb . 'api/pieseauto_accounts.php', ENT_QUOTES, 'UTF-8') ?>" data-method="POST">
                            <div class="mb-2">
                                <label class="pa-form-label">Firmă</label>
                                <input type="text" name="company_name" class="form-control form-control-sm" id="accCompanyName" placeholder="Ex: Blue-Car SRL" autocomplete="organization">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="pa-form-label">Email site</label>
                                    <input type="text" name="email" class="form-control form-control-sm" id="accEmail" autocomplete="username">
                                </div>
                                <div class="col-6">
                                    <label class="pa-form-label">Parolă</label>
                                    <input type="password" name="pas" class="form-control form-control-sm" id="accPass" autocomplete="current-password">
                                </div>
                                <input type="hidden" name="type_product" value="add">
                                <input type="hidden" name="ridusers" id="ridusers" value="">
                            </div>
                            <div class="mb-2">
                                <label class="pa-form-label">Utilizator target</label>
                                <input type="text" id="target-user" class="form-control form-control-sm" value="bluecar" placeholder="Ex: bluecar">
                            </div>
                            <button class="btn btn-sm w-100 pa-btn-teal mb-2" type="submit" id="btnSaveAccount">AUTENTIFICARE &amp; SALVARE</button>
                        </form>

                        <label class="pa-form-label">Conturi salvate</label>
                        <div class="pa-account-pills" id="accountPills">
                            <?php foreach ($accounts as $id => $info): ?>
                                <button type="button" class="pa-pill"
                                        data-client-id="<?= htmlspecialchars($id, ENT_QUOTES) ?>"
                                        data-id="<?= htmlspecialchars((string)$info['id'], ENT_QUOTES) ?>"
                                        data-name="<?= htmlspecialchars($info['name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($info['email'], ENT_QUOTES) ?>"
                                        data-pass="<?= htmlspecialchars($info['pass'], ENT_QUOTES) ?>"
                                        onclick="selectAccountPill(this)">
                                    <?= htmlspecialchars($info['label'], ENT_QUOTES) ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="pa-pill pa-pill--new" onclick="resetAccountFormForNew()">+ Cont nou</button>
                        </div>

                        <div class="d-flex gap-2 mt-2" id="accountManageBtns" style="display:none !important;">
                            <button type="button" class="btn btn-sm pa-btn-outline-teal flex-fill" onclick="salveazaContSelectat()">
                                <i class="bi bi-pencil-square"></i> Salvează
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger flex-fill" onclick="stergeContPieseauto()">
                                <i class="bi bi-trash"></i> Șterge
                            </button>
                        </div>

                        <!-- select ascuns pentru compatibilitate JS vechi -->
                        <select class="d-none" id="clientSelect" onchange="fillFieldsFromSelect()">
                            <option value="" selected>—</option>
                            <?php foreach ($accounts as $id => $info): ?>
                                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"
                                        data-id="<?= htmlspecialchars((string)$info['id'], ENT_QUOTES) ?>"
                                        data-name="<?= htmlspecialchars($info['name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($info['email'], ENT_QUOTES) ?>"
                                        data-pass="<?= htmlspecialchars($info['pass'], ENT_QUOTES) ?>"><?= htmlspecialchars($info['label'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pa-station">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 2 · Bandă robot</p>
                        <p class="pa-station-sub">Consolă și lansare browser</p>
                    </div>
                    <div class="pa-station-body">
                        <div id="consoleBox">
                            <div id="consoleText">
                                <div class="console-line">Sistem gata. Selectează un cont și lansează robotul.</div>
                            </div>
                        </div>
                        <button class="btn btn-sm w-100 pa-btn-teal mt-3" type="button" onclick="startRobot()">
                            <i class="bi bi-cpu-fill me-1"></i> Lansează browser robot
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dreapta: Stația 3 Magazie → Anunț -->
            <div class="col-12 col-xxl-8">
                <div class="pa-station pa-station--wide">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 3 · Magazie → Anunț</p>
                        <p class="pa-station-sub">Inventar scanat stânga · fișă publicare dreapta</p>
                    </div>
                    <div class="pa-station-body">
                        <div class="pa-split">
                            <!-- Inventar -->
                            <div>
                                <label class="pa-form-label">Inventar scanat</label>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" id="scan_search" class="form-control" placeholder="Caută în magazie...">
                                    <button class="btn pa-btn-teal" type="button" onclick="incarcaProduseScanate(true)" title="Reîmprospătează">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div id="scanate_list" class="pa-inventory border rounded-3 bg-white p-2">
                                    <div class="text-muted small">Se încarcă magazia...</div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button class="btn btn-sm pa-btn-outline-teal flex-fill" type="button" onclick="pornesteAutoProduse()">
                                        <i class="bi bi-play-fill"></i> Auto coadă
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary flex-fill" type="button" onclick="opresteAutoProduse()">Stop auto</button>
                                </div>
                                <div class="pa-history-box mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="pa-form-label mb-0">Procesate</span>
                                        <button class="btn btn-link btn-sm p-0 text-danger" type="button" onclick="stergeIstoricAuto()">Șterge</button>
                                    </div>
                                    <div id="auto_history">
                                        <div class="text-muted small">Niciun produs procesat.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fișă anunț -->
                            <div>
                                <label class="pa-form-label">Fișă anunț — preview</label>
                                <div class="pa-preview-card">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div id="preview_card_img_wrap">
                                            <div class="pa-preview-img-empty" id="preview_card_img_empty">Fără img</div>
                                            <img id="preview_card_img" class="pa-preview-img d-none" src="" alt="">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-break" id="preview_card_title">Selectează o piesă din magazie</div>
                                            <div class="small text-muted mt-1" id="preview_card_meta">—</div>
                                            <div class="fw-bold text-success mt-2" id="preview_card_price">— LEI</div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="scanat_id_selectat">
                                <div class="mb-2">
                                    <label class="pa-form-label">Titlu anunț</label>
                                    <input type="text" id="piesa_titlu" class="form-control form-control-sm" placeholder="Titlu anunț">
                                </div>
                                <div class="mb-2">
                                    <label class="pa-form-label">Descriere</label>
                                    <textarea id="piesa_descriere" class="form-control form-control-sm" rows="3" placeholder="Descriere detaliată..."></textarea>
                                    <div class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                                    <button class="btn btn-sm pa-btn-outline-teal" type="button" id="ovokoFillBtn" onclick="completePieseFromOvoko(false)">Completează din Autodoc24</button>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2 pa-form-row-price">
                                    <div class="col-sm-4 col-12">
                                        <label class="pa-form-label">Preț (LEI)</label>
                                        <input type="number" id="piesa_pret" class="form-control form-control-sm" value="100">
                                    </div>
                                    <div class="col-sm-4 col-12">
                                        <label class="pa-form-label">Stare</label>
                                        <select id="piesa_stare" class="form-select form-select-sm">
                                            <option value="Second" selected>Second Hand</option>
                                            <option value="Nou">Nou</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 col-12">
                                        <label class="pa-form-label">Categorie</label>
                                        <input type="text" id="piesa_cat_nume" class="form-control form-control-sm" value="Alte piese de caroserie" placeholder="Subcategorie PieseAuto.ro">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="pa-form-label">Imagini</label>
                                    <div id="imagini_multiple"></div>
                                </div>
                                <div id="preview_scanat" class="d-none">
                                    <img id="preview_scanat_img" src="" alt="">
                                </div>
                                <div class="d-flex gap-2 justify-content-end mt-3">
                                    <button class="btn btn-sm btn-link text-danger" type="button" onclick="stopTotalRobot()">Stop total</button>
                                    <button class="btn btn-sm pa-btn-teal px-4" type="button" onclick="trimitePiesaNoua()">
                                        <i class="bi bi-send-fill me-1"></i> Publică în browser
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const API_URL = <?= json_encode($wb . 'robot_pieseauto_proxy.php', JSON_UNESCAPED_SLASHES) ?>;
    const ROBOT_LAUNCHER = <?= json_encode($wb . 'api/robot_launcher.php', JSON_UNESCAPED_SLASHES) ?>;
    const SCANNED_API = <?= json_encode($wb . 'api/scanned_products.php', JSON_UNESCAPED_SLASHES) ?>;
    const OVOKO_SCRAPE_API = <?= json_encode($wb . 'api/pieseauto_autodoc_scrape.php', JSON_UNESCAPED_SLASHES) ?>;
    const ROBOT_PA_CFG = <?= json_encode([
        'direct_pieseauto' => $robotPaCfg['direct_pieseauto'] ?? '',
        'configured' => $robotPaUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.produseScanateAll = <?= json_encode($scannedProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.produseScanate = [];
    const ngrokHeaders = { "Content-Type": "application/json", "ngrok-skip-browser-warning": "69420" };
    let statusTimer = null;

    function robotUrl(path) { return API_URL + '?path=' + encodeURIComponent(path); }

    async function robotJson(res, label) {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            const preview = text.replace(/\s+/g, ' ').trim().slice(0, 120);
            throw new Error((label || 'Robot') + ' a returnat HTML, nu JSON: ' + preview);
        }
    }

    async function robotFetch(path, options = {}, timeoutMs = 12000) {
        const headers = Object.assign({}, ngrokHeaders, options.headers || {});
        const opts = Object.assign({}, options, { headers });
        const directBase = ROBOT_PA_CFG.direct_pieseauto || '';

        async function doFetch(url, ms) {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), ms);
            try {
                return await fetch(url, Object.assign({}, opts, { signal: ctrl.signal }));
            } finally {
                clearTimeout(timer);
            }
        }

        if (directBase) {
            try {
                const res = await doFetch(directBase + path, timeoutMs);
                if (res.ok) return res;
            } catch (e) { /* fallback la proxy */ }
        }

        return doFetch(robotUrl(path), timeoutMs);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function updateDashboard() {
        const company = document.getElementById('accCompanyName')?.value.trim() || '—';
        const target = document.getElementById('target-user')?.value.trim() || '—';
        const dashAcc = document.getElementById('dashActiveAccount');
        const dashTgt = document.getElementById('dashTarget');
        if (dashAcc) dashAcc.textContent = company !== '' ? company : '—';
        if (dashTgt) dashTgt.textContent = target;
        const hasAccount = !!document.getElementById('ridusers')?.value || !!document.getElementById('accEmail')?.value;
        const s1 = document.getElementById('flowStep1');
        if (s1) s1.classList.toggle('is-done', hasAccount);
    }

    function updateLivePreview() {
        const titlu = document.getElementById('piesa_titlu')?.value.trim() || 'Selectează o piesă din magazie';
        const pret = document.getElementById('piesa_pret')?.value || '—';
        const stare = document.getElementById('piesa_stare');
        const stareTxt = stare ? stare.options[stare.selectedIndex].text : '';
        const cat = document.getElementById('piesa_cat_nume')?.value.trim() || '—';
        const pt = document.getElementById('preview_card_title');
        const pm = document.getElementById('preview_card_meta');
        const pp = document.getElementById('preview_card_price');
        if (pt) pt.textContent = titlu;
        if (pm) pm.textContent = stareTxt + ' · ' + cat;
        if (pp) pp.textContent = pret + ' LEI';
    }

    function setPreviewImage(url) {
        const img = document.getElementById('preview_card_img');
        const empty = document.getElementById('preview_card_img_empty');
        const legacy = document.getElementById('preview_scanat_img');
        if (url) {
            if (img) { img.src = url; img.classList.remove('d-none'); }
            if (empty) empty.classList.add('d-none');
            if (legacy) legacy.src = url;
        } else {
            if (img) { img.src = ''; img.classList.add('d-none'); }
            if (empty) empty.classList.remove('d-none');
            if (legacy) legacy.src = '';
        }
    }

    function seteazaImaginiMultiple(images) {
        const container = document.getElementById('imagini_multiple');
        container.innerHTML = '';
        if (!images || images.length === 0) {
            container.innerHTML = '<div class="text-muted small">Nu există imagini</div>';
            return;
        }
        images.forEach((img, index) => {
            const url = typeof img === 'string' ? img : (img.url || '');
            const div = document.createElement('div');
            div.className = 'input-group input-group-sm mb-2';
            div.innerHTML = `<span class="input-group-text">${index+1}</span><input type="text" class="form-control img-input" value="${escapeHtml(url)}"><a href="${escapeHtml(url)}" target="_blank" class="btn btn-outline-secondary btn-sm">↗</a>`;
            container.appendChild(div);
        });
    }

    function filtreazaProduseScanate(items, q) {
        const needle = String(q || '').trim().toLowerCase();
        if (!needle) return items;
        return items.filter(item => [item.title, item.car_brand, item.category_name, item.description].join(' ').toLowerCase().includes(needle));
    }

    function afiseazaProduseScanate(items, autoSelectFirst = false) {
        const box = document.getElementById('scanate_list');
        const countEl = document.getElementById('scanate_count');
        window.produseScanate = Array.isArray(items) ? items : [];
        if (countEl) countEl.textContent = String(window.produseScanate.length);
        if (!window.produseScanate.length) {
            box.innerHTML = '<div class="text-muted small p-2">Magazia e goală. <a href="?page=imported">Mergi la Importate</a> pentru a adăuga piese.</div>';
            return;
        }
        box.innerHTML = '';
        window.produseScanate.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'border rounded-2 p-2 mb-2 bg-light scan-item';
            const title = item.title || 'Fără titlu';
            const price = item.price || 0;
            const category = item.category_name || item.category_full || '';
            const brand = item.car_brand || '';
            const image = item.image_url || '';
            row.innerHTML = `<div class="d-flex gap-2 align-items-center">
                <div style="width:44px;height:44px;flex:0 0 44px;">${image ? `<img src="${escapeHtml(image)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">` : `<div style="width:44px;height:44px;border-radius:8px;border:1px dashed #cbd5e1;background:#fff;"></div>`}</div>
                <div class="flex-grow-1 overflow-hidden"><div class="fw-bold small text-truncate">${escapeHtml(title)}</div><div class="small text-muted text-truncate">${escapeHtml(brand)} · ${escapeHtml(category)}</div></div>
                <div class="small fw-bold text-success">${escapeHtml(String(price))}</div></div>`;
            row.onclick = function() {
                document.querySelectorAll('#scanate_list .scan-item').forEach(el => el.classList.remove('scan-selected'));
                row.classList.add('scan-selected');
                selecteazaProdusScannat(item);
            };
            box.appendChild(row);
            if (autoSelectFirst && index === 0) { row.classList.add('scan-selected'); selecteazaProdusScannat(item); }
        });
    }

    async function incarcaProduseScanate(refreshFromServer = false) {
        const q = document.getElementById('scan_search').value.trim();
        const box = document.getElementById('scanate_list');
        if (!refreshFromServer && window.produseScanateAll?.length) {
            afiseazaProduseScanate(filtreazaProduseScanate(window.produseScanateAll, q), q === '' && !refreshFromServer);
            return;
        }
        box.innerHTML = '<div class="text-muted small p-2">Se încarcă magazia...</div>';
        try {
            const res = await fetch(SCANNED_API + '?q=' + encodeURIComponent(q) + '&limit=200');
            const data = await res.json();
            if (!data || data.status !== 'ok' || !Array.isArray(data.items)) {
                box.innerHTML = '<div class="text-muted small p-2">Nu s-au găsit produse.</div>';
                return;
            }
            window.produseScanateAll = data.items;
            afiseazaProduseScanate(data.items, q === '');
        } catch (e) {
            box.innerHTML = '<div class="text-danger small p-2">Eroare la încărcare.</div>';
        }
    }

    function selecteazaProdusScannat(item) {
        document.getElementById('scanat_id_selectat').value = item.id || '';
        document.getElementById('piesa_titlu').value = item.title || '';
        document.getElementById('piesa_descriere').value = item.description || '';
        document.getElementById('piesa_pret').value = item.price || 100;
        document.getElementById('piesa_cat_nume').value = item.pieseauto_category || item.sub_category || item.category_name || '';
        if (item.images?.length) seteazaImaginiMultiple(item.images);
        else if (item.image_url) seteazaImaginiMultiple([item.image_url]);
        else seteazaImaginiMultiple([]);
        setPreviewImage(item.image_url || '');
        updateLivePreview();
        window.__pieseautoSelectedItem = item || {};
    }

    async function completePieseFromOvoko(forceRescan = false) {
        const btn = document.getElementById('ovokoFillBtn');
        const it = window.__pieseautoSelectedItem || {};
        const descrEl = document.getElementById('piesa_descriere');
        const descr = descrEl ? String(descrEl.value || '') : '';

        // OEM: din descriere (Cod OE: ...) sau fallback it.cod_oem
        let oem = '';
        const mCod = descr.match(/Cod OE:\s*([0-9A-Za-z.\-]+)\s*\/?/u);
        if (mCod && mCod[1]) {
            oem = String(mCod[1]).trim();
        }
        if (!oem) {
            oem = String(it.cod_oem || '').trim();
        }
        if (oem.includes('/')) {
            oem = oem.split('/')[0];
        }
        oem = String(oem).trim().match(/[0-9A-Za-z][0-9A-Za-z.\-]*/u)?.[0] ?? String(oem).trim();
        oem = String(oem).trim();
        if (!oem) {
            showOvokoProgressModal('Nu există Cod OEM (cod_oem) pentru produsul selectat.', true);
            return;
        }

        if (!forceRescan && descr.includes('Cod OE:') && descr.includes('cod INT:') && descr.includes('COMPATIBIL CU:')) {
            showOvokoProgressModal('Acest produs pare deja completat. Poți apăsa „Scanează din nou” dacă vrei rescriere.', true);
            return;
        }

        // Ascunde butonul de rescan la pornire.
        setOvokoRescanVisible(false);

        // Previne dublu-click / cereri concurente.
        if (window.__ovokoScrapeBusy) return;
        window.__ovokoScrapeBusy = true;

        if (btn) { btn.disabled = true; btn.textContent = 'Se încarcă...'; }

        showOvokoProgressModal('Pornesc căutarea pe Autodoc24...', false);
        setOvokoProgress(8);

        // Progres “live” (simulat) până vine răspunsul, ca să se vadă clar ce se întâmplă.
        let __ovokoProgTimer = null;
        let __ovokoStepTimers = [];
        let __ovokoToken = (window.__ovokoScrapeToken = (window.__ovokoScrapeToken || 0) + 1);

        const startFakeProgress = () => {
            if (__ovokoProgTimer) clearInterval(__ovokoProgTimer);
            __ovokoProgTimer = setInterval(() => {
                // Nu depășim ~94% înainte de final, ca să nu “sară” direct la 100.
                const cur = getOvokoProgress();
                const next = Math.min(94, cur + Math.max(2, Math.random() * 9));
                setOvokoProgress(next);
            }, 520);
        };
        const scheduleStep = (delayMs, fn) => {
            const t = setTimeout(() => {
                if (__ovokoToken !== window.__ovokoScrapeToken) return;
                fn();
            }, delayMs);
            __ovokoStepTimers.push(t);
        };

        startFakeProgress();
        scheduleStep(900, () => setOvokoProgressMsg('Caut pe Autodoc24 după Cod OEM…'));
        scheduleStep(2400, () => setOvokoProgress(38));
        scheduleStep(3600, () => setOvokoProgressMsg('Preiau detaliile și formatez descrierea…'));
        scheduleStep(5200, () => setOvokoProgress(68));

        try {
            setOvokoProgressMsg('Execut cererea către API…');
            const it = window.__pieseautoSelectedItem || {};
            const res = await fetch(OVOKO_SCRAPE_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    oem,
                    brand: it.car_brand || '',
                    model: it.car_model || '',
                    cod_articol: it.cod_articol || '',
                    coduri_oem: it.coduri_oem || '',
                    description: descr,
                }),
            });
            const raw = await res.text().catch(() => '');
            let data = null;
            try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }
            if (!data || data.ok !== true) {
                const apiErr = data && data.error ? data.error : '';
                const rawPreview = raw ? raw.slice(0, 220).replace(/\s+/g,' ').trim() : '';
                throw new Error(apiErr || (rawPreview ? ('Răspuns invalid API: ' + rawPreview) : 'Eroare la scraping Autodoc24.'));
            }

            setOvokoProgress(92);
            setOvokoProgressMsg('Completez câmpurile din fișă…');
            if (data.title) document.getElementById('piesa_titlu').value = data.title;
            if (data.description) document.getElementById('piesa_descriere').value = data.description;
            updateLivePreview();

            setOvokoProgress(100);
            setOvokoProgressMsg('Gata. Text completat din Autodoc24.', 'ok');
            logAuto('Autodoc completat pentru Cod OEM: ' + oem, '#86efac');
        } catch (e) {
            const msg = (e && e.message) ? e.message : String(e);
            logAuto('Autodoc eșuat: ' + msg, '#f87171');
            setOvokoProgress(100);
            setOvokoProgressMsg('Eroare: ' + msg, 'error');
        } finally {
            __ovokoToken = null;
            if (__ovokoProgTimer) clearInterval(__ovokoProgTimer);
            __ovokoProgTimer = null;
            for (const t of __ovokoStepTimers) clearTimeout(t);
            __ovokoStepTimers = [];

            if (btn) { btn.disabled = false; btn.textContent = 'Completează din Autodoc24'; }
            window.__ovokoScrapeBusy = false;

            // Închide automat doar când a reușit (nu în eroare).
            const m = document.getElementById('ovokoProgressMsg')?.textContent || '';
            if (m.toLowerCase().includes('gata')) {
                setTimeout(() => hideOvokoProgressModal(), 900);
            } else {
                // În eroare, lăsăm modalul deschis ca să vadă mesajul.
            }
        }
    }

    function getOvokoProgress() {
        const bar = document.getElementById('ovokoProgressBar');
        if (!bar) return 0;
        const w = String(bar.style.width || '0%');
        return parseFloat(w.replace('%', '')) || 0;
    }

    function showOvokoProgressModal(msg, isError) {
        const modal = document.getElementById('ovokoProgressModal');
        const m = document.getElementById('ovokoProgressMsg');
        if (!modal || !m) return;
        m.classList.remove('is-error');
        m.classList.remove('is-ok');
        if (isError) m.classList.add('is-error');
        m.textContent = msg || 'Se pregătește...';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setOvokoProgress(0);
        // La mesaje de tip “already completed”, arătăm butonul de rescan.
        if (isError) {
            setOvokoRescanVisible(true);
        } else {
            setOvokoRescanVisible(false);
        }
    }

    function setOvokoRescanVisible(isVisible) {
        const b = document.getElementById('ovokoRescanBtn');
        if (!b) return;
        b.hidden = !isVisible;
    }

    function setOvokoProgress(percent) {
        const bar = document.getElementById('ovokoProgressBar');
        if (!bar) return;
        bar.style.width = String(Math.max(0, Math.min(100, percent))) + '%';
    }

    function setOvokoProgressMsg(msg, kind) {
        const m = document.getElementById('ovokoProgressMsg');
        if (!m) return;
        m.classList.remove('is-error');
        m.classList.remove('is-ok');
        if (kind === 'ok') m.classList.add('is-ok');
        if (kind === 'error') m.classList.add('is-error');
        m.textContent = msg || '';
    }

    function hideOvokoProgressModal() {
        const modal = document.getElementById('ovokoProgressModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    async function checkGlobal() {
        const s = document.getElementById('globalStatus');
        const dot = document.getElementById('server-status-dot');
        try {
            const res = await robotFetch('/verificare_sesiune', { headers: ngrokHeaders });
            if (res.ok) {
                s.textContent = 'ONLINE';
                s.className = 'pa-dash-value text-success';
                dot.style.background = '#22c55e';
                return true;
            }
            throw new Error('offline');
        } catch (e) {
            s.textContent = 'OFFLINE';
            s.className = 'pa-dash-value text-danger';
            dot.style.background = '#ef4444';
            return false;
        }
    }

    async function ensurePieseautoRobot() {
        const s = document.getElementById('globalStatus');
        if (await checkGlobal()) return true;
        s.textContent = 'Pornesc serviciu...';
        logAuto('Serviciul PieseAuto e offline — încerc pornire automată...', '#fbbf24');
        try {
            const launchRes = await fetch(ROBOT_LAUNCHER, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start', robot: 'pieseauto' }),
            });
            const launchData = await launchRes.json().catch(() => ({}));
            if (launchData.message) {
                logAuto(String(launchData.message), launchData.online ? '#86efac' : '#fbbf24');
            }
        } catch (e) {
            logAuto('Nu am putut apela robot_launcher.php', '#f87171');
        }
        for (let i = 0; i < 8; i++) {
            s.textContent = 'Aștept serviciu... (' + (i + 1) + '/8)';
            await new Promise(r => setTimeout(r, 2000));
            if (await checkGlobal()) {
                logAuto('Serviciul PieseAuto e online.', '#86efac');
                return true;
            }
        }
        await checkGlobal();
        return false;
    }

    function startStatusPolling(id) {
        if (statusTimer) clearInterval(statusTimer);
        const botLabel = document.getElementById('bot-status-label');
        const s2 = document.getElementById('flowStep2');
        statusTimer = setInterval(async () => {
            try {
                const res = await robotFetch('/get_status?cont_id=' + encodeURIComponent(id), { headers: ngrokHeaders }, 8000);
                const data = await res.json();
                const div = document.getElementById('consoleText');
                if (div && data.status && !div.innerText.includes(data.status)) {
                    const line = document.createElement('div');
                    line.className = 'console-line';
                    const st = String(data.status || '');
                    if (st.includes('❌') || st.toLowerCase().includes('eșuat') || st.toLowerCase().includes('esuat') || st.toLowerCase().includes('gre')) {
                        line.style.color = '#f87171';
                    } else if (st.includes('🏁') || st.includes('✅')) {
                        line.style.color = '#4ade80';
                    }
                    line.innerText = `[${new Date().toLocaleTimeString()}] ${st}`;
                    div.appendChild(line);
                    div.parentElement.scrollTop = div.parentElement.scrollHeight;
                }
                const st = String(data.status || '');
                const isError = st.includes('❌') || st.toLowerCase().includes('eșuat') || st.toLowerCase().includes('esuat') || st.toLowerCase().includes('parola gre');
                const isSuccess = st.includes('🏁') || st.includes('✅ Sesiune') || st.includes('Logat cu succes');
                if (isError) {
                    if (botLabel) { botLabel.textContent = 'EROARE LOGIN'; botLabel.className = 'ms-auto badge bg-danger'; }
                    if (s2) s2.classList.remove('is-done');
                } else if (isSuccess) {
                    if (botLabel) { botLabel.textContent = 'ACTIV'; botLabel.className = 'ms-auto badge bg-success'; }
                    if (s2) s2.classList.add('is-done');
                } else if (st !== 'Inactiv' && !isError) {
                    if (botLabel) { botLabel.textContent = 'ACTIV'; botLabel.className = 'ms-auto badge bg-warning text-dark'; }
                }
            } catch (e) {}
        }, 4000);
    }

    async function reconectareAutomataRobot() {
        const user = document.getElementById('target-user').value;
        const actualId = user.replace(/[^a-zA-Z0-9]/g, '');
        if (!actualId) return;
        try {
            const res = await robotFetch('/get_status?cont_id=' + encodeURIComponent(actualId), { headers: ngrokHeaders });
            const data = await res.json();
            if (data.status !== 'Inactiv') startStatusPolling(actualId);
        } catch (e) {}
    }

    const AUTO_HISTORY_KEY = 'blu_pieseauto_auto_history_v1';
    let autoQueue = [], autoRunning = false, autoIndex = 0;

    function logAuto(mesaj, color = '#fbbf24') {
        const div = document.getElementById('consoleText');
        if (!div) return;
        const line = document.createElement('div');
        line.className = 'console-line';
        line.style.color = color;
        line.innerText = `[${new Date().toLocaleTimeString()}] ${mesaj}`;
        div.appendChild(line);
        div.parentElement.scrollTop = div.parentElement.scrollHeight;
    }

    function sleepAuto(ms) { return new Promise(r => setTimeout(r, ms)); }
    function getProdusKey(item) { return String(item.id || item.title || item.image_url || '').trim(); }
    function citesteIstoricAuto() { try { return JSON.parse(localStorage.getItem(AUTO_HISTORY_KEY) || '{}'); } catch (e) { return {}; } }
    function salveazaIstoricAuto(h) { localStorage.setItem(AUTO_HISTORY_KEY, JSON.stringify(h)); }

    function seteazaStatusProdus(item, status, mesaj = '') {
        const key = getProdusKey(item);
        if (!key) return;
        const history = citesteIstoricAuto();
        history[key] = { id: item.id||'', title: item.title||'', price: item.price||'', image: item.image_url||'', status, mesaj, updated_at: new Date().toLocaleString() };
        salveazaIstoricAuto(history);
        randareIstoricAuto();
        if (status === 'Adăugat') {
            const s3 = document.getElementById('flowStep3');
            if (s3) s3.classList.add('is-done');
        }
    }

    function produsEsteAdaugat(item) {
        const h = citesteIstoricAuto();
        const k = getProdusKey(item);
        return !!(h[k] && h[k].status === 'Adăugat');
    }

    function produsEsteFinalizat(item) {
        const h = citesteIstoricAuto();
        const k = getProdusKey(item);
        if (!h[k]) return false;
        return h[k].status === 'Adăugat' || h[k].status === 'Sărit';
    }

    function randareIstoricAuto() {
        const box = document.getElementById('auto_history');
        if (!box) return;
        const items = Object.values(citesteIstoricAuto()).reverse();
        if (!items.length) { box.innerHTML = '<div class="text-muted small">Niciun produs procesat.</div>'; return; }
        box.innerHTML = items.map(item => {
            let badge = 'secondary';
            if (item.status === 'Adăugat') badge = 'success';
            if (item.status === 'Eroare') badge = 'danger';
            if (item.status === 'Se adaugă') badge = 'warning';
            return `<div class="d-flex align-items-center gap-2 py-1 border-bottom"><div class="flex-grow-1"><div class="small fw-bold text-truncate">${escapeHtml(item.title||'—')}</div></div><span class="badge bg-${badge}">${escapeHtml(item.status)}</span></div>`;
        }).join('');
    }

    function stergeIstoricAuto() {
        if (!confirm('Ștergi istoricul?')) return;
        localStorage.removeItem(AUTO_HISTORY_KEY);
        randareIstoricAuto();
    }

    async function asteaptaRobotLiber(contId) {
        while (autoRunning) {
            try {
                const res = await robotFetch('/este_ocupat?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders });
                const data = await res.json();
                if (!data.busy) return true;
                logAuto('Robot ocupat. Aștept...');
            } catch (e) { logAuto('Reîncerc verificare robot...', '#ef4444'); }
            await sleepAuto(5000);
        }
        return false;
    }

    function payloadDinProdusScanat(item, contId) {
        const images = item.images?.length ? item.images.map(i => typeof i === 'string' ? i : (i.url||'')).filter(Boolean) : (item.image_url ? [item.image_url] : []);
        const categorie = item.pieseauto_category || item.sub_category || item.category_name || 'Alte piese de caroserie';
        return { cont_id: contId, titlu: item.title||'', descriere: item.description||item.title||'', pret: item.price||100, stare_produs: 'Second', categorie_nume: categorie, imagine_url: images[0]||'', imagini_multiple: images };
    }

    async function pornesteAutoProduse() {
        if (autoRunning) return alert('Auto deja rulează.');
        if (!window.produseScanate?.length) return alert('Magazia e goală.');
        const actualId = document.getElementById('target-user').value.replace(/[^a-zA-Z0-9]/g, '');
        if (!actualId) return alert('Completează target.');
        autoQueue = [...window.produseScanate]; autoRunning = true; autoIndex = 0;
        logAuto('AUTO: ' + autoQueue.length + ' piese în coadă.', '#86efac');
        while (autoRunning && autoIndex < autoQueue.length) {
            const item = autoQueue[autoIndex];
            const payload = payloadDinProdusScanat(item, actualId);
            if (produsEsteFinalizat(item)) { seteazaStatusProdus(item,'Sărit','Deja procesat'); autoIndex++; continue; }
            if (!payload.titlu || payload.titlu.length < 15) { seteazaStatusProdus(item,'Sărit','Titlu scurt'); autoIndex++; continue; }
            seteazaStatusProdus(item,'Se adaugă');
            logAuto(`AUTO ${autoIndex+1}/${autoQueue.length}: ${payload.titlu}`);
            try {
                const res = await robotFetch('/adauga_piesa_noua', { method:'POST', headers: ngrokHeaders, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.status !== 'succes') { seteazaStatusProdus(item,'Eroare', data.mesaj||''); autoIndex++; await sleepAuto(5000); continue; }
                await sleepAuto(3000);
                if (!(await asteaptaRobotLiber(actualId))) { seteazaStatusProdus(item,'Oprit','Manual'); break; }
                seteazaStatusProdus(item,'Adăugat');
            } catch (e) { seteazaStatusProdus(item,'Eroare','Conexiune'); }
            autoIndex++; await sleepAuto(5000);
        }
        autoRunning = false;
        logAuto('AUTO terminat.', '#86efac');
        alert('Auto terminat sau oprit.');
    }

    function opresteAutoProduse() { autoRunning = false; logAuto('AUTO oprit.', '#ef4444'); }

    async function stopTotalRobot() {
        autoRunning = false;
        const actualId = document.getElementById('target-user').value.replace(/[^a-zA-Z0-9]/g, '');
        if (statusTimer) {
            clearInterval(statusTimer);
            statusTimer = null;
        }
        logAuto('STOP TOTAL...', '#ef4444');
        try {
            const res = await robotFetch('/stop_total?cont_id=' + encodeURIComponent(actualId), { method:'POST', headers: ngrokHeaders, body: JSON.stringify({ cont_id: actualId }) });
            const data = await res.json();
            logAuto(data.mesaj || 'Oprit.', '#ef4444');
            const botLabel = document.getElementById('bot-status-label');
            if (botLabel) { botLabel.textContent = 'OPRIT'; botLabel.className = 'ms-auto badge bg-danger'; }
            document.getElementById('flowStep2')?.classList.remove('is-done');
        } catch (e) { alert('Nu am putut opri robotul.'); }
    }

    async function startRobot() {
        const email = document.getElementById('accEmail').value;
        const pass = document.getElementById('accPass').value;
        const userTarget = document.getElementById('target-user').value.trim();
        logAuto('Comandă: lansare browser robot...', '#fbbf24');
        if (!email || !pass) {
            logAuto('Lipsesc email/parolă — selectează un cont salvat sau completează Stația 1.', '#f87171');
            return alert('Completează datele de logare!');
        }
        if (!userTarget) {
            logAuto('Lipsește utilizator target.', '#f87171');
            return alert('Completează utilizator target!');
        }
        const launchBtns = document.querySelectorAll('[onclick="startRobot()"]');
        launchBtns.forEach(b => { b.disabled = true; });
        try {
            if (!(await ensurePieseautoRobot())) {
                logAuto('Serviciul PieseAuto nu răspunde. Deschide robot\\start_pieseauto_visible.bat și lasă fereastra deschisă.', '#f87171');
                return alert('Serviciul PieseAuto nu răspunde.\n\n1. Dublu-click: robot\\start_pieseauto_visible.bat\n2. Lasă fereastra deschisă\n3. Reîncarcă pagina și apasă din nou «Lansează browser robot»');
            }
            const actualId = userTarget.replace(/[^a-zA-Z0-9]/g, '');
            const st = await robotFetch('/este_ocupat?cont_id=' + encodeURIComponent(actualId), { headers: ngrokHeaders }, 8000);
            const stData = await robotJson(st, '/este_ocupat');
            if (stData.browser_active) {
                startStatusPolling(actualId);
                logAuto('Browser deja activ — nu deschid altul.', '#86efac');
                return;
            }
            logAuto('Trimit comanda /comanda către robot...', '#fbbf24');
            const cmdRes = await robotFetch('/comanda', { method: 'POST', headers: ngrokHeaders, body: JSON.stringify({ cont_id: actualId, user: email, pass: pass }) });
            const cmdData = await robotJson(cmdRes, '/comanda');
            if (!cmdRes.ok) {
                throw new Error(cmdData.mesaj || ('HTTP ' + cmdRes.status));
            }
            startStatusPolling(actualId);
            logAuto((cmdData.mesaj || 'Browser robot lansat') + ' (' + actualId + ')', '#86efac');
            const botLabel = document.getElementById('bot-status-label');
            if (botLabel) { botLabel.textContent = 'PORNIRE'; botLabel.className = 'ms-auto badge bg-warning text-dark'; }
        } catch (e) {
            logAuto('Eroare la lansare: ' + (e.message || e), '#f87171');
            alert('Eroare Robot Python: ' + (e.message || 'verifică consola.'));
        } finally {
            launchBtns.forEach(b => { b.disabled = false; });
        }
    }

    async function trimitePiesaNoua() {
        const actualId = document.getElementById('target-user').value.replace(/[^a-zA-Z0-9]/g, '');
        if (!actualId) return alert('Completează target.');
        const imagini = Array.from(document.querySelectorAll('#imagini_multiple .img-input')).map(i => i.value.trim()).filter(Boolean);
        const payload = {
            cont_id: actualId,
            titlu: document.getElementById('piesa_titlu').value.trim(),
            descriere: document.getElementById('piesa_descriere').value.trim(),
            pret: document.getElementById('piesa_pret').value || 0,
            stare_produs: document.getElementById('piesa_stare').value,
            categorie_nume: document.getElementById('piesa_cat_nume').value.trim(),
            imagine_url: imagini[0] || '',
            imagini_multiple: imagini
        };
        if (payload.titlu.length < 15) return alert('Titlul: minim 15 caractere.');
        if (payload.pret === '' || Number(payload.pret) < 0) return alert('Preț invalid.');
        try {
            const res = await robotFetch('/adauga_piesa_noua', { method:'POST', headers: ngrokHeaders, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.status === 'succes') logAuto('Trimit ' + imagini.length + ' imagini...', '#fbbf24');
            alert(data.mesaj || 'Trimis către robot!');
        } catch (e) { alert('Eroare conexiune Python.'); }
    }

    function updateAccountMode() {
        const rid = document.getElementById('ridusers').value;
        const isEdit = !!rid;
        const btn = document.getElementById('btnSaveAccount');
        const manage = document.getElementById('accountManageBtns');
        if (btn) btn.textContent = isEdit ? 'SALVEAZĂ MODIFICĂRILE' : 'AUTENTIFICARE & SALVARE';
        if (manage) manage.style.setProperty('display', isEdit ? 'flex' : 'none', 'important');
        updateDashboard();
    }

    function selectAccountPill(el) {
        document.querySelectorAll('#accountPills .pa-pill:not(.pa-pill--new)').forEach(p => p.classList.remove('is-active'));
        el.classList.add('is-active');
        document.getElementById('accCompanyName').value = el.getAttribute('data-name') || el.textContent.trim();
        document.getElementById('accEmail').value = el.getAttribute('data-email') || '';
        document.getElementById('accPass').value = el.getAttribute('data-pass') || '';
        document.getElementById('ridusers').value = el.getAttribute('data-id') || '';
        const sel = document.getElementById('clientSelect');
        if (sel) sel.value = el.getAttribute('data-client-id') || '';
        updateAccountMode();
        reconectareAutomataRobot();
    }

    function fillFieldsFromSelect() {
        const sel = document.getElementById('clientSelect');
        const opt = sel.options[sel.selectedIndex];
        if (!sel.value) { document.getElementById('ridusers').value = ''; updateAccountMode(); return; }
        document.getElementById('accCompanyName').value = opt.getAttribute('data-name') || '';
        document.getElementById('accEmail').value = opt.getAttribute('data-email') || '';
        document.getElementById('accPass').value = opt.getAttribute('data-pass') || '';
        document.getElementById('ridusers').value = opt.getAttribute('data-id') || '';
        document.querySelectorAll('#accountPills .pa-pill').forEach(p => {
            p.classList.toggle('is-active', p.getAttribute('data-client-id') === sel.value);
        });
        updateAccountMode();
        reconectareAutomataRobot();
    }

    function resetAccountFormForNew() {
        document.getElementById('clientSelect').value = '';
        document.getElementById('ridusers').value = '';
        document.getElementById('accCompanyName').value = '';
        document.getElementById('accEmail').value = '';
        document.getElementById('accPass').value = '';
        document.querySelectorAll('#accountPills .pa-pill').forEach(p => p.classList.remove('is-active'));
        updateAccountMode();
        document.getElementById('accCompanyName').focus();
    }

    function salveazaContSelectat() {
        if (!document.getElementById('ridusers').value) return alert('Selectează un cont.');
        document.getElementById('addpieseauto').requestSubmit();
    }

    async function stergeContPieseauto() {
        const rid = document.getElementById('ridusers').value;
        const active = document.querySelector('#accountPills .pa-pill.is-active');
        const label = active?.textContent?.trim() || 'acest cont';
        if (!rid) return alert('Selectează un cont.');
        if (!confirm('Ștergi contul „' + label + '”?')) return;
        try {
            const res = await fetch(<?= json_encode($wb . 'api/pieseauto_accounts.php', JSON_UNESCAPED_SLASHES) ?>, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', randomn_id: rid }) });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            alert(json.message || 'Cont șters.');
            window.location.reload();
        } catch (e) { alert('Eroare conexiune.'); }
    }

    async function salveazaContPieseauto(e) {
        e.preventDefault();
        const companyName = document.getElementById('accCompanyName').value.trim();
        const email = document.getElementById('accEmail').value.trim();
        const pass = document.getElementById('accPass').value;
        if (!companyName) return alert('Introdu firma.');
        if (!email || !pass) return alert('Completează email și parolă.');
        const form = document.getElementById('addpieseauto');
        const data = {};
        form.querySelectorAll('input, textarea, select').forEach(i => { if (i.name) data[i.name] = i.value; });
        data.company_name = companyName; data.email = email; data.pas = pass;
        try {
            const res = await fetch(form.dataset.endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            alert(document.getElementById('ridusers').value ? 'Cont actualizat!' : 'Cont salvat!');
            window.location.reload();
        } catch (e) { alert('Eroare conexiune.'); }
    }

    window.addEventListener('load', () => {
        document.getElementById('addpieseauto')?.addEventListener('submit', salveazaContPieseauto);
        ['accCompanyName','target-user'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', updateDashboard);
        });
        ['piesa_titlu','piesa_pret','piesa_stare','piesa_cat_nume'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', updateLivePreview);
            document.getElementById(id)?.addEventListener('change', updateLivePreview);
        });
        checkGlobal().then(function (online) {
            if (!online) ensurePieseautoRobot();
        });
        // Evită suprapunerea request-urilor: dacă checkGlobal durează mai mult,
        // setInterval poate acumula multe apeluri și pare "loop infinit".
        let __checkGlobalBusy = false;
        async function __checkGlobalLoop() {
            if (__checkGlobalBusy) {
                setTimeout(__checkGlobalLoop, 15000);
                return;
            }
            __checkGlobalBusy = true;
            try {
                await checkGlobal();
            } catch (e) {
                // checkGlobal gestionează deja UI; ignorăm erorile aici.
            } finally {
                __checkGlobalBusy = false;
                setTimeout(__checkGlobalLoop, 15000);
            }
        }
        __checkGlobalLoop();
        setTimeout(reconectareAutomataRobot, 1200);
        randareIstoricAuto();
        incarcaProduseScanate(true);
        updateAccountMode();
        updateLivePreview();
        document.getElementById('scan_search')?.addEventListener('input', () => incarcaProduseScanate(false));
    });
    </script>
    <?php
}
