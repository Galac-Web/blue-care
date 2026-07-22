<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/lib/catalog_import.php';
require_once dirname(__DIR__) . '/lib/autodoc_scraper.php';

$input = array_merge($_GET, $_POST);
$format = strtolower(trim((string)($input['format'] ?? 'json')));
$oem = preg_replace('/\s+/u', '', trim((string)($input['oem'] ?? $input['keyword'] ?? '')));

if ($oem === '') {
    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        echo blu_autodoc_search_help_html('');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametrul oem este obligatoriu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!blu_import_access_ok()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acces interzis (IMPORT_ACCESS_KEY).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$noCache = in_array(strtolower(trim((string)($input['refresh'] ?? ''))), ['1', 'true', 'yes'], true);
$ttl = $noCache ? 0 : 3600;

$filterOverride = [
    'brand' => $input['brand'] ?? $input['brands'] ?? '',
    'min_price' => $input['min_price'] ?? '',
    'max_price' => $input['max_price'] ?? '',
    'in_stock_only' => $input['in_stock'] ?? $input['in_stock_only'] ?? '',
];

$search = blu_autodoc_search($oem, $filterOverride, $ttl);
if (!empty($search['error'])) {
    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        echo blu_autodoc_search_help_html($oem, $search);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $search['error'], 'oem' => $oem], JSON_UNESCAPED_UNICODE);
    exit;
}

$import = in_array(strtolower(trim((string)($input['import'] ?? ''))), ['1', 'true', 'yes'], true);
$importAll = blu_autodoc_import_all_enabled();
if (isset($input['all'])) {
    $importAll = in_array(strtolower(trim((string)$input['all'])), ['1', 'true', 'yes'], true);
}

$products = $search['products'] ?? [];
$toImport = blu_autodoc_items_for_import($products, $importAll);
$cards = [];
$saved = ['saved_db' => 0, 'saved_admin' => 0];

if ($import && $toImport !== []) {
    $contextBase = [
        'brand' => trim((string)($input['car_brand'] ?? $input['brand_car'] ?? '')),
        'model' => trim((string)($input['model'] ?? '')),
        'cod_articol' => trim((string)($input['cod_articol'] ?? '')),
        'coduri_oem' => $oem,
    ];
    $fallbackImage = blu_autodoc_first_item_image($toImport);
    $cardsToSave = [];

    foreach ($toImport as $item) {
        if (!is_array($item)) {
            continue;
        }
        $context = $contextBase + ['search_code' => $oem];
        $card = blu_autodoc_item_to_product_card($item, $context, $fallbackImage);
        $cardsToSave[] = $card;
        $cards[] = blu_card_to_display_row($card, $contextBase, 'imported');
    }

    if ($cardsToSave !== []) {
        $db = blu_db_products_bootstrap();
        if ($db) {
            $sync = blu_save_products_to_db($db['pdo'], $cardsToSave);
            $saved['saved_db'] = (int)($sync['imported'] ?? 0);
        }
        $saved['saved_admin'] = blu_merge_admin_products($cardsToSave);
        blu_merge_imported_cards($cards);
        if (is_file(dirname(__DIR__) . '/lib/products_store.php')) {
            require_once dirname(__DIR__) . '/lib/products_store.php';
            blu_sync_imported_cards_to_products();
        }
    }
}

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo blu_autodoc_search_help_html($oem, $search, $products, $import, $saved);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'oem' => $oem,
    'from_cache' => !empty($search['from_cache']),
    'total_count' => (int)($search['total_count'] ?? 0),
    'filtered_count' => (int)($search['filtered_count'] ?? 0),
    'import_all' => $importAll,
    'would_import' => count($toImport),
    'filters' => $search['filters'] ?? [],
    'products' => array_map(static function (array $p): array {
        return [
            'article_id' => $p['article_id'] ?? '',
            'article_no' => $p['article_no'] ?? '',
            'brand' => $p['brand'] ?? '',
            'title' => $p['title'] ?? '',
            'price' => $p['price'] ?? 0,
            'currency' => $p['currency'] ?? 'RON',
            'in_stock' => !empty($p['in_stock']),
            'image' => $p['image'] ?? '',
            'url' => $p['url'] ?? '',
            'oem' => $p['oem_highlight'] ?? '',
        ];
    }, $products),
    'imported' => $import,
    'saved' => $saved,
    'cards' => $cards,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * @param list<array>|null $products
 * @param array<string,mixed>|null $search
 */
