<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/pricing.php';

const BLU_RAPIDAPI_HOST = 'auto-parts-catalog.p.rapidapi.com';
const BLU_RAPIDAPI_LANG_ID = 21;
const BLU_API_DELAY_MS = 250;

function blu_project_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }
    $resolved = realpath(dirname(__DIR__));
    $root = $resolved !== false ? $resolved : dirname(__DIR__);
    return $root;
}

function blu_data_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_uploads_dir(): string
{
    $dir = blu_data_dir() . DIRECTORY_SEPARATOR . 'catalog_uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_import_access_ok(): bool
{
    $secret = trim((string)blu_env('IMPORT_ACCESS_KEY', ''));
    if ($secret === '') {
        return true;
    }
    $provided = trim((string)($_GET['key'] ?? $_POST['key'] ?? ''));
    return hash_equals($secret, $provided);
}

function blu_read_json_file(string $file, $fallback = [])
{
    if (!is_file($file)) {
        return $fallback;
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $fallback;
}

function blu_write_json_file(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    // Scriere atomica: fisier temporar + rename, ca datele sa nu ramana corupte
    // daca procesul moare in timpul scrierii.
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $file)) {
        // Pe Windows rename peste fisier existent poate esua — fallback cu stergere.
        @unlink($file);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
    }
    return true;
}

/**
 * Extrage intrările din catalog_auto.json: brand, model, cod_articol, coduri_oem.
 *
 * @return list<array{brand:string,model:string,cod_articol:string,coduri_oem:string,codes:list<string>}>
 */
function blu_catalog_flatten_entries(array $catalog): array
{
    $entries = [];
    foreach ($catalog as $brand => $models) {
        if (!is_array($models)) {
            continue;
        }
        $brandName = trim((string)$brand);
        foreach ($models as $model => $parts) {
            if (!is_array($parts)) {
                continue;
            }
            $modelName = trim((string)$model);
            foreach ($parts as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $codArticol = trim((string)($part['cod_articol'] ?? ''));
                $coduriOem = trim((string)($part['coduri_oem'] ?? ''));
                $codes = blu_extract_search_codes($codArticol, $coduriOem);
                if ($codes === []) {
                    continue;
                }
                $entries[] = [
                    'brand' => $brandName,
                    'model' => $modelName,
                    'cod_articol' => $codArticol,
                    'coduri_oem' => $coduriOem,
                    'codes' => $codes,
                ];
            }
        }
    }
    return $entries;
}

/** @return list<string> Doar coduri OEM (separate prin virgula/punct-virgula). */
function blu_extract_oem_codes_only(string $coduriOem): array
{
    $raw = [];
    foreach (preg_split('/[,;\/|]+/u', $coduriOem) as $piece) {
        $c = preg_replace('/\s+/u', '', trim($piece));
        $c = preg_replace('/[^A-Za-z0-9]/', '', (string) $c) ?? '';
        if ($c !== '') {
            $raw[] = $c;
        }
    }
    $seen = [];
    $out = [];
    foreach ($raw as $code) {
        $key = strtoupper($code);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $code;
    }
    return $out;
}

/**
 * OEM-uri optimizate pentru căutare (variante + Ollama dacă e nevoie).
 * @return list<string>
 */
function blu_extract_oem_codes_optimized(string $coduriOem, string $codArticol = '', string $brand = ''): array
{
    $base = blu_extract_oem_codes_only($coduriOem);
    require_once __DIR__ . '/ollama_client.php';
    if (!function_exists('blu_ollama_optimize_oem_codes')) {
        return $base;
    }
    $opt = blu_ollama_optimize_oem_codes($base, $codArticol, $brand);
    return $opt !== [] ? $opt : $base;
}

function blu_entry_missing_oem(string $codArticol, string $coduriOem): bool
{
    return blu_extract_oem_codes_only($coduriOem) === [];
}

/** @return list<string> OEM-uri intai (fiecare din lista), apoi cod articol. */
function blu_extract_search_codes(string $codArticol, string $coduriOem): array
{
    $raw = blu_extract_oem_codes_only($coduriOem);
    if ($codArticol !== '') {
        $raw[] = preg_replace('/\s+/u', '', trim($codArticol));
    }
    $seen = [];
    $out = [];
    foreach ($raw as $code) {
        $key = strtoupper($code);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $code;
    }
    return $out;
}

function blu_rapidapi_key(): string
{
    return trim((string)blu_env('RAPIDAPI_AUTOPARTS_KEY', ''));
}

function blu_cache_dir(): string
{
    $dir = blu_data_dir() . DIRECTORY_SEPARATOR . 'rapidapi_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_api_response_is_cacheable(array $decoded): bool
{
    if (!empty($decoded['error'])) {
        return false;
    }
    $msg = (string)($decoded['message'] ?? '');
    if ($msg !== '' && (stripos($msg, 'does not exist') !== false || stripos($msg, 'not subscribed') !== false)) {
        return false;
    }
    return isset($decoded['articles']) || isset($decoded['countArticles']);
}

function blu_api_request(string $url, int $ttl = 86400): array
{
    $apiKey = blu_rapidapi_key();
    if ($apiKey === '') {
        return ['error' => 'Lipseste RAPIDAPI_AUTOPARTS_KEY in .env (robot sau blu-car.ro).'];
    }

    $cacheFile = blu_cache_dir() . DIRECTORY_SEPARATOR . md5($url) . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && blu_api_response_is_cacheable($cached)) {
            return $cached;
        }
        @unlink($cacheFile);
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-rapidapi-host: ' . BLU_RAPIDAPI_HOST,
            'x-rapidapi-key: ' . $apiKey,
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($err) {
        return ['error' => 'cURL: ' . $err];
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        return ['error' => 'Raspuns API invalid.', 'http' => $httpCode];
    }

    if (isset($decoded['message']) && stripos((string)$decoded['message'], 'not subscribed') !== false) {
        return [
            'error' => 'Contul RapidAPI nu este abonat la auto-parts-catalog.',
            'rapidapi_message' => $decoded['message'],
        ];
    }

    if (blu_api_response_is_cacheable($decoded)) {
        @file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));
    }

    return $decoded;
}

