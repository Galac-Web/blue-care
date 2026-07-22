<?php
declare(strict_types=1);

/**
 * Panou monitor robot — integrat in admin (index.php?page=robot-monitor).
 */
function blu_render_robot_monitor_panel(array $gbgSuppliers = []): void
{
    require_once __DIR__ . '/gbg_robot_panel.php';
    blu_render_gbg_robot_panel($gbgSuppliers);

    $apiKey = trim((string)blu_env('ROBOT_API_KEY', ''));
    $keyQs = $apiKey !== '' ? '&api_key=' . rawurlencode($apiKey) : '';
    $apiBase = (function_exists('blu_admin_web_base') ? blu_admin_web_base() : '') . 'api/robot-oem.php';
    ?>
    <style>
        .robot-feed-item { border-left: 4px solid #ccc; padding: .75rem 1rem; margin-bottom: .5rem; background: #fff; border-radius: .5rem; }
        .robot-feed-item.imported { border-left-color: #198754; }
        .robot-feed-item.processing { border-left-color: #0d6efd; }
        .robot-feed-item.empty { border-left-color: #ffc107; }
        .robot-feed-item.no_oem, .robot-feed-item.error, .robot-feed-item.skip { border-left-color: #dc3545; }
        .robot-stat-num { font-size: 1.75rem; font-weight: 800; }
        #robotFeedBox { max-height: 55vh; overflow-y: auto; }
        .robot-thumb { width: 48px; height: 48px; object-fit: contain; background: #f8f9fa; border-radius: 4px; }
    </style>

    <div class="row g-3 mb-3">
        <div class="col-md-2 col-6">
            <div class="card text-center p-3 mvp-stat">
                <div class="robot-stat-num text-primary" id="stTotal">0</div>
                <div class="small text-muted">Total scanate</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center p-3 mvp-stat">
                <div class="robot-stat-num text-success" id="stFound">0</div>
                <div class="small text-muted">Gasite TecDoc</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center p-3 mvp-stat">
                <div class="robot-stat-num text-warning" id="stEmpty">0</div>
                <div class="small text-muted">Fara rezultat</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center p-3 mvp-stat">
                <div class="robot-stat-num text-danger" id="stNoOem">0</div>
                <div class="small text-muted">OEM nu e</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center p-3 mvp-stat">
                <div class="robot-stat-num text-danger" id="stErrors">0</div>
                <div class="small text-muted">Erori</div>
            </div>
        </div>
        <div class="col-md-2 col-12 d-flex align-items-center justify-content-md-end">
            <span class="badge bg-secondary" id="lastUpdate">—</span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header card-no-border pb-0"><h4 class="h6 mb-0">Test manual piesa</h4></div>
        <div class="card-body">
            <form id="robotTestForm" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small">Marca</label><input class="form-control form-control-sm" name="brand" placeholder="ALFA ROMEO"></div>
                <div class="col-md-3"><label class="form-label small">Model</label><input class="form-control form-control-sm" name="model" placeholder="33"></div>
                <div class="col-md-2"><label class="form-label small">Cod articol</label><input class="form-control form-control-sm" name="cod_articol" placeholder="065807042"></div>
                <div class="col-md-3"><label class="form-label small">OEM (virgula = mai multe)</label><input class="form-control form-control-sm" name="coduri_oem" placeholder="60573687 sau 156045004,60596492"></div>
                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Trimite</button></div>
            </form>
            <p class="small text-muted mb-0 mt-2">API: <code><?= e($apiBase) ?></code> — fiecare OEM din lista e cautat pana gaseste articol.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-no-border d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills" id="robotTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-feed-btn" data-bs-toggle="pill" data-bs-target="#tab-feed" type="button" role="tab">
                        Jurnal live
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-nooem-btn" data-bs-toggle="pill" data-bs-target="#tab-nooem" type="button" role="tab">
                        Fără OEM <span class="badge bg-danger ms-1" id="noOemBadge">0</span>
                    </button>
                </li>
            </ul>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-danger btn-sm d-none" id="btnClearNoOem">Golește lista fără OEM</button>
                <a class="btn btn-outline-secondary btn-sm" href="?page=imported">Vezi importate</a>
            </div>
        </div>
        <div class="card-body p-2">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-feed" role="tabpanel">
                    <div id="robotFeedBox" style="max-height:55vh;overflow-y:auto;">
                        <p class="text-muted p-3 mb-0">Astept date de la robot...</p>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-nooem" role="tabpanel">
                    <p class="small text-muted px-2 pt-2 mb-2">
                        Produse scanate la care nu s-au găsit coduri OEM. Caută codul OEM manual și adaugă-l, apoi reia scanarea pentru ele.
                    </p>
                    <div id="robotNoOemBox" style="max-height:55vh;overflow-y:auto;">
                        <p class="text-muted p-3 mb-0">Nicio piesă fără OEM (încă).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const apiBase = <?= json_encode($apiBase, JSON_UNESCAPED_UNICODE) ?>;
        const keyQs = <?= json_encode($keyQs, JSON_UNESCAPED_UNICODE) ?>;
        const feedUrl = '?page=robot-monitor&api_action=robot_feed&limit=80';

        function esc(s) {
            return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function statusBadge(st) {
            const map = {
                imported: ['Importat', 'bg-success'],
                processing: ['In curs', 'bg-primary'],
                empty: ['Fara TecDoc', 'bg-warning text-dark'],
                no_oem: ['OEM nu e', 'bg-danger'],
                error: ['Eroare', 'bg-danger'],
                skip: ['Sarit', 'bg-secondary'],
            };
            const x = map[st] || [st || '-', 'bg-secondary'];
            return `<span class="badge ${x[1]} ms-1">${esc(x[0])}</span>`;
        }

        function renderFeed(feed) {
            const box = document.getElementById('robotFeedBox');
            if (!feed.length) {
                box.innerHTML = '<p class="text-muted p-3 mb-0">Niciun eveniment. Porneste robotul Python sau trimite test manual.</p>';
                return;
            }
            box.innerHTML = feed.map(e => {
                const img = e.image ? `<img class="robot-thumb me-2" src="${esc(e.image)}" alt="">` : '';
                const miss = e.missing_oem ? '<span class="badge bg-danger">OEM nu e</span>' : '';
                const codes = Array.isArray(e.codes) ? e.codes.join(', ') : '';
                return `<div class="robot-feed-item ${esc(e.status || '')}">
                    <div class="d-flex align-items-start">
                        ${img}
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-1">
                                <div><strong>${esc(e.title || e.message || e.status || '-')}</strong> ${statusBadge(e.status)} ${miss}</div>
                                <small class="text-muted">${esc(e.time || '')}</small>
                            </div>
                            <div class="small">${esc(e.brand || '')} — ${esc(e.model || '')}</div>
                            <div class="small">Art: <code>${esc(e.cod_articol || '-')}</code> · OEM: <code>${esc(e.coduri_oem || e.cod_oem || '-')}</code></div>
                            ${codes ? `<div class="small text-muted">Cautat: ${esc(codes)}</div>` : ''}
                            ${e.cont_id ? `<div class="small text-muted">Robot: ${esc(e.cont_id)}</div>` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function renderStats(stats) {
            document.getElementById('stTotal').textContent = stats.total || 0;
            document.getElementById('stFound').textContent = stats.found || 0;
            document.getElementById('stEmpty').textContent = stats.empty || 0;
            document.getElementById('stNoOem').textContent = stats.no_oem || 0;
            document.getElementById('stErrors').textContent = stats.errors || 0;
            document.getElementById('lastUpdate').textContent = stats.last_at || '—';
        }

        function renderNoOem(list) {
            const box = document.getElementById('robotNoOemBox');
            const badge = document.getElementById('noOemBadge');
            const clearBtn = document.getElementById('btnClearNoOem');
            badge.textContent = list.length;
            clearBtn.classList.toggle('d-none', list.length === 0);
            if (!list.length) {
                box.innerHTML = '<p class="text-muted p-3 mb-0">Nicio piesă fără OEM (încă).</p>';
                return;
            }
            box.innerHTML = list.map(e => `
                <div class="robot-feed-item no_oem">
                    <div class="d-flex flex-wrap justify-content-between gap-1">
                        <div><strong>${esc(e.brand || '')} — ${esc(e.model || '')}</strong></div>
                        <small class="text-muted">${esc(e.time || '')}</small>
                    </div>
                    <div class="small">Cod articol: <code>${esc(e.cod_articol || '-')}</code> · OEM: <code>${esc(e.coduri_oem || '-')}</code></div>
                    <div class="small text-muted">${esc(e.reason || 'Fara coduri OEM')}</div>
                </div>`).join('');
        }

        async function poll() {
            try {
                const res = await fetch(feedUrl);
                const data = await res.json();
                if (data.ok) {
                    renderFeed(data.feed || []);
                    renderStats(data.stats || {});
                    renderNoOem(data.no_oem || []);
                }
            } catch (e) { /* ignore */ }
        }

        document.getElementById('btnClearNoOem').addEventListener('click', async function () {
            if (!confirm('Golești lista produselor fără OEM?')) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch('?page=robot-monitor&api_action=robot_clear_no_oem', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
            await poll();
        });

        document.getElementById('robotTestForm').addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const fd = new FormData(ev.target);
            const params = new URLSearchParams(fd);
            params.set('cont_id', 'admin_test');
            await fetch(`${apiBase}?${params}${keyQs}`);
            await poll();
        });

        setInterval(poll, 2000);
        poll();
    })();
    </script>
    <?php
}