function blu_autodoc_search_help_html(string $oem, ?array $search = null, ?array $products = null, bool $imported = false, array $saved = []): string
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $products = $products ?? ($search['products'] ?? []);
    $error = is_array($search) ? (string)($search['error'] ?? '') : '';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Autodoc OEM <?= $oem !== '' ? $esc($oem) : 'test' ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; background: #f4f6f8; color: #1a1a1a; }
        .box { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; }
        img { max-width: 72px; max-height: 72px; object-fit: contain; }
        code { background: #eef2ff; padding: 2px 6px; border-radius: 4px; }
        .muted { color: #6b7280; font-size: 13px; }
        .err { color: #b91c1c; }
        form label { display: inline-block; min-width: 110px; }
        form p { margin: 8px 0; }
        input[type=text], input[type=number] { padding: 6px 8px; min-width: 220px; }
        button { padding: 8px 14px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Cautare Autodoc24</h1>
    <div class="box">
        <form method="get">
            <p><label>OEM</label> <input type="text" name="oem" value="<?= $esc($oem) ?>" placeholder="60573516" required></p>
            <p><label>Brand</label> <input type="text" name="brand" placeholder="MIRAGLIO,BLIC"></p>
            <p><label>Pret min</label> <input type="number" step="0.01" name="min_price" placeholder="0"></p>
            <p><label>Pret max</label> <input type="number" step="0.01" name="max_price" placeholder="0"></p>
            <p><label>In stoc</label> <input type="checkbox" name="in_stock" value="1"></p>
            <p><label>Import DB</label> <input type="checkbox" name="import" value="1"></p>
            <p><label>Toate</label> <input type="checkbox" name="all" value="1" checked></p>
            <p><label>Refresh</label> <input type="checkbox" name="refresh" value="1"></p>
            <p><label></label> <button type="submit">Cauta</button></p>
            <p class="muted">JSON: <code>?oem=60573516&amp;format=json</code></p>
        </form>
    </div>
    <?php if ($error !== ''): ?>
        <div class="box err"><?= $esc($error) ?></div>
    <?php elseif ($oem !== ''): ?>
        <div class="box">
            <p><strong>OEM:</strong> <?= $esc($oem) ?>
                — <?= (int)($search['filtered_count'] ?? count($products)) ?> / <?= (int)($search['total_count'] ?? count($products)) ?> produse
                <?php if (!empty($search['from_cache'])): ?><span class="muted">(cache)</span><?php endif; ?>
            </p>
            <?php if ($imported): ?>
                <p class="muted">Import: DB <?= (int)($saved['saved_db'] ?? 0) ?>, admin <?= (int)($saved['saved_admin'] ?? 0) ?></p>
            <?php endif; ?>
        </div>
        <div class="box">
            <table>
                <thead>
                    <tr>
                        <th>Imagine</th>
                        <th>Brand / Titlu</th>
                        <th>Articol</th>
                        <th>Pret</th>
                        <th>Stoc</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <?php if (!is_array($p)) { continue; } ?>
                    <tr>
                        <td><?php if (!empty($p['image'])): ?><img src="<?= $esc((string)$p['image']) ?>" alt=""><?php endif; ?></td>
                        <td><strong><?= $esc((string)($p['brand'] ?? '')) ?></strong><br><?= $esc((string)($p['title'] ?? '')) ?></td>
                        <td><?= $esc((string)($p['article_no'] ?? '')) ?></td>
                        <td><?= $esc(number_format((float)($p['price'] ?? 0), 2, ',', '.')) ?> lei</td>
                        <td><?= !empty($p['in_stock']) ? 'Da' : 'Nu' ?></td>
                        <td><?php if (!empty($p['url'])): ?><a href="<?= $esc((string)$p['url']) ?>" target="_blank" rel="noopener">Detalii</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}