/**
 * Cauta articole dupa cod (OEM / numar articol) — endpoint RapidAPI valid.
 */
function blu_fetch_articles_by_code(string $code, string $articleType = 'OENumber', int $ttl = 86400): array
{
    // Cast explicit: OEM numeric din JSON/array poate ajunge int în PHP.
    $code = preg_replace('/\s+/u', '', trim((string) $code));
    if ($code === '') {
        return ['error' => 'Cod gol.'];
    }

    $url = 'https://' . BLU_RAPIDAPI_HOST
        . '/artlookup/search-for-cross-numbers/lang-id/' . BLU_RAPIDAPI_LANG_ID
        . '/article-type/' . rawurlencode($articleType)
        . '/article-no/' . rawurlencode($code);

    return blu_api_request($url, $ttl);
}

function blu_fetch_articles_by_oem(string $oemNo, int $ttl = 86400): array
{
    return blu_fetch_articles_by_code($oemNo, 'OENumber', $ttl);
}

function blu_fetch_articles_for_entry(array $entry, int $ttl = 86400): array
{
    $codArticol = preg_replace('/\s+/u', '', trim((string)($entry['cod_articol'] ?? '')));
    $oemCodes = blu_extract_oem_codes_optimized(
        (string) ($entry['coduri_oem'] ?? ''),
        (string) ($entry['cod_articol'] ?? ''),
        (string) ($entry['brand'] ?? '')
    );
    if ($oemCodes === []) {
        $oemCodes = blu_extract_oem_codes_only((string) ($entry['coduri_oem'] ?? ''));
    }
    $tried = [];

    foreach ($oemCodes as $code) {
        $code = (string) $code;
        $api = blu_fetch_articles_by_code($code, 'OENumber', $ttl);
        $tried[] = ['code' => $code, 'type' => 'OENumber', 'count' => is_array($api['articles'] ?? null) ? count($api['articles']) : 0];
        if (!empty($api['error'])) {
            continue;
        }
        $articles = $api['articles'] ?? [];
        if (is_array($articles) && $articles !== []) {
            $api['_search_type'] = 'OENumber';
            $api['_search_code'] = $code;
            $api['_tried'] = $tried;
            return $api;
        }
    }

    if ($codArticol !== '') {
        $codArticol = (string) $codArticol;
        foreach (['ArticleNumber', 'OENumber', 'IAMNumber'] as $type) {
            $api = blu_fetch_articles_by_code($codArticol, $type, $ttl);
            $tried[] = ['code' => $codArticol, 'type' => $type, 'count' => is_array($api['articles'] ?? null) ? count($api['articles']) : 0];
            if (!empty($api['error'])) {
                continue;
            }
            $articles = $api['articles'] ?? [];
            if (is_array($articles) && $articles !== []) {
                $api['_search_type'] = $type;
                $api['_search_code'] = $codArticol;
                $api['_tried'] = $tried;
                return $api;
            }
        }
    }

    return ['articles' => [], 'countArticles' => 0, '_tried' => $tried];
}

require_once __DIR__ . '/tecdoc_product_enrich.php';

/** Imaginea unui singur articol RapidAPI (s3image sau prima din images[]). */
function blu_article_image(array $article): string
{
    $img = trim((string)($article['s3image'] ?? ''));
    if ($img === '' && !empty($article['images'][0]['url'])) {
        $img = trim((string)$article['images'][0]['url']);
    }
    return $img;
}

/**
 * Prima imagine disponibila dintre TOATE articolele (furnizorii) pentru acelasi cod.
 * Alternativa cand articolul ales nu are imagine proprie in TecDoc.
 */
function blu_first_article_image(array $articles): string
{
    foreach ($articles as $a) {
        if (!is_array($a)) {
            continue;
        }
        $img = blu_article_image($a);
        if ($img !== '') {
            return $img;
        }
    }
    return '';
}

/**
 * Transformă un articol RapidAPI + context catalog în cartelă produs.
 * $imageFallback = imagine de rezerva (de la alt furnizor) cand articolul nu are una.
 */
