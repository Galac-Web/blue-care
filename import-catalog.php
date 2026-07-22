<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/catalog_import.php';

header('X-Robots-Tag: noindex, nofollow');

if (!blu_import_access_ok()) {
    http_response_code(403);
    exit('Acces interzis. Furnizeaza cheia corecta (?key=...).');
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'process') {
    header('Content-Type: application/json; charset=utf-8');
    $jobId = trim((string)($_GET['job'] ?? ''));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 5)));

    $job = blu_load_import_job($jobId);
    if ($job === null) {
        echo json_encode(['ok' => false, 'error' => 'Job invalid sau expirat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $entries = $job['entries'] ?? [];
    $total = count($entries);
    $batch = blu_process_catalog_batch($entries, $offset, $limit, true);
    $nextOffset = $offset + $batch['processed'];
    $done = $nextOffset >= $total;

    echo json_encode([
        'ok' => true,
        'job' => $jobId,
        'total' => $total,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'done' => $done,
        'batch' => $batch,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'last_job') {
    header('Content-Type: application/json; charset=utf-8');
    $files = glob(blu_uploads_dir() . DIRECTORY_SEPARATOR . 'job_*.json') ?: [];
    if ($files === []) {
        echo json_encode(['ok' => false, 'error' => 'Nu exista un import anterior.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    $job = blu_load_import_job(basename($files[0], '.json'));
    if ($job === null) {
        echo json_encode(['ok' => false, 'error' => 'Job invalid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'job' => $job['id'] ?? basename($files[0], '.json'),
        'total' => count($job['entries'] ?? []),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'purge_cache') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'removed' => blu_purge_invalid_api_cache()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'enrich_pending') {
    header('Content-Type: application/json; charset=utf-8');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 5)));
    echo json_encode(blu_enrich_pending_cards($offset, $limit), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reenrich_imported') {
    header('Content-Type: application/json; charset=utf-8');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(5, max(1, (int)($_GET['limit'] ?? 1)));
    echo json_encode(blu_reenrich_imported_cards_batch($offset, $limit), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'sync_all') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(blu_sync_all_product_sources(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'refresh_all') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(blu_refresh_all_imported_products(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    blu_purge_invalid_api_cache();

    if (empty($_FILES['catalog_json']['tmp_name'])) {
        echo json_encode(['ok' => false, 'error' => 'Selecteaza un fisier JSON.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tmp = $_FILES['catalog_json']['tmp_name'];
    $size = (int)($_FILES['catalog_json']['size'] ?? 0);
    if ($size > 50 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'Fisierul depaseste 50 MB.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents($tmp);
    $catalog = json_decode((string)$raw, true);
    if (!is_array($catalog) || json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['ok' => false, 'error' => 'JSON invalid: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $entries = blu_catalog_flatten_entries($catalog);
    if ($entries === []) {
        echo json_encode(['ok' => false, 'error' => 'Nu s-au gasit piese in structura asteptata (marca -> model -> cod_articol).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $jobId = blu_create_import_job($catalog);
    echo json_encode([
        'ok' => true,
        'job' => $jobId,
        'total' => count($entries),
        'message' => 'Catalog incarcat. Porneste importul.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiConfigured = blu_rapidapi_key() !== '';
$dbOk = blu_db_products_bootstrap() !== null;
$keyParam = trim((string)($_GET['key'] ?? ''));
$keyQs = $keyParam !== '' ? '&key=' . rawurlencode($keyParam) : '';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import catalog piese — Blue-Car</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin-lite.css?v=catalog-import-1">
    <style>
        body { background: #f4f7f6; font-family: "Nunito Sans", system-ui, sans-serif; }
        .hero { background: linear-gradient(135deg, #1a5c56, #308e87); color: #fff; border-radius: 1rem; padding: 2rem; }
        .product-card-import { border: 0; border-radius: 1rem; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,.08); height: 100%; }
        .product-card-import .card-img-top { height: 160px; object-fit: contain; background: #f8f9fa; }
        .status-pill { font-size: .8rem; }
        #logBox { max-height: 220px; overflow: auto; font-size: .85rem; }
        .progress { height: 1.25rem; }
    </style>
</head>
<body class="py-4">
<div class="container" style="max-width: 1100px;">
    <div class="hero mb-4">
        <h1 class="h3 mb-2">Import catalog JSON → RapidAPI → Baza de date</h1>
        <p class="mb-0 opacity-90">Incarca <code>catalog_auto.json</code> (marca → model → <code>cod_articol</code> / <code>coduri_oem</code>). Pentru fiecare cod OEM se interogheaza Auto Parts Catalog (RapidAPI), se genereaza cartela produsului si se salveaza in MySQL + JSON admin.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">1. Incarca JSON</h2>
                    <form id="uploadForm" enctype="multipart/form-data">
                        <?php if ($keyParam !== ''): ?>
                            <input type="hidden" name="key" value="<?= htmlspecialchars($keyParam, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <input class="form-control" type="file" name="catalog_json" accept=".json,application/json" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Piese per lot (max 50)</label>
                            <input class="form-control" type="number" id="batchSize" value="5" min="1" max="50">
                        </div>
                        <button type="submit" class="btn btn-primary" id="btnUpload" <?= $apiConfigured ? '' : 'disabled' ?>>Incarca si pregateste import</button>
                        <button type="button" class="btn btn-outline-secondary mt-2 w-100" id="btnRebuildLast">Reproceseaza ultimul catalog</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Status sistem</h2>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="badge <?= $apiConfigured ? 'bg-success' : 'bg-danger' ?> status-pill">RapidAPI</span>
                            <?= $apiConfigured ? 'Cheie configurata' : 'Lipseste RAPIDAPI_AUTOPARTS_KEY in robot/.env' ?>
                        </li>
                        <li class="mb-2">
                            <span class="badge <?= $dbOk ? 'bg-success' : 'bg-warning text-dark' ?> status-pill">MySQL</span>
                            <?= $dbOk ? 'Conexiune OK (prd/config.php)' : 'DB neconfigurata — doar JSON local' ?>
                        </li>
                        <li>
                            <span class="badge bg-secondary status-pill">Endpoint</span>
                            <code>artlookup/search-for-cross-numbers (OENumber)</code>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="progressCard" style="display:none;">
        <div class="card-body">
            <h2 class="h5">2. Procesare</h2>
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width:0%">0%</div>
            </div>
            <p class="mb-2 text-muted" id="progressText">Astept...</p>
            <pre class="bg-light border rounded p-2 mb-0" id="logBox"></pre>
        </div>
    </div>

    <div id="cardsSection" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Cartele produse importate</h2>
            <a class="btn btn-outline-primary btn-sm" href="admin/?page=products" id="adminCardsLink" style="display:none;">Vezi produse in Admin</a>
        </div>
        <div class="row g-3" id="cardsGrid"></div>
    </div>
</div>

<script>
(function () {
    const keyQs = <?= json_encode($keyQs, JSON_UNESCAPED_UNICODE) ?>;
    const uploadForm = document.getElementById('uploadForm');
    const progressCard = document.getElementById('progressCard');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logBox = document.getElementById('logBox');
    const cardsSection = document.getElementById('cardsSection');
    const cardsGrid = document.getElementById('cardsGrid');

    let jobId = null;
    let total = 0;
    let offset = 0;
    let savedDb = 0;
    let savedAdmin = 0;
    let found = 0;

    function appendLog(msg) {
        logBox.textContent += msg + "\n";
        logBox.scrollTop = logBox.scrollHeight;
    }

    function renderCard(c) {
        const col = document.createElement('div');
        col.className = 'col-md-4 col-sm-6';
        const img = c.image
            ? `<img src="${escapeAttr(c.image)}" class="card-img-top" alt="">`
            : `<div class="card-img-top d-flex align-items-center justify-content-center text-muted">Fara imagine</div>`;
        col.innerHTML = `
            <article class="card product-card-import">
                ${img}
                <div class="card-body">
                    <h3 class="h6 card-title">${escapeHtml(c.title || 'Piesa')}</h3>
                    <p class="small text-muted mb-1">${escapeHtml(c.brand || '')}</p>
                    <p class="small mb-1">${escapeHtml(c.car || '')}</p>
                    <div class="badge bg-light text-dark">OEM: ${escapeHtml(c.cod_oem || '-')}</div>
                </div>
            </article>`;
        cardsGrid.appendChild(col);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
    function escapeAttr(s) { return escapeHtml(s).replace(/"/g, '&quot;'); }

    async function processBatch() {
        const limit = Math.min(50, Math.max(1, parseInt(document.getElementById('batchSize').value, 10) || 5));
        const url = `import-catalog.php?action=process&job=${encodeURIComponent(jobId)}&offset=${offset}&limit=${limit}${keyQs}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) {
            appendLog('Eroare: ' + (data.error || 'necunoscuta'));
            return;
        }
        const b = data.batch || {};
        offset = data.next_offset;
        savedDb += b.saved_db || 0;
        savedAdmin += b.saved_admin || 0;
        found += b.found || 0;
        (b.log || []).forEach(l => appendLog(JSON.stringify(l)));
        (b.errors || []).forEach(e => appendLog('ERR: ' + e));
        (b.cards || []).forEach(renderCard);

        const pct = total > 0 ? Math.round((offset / total) * 100) : 0;
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
        progressText.textContent = `Procesat ${offset} / ${total} intrari | gasite: ${found} | DB: ${savedDb} | JSON admin: ${savedAdmin}`;

        if (!data.done) {
            await processBatch();
        } else {
            progressBar.classList.remove('progress-bar-animated');
            appendLog('Import finalizat. Produsele sunt in admin.');
            const link = document.getElementById('adminCardsLink');
            link.style.display = 'inline-block';
            link.href = 'admin/?page=products';
        }
    }

    async function startJob(id, totalEntries) {
        jobId = id;
        total = totalEntries;
        offset = 0;
        savedDb = 0;
        savedAdmin = 0;
        found = 0;
        cardsGrid.innerHTML = '';
        cardsSection.style.display = 'block';
        progressCard.style.display = 'block';
        progressBar.classList.add('progress-bar-animated');
        await fetch('import-catalog.php?action=purge_cache' + keyQs);
        await processBatch();
    }

    document.getElementById('btnRebuildLast')?.addEventListener('click', async function () {
        const res = await fetch('import-catalog.php?action=last_job' + keyQs);
        const data = await res.json();
        if (!data.ok) {
            alert(data.error || 'Nu exista job anterior.');
            return;
        }
        logBox.textContent = '';
        appendLog('Reprocesare job ' + data.job + '...');
        await startJob(data.job, data.total);
        document.getElementById('adminCardsLink').style.display = 'inline-block';
    });

    const autostart = new URLSearchParams(window.location.search).get('autostart');
    if (autostart) {
        (async function () {
            logBox.textContent = '';
            appendLog('Pornire automata job ' + autostart + '...');
            const res = await fetch('import-catalog.php?action=last_job' + keyQs);
            const data = await res.json();
            const totalEntries = data.ok && data.job === autostart ? data.total : 0;
            if (totalEntries > 0) {
                await startJob(autostart, totalEntries);
            } else {
                const jobRes = await fetch('import-catalog.php?action=process&job=' + encodeURIComponent(autostart) + '&offset=0&limit=1' + keyQs);
                const probe = await jobRes.json();
                const total = probe.total || 0;
                if (total > 0) {
                    await startJob(autostart, total);
                } else {
                    appendLog('Job negasit: ' + autostart);
                }
            }
            document.getElementById('adminCardsLink').style.display = 'inline-block';
        })();
    }

    uploadForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(uploadForm);
        fd.append('action', 'upload');
        const btn = document.getElementById('btnUpload');
        btn.disabled = true;
        cardsGrid.innerHTML = '';
        cardsSection.style.display = 'block';
        progressCard.style.display = 'block';
        logBox.textContent = '';
        appendLog('Incarcare fisier...');

        try {
            const res = await fetch('import-catalog.php?action=upload' + keyQs, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) {
                appendLog('Eroare: ' + (data.error || ''));
                btn.disabled = false;
                return;
            }
            appendLog(`Job ${data.job}, ${data.total} intrari de procesat.`);
            await startJob(data.job, data.total);
        } catch (err) {
            appendLog('Eroare retea: ' + err.message);
        }
        btn.disabled = false;
    });
})();
</script>
</body>
</html>
