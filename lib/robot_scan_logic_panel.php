<?php
declare(strict_types=1);

require_once __DIR__ . '/robot_workflow.php';
require_once __DIR__ . '/robot_snapshots.php';

function blu_render_robot_scan_logic_panel(string $contId, array $workflow): void
{
    $wb = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';
    $snapApi = $wb . 'api/robot_snapshots.php';
    $htmlPages = $workflow['html_pages'] ?? [];
    $blocks = blu_default_gbg_scan_logic_blocks();
    $snapshots = $contId !== '' ? blu_list_robot_snapshots($contId, 30) : [];
    ?>
    <style>
        .rsl-doc { font-size: .9rem; line-height: 1.55; }
        .rsl-doc h6 { font-weight: 700; margin-top: 1rem; color: #0f172a; }
        .rsl-html-table td { vertical-align: top; font-size: .85rem; }
        .rsl-snap-list { max-height: 320px; overflow-y: auto; }
        .rsl-preview { border: 1px solid #e2e8f0; border-radius: 10px; min-height: 420px; background: #fff; }
        .rsl-preview iframe { width: 100%; height: 480px; border: 0; border-radius: 10px; }
    </style>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header card-no-border pb-0">
                    <h5 class="mb-1">Logica completă de scanare</h5>
                    <p class="text-muted small mb-0">Furnizor: <code><?= htmlspecialchars($contId ?: '-', ENT_QUOTES) ?></code></p>
                </div>
                <div class="card-body rsl-doc">
                    <?php foreach ($blocks as $block): ?>
                        <h6><?= htmlspecialchars($block['title'], ENT_QUOTES) ?></h6>
                        <?= $block['html'] ?>
                    <?php endforeach; ?>
                    <?php if (!empty($workflow['steps'])): ?>
                        <h6>Pașii tăi configurați (<?= count($workflow['steps']) ?>)</h6>
                        <ol class="small mb-0">
                            <?php foreach ($workflow['steps'] as $s): ?>
                                <li>
                                    <strong><?= (int)($s['order'] ?? 0) ?>.</strong>
                                    <?= htmlspecialchars((string)($s['label'] ?? $s['type']), ENT_QUOTES) ?>
                                    <span class="text-muted">(<?= htmlspecialchars((string)($s['type'] ?? ''), ENT_QUOTES) ?>)</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header card-no-border pb-0">
                    <h5 class="mb-1">Pagini HTML de deschis și analizat</h5>
                    <p class="text-muted small mb-0">Robotul deschide aceste pagini, verifică selectori, salvează HTML pentru inspecție în admin.</p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm rsl-html-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pagină</th>
                                    <th>URL</th>
                                    <th>Ce scanează / extrage</th>
                                    <th>Verificare HTML</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$htmlPages): ?>
                                    <tr><td colspan="4" class="text-muted p-3">Nicio pagină definită — adaugă în tab «Pași robot».</td></tr>
                                <?php endif; ?>
                                <?php foreach ($htmlPages as $p): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES) ?></strong><br>
                                            <code class="small"><?= htmlspecialchars((string)($p['key'] ?? ''), ENT_QUOTES) ?></code>
                                            <?php if (!empty($p['save_html'])): ?>
                                                <span class="badge bg-light text-dark">salvează HTML</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code class="small"><?= htmlspecialchars((string)($p['url'] ?? ''), ENT_QUOTES) ?></code></td>
                                        <td><?= nl2br(htmlspecialchars((string)($p['what_scans'] ?? ''), ENT_QUOTES)) ?></td>
                                        <td>
                                            <?php foreach ($p['verify_selectors'] ?? [] as $sel): ?>
                                                <code class="d-block small"><?= htmlspecialchars($sel, ENT_QUOTES) ?></code>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-no-border pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Snapshot-uri HTML salvate de robot</h5>
                        <p class="text-muted small mb-0">După lansare scanare — deschide HTML-ul real pe care l-a văzut robotul.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="rslRefreshSnaps">Reîncarcă</button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <div class="rsl-snap-list list-group" id="rslSnapList">
                                <?php if (!$snapshots): ?>
                                    <div class="text-muted small p-2">Niciun snapshot încă. Rulează robotul cu pași «analyze_html» sau navigate + salvare HTML.</div>
                                <?php endif; ?>
                                <?php foreach ($snapshots as $snap): ?>
                                    <a href="#" class="list-group-item list-group-item-action rsl-snap-item small"
                                       data-id="<?= htmlspecialchars((string)($snap['id'] ?? ''), ENT_QUOTES) ?>">
                                        <strong><?= htmlspecialchars((string)($snap['html_purpose'] ?? $snap['label'] ?? 'Snapshot'), ENT_QUOTES) ?></strong><br>
                                        <span class="text-muted"><?= htmlspecialchars((string)($snap['saved_at'] ?? ''), ENT_QUOTES) ?></span>
                                        · <?= (int)($snap['html_length'] ?? 0) ?> chr
                                        <?php if (!empty($snap['title'])): ?>
                                            <br><span class="text-truncate d-block"><?= htmlspecialchars((string)$snap['title'], ENT_QUOTES) ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="rsl-preview">
                                <iframe id="rslPreviewFrame" title="Preview HTML robot" src="about:blank"></iframe>
                            </div>
                            <pre class="small mt-2 mb-0 bg-light p-2 rounded" id="rslSnapMeta" style="max-height:120px;overflow:auto;">Selectează un snapshot.</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const CONT = <?= json_encode($contId, JSON_UNESCAPED_UNICODE) ?>;
        const API = <?= json_encode($snapApi, JSON_UNESCAPED_SLASHES) ?>;
        const metaEl = document.getElementById('rslSnapMeta');
        const frame = document.getElementById('rslPreviewFrame');

        function bindSnapItems() {
            document.querySelectorAll('.rsl-snap-item').forEach(el => {
                el.onclick = function (e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    document.querySelectorAll('.rsl-snap-item').forEach(x => x.classList.remove('active'));
                    this.classList.add('active');
                    frame.src = API + '?cont_id=' + encodeURIComponent(CONT) + '&id=' + encodeURIComponent(id) + '&view=html';
                    fetch(API + '?cont_id=' + encodeURIComponent(CONT))
                        .then(r => r.json())
                        .then(data => {
                            const snap = (data.snapshots || []).find(s => s.id === id);
                            if (snap) metaEl.textContent = JSON.stringify(snap, null, 2);
                        });
                };
            });
        }
        bindSnapItems();

        document.getElementById('rslRefreshSnaps')?.addEventListener('click', () => location.reload());
    })();
    </script>
    <?php
}