function blu_article_to_product_card(array $article, array $context, string $imageFallback = ''): array
{
    $articleId = (int)($article['articleId'] ?? 0);
    $articleNo = trim((string)($article['articleNo'] ?? ''));
    $titleOriginal = trim((string)($article['articleProductName'] ?? $article['articleName'] ?? 'Piesa auto'));
    $brand = trim((string)($article['supplierName'] ?? $article['brand'] ?? ''));
    $image = blu_article_image($article);
    if ($image === '' && $imageFallback !== '') {
        $image = trim($imageFallback);
    }

    $productId = $articleId > 0 ? 'tecdoc_' . $articleId : 'oem_' . md5($articleNo . '|' . ($context['search_code'] ?? ''));

    $meta = blu_tecdoc_build_enriched_product_meta($article, $context);
    $title = (string)($meta['title_ro'] ?? $titleOriginal);
    $description = (string)($meta['description'] ?? $titleOriginal);
    $mainCategory = (string)($meta['main_category'] ?? 'Piese auto');
    $subCategory = (string)($meta['sub_category'] ?? blu_tecdoc_translate_title($titleOriginal));
    $productCategory = (string)($meta['product_category'] ?? ($mainCategory . ' -> ' . $subCategory));

    $imagesForDb = $image !== '' ? [['url' => $image, 'remote' => true]] : [];
    $mainImageJson = $image !== '' ? json_encode([$image], JSON_UNESCAPED_UNICODE) : '';

    return [
        'id' => $productId,
        'product_id' => $productId,
        'slug' => blu_slugify($title . '-' . $articleNo),
        'source_url' => '',
        'title' => $title,
        'title_original' => $titleOriginal,
        'brand' => $brand,
        'car_brand' => (string)($context['brand'] ?? ''),
        'main_category' => $mainCategory,
        'sub_category' => $subCategory,
        'product_category' => $productCategory,
        'price_pln' => 0,
        'price_ron_final' => 0,
        'price_ron_display' => '',
        'description' => $description,
        'ad_title' => $title,
        'ad_description' => $description,
        'compatibility' => trim((string)($context['brand'] ?? '') . ' — ' . (string)($context['model'] ?? '')),
        'main_image' => $mainImageJson,
        'images_json' => json_encode($imagesForDb, JSON_UNESCAPED_UNICODE),
        'parameters_json' => json_encode([
            'article_id' => $articleId,
            'article_no' => $articleNo,
            'article_search_no' => $article['articleSearchNo'] ?? '',
            'supplier_id' => $article['supplierId'] ?? null,
            'tecdoc_categories' => $meta['tecdoc_categories'] ?? [],
            'criteria_count' => $meta['criteria_count'] ?? 0,
            'eans' => $meta['eans'] ?? [],
            'engine_codes' => $meta['engine_codes'] ?? [],
            'catalog' => [
                'cod_articol' => $context['cod_articol'] ?? '',
                'coduri_oem' => $context['coduri_oem'] ?? '',
                'search_code' => $context['search_code'] ?? '',
            ],
            'rapidapi_raw' => $article,
        ], JSON_UNESCAPED_UNICODE),
        'stock' => '1',
        'item_condition' => 'Nou',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'admin_card' => [
            'nume' => $title,
            'cod_oem' => $articleNo !== '' ? $articleNo : (string)($context['search_code'] ?? ''),
            'categorie' => $subCategory,
            'pret' => '',
            'stoc' => '1',
            'descriere' => $description,
            'imagine' => $image,
            'marca_masina' => (string)($context['brand'] ?? ''),
        ],
    ];
}

function blu_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 200) : 'piesa-auto';
}

function blu_db_settings_from_env(): ?array
{
    $name = trim((string)blu_env('DB_NAME', ''));
    $user = trim((string)blu_env('DB_USER', ''));
    if ($name === '' || $user === '') {
        return null;
    }
    return [
        'db_host' => trim((string)blu_env('DB_HOST', 'localhost')),
        'db_name' => $name,
        'db_user' => $user,
        'db_pass' => (string)blu_env('DB_PASS', ''),
    ];
}

function blu_db_products_bootstrap(): ?array
{
    $prdDb = blu_project_root() . DIRECTORY_SEPARATOR . 'prd' . DIRECTORY_SEPARATOR . 'db_products.php';
    if (!is_file($prdDb)) {
        return null;
    }
    require_once $prdDb;
    $settings = blu_db_settings_from_env();
    if ($settings === null && function_exists('dbProductsResolveSettings')) {
        $settings = dbProductsResolveSettings();
    }
    if ($settings === null) {
        return null;
    }
    if (!function_exists('dbProductsConnect')) {
        return null;
    }
    $pdo = dbProductsConnect($settings);
    if (!$pdo && function_exists('dbProductsResolveSettings')) {
        $fallback = dbProductsResolveSettings(null);
        if (is_array($fallback)) {
            $pdoFallback = dbProductsConnect($fallback);
            if ($pdoFallback) {
                $pdo = $pdoFallback;
                $settings = $fallback;
            }
        }
    }
    if (!$pdo) {
        return null;
    }
    return ['pdo' => $pdo, 'settings' => $settings];
}

function blu_save_products_to_db(PDO $pdo, array $products): array
{
    if (!function_exists('dbProductsCreateTable') || !function_exists('dbProductsSyncFromArray')) {
        return ['ok' => false, 'message' => 'Modul db_products indisponibil.'];
    }
    dbProductsCreateTable($pdo);
    return dbProductsSyncFromArray($pdo, $products, false, false);
}

function blu_admin_products_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'products.json';
}

