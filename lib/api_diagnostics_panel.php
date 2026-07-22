<?php
declare(strict_types=1);

require_once __DIR__ . '/api_diagnostics.php';

/**
 * Panou admin: Diagnostic API — vede clar erorile de token/cheie și le poate corecta.
 */
function blu_render_api_diagnostics_panel(string $csrf): void
{
    $editable = blu_diag_editable_keys();
    $current = blu_diag_current_values();
    ?>
    <style>
        /* Blocuri „complexe”, corecte pe desktop + mobile. */
        .diag-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
            align-items: stretch;
        }

        @media (max-width: 1200px) {
            .diag-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        @media (max-width: 992px) {
            .diag-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 576px) {
            .diag-grid { grid-template-columns: 1fr; }
        }

        .diag-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: .9rem 1rem;
            background: #fff;
            height: 100%;
            min-width: 0;
        }
        .diag-card.ok { border-left: 5px solid #16a34a; }
        .diag-card.warn { border-left: 5px solid #d97706; }
        .diag-card.err { border-left: 5px solid #dc2626; }

        .diag-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .diag-dot.ok { background: #16a34a; }
        .diag-dot.warn { background: #d97706; }
        .diag-dot.err { background: #dc2626; }

        .diag-msg { font-size: .9rem; margin: .4rem 0 .2rem; }
        .diag-hint { font-size: .78rem; color: #64748b; }

        .diag-detail {
            font-family: Consolas, monospace;
            font-size: .72rem;
            white-space: pre-wrap;
            word-break: break-word;
            background: #0b1220;
            color: #e2e8f0;
            border-radius: 8px;
            padding: .5rem .6rem;
            margin-top: .5rem;
            max-height: 160px;
            overflow: auto;
        }

        @media (max-width: 576px) {
            .diag-detail { max-height: 120px; }
        }

        .diag-errbox {
            font-family: Consolas, monospace;
            font-size: .76rem;
            background: #0b1220;
            color: #fca5a5;
            border-radius: 10px;
            padding: .75rem;
            max-height: 280px;
            overflow-y: auto;
        }

        @media (max-width: 576px) {
            .diag-errbox { max-height: 220px; }
        }

        .diag-err-line { margin-bottom: 4px; line-height: 1.35; }
        .diag-fix-badge { font-size: .68rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: .1rem .4rem; border-radius: 999px; }
    </style>

    <div class="card">
        <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">Diagnostic API — chei &amp; conexiuni</h4>
                <p class="text-muted small mb-0">Vezi clar orice eroare de token/cheie și corectează direct din admin.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted" id="diagGeneratedAt"></span>
                <button type="button" class="btn btn-sm btn-primary" id="diagRunBtn"><i class="fa-solid fa-rotate"></i> Rulează teste</button>
            </div>
        </div>
        <div class="card-body">
            <div class="diag-grid" id="diagGrid">
                <div class="text-muted small">Se rulează verificările...</div>
            </div>

            <h5 class="mt-4 mb-2">Ultimele erori înregistrate (RapidAPI / import)</h5>
            <div class="diag-errbox" id="diagErrors">
                <div class="diag-err-line">Se încarcă...</div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header card-no-border pb-0">
            <h4 class="mb-1">Corectează cheile (.env)</h4>
            <p class="text-muted small mb-0">Modifică și salvează. Roboții Python trebuie reporniți ca să preia valorile noi.</p>
        </div>
        <div class="card-body">
            <form method="post" class="theme-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="save_api_env">
                <div class="row g-3">
                    <?php foreach ($editable as $key => $meta): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                <?= htmlspecialchars($meta['label'], ENT_QUOTES) ?>
                                <span class="diag-fix-badge"><?= htmlspecialchars($key, ENT_QUOTES) ?></span>
                            </label>
                            <input type="text" class="form-control form-control-sm" name="env[<?= htmlspecialchars($key, ENT_QUOTES) ?>]"
                                   value="<?= htmlspecialchars((string)$current[$key], ENT_QUOTES) ?>"
                                   autocomplete="off" spellcheck="false">
                            <div class="diag-hint mt-1"><?= htmlspecialchars($meta['hint'], ENT_QUOTES) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-success mt-3"><i class="fa-solid fa-floppy-disk"></i> Salvează &amp; aplică</button>
                <span class="text-muted small ms-2">După salvare, apasă „Rulează teste” pentru re-verificare.</span>
            </form>
        </div>
    </div>

    <script>
    (function () {
        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function renderChecks(checks) {
            const grid = document.getElementById('diagGrid');
            if (!checks || !checks.length) {
                grid.innerHTML = '<div class="text-muted small">Nicio verificare.</div>';
                return;
            }
            grid.innerHTML = checks.map(function (c) {
                const lvl = c.level || (c.ok ? 'ok' : 'err');
                const detail = c.detail ? '<div class="diag-detail">' + esc(c.detail) + '</div>' : (c.masked ? '<div class="diag-hint mt-1">Valoare: ' + esc(c.masked) + '</div>' : '');
                const hint = c.hint ? '<div class="diag-hint mt-1">💡 ' + esc(c.hint) + '</div>' : '';
                const fix = c.fix_key ? '<span class="diag-fix-badge">' + esc(c.fix_key) + '</span>' : '';
                return '<div class="diag-card ' + esc(lvl) + '">'
                    + '<div class="d-flex justify-content-between align-items-center">'
                    + '<strong class="small">' + esc(c.label) + '</strong>'
                    + '<span class="diag-dot ' + esc(lvl) + '"></span>'
                    + '</div>'
                    + '<div class="diag-msg">' + esc(c.message) + '</div>'
                    + fix
                    + detail
                    + hint
                    + '</div>';
            }).join('');
        }

        function renderErrors(errors) {
            const box = document.getElementById('diagErrors');
            if (!errors || !errors.length) {
                box.innerHTML = '<div class="diag-err-line" style="color:#86efac">Nicio eroare recentă. 👍</div>';
                return;
            }
            box.innerHTML = errors.map(function (e) {
                return '<div class="diag-err-line">[' + esc(e.t) + '] ' + esc(e.msg) + '</div>';
            }).join('');
        }

        async function runDiag() {
            const btn = document.getElementById('diagRunBtn');
            if (btn) { btn.disabled = true; btn.classList.add('disabled'); }
            try {
                const res = await fetch('?api_action=diag_run', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderChecks(data.checks || []);
                renderErrors(data.errors || []);
                const at = document.getElementById('diagGeneratedAt');
                if (at) at.textContent = 'Ultima verificare: ' + (data.generated_at || '');
            } catch (e) {
                document.getElementById('diagGrid').innerHTML = '<div class="text-danger small">Nu am putut rula diagnosticul: ' + esc(e.message) + '</div>';
            } finally {
                if (btn) { btn.disabled = false; btn.classList.remove('disabled'); }
            }
        }

        document.getElementById('diagRunBtn').addEventListener('click', runDiag);
        runDiag();
    })();
    </script>
    <?php
}
