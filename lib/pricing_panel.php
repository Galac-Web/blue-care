<?php
declare(strict_types=1);

require_once __DIR__ . '/pricing.php';

/**
 * Panou admin: Adaos comercial — setează adaos %, TVA % și toate cheltuielile.
 */
function blu_render_pricing_panel(string $csrf): void
{
    $cfg = blu_pricing_settings();
    $preview = blu_compute_price(100.0, $cfg);
    ?>
    <style>
        .pr-formula { font-size: .82rem; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .75rem 1rem; }
        .pr-chelt-row { display: grid; grid-template-columns: 1fr 130px 130px 40px; gap: .5rem; align-items: center; margin-bottom: .5rem; }
        .pr-calc { background: #0b1220; color: #e2e8f0; border-radius: 12px; padding: 1rem; font-family: Consolas, monospace; }
        .pr-calc .line { display: flex; justify-content: space-between; padding: .15rem 0; }
        .pr-calc .total { border-top: 1px dashed #334155; margin-top: .4rem; padding-top: .5rem; font-weight: 700; color: #4ade80; font-size: 1.05rem; }
        @media (max-width: 575px) { .pr-chelt-row { grid-template-columns: 1fr 100px 90px 36px; } }
    </style>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <h4 class="mb-1">Adaos comercial, TVA și cheltuieli</h4>
                    <p class="text-muted small mb-0">Aceste valori se aplică automat la calculul prețului final al produselor.</p>
                </div>
                <div class="card-body">
                    <form method="post" class="theme-form" id="pricingForm">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <input type="hidden" name="action" value="save_pricing">

                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label fw-bold">Adaos comercial (%)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="adaos_pct" id="prAdaos" value="<?= htmlspecialchars((string)$cfg['adaos_pct'], ENT_QUOTES) ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-bold">TVA (%)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="tva_pct" id="prTva" value="<?= htmlspecialchars((string)$cfg['tva_pct'], ENT_QUOTES) ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-bold">Curs EUR → RON</label>
                                <input type="number" step="0.0001" min="0.01" class="form-control" name="eur_ron_rate" id="prEurRon" value="<?= htmlspecialchars((string)($cfg['eur_ron_rate'] ?? 4.975), ENT_QUOTES) ?>">
                                <div class="form-text">Pret GBG (€) × curs = pret baza RON, apoi + adaos.</div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Cheltuieli</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="prAddChelt"><i class="fa-solid fa-plus"></i> Adaugă cheltuială</button>
                        </div>
                        <div class="pr-chelt-row text-muted small fw-bold">
                            <div>Denumire</div><div>Tip</div><div>Valoare</div><div></div>
                        </div>
                        <div id="prCheltList">
                            <?php foreach ($cfg['cheltuieli'] as $c): ?>
                                <div class="pr-chelt-row">
                                    <input type="text" class="form-control form-control-sm" name="chelt_nume[]" value="<?= htmlspecialchars((string)$c['nume'], ENT_QUOTES) ?>" placeholder="Ex: Transport">
                                    <select class="form-select form-select-sm" name="chelt_tip[]">
                                        <option value="fix" <?= $c['tip'] === 'fix' ? 'selected' : '' ?>>Fix (lei)</option>
                                        <option value="procent" <?= $c['tip'] === 'procent' ? 'selected' : '' ?>>Procent (%)</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="chelt_val[]" value="<?= htmlspecialchars((string)$c['valoare'], ENT_QUOTES) ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger pr-del-chelt"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pr-formula mt-2">
                            <strong>Formula:</strong> preț final = (preț bază + cheltuieli + adaos comercial) + TVA.<br>
                            Cheltuielile „Fix" se adună în lei; cele „Procent" se calculează din prețul de bază.
                        </div>

                        <button type="submit" class="btn btn-success mt-3"><i class="fa-solid fa-floppy-disk"></i> Salvează setările</button>
                        <?php if (!empty($cfg['updated_at'])): ?>
                            <span class="text-muted small ms-2">Actualizat: <?= htmlspecialchars((string)$cfg['updated_at'], ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <h4 class="mb-1">Calculator preț</h4>
                    <p class="text-muted small mb-0">Introdu prețul de bază (cost achiziție) și vezi prețul final.</p>
                </div>
                <div class="card-body">
                    <label class="form-label fw-bold">Preț bază (lei)</label>
                    <input type="number" step="0.01" min="0" class="form-control mb-3" id="prBase" value="100">
                    <div class="pr-calc" id="prCalc"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        function num(id) { const v = parseFloat(document.getElementById(id).value); return isNaN(v) ? 0 : v; }
        function lei(v) { return (Math.round(v * 100) / 100).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei'; }

        function readChelt() {
            const rows = document.querySelectorAll('#prCheltList .pr-chelt-row');
            const out = [];
            rows.forEach(function (r) {
                const nume = r.querySelector('[name="chelt_nume[]"]').value.trim();
                const tip = r.querySelector('[name="chelt_tip[]"]').value;
                const val = parseFloat(r.querySelector('[name="chelt_val[]"]').value) || 0;
                if (nume === '' && val === 0) return;
                out.push({ nume: nume || 'Cheltuială', tip: tip, valoare: val });
            });
            return out;
        }

        function recalc() {
            const baza = num('prBase');
            const adaos = num('prAdaos');
            const tva = num('prTva');
            const chelt = readChelt();
            let cheltTotal = 0;
            let liniiChelt = '';
            chelt.forEach(function (c) {
                const s = c.tip === 'procent' ? (baza * c.valoare / 100) : c.valoare;
                cheltTotal += s;
                liniiChelt += '<div class="line"><span>+ ' + esc(c.nume) + (c.tip === 'procent' ? ' (' + c.valoare + '%)' : '') + '</span><span>' + lei(s) + '</span></div>';
            });
            const costTotal = baza + cheltTotal;
            const adaosVal = costTotal * adaos / 100;
            const faraTva = costTotal + adaosVal;
            const tvaVal = faraTva * tva / 100;
            const raw = faraTva + tvaVal;
            // Rotunjire în sus la leu întreg (ca în BD): 8.20 → 9
            const final = Math.ceil(Math.round(raw * 100) / 100 - 1e-9);

            document.getElementById('prCalc').innerHTML =
                '<div class="line"><span>Preț bază</span><span>' + lei(baza) + '</span></div>'
                + liniiChelt
                + '<div class="line"><span>= Cost total</span><span>' + lei(costTotal) + '</span></div>'
                + '<div class="line"><span>+ Adaos comercial (' + adaos + '%)</span><span>' + lei(adaosVal) + '</span></div>'
                + '<div class="line"><span>= Preț fără TVA</span><span>' + lei(faraTva) + '</span></div>'
                + '<div class="line"><span>+ TVA (' + tva + '%)</span><span>' + lei(tvaVal) + '</span></div>'
                + '<div class="line"><span>Înainte de rotunjire</span><span>' + lei(raw) + '</span></div>'
                + '<div class="line total"><span>Preț final (↑ leu)</span><span>' + lei(final) + '</span></div>';
        }

        function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

        function bindRow(row) {
            row.querySelector('.pr-del-chelt').addEventListener('click', function () { row.remove(); recalc(); });
            row.querySelectorAll('input, select').forEach(function (el) {
                el.addEventListener('input', recalc);
                el.addEventListener('change', recalc);
            });
        }

        document.getElementById('prAddChelt').addEventListener('click', function () {
            const wrap = document.createElement('div');
            wrap.className = 'pr-chelt-row';
            wrap.innerHTML =
                '<input type="text" class="form-control form-control-sm" name="chelt_nume[]" placeholder="Ex: Ambalare">'
                + '<select class="form-select form-select-sm" name="chelt_tip[]"><option value="fix">Fix (lei)</option><option value="procent">Procent (%)</option></select>'
                + '<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="chelt_val[]" value="0">'
                + '<button type="button" class="btn btn-sm btn-outline-danger pr-del-chelt"><i class="fa-solid fa-xmark"></i></button>';
            document.getElementById('prCheltList').appendChild(wrap);
            bindRow(wrap);
            recalc();
        });

        document.querySelectorAll('#prCheltList .pr-chelt-row').forEach(bindRow);
        ['prBase', 'prAdaos', 'prTva'].forEach(function (id) {
            const el = document.getElementById(id);
            el.addEventListener('input', recalc);
            el.addEventListener('change', recalc);
        });
        recalc();
    })();
    </script>
    <?php
}