function blu_merge_admin_products(array $cards): int
{
    $file = blu_admin_products_file();
    $existing = blu_read_json_file($file, []);
    if (!is_array($existing)) {
        $existing = [];
    }
    $isList = array_keys($existing) === range(0, max(0, count($existing) - 1));
    $list = $isList ? $existing : array_values($existing);

    $byOem = [];
    foreach ($list as $row) {
        if (!is_array($row)) {
            continue;
        }
        $k = strtoupper(trim((string)($row['cod_oem'] ?? $row['cod'] ?? '')));
        if ($k !== '') {
            $byOem[$k] = $row;
        }
    }

    $maxId = 0;
    foreach ($list as $row) {
        $maxId = max($maxId, (int)($row['id'] ?? $row['randomn_id'] ?? 0));
    }

    $added = 0;
    foreach ($cards as $card) {
        if (!is_array($card) || empty($card['admin_card'])) {
            continue;
        }
        $admin = $card['admin_card'];
        $oemKey = strtoupper(trim((string)($admin['cod_oem'] ?? '')));
        if ($oemKey === '') {
            continue;
        }
        if (isset($byOem[$oemKey])) {
            $byOem[$oemKey] = array_merge($byOem[$oemKey], $admin, ['updated_at' => date('Y-m-d H:i:s')]);
            continue;
        }
        $maxId++;
        $byOem[$oemKey] = array_merge($admin, [
            'id' => $maxId,
            'randomn_id' => $maxId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $added++;
    }

    blu_write_json_file($file, array_values($byOem));
    return $added;
}

/**
 * Procesează un lot de intrări din catalog.
 *
 * @param list<array> $entries
 * @return array{processed:int,found:int,saved_db:int,saved_admin:int,errors:list<string>,cards:list<array>,log:list<array>}
 */
function blu_process_catalog_batch(array $entries, int $offset, int $limit, bool $useFirstArticleOnly = true): array
{
    $slice = array_slice($entries, $offset, $limit);
    $result = [
        'processed' => 0,
        'found' => 0,
        'saved_db' => 0,
        'saved_admin' => 0,
        'errors' => [],
        'cards' => [],
        'log' => [],
    ];

    $cardsToSave = [];
    $seenProductIds = [];

    foreach ($slice as $entry) {
        $result['processed']++;
        $contextBase = [
            'brand' => $entry['brand'] ?? '',
            'model' => $entry['model'] ?? '',
            'cod_articol' => $entry['cod_articol'] ?? '',
            'coduri_oem' => $entry['coduri_oem'] ?? '',
        ];

        usleep(BLU_API_DELAY_MS * 1000);

        $oemOptimized = blu_extract_oem_codes_optimized(
            (string) ($entry['coduri_oem'] ?? ''),
            (string) ($entry['cod_articol'] ?? ''),
            (string) ($entry['brand'] ?? '')
        );
        if ($oemOptimized !== []) {
            $entry['coduri_oem'] = implode(', ', $oemOptimized);
            $entry['codes'] = array_values(array_unique(array_merge(
                $oemOptimized,
                is_array($entry['codes'] ?? null) ? $entry['codes'] : []
            )));
        }
        $useAutodoc = $oemOptimized !== [] || blu_extract_oem_codes_only((string) ($entry['coduri_oem'] ?? '')) !== [];
        if ($useAutodoc) {
            require_once __DIR__ . '/autodoc_scraper.php';
            $api = blu_autodoc_fetch_for_entry($entry);
            $searchCode = (string)($api['_search_code'] ?? ($entry['codes'][0] ?? ''));
            if (!empty($api['error'])) {
                $result['errors'][] = $searchCode . ': ' . $api['error'];
                $result['log'][] = ['code' => $searchCode, 'status' => 'error', 'message' => $api['error'], 'source' => 'autodoc'];
            }

            $autodocItemsRaw = $api['products'] ?? [];
            $filters = blu_autodoc_filter_options(is_array($entry['autodoc_filters'] ?? null) ? $entry['autodoc_filters'] : []);
            $autodocItems = blu_autodoc_apply_filters(
                is_array($autodocItemsRaw) ? $autodocItemsRaw : [],
                $filters
            );
            $foundForEntry = $autodocItems !== [];

            if ($foundForEntry) {
                $fallbackImage = blu_autodoc_first_item_image($autodocItems);
                $importAll = blu_autodoc_import_all_enabled();
                $toProcess = blu_autodoc_items_for_import($autodocItems, $importAll);

                foreach ($toProcess as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $context = $contextBase + ['search_code' => $searchCode];
                    $card = blu_autodoc_item_to_product_card($item, $context, $fallbackImage);
                    $card = blu_apply_strict_card_template($card, $context, $entry);
                    $card = blu_apply_gbg_supplier_pricing($card, $entry);
                    $pid = (string)($card['product_id'] ?? '');
                    if ($pid === '' || isset($seenProductIds[$pid])) {
                        continue;
                    }
                    $seenProductIds[$pid] = true;
                    $cardsToSave[] = $card;
                    $display = blu_card_to_display_row($card, $contextBase, 'imported');
                    $result['cards'][] = $display;
                    blu_merge_imported_cards([$display]);
                }
                $result['log'][] = [
                    'code' => $searchCode,
                    'status' => 'ok',
                    'count' => count($autodocItems),
                    'total' => is_array($autodocItemsRaw) ? count($autodocItemsRaw) : 0,
                    'imported' => count($toProcess),
                    'import_all' => $importAll,
                    'filters' => $filters,
                    'source' => 'autodoc',
                ];
                $result['found']++;
            } else {
                // Autodoc gol (piese vechi / OEM rar) → fallback TecDoc RapidAPI pe aceleași OEM.
                $result['log'][] = [
                    'code' => $searchCode,
                    'status' => 'empty',
                    'count' => 0,
                    'tried' => $api['_tried'] ?? [],
                    'source' => 'autodoc',
                    'message' => 'Autodoc24 gol — trec la TecDoc',
                ];
                $useAutodoc = false;
            }
        }

        if (!$useAutodoc) {
            $api = blu_fetch_articles_for_entry($entry);
            $searchCode = (string)($api['_search_code'] ?? ($entry['codes'][0] ?? ''));
            if (!empty($api['error'])) {
                $result['errors'][] = $searchCode . ': ' . $api['error'];
                $result['log'][] = ['code' => $searchCode, 'status' => 'error', 'message' => $api['error'], 'source' => 'tecdoc'];
            }

            $articles = $api['articles'] ?? [];
            $foundForEntry = is_array($articles) && $articles !== [];

            if ($foundForEntry) {
                // Imagine de rezerva: prima disponibila dintre toti furnizorii pentru acest cod.
                $fallbackImage = blu_first_article_image($articles);

                if ($useFirstArticleOnly) {
                    // Preferam ca articol principal pe cel care ARE imagine proprie,
                    // ca sa formam o cartela completa (imagine + descriere).
                    $primary = null;
                    foreach ($articles as $a) {
                        if (is_array($a) && blu_article_image($a) !== '') {
                            $primary = $a;
                            break;
                        }
                    }
                    if ($primary === null) {
                        $primary = reset($articles);
                    }
                    $toProcess = [$primary];
                } else {
                    $toProcess = $articles;
                }

                foreach ($toProcess as $article) {
                    if (!is_array($article)) {
                        continue;
                    }
                    $context = $contextBase + ['search_code' => $searchCode];
                    $card = blu_article_to_product_card($article, $context, $fallbackImage);
                    $card = blu_apply_gbg_supplier_pricing($card, $entry);
                    $pid = (string)($card['product_id'] ?? '');
                    if ($pid === '' || isset($seenProductIds[$pid])) {
                        continue;
                    }
                    $seenProductIds[$pid] = true;
                    $cardsToSave[] = $card;
                    $display = blu_card_to_display_row($card, $contextBase, 'imported');
                    $result['cards'][] = $display;
                    blu_merge_imported_cards([$display]);
                }
                $result['log'][] = [
                    'code' => $searchCode,
                    'status' => 'ok',
                    'count' => count($articles),
                    'source' => 'tecdoc',
                ];
                $result['found']++;
            } else {
                $stub = blu_catalog_stub_card($contextBase, $entry);
                $stubStatus = (string) ($stub['status'] ?? 'empty');
                if ($stubStatus === 'imported') {
                    // Traducere Ollama din textul GBG — salvăm ca produs valid.
                    $stub = blu_apply_gbg_supplier_pricing($stub, $entry);
                    $cardsToSave[] = $stub;
                    $result['found']++;
                    $result['log'][] = [
                        'code' => $searchCode,
                        'status' => 'ok',
                        'count' => 0,
                        'tried' => $api['_tried'] ?? [],
                        'source' => 'ollama_gbg',
                        'message' => 'Autodoc/TecDoc gol — titlu/descriere din Ollama (GR→RO)',
                    ];
                } else {
                    if (!blu_entry_missing_oem(
                        (string) ($entry['cod_articol'] ?? ''),
                        (string) ($entry['coduri_oem'] ?? '')
                    )) {
                        $stub['status'] = 'empty';
                        $stub['descriere'] = (string) ($stub['descriere'] ?? '') . "\nTecDoc: niciun articol gasit.";
                    }
                    $result['log'][] = [
                        'code' => $searchCode,
                        'status' => ($stub['status'] ?? 'empty'),
                        'count' => 0,
                        'tried' => $api['_tried'] ?? [],
                        'source' => 'tecdoc',
                    ];
                }
                $result['cards'][] = $stub;
                blu_merge_imported_cards([$stub]);
            }
        }
    }

    if ($cardsToSave !== []) {
        $db = blu_db_products_bootstrap();
        if ($db) {
            $sync = blu_save_products_to_db($db['pdo'], $cardsToSave);
            $result['saved_db'] = (int)($sync['imported'] ?? 0);
            if (!empty($sync['errors'])) {
                $result['errors'][] = 'Erori DB: ' . (int)$sync['errors'];
            }
        } else {
            $result['errors'][] = 'Baza de date MySQL nu este configurata (prd/config.php).';
        }

        $result['saved_admin'] = blu_merge_admin_products($cardsToSave);
    }

    if (is_file(__DIR__ . '/products_store.php')) {
        require_once __DIR__ . '/products_store.php';
        blu_sync_imported_cards_to_products();
    }

    return $result;
}

function blu_imported_cards_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'catalog_imported_cards.json';
}

/** Cheie stabila pentru acelasi cod catalog (indiferent de product_id tecdoc vs stub). */
function blu_catalog_card_key(array $row): string
{
    $productId = trim((string)($row['product_id'] ?? ''));
    if ($productId !== '' && str_starts_with($productId, 'autodoc_')) {
        return 'autodoc:' . $productId;
    }
    $art = strtoupper(preg_replace('/\s+/u', '', trim((string)($row['cod_articol'] ?? ''))));
    $oemRaw = trim((string)($row['coduri_oem'] ?? $row['cod_oem'] ?? ''));
    $oem = strtoupper(preg_replace('/[\s,;]+/u', '', $oemRaw));
    if ($art === '' && $oem === '') {
        return 'id:' . (string)($row['product_id'] ?? md5(json_encode($row, JSON_UNESCAPED_UNICODE)));
    }
    return $art . '|' . $oem;
}

function blu_card_status_priority(string $status): int
{
    return match ($status) {
        'imported' => 4,
        'error' => 3,
        'pending' => 2,
        'empty' => 2,
        'no_oem' => 1,
        default => 0,
    };
}

function blu_card_missing_oem(array $card): bool
{
    if (!empty($card['missing_oem'])) {
        return true;
    }
    if (($card['status'] ?? '') === 'no_oem') {
        return true;
    }
    $oemField = trim((string)($card['coduri_oem'] ?? ''));
    if ($oemField === '') {
        return true;
    }
    return blu_extract_oem_codes_only($oemField) === [];
}

/** @return list<array> */
function blu_dedupe_imported_cards(array $cards): array
{
    $byKey = [];
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $k = blu_catalog_card_key($card);
        if (!isset($byKey[$k]) || blu_card_status_priority((string)($card['status'] ?? ''))
            >= blu_card_status_priority((string)($byKey[$k]['status'] ?? ''))) {
            $byKey[$k] = array_merge($byKey[$k] ?? [], $card);
        }
    }
    return array_values($byKey);
}

