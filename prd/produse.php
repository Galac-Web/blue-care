<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/products_store.php';
require_once dirname(__DIR__) . '/lib/products_admin_panel.php';
require_once dirname(__DIR__) . '/lib/pricing.php';

function prd_h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function prd_require_admin(): void
{
    if (empty($_SESSION['admin'])) {
        header('Location: ../admin/?page=login');
        exit;
    }
}

function prd_image_url(string $img): string
{
    $img = trim($img);
    if ($img === '') {
        return 'https://via.placeholder.com/300x200?text=Fara+imagine';
    }
    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }
    if (str_starts_with($img, 'data/')) {
        return '../' . $img;
    }
    return '../data/uploads/' . ltrim($img, '/');
}

/** @return array<int, array> */
function prd_load_views(): array
{
    $views = [];
    foreach (blu_load_products() as $product) {
        if (!is_array($product)) {
            continue;
        }
        $id = (int)($product['id'] ?? $product['randomn_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $views[$id] = blu_product_view_data($product);
    }
    return $views;
}

function prd_update_product(int $id, array $fields): bool
{
    $products = blu_load_products();
    $updated = false;
    foreach ($products as &$product) {
        $pid = (int)($product['id'] ?? $product['randomn_id'] ?? 0);
        if ($pid !== $id) {
            continue;
        }
        foreach (['nume', 'descriere', 'pret', 'stoc', 'categorie', 'imagine'] as $key) {
            if (array_key_exists($key, $fields)) {
                $product[$key] = $fields[$key];
            }
        }
        $product['updated_at'] = date('Y-m-d H:i:s');
        $updated = true;
        break;
    }
    unset($product);
    return $updated && blu_save_products($products);
}

prd_require_admin();

$flash = '';
$error = '';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'quick_save') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['product_id'] ?? 0);
    $txt = (string)($_POST['ad_description'] ?? '');
    $ok = $id > 0 && prd_update_product($id, ['descriere' => $txt]);
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'export_csv') {
        $minPrice = max(0, (float)($_POST['min_price'] ?? 0));
        $views = prd_load_views();
        $rows = [];
        foreach ($views as $v) {
            if ((float)($v['pret_final'] ?? 0) >= $minPrice) {
                $rows[] = $v;
            }
        }
        if ($rows === []) {
            $error = 'Nu există produse cu preț final >= ' . (int)$minPrice . ' RON.';
        } else {
            $exportDir = __DIR__ . '/exports';
            if (!is_dir($exportDir)) {
                @mkdir($exportDir, 0775, true);
            }
            $filename = 'produse_export_' . date('Ymd_His') . '.csv';
            $filepath = $exportDir . '/' . $filename;
            $fp = fopen($filepath, 'w');
            fprintf($fp, "\xEF\xBB\xBF");
            fputcsv($fp, ['ID', 'Titlu', 'Marca', 'Categorie', 'Cod OEM', 'Pret final RON', 'Stoc', 'Descriere']);
            foreach ($rows as $v) {
                fputcsv($fp, [
                    (string)($v['id'] ?? ''),
                    (string)($v['title'] ?? ''),
                    (string)($v['car_brand'] ?? ''),
                    (string)($v['sub_category'] ?? ''),
                    (string)($v['cod_oem'] ?? ''),
                    number_format((float)($v['pret_final'] ?? 0), 2, '.', ''),
                    (string)($v['stoc'] ?? '1'),
                    (string)($v['descriere'] ?? ''),
                ]);
            }
            fclose($fp);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($filepath);
            exit;
        }
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $res = blu_delete_product_everywhere($id);
            $flash = 'Produs șters (#' . $id . ').';
        }
    }

    if ($action === 'update_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $pret = trim((string)($_POST['price_ron_base'] ?? ''));
            if (prd_update_product($id, [
                'nume' => trim((string)($_POST['ad_title'] ?? $_POST['title'] ?? '')),
                'descriere' => trim((string)($_POST['ad_description'] ?? '')),
                'pret' => $pret,
            ])) {
                $flash = 'Produs actualizat.';
            } else {
                $error = 'Nu am putut salva produsul.';
            }
        }
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$filterMinPrice = max(0, (float)($_GET['min_price'] ?? 0));
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 30;