/** Piese care inca nu au fost procesate cu RapidAPI. */
function blu_count_pending_import_cards(): int
{
    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    if (!is_array($rows)) {
        return 0;
    }
    $n = 0;
    foreach ($rows as $row) {
        if (is_array($row) && ($row['status'] ?? '') === 'pending') {
            $n++;
        }
    }
    return $n;
}

function blu_count_import_cards_by_status(): array
{
    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    $counts = ['pending' => 0, 'empty' => 0, 'no_oem' => 0, 'imported' => 0, 'error' => 0];
    if (!is_array($rows)) {
        return $counts;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $st = (string)($row['status'] ?? '');
        if (isset($counts[$st])) {
            $counts[$st]++;
        }
    }
    return $counts;
}

/** Verificare rapida RapidAPI (un OEM cunoscut). */
function blu_rapidapi_diagnostic(): array
{
    if (blu_rapidapi_key() === '') {
        return ['ok' => false, 'error' => 'Lipseste RAPIDAPI_AUTOPARTS_KEY in .env'];
    }
    $api = blu_fetch_articles_by_oem('60573687', 60);
    if (!empty($api['error'])) {
        return ['ok' => false, 'error' => (string)$api['error']];
    }
    $n = is_array($api['articles'] ?? null) ? count($api['articles']) : 0;
    return ['ok' => $n > 0, 'articles' => $n, 'error' => $n > 0 ? '' : 'API raspunde dar 0 articole (verifica abonament RapidAPI)'];
}

/** Dupa un enrich complet, pending ramase devin empty (evita bucla infinita). */
function blu_finalize_pending_after_enrich(): int
{
    $file = blu_imported_cards_file();
    $rows = blu_read_json_file($file, []);
    if (!is_array($rows)) {
        return 0;
    }
    $changed = 0;
    foreach ($rows as &$row) {
        if (!is_array($row) || ($row['status'] ?? '') !== 'pending') {
            continue;
        }
        $row['status'] = 'empty';
        $row['descriere'] = trim((string)($row['descriere'] ?? '')) . "\nTecDoc: cautare fara rezultat.";
        $row['updated_at'] = date('Y-m-d H:i:s');
        $changed++;
    }
    unset($row);
    if ($changed > 0) {
        blu_write_json_file($file, $rows);
    }
    return $changed;
}

/**
 * Re-proceseaza cartele pending prin RapidAPI (loturi).
 *
 * @return array{ok:bool,total:int,offset:int,next_offset:int,done:bool,batch:array,pending_remaining:int}
 */
function blu_enrich_pending_cards(int $offset, int $limit): array
{
    blu_purge_invalid_api_cache();

    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    if (!is_array($rows)) {
        $rows = [];
    }

    $entries = [];
    foreach ($rows as $card) {
        if (!is_array($card)) {
            continue;
        }
        $st = (string)($card['status'] ?? '');
        if ($st !== 'pending' && $st !== 'no_oem') {
            continue;
        }
        $codArticol = trim((string)($card['cod_articol'] ?? ''));
        $coduriOem = trim((string)($card['coduri_oem'] ?? $card['cod_oem'] ?? ''));
        $codes = blu_extract_search_codes($codArticol, $coduriOem);
        if ($codes === []) {
            continue;
        }
        $entries[] = [
            'brand' => trim((string)($card['marca_masina'] ?? '')),
            'model' => trim((string)($card['model'] ?? '')),
            'cod_articol' => $codArticol,
            'coduri_oem' => $coduriOem,
            'codes' => $codes,
        ];
    }

    $total = count($entries);
    if ($total === 0) {
        return [
            'ok' => true,
            'total' => 0,
            'offset' => 0,
            'next_offset' => 0,
            'done' => true,
            'batch' => ['processed' => 0, 'found' => 0, 'saved_db' => 0, 'errors' => [], 'cards' => []],
            'pending_remaining' => 0,
        ];
    }

    $batch = blu_process_catalog_batch($entries, $offset, $limit, true);
    $nextOffset = $offset + (int)($batch['processed'] ?? 0);
    $done = $nextOffset >= $total;

    if ($done) {
        blu_finalize_pending_after_enrich();
        blu_sync_imported_cards_to_products();
    }

    $diag = $offset === 0 ? blu_rapidapi_diagnostic() : null;

    return [
        'ok' => true,
        'total' => $total,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'done' => $done,
        'batch' => $batch,
        'pending_remaining' => blu_count_pending_import_cards(),
        'status_counts' => blu_count_import_cards_by_status(),
        'rapidapi_diagnostic' => $diag,
        'rapidapi_configured' => blu_rapidapi_key() !== '',
    ];
}