$allViews = prd_load_views();
$filtered = [];
foreach ($allViews as $id => $v) {
    $hay = mb_strtolower(implode(' ', [
        (string)($v['title'] ?? ''),
        (string)($v['car_brand'] ?? ''),
        (string)($v['cod_oem'] ?? ''),
        (string)($v['sub_category'] ?? ''),
        (string)($v['descriere'] ?? ''),
    ]), 'UTF-8');
    if ($search !== '' && !str_contains($hay, mb_strtolower($search, 'UTF-8'))) {
        continue;
    }
    if ($filterMinPrice > 0 && (float)($v['pret_final'] ?? 0) < $filterMinPrice) {
        continue;
    }
    $filtered[$id] = $v;
}

$totalCount = count($filtered);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$slice = array_slice($filtered, ($page - 1) * $perPage, $perPage, true);

?><!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Produse BD — <?= (int)$totalCount ?> produse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', sans-serif; }
        .prd-card { transition: box-shadow .2s; }
        .prd-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.12); }
        .prd-thumb { height: 140px; object-fit: contain; width: 100%; background: #fff; }
        .prd-title { font-size: .82rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .ad-text { font-size: .72rem; line-height: 1.35; resize: vertical; min-height: 23vh; }
    </style>
</head>
<body>
<div class="container-fluid py-3">

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show py-2"><?= prd_h($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2"><?= prd_h($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Produse BD <span class="badge bg-primary"><?= (int)$totalCount ?></span></h4>
            <small class="text-muted">Pagina <?= (int)$page ?>/<?= (int)$totalPages ?> · date din <code>data/products.json</code></small>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form class="d-flex gap-2 align-items-center" method="get">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Caută produs..." value="<?= prd_h($search) ?>" style="width:160px">
                <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Preț min" value="<?= $filterMinPrice > 0 ? (int)$filterMinPrice : '' ?>" style="width:90px" title="Preț final minim RON">
                <button class="btn btn-sm btn-outline-primary">Filtrează</button>
            </form>
            <form method="post" class="d-flex gap-1 align-items-center">
                <input type="hidden" name="action" value="export_csv">
                <input type="number" name="min_price" value="0" min="0" step="100" class="form-control form-control-sm" style="width:90px" title="Preț final minim RON">
                <button class="btn btn-sm btn-success"><i class="bx bx-download"></i> CSV</button>
            </form>
            <a href="../admin/?page=products" class="btn btn-sm btn-outline-primary"><i class="bx bx-grid-alt"></i> Admin produse</a>
            <a href="../admin/?page=furnizori&tab=adaos" class="btn btn-sm btn-outline-secondary"><i class="bx bx-wallet"></i> Adaos</a>
            <a href="../admin/?page=dashboard" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back"></i> Admin</a>
        </div>
    </div>

    <?php if ($slice === []): ?>
        <div class="text-center py-5 text-muted">
            <i class="bx bx-data bx-lg"></i>
            <p class="mb-2">Nu există produse<?= $search !== '' ? ' pentru „' . prd_h($search) . '”' : '' ?>.</p>
            <p class="small">Scanează cu robotul GBG sau importă din TecDoc.</p>
        </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($slice as $id => $v):
            $thumb = prd_image_url((string)($v['image'] ?? ''));
            $priceDisplay = number_format((float)($v['pret_final'] ?? 0), 0, ',', ' ') . ' RON';
            $payload = json_encode([
                'product_id' => $id,
                'title' => (string)($v['title'] ?? ''),
                'ad_title' => (string)($v['title'] ?? ''),
                'ad_description' => (string)($v['descriere'] ?? ''),
                'price_ron_base' => (string)($v['pret_baza'] ?? 0),
                'price_ron_final' => (float)($v['pret_final'] ?? 0),
                'images' => [$thumb],
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?>
        <div class="col-xl-4 col-lg-4 col-md-6">
            <div class="card prd-card h-100">
                <div class="row g-0">
                    <div class="col-4">
                        <img class="prd-thumb rounded-start" src="<?= prd_h($thumb) ?>" alt="" loading="lazy">
                    </div>
                    <div class="col-8 p-2 d-flex flex-column">
                        <div class="prd-title fw-semibold mb-1"><?= prd_h((string)($v['title'] ?? '')) ?></div>
                        <div class="text-primary fw-bold" style="font-size:.85rem"><?= prd_h($priceDisplay) ?></div>
                        <div class="text-muted" style="font-size:.7rem"><?= prd_h((string)($v['car_brand'] ?? '')) ?> · <?= prd_h((string)($v['sub_category'] ?? '')) ?></div>
                        <div class="mt-auto d-flex gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" style="font-size:.65rem" onclick="openEdit(this)" data-p="<?= prd_h($payload) ?>">Editare</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Sigur ștergi?')">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= (int)$id ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:.65rem">&times;</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-footer p-2" style="border-top:1px solid #eee">
                    <textarea class="form-control ad-text" rows="4" placeholder="Text anunț (cartelă TecDoc)..." onchange="quickSave(<?= (int)$id ?>, this.value)"><?= prd_h((string)($v['descriere'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-sm">
            <?php
            $qs = ($search !== '' ? '&q=' . rawurlencode($search) : '') . ($filterMinPrice > 0 ? '&min_price=' . (int)$filterMinPrice : '');
            ?>
            <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?p=<?= $page - 1 ?><?= $qs ?>">&laquo;</a></li><?php endif; ?>
            <?php for ($pg = max(1, $page - 3); $pg <= min($totalPages, $page + 3); $pg++): ?>
            <li class="page-item <?= $pg === $page ? 'active' : '' ?>"><a class="page-link" href="?p=<?= $pg ?><?= $qs ?>"><?= $pg ?></a></li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?p=<?= $page + 1 ?><?= $qs ?>">&raquo;</a></li><?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editare produs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" id="emPid">
                <div class="modal-body">
                    <div id="emCarousel" class="mb-3" style="max-height:250px;overflow:hidden;background:#f0f0f0;border-radius:8px;text-align:center"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Titlu anunț</label><input type="text" name="ad_title" id="emAdTitle" class="form-control form-control-sm"></div>
                    <div class="mb-2">
                        <label class="form-label small">Preț bază (cost) — finalul se calculează cu adaos + TVA</label>
                        <input type="number" step="0.01" name="price_ron_base" id="emPriceBase" class="form-control form-control-sm">
                        <div class="form-text">Preț final afișat: <strong id="emPriceFinal">—</strong></div>
                    </div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Text anunț complet</label><textarea name="ad_description" id="emAdDesc" class="form-control" rows="8" style="font-size:.8rem"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anulează</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-save"></i> Salvează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(btn) {
    var d = JSON.parse(btn.getAttribute('data-p'));
    document.getElementById('emPid').value = d.product_id;
    document.getElementById('emAdTitle').value = d.ad_title || d.title || '';
    document.getElementById('emPriceBase').value = d.price_ron_base || 0;
    document.getElementById('emPriceFinal').textContent = (d.price_ron_final || 0).toLocaleString('ro-RO') + ' RON (cu adaos)';
    document.getElementById('emAdDesc').value = d.ad_description || '';
    var car = document.getElementById('emCarousel');
    car.innerHTML = (d.images || []).map(function (src) {
        return '<img src="' + src + '" style="max-height:240px;max-width:100%;object-fit:contain;margin:5px" loading="lazy">';
    }).join('');
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function quickSave(pid, val) {
    var fd = new FormData();
    fd.append('product_id', pid);
    fd.append('ad_description', val);
    fetch('?ajax=quick_save', { method: 'POST', body: fd }).catch(function () {});
}
</script>
</body>
</html>