/**
 * Aplică modelul strict de cartelă: încearcă TecDoc pentru compatibilitate,
 * păstrează datele comerciale Autodoc (preț, stoc, imagine).
 *
 * @param array<string,mixed> $card
 * @param array<string,mixed> $context
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
function blu_apply_strict_card_template(array $card, array $context, array $entry): array
{
    $tecdocEntry = [
        'brand' => (string)($entry['brand'] ?? $context['brand'] ?? ''),
        'model' => (string)($entry['model'] ?? $context['model'] ?? ''),
        'cod_articol' => (string)($entry['cod_articol'] ?? $context['cod_articol'] ?? ''),
        'coduri_oem' => (string)($entry['coduri_oem'] ?? $context['coduri_oem'] ?? ''),
        'codes' => is_array($entry['codes'] ?? null) && ($entry['codes'] ?? []) !== []
            ? $entry['codes']
            : blu_extract_search_codes(
                (string)($entry['cod_articol'] ?? $context['cod_articol'] ?? ''),
                (string)($entry['coduri_oem'] ?? $context['coduri_oem'] ?? '')
            ),
    ];

    $api = blu_fetch_articles_for_entry($tecdocEntry);
    $articles = $api['articles'] ?? [];
    if (is_array($articles) && $articles !== []) {
        $fallbackImage = blu_first_article_image($articles);
        $primary = null;
        foreach ($articles as $a) {
            if (is_array($a) && blu_article_image($a) !== '') {
                $primary = $a;
                break;
            }
        }
        if ($primary === null) {
            $primary = reset($articles);
        }
        if (is_array($primary)) {
            $tecdocCard = blu_article_to_product_card($primary, $context, $fallbackImage);
            $keepId = (string)($card['product_id'] ?? $tecdocCard['product_id'] ?? '');
            if ($keepId !== '') {
                $tecdocCard['product_id'] = $keepId;
                $tecdocCard['id'] = $keepId;
            }

            foreach (['price_ron_final', 'price_pln', 'price_ron_display', 'price_ron_base', 'price_eur', 'stock', 'source_url'] as $field) {
                if (array_key_exists($field, $card) && $card[$field] !== '' && $card[$field] !== null) {
                    $tecdocCard[$field] = $card[$field];
                }
            }

            $autodocAdmin = is_array($card['admin_card'] ?? null) ? $card['admin_card'] : [];
            if (!isset($tecdocCard['admin_card']) || !is_array($tecdocCard['admin_card'])) {
                $tecdocCard['admin_card'] = [];
            }
            foreach (['pret', 'stoc'] as $field) {
                if (isset($autodocAdmin[$field]) && (string)$autodocAdmin[$field] !== '') {
                    $tecdocCard['admin_card'][$field] = $autodocAdmin[$field];
                }
            }
            $autodocImg = trim((string)($autodocAdmin['imagine'] ?? ''));
            if ($autodocImg !== '') {
                $tecdocCard['admin_card']['imagine'] = $autodocImg;
                $tecdocCard['main_image'] = json_encode([$autodocImg], JSON_UNESCAPED_UNICODE);
                $tecdocCard['images_json'] = json_encode([['url' => $autodocImg, 'remote' => true]], JSON_UNESCAPED_UNICODE);
            }

            if (!empty($card['parameters_json'])) {
                $origParams = json_decode((string)$card['parameters_json'], true);
                $newParams = json_decode((string)($tecdocCard['parameters_json'] ?? '{}'), true);
                if (is_array($origParams) && is_array($newParams)) {
                    if (!empty($origParams['autodoc_raw'])) {
                        $newParams['autodoc_raw'] = $origParams['autodoc_raw'];
                        $newParams['source'] = 'autodoc24';
                    }
                    $tecdocCard['parameters_json'] = json_encode($newParams, JSON_UNESCAPED_UNICODE);
                }
            }

            return $tecdocCard;
        }
    }

    return blu_apply_card_template_to_card($card, $context);
}

function blu_card_to_display_row(array $card, array $contextBase, string $status = 'imported'): array
{
    require_once __DIR__ . '/pieseauto_categories.php';

    if (!empty($card['gbg_price_missing'])) {
        $status = 'no_stock';
    }

    $admin = $card['admin_card'] ?? [];
    $codArticol = (string)($contextBase['cod_articol'] ?? '');
    $coduriOem = (string)($contextBase['coduri_oem'] ?? '');
    $paMeta = blu_pieseauto_classify_product(array_merge($card, [
        'title' => (string)($card['title'] ?? $admin['nume'] ?? ''),
        'description' => (string)($card['description'] ?? $admin['descriere'] ?? ''),
    ]));
    $subCategory = trim((string)($paMeta['sub_category'] ?? ''));
    $mainCategory = trim((string)($paMeta['main_category'] ?? ''));
    $productCategory = trim((string)($paMeta['product_category'] ?? ''));

    return [
        'product_id' => (string)($card['product_id'] ?? ''),
        'title' => (string)($card['title'] ?? $admin['nume'] ?? 'Piesa auto'),
        'title_original' => (string)($card['title_original'] ?? $admin['title_original'] ?? ''),
        'brand' => (string)($card['brand'] ?? $admin['brand'] ?? ''),
        'cod_oem' => (string)($admin['cod_oem'] ?? $card['cod_oem'] ?? ''),
        'cod_articol' => $codArticol,
        'coduri_oem' => $coduriOem,
        'image' => (string)($card['image'] ?? $admin['imagine'] ?? ''),
        'descriere' => (string)($card['description'] ?? $admin['descriere'] ?? ''),
        'car' => trim((string)($contextBase['brand'] ?? '') . ' — ' . (string)($contextBase['model'] ?? '')),
        'marca_masina' => (string)($contextBase['brand'] ?? ''),
        'model' => (string)($contextBase['model'] ?? ''),
        'main_category' => $mainCategory,
        'sub_category' => $subCategory,
        'product_category' => $productCategory !== '' ? $productCategory : ($mainCategory !== '' && $subCategory !== '' ? ($mainCategory . ' -> ' . $subCategory) : $subCategory),
        'category_name' => $subCategory,
        'category_full' => $productCategory !== '' ? $productCategory : $subCategory,
        'pieseauto_category' => $subCategory,
        'status' => $status,
        'missing_oem' => blu_entry_missing_oem($codArticol, $coduriOem),
        'price_eur' => (float)($card['price_eur'] ?? 0),
        'price_ron_base' => (float)($card['price_ron_base'] ?? 0),
        'price_ron_final' => (float)($card['price_ron_final'] ?? 0),
        'pret' => (string)($card['admin_card']['pret'] ?? $card['pret'] ?? ''),
        'stoc' => (string)($card['admin_card']['stoc'] ?? $card['stock'] ?? '1'),
        'gbg_price_missing' => !empty($card['gbg_price_missing']),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function blu_catalog_stub_card(array $contextBase, array $entry): array
{
    $cod = (string)($entry['cod_articol'] ?? '');
    $oem = (string)($entry['coduri_oem'] ?? '');
    $missingOem = blu_entry_missing_oem($cod, $oem);
    $pid = 'catalog_' . md5(json_encode($contextBase, JSON_UNESCAPED_UNICODE));
    $status = $missingOem ? 'no_oem' : 'pending';

    $oems = blu_extract_oem_codes_only($oem);
    if ($oems === [] && $cod !== '') {
        $oems = [$cod];
    }

    $partName = blu_tecdoc_translate_title('Piesa catalog');
    $aiNote = '';
    $greek = trim((string) ($entry['descriere_gr'] ?? $entry['description_gr'] ?? $entry['titlu_gr'] ?? ''));
    if ($greek !== '' && !$missingOem) {
        require_once __DIR__ . '/ollama_client.php';
        $ai = blu_ollama_translate_gbg_product(
            $greek,
            (string) ($contextBase['brand'] ?? ''),
            (string) ($contextBase['model'] ?? ''),
            implode(', ', $oems),
            $cod
        );
        if (!empty($ai['ok'])) {
            $partName = (string) ($ai['part_name'] ?? $partName);
            $status = 'imported';
            $aiNote = "\n\n[Traducere Ollama din catalogul GBG — Autodoc/TecDoc fără rezultat]";
            $formatted = blu_card_template_format([
                'part_name' => $partName,
                'brand' => (string) ($contextBase['brand'] ?? ''),
                'model' => (string) ($contextBase['model'] ?? ''),
                'oem_codes' => $oems,
                'internal_code' => $cod,
            ]);
            $title = (string) ($ai['title'] ?? $formatted['title']);
            $descriere = trim((string) ($ai['description'] ?? $formatted['description'])) . $aiNote;
            return [
                'product_id' => $pid,
                'title' => $title,
                'brand' => '',
                'cod_oem' => $oem !== '' ? preg_replace('/\s*,\s*/', ', ', $oem) : $cod,
                'cod_articol' => $cod,
                'coduri_oem' => $oem,
                'image' => '',
                'descriere' => $descriere,
                'car' => trim((string) ($contextBase['brand'] ?? '') . ' — ' . (string) ($contextBase['model'] ?? '')),
                'marca_masina' => (string) ($contextBase['brand'] ?? ''),
                'model' => (string) ($contextBase['model'] ?? ''),
                'status' => $status,
                'missing_oem' => $missingOem,
                'source' => 'ollama_gbg',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    $formatted = blu_card_template_format([
        'part_name' => $partName,
        'brand' => (string)($contextBase['brand'] ?? ''),
        'model' => (string)($contextBase['model'] ?? ''),
        'oem_codes' => $oems,
        'internal_code' => $cod,
    ]);

    $descriere = $formatted['description'];
    if ($missingOem) {
        $descriere .= "\n\nStatus: OEM lipsă — așteaptă re-import cu API.";
    } else {
        $descriere .= "\n\nStatus: Așteaptă re-import cu API (TecDoc/Autodoc).";
    }

    return [
        'product_id' => $pid,
        'title' => $formatted['title'],
        'brand' => '',
        'cod_oem' => $oem !== '' ? preg_replace('/\s*,\s*/', ', ', $oem) : $cod,
        'cod_articol' => $cod,
        'coduri_oem' => $oem,
        'image' => '',
        'descriere' => $descriere,
        'car' => trim((string)($contextBase['brand'] ?? '') . ' — ' . (string)($contextBase['model'] ?? '')),
        'marca_masina' => (string)($contextBase['brand'] ?? ''),
        'model' => (string)($contextBase['model'] ?? ''),
        'status' => $status,
        'missing_oem' => $missingOem,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function blu_merge_imported_cards(array $rows): void
{
    if ($rows === []) {
        return;
    }
    $file = blu_imported_cards_file();
    $existing = blu_read_json_file($file, []);
    if (!is_array($existing)) {
        $existing = [];
    }
    $byKey = [];
    foreach ($existing as $row) {
        if (!is_array($row)) {
            continue;
        }
        $byKey[blu_catalog_card_key($row)] = $row;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $k = blu_catalog_card_key($row);
        if (!isset($byKey[$k])) {
            $byKey[$k] = $row;
            continue;
        }
        $existingRow = $byKey[$k];
        if (blu_card_status_priority((string)($row['status'] ?? ''))
            >= blu_card_status_priority((string)($existingRow['status'] ?? ''))) {
            $byKey[$k] = array_merge($existingRow, $row);
        }
    }
    blu_write_json_file($file, array_values($byKey));
}

/** @return list<array> */
function blu_load_imported_cards_for_admin(): array
{
    $cards = [];
    $file = blu_imported_cards_file();
    $rows = blu_read_json_file($file, []);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (is_array($row)) {
                $cards[] = $row;
            }
        }
    }

    $adminFile = blu_admin_products_file();
    $adminRows = blu_read_json_file($adminFile, []);
    if (is_array($adminRows)) {
        foreach ($adminRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cards[] = [
                'product_id' => 'admin_' . (string)($row['id'] ?? $row['randomn_id'] ?? ''),
                'title' => (string)($row['nume'] ?? ''),
                'brand' => '',
                'cod_oem' => (string)($row['cod_oem'] ?? ''),
                'cod_articol' => '',
                'image' => (string)($row['imagine'] ?? ''),
                'descriere' => (string)($row['descriere'] ?? ''),
                'car' => (string)($row['marca_masina'] ?? '') . ' — ' . (string)($row['categorie'] ?? ''),
                'status' => 'imported',
            ];
        }
    }

    $db = blu_db_products_bootstrap();
    if ($db) {
        try {
            $stmt = $db['pdo']->query(
                "SELECT product_id, title, brand, car_brand, main_image, compatibility, description
                 FROM products WHERE product_id LIKE 'tecdoc_%' ORDER BY updated_at DESC LIMIT 200"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $img = '';
                $raw = (string)($row['main_image'] ?? '');
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    $img = is_array($decoded) && !empty($decoded[0]) ? (string)$decoded[0] : $raw;
                }
                $cards[] = [
                    'product_id' => (string)($row['product_id'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'brand' => (string)($row['brand'] ?? ''),
                    'cod_oem' => '',
                    'image' => $img,
                    'descriere' => (string)($row['description'] ?? ''),
                    'car' => (string)($row['compatibility'] ?? $row['car_brand'] ?? ''),
                    'status' => 'imported',
                ];
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return blu_dedupe_imported_cards($cards);
}

function blu_purge_invalid_api_cache(): int
{
    $removed = 0;
    $dir = blu_cache_dir();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || !blu_api_response_is_cacheable($data)) {
            @unlink($file);
            $removed++;
        }
    }
    return $removed;
}

function blu_create_import_job(array $catalog): string
{
    $jobId = 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $path = blu_uploads_dir() . DIRECTORY_SEPARATOR . $jobId . '.json';
    blu_write_json_file($path, [
        'id' => $jobId,
        'created_at' => date('c'),
        'catalog' => $catalog,
        'entries' => blu_catalog_flatten_entries($catalog),
    ]);
    return $jobId;
}

function blu_load_import_job(string $jobId): ?array
{
    if (!preg_match('/^job_[a-zA-Z0-9_]+$/', $jobId)) {
        return null;
    }
    $path = blu_uploads_dir() . DIRECTORY_SEPARATOR . $jobId . '.json';
    $data = blu_read_json_file($path, null);
    return is_array($data) ? $data : null;
}
