<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

const BLU_AUTODOC_SEARCH_URL = 'https://www.autodoc24.ro/search?keyword=';

function blu_autodoc_cache_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_scrape_do_token(): string
{
    return trim((string)blu_env('SCRAPE_DO_TOKEN', ''));
}

function blu_autodoc_search_url(string $keyword): string
{
    return BLU_AUTODOC_SEARCH_URL . rawurlencode(trim($keyword));
}

/** Calea catre interpretorul Python din venv-ul stealth-browser-mcp. */
function blu_stealth_python_bin(): string
{
    $custom = trim((string)blu_env('STEALTH_PYTHON', ''));
    if ($custom !== '' && is_file($custom)) {
        return $custom;
    }
    $venv = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'robot' . DIRECTORY_SEPARATOR
        . 'stealth-browser-mcp' . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR
        . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    if (is_file($venv)) {
        return $venv;
    }
    // Linux/mac fallback
    $venvNix = dirname(__DIR__) . '/robot/stealth-browser-mcp/venv/bin/python';
    return is_file($venvNix) ? $venvNix : 'python';
}

/** Calea catre scriptul-punte stealth_fetch.py. */
function blu_stealth_script(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'robot' . DIRECTORY_SEPARATOR . 'stealth_fetch.py';
}

/** Este disponibila puntea stealth (script + interpretor)? */
function blu_stealth_available(): bool
{
    if (strtolower((string)blu_env('STEALTH_FETCH_ENABLED', '1')) === '0') {
        return false;
    }
    return is_file(blu_stealth_script()) && is_file(blu_stealth_python_bin());
}

/**
 * Descarca HTML cu browser stealth (nodriver), trecand de Cloudflare.
 * Inlocuieste scrape.do la scanarea produselor.
 *
 * @return array{html?:string,error?:string,from_cache?:bool}
 */
function blu_stealth_fetch(string $targetUrl, int $ttl = 3600): array
{
    if (!blu_stealth_available()) {
        return ['error' => 'stealth_fetch indisponibil (verifica robot/stealth-browser-mcp/venv)'];
    }

    $cacheDir = blu_autodoc_cache_dir() . DIRECTORY_SEPARATOR . 'autodoc';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($targetUrl) . '.html';
    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            return ['html' => $cached, 'from_cache' => true];
        }
    }

    $outFile = $cacheDir . DIRECTORY_SEPARATOR . 'sf_' . md5($targetUrl . microtime(true)) . '.json';
    $selector = '.listing-list .listing-item';
    $waitSec = (float)blu_env('STEALTH_WAIT', '6');
    $timeoutSec = (int)blu_env('STEALTH_TIMEOUT', '60');
    // Cat asteptam listingul dupa ce trece de Cloudflare. Daca nu apare in acest
    // interval => pagina nu are rezultate pentru acest OEM si ne intoarcem rapid,
    // ca fluxul sa treaca imediat la urmatorul cod OEM (fara blocaj).
    $selectorTimeout = (int)blu_env('STEALTH_SELECTOR_TIMEOUT', '15');
    $headful = strtolower((string)blu_env('STEALTH_HEADFUL', '1')) !== '0';

    $args = [
        blu_stealth_python_bin(),
        blu_stealth_script(),
        $targetUrl,
        '--wait', (string)$waitSec,
        '--timeout', (string)$timeoutSec,
        '--selector', $selector,
        '--selector-timeout', (string)$selectorTimeout,
        '--out', $outFile,
    ];
    if ($headful) {
        $args[] = '--headful';
    }

    $cmd = implode(' ', array_map(static fn($a) => '"' . str_replace('"', '', $a) . '"', $args));

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $descriptors, $pipes, dirname(blu_stealth_script()));
    if (!is_resource($proc)) {
        return ['error' => 'nu pot porni stealth_fetch'];
    }
    // Nu ne intereseaza stdout/stderr — JSON-ul e in --out.
    if (isset($pipes[1])) {
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    if (isset($pipes[2])) {
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    }
    proc_close($proc);

    if (!is_file($outFile)) {
        return ['error' => 'stealth_fetch nu a produs rezultat'];
    }
    $json = json_decode((string)file_get_contents($outFile), true);
    @unlink($outFile);
    if (!is_array($json)) {
        return ['error' => 'stealth_fetch: raspuns invalid'];
    }
    if (empty($json['ok'])) {
        return ['error' => 'stealth_fetch: ' . (string)($json['error'] ?? 'esuat')];
    }
    $html = (string)($json['html'] ?? '');
    if ($html === '') {
        return ['error' => 'stealth_fetch: HTML gol'];
    }

    @file_put_contents($cacheFile, $html, LOCK_EX);
    return ['html' => $html, 'from_cache' => false];
}

/**
 * Descarca HTML: intai stealth (nodriver), apoi fallback pe scrape.do.
 *
 * @return array{html?:string,error?:string,from_cache?:bool,source?:string}
 */
function blu_autodoc_fetch_html(string $targetUrl, int $ttl = 3600): array
{
    if (blu_stealth_available()) {
        $res = blu_stealth_fetch($targetUrl, $ttl);
        if (empty($res['error']) && !empty($res['html'])) {
            $res['source'] = 'stealth';
            return $res;
        }
        // Stealth a esuat — incearca scrape.do daca exista token.
        if (blu_scrape_do_token() !== '') {
            $fallback = blu_scrape_do_fetch($targetUrl, $ttl);
            if (empty($fallback['error'])) {
                $fallback['source'] = 'scrape.do';
                return $fallback;
            }
        }
        return $res;
    }

    $res = blu_scrape_do_fetch($targetUrl, $ttl);
    $res['source'] = 'scrape.do';
    return $res;
}

/**
 * Descarca HTML prin scrape.do (cu cache local).
 *
 * @return array{html?:string,error?:string,from_cache?:bool,http_code?:int}
 */
function blu_scrape_do_fetch(string $targetUrl, int $ttl = 3600): array
{
    $token = blu_scrape_do_token();
    if ($token === '') {
        return ['error' => 'SCRAPE_DO_TOKEN lipseste din .env'];
    }

    $cacheDir = blu_autodoc_cache_dir() . DIRECTORY_SEPARATOR . 'autodoc';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($targetUrl) . '.html';
    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            return ['html' => $cached, 'from_cache' => true];
        }
    }

    $apiUrl = 'http://api.scrape.do/?url=' . rawurlencode($targetUrl) . '&token=' . rawurlencode($token);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => ['Accept: */*'],
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '') {
        return ['error' => $curlErr !== '' ? $curlErr : 'Raspuns gol de la scrape.do', 'http_code' => $httpCode];
    }
    if ($httpCode >= 400) {
        return ['error' => 'scrape.do HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    @file_put_contents($cacheFile, $body, LOCK_EX);
    return ['html' => $body, 'from_cache' => false, 'http_code' => $httpCode];
}

/**
 * @return list<array<string,mixed>>
 */
function blu_autodoc_parse_listing_items(string $html): array
{
    if (trim($html) === '') {
        return [];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    if (!$loaded) {
        return [];
    }

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query(
        "//div[contains(concat(' ', normalize-space(@class), ' '), ' listing-list ')]"
        . "//div[contains(concat(' ', normalize-space(@class), ' '), ' listing-item ') and @data-article-id]"
    );
    if (!$nodes instanceof DOMNodeList || $nodes->length === 0) {
        return [];
    }

    $items = [];
    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $parsed = blu_autodoc_parse_listing_node($node, $xpath);
        if ($parsed !== null) {
            $items[] = $parsed;
        }
    }

    return $items;
}

/**
 * @return array<string,mixed>|null
 */
function blu_autodoc_parse_listing_node(DOMElement $node, DOMXPath $xpath): ?array
{
    $articleId = trim((string)$node->getAttribute('data-article-id'));
    if ($articleId === '') {
        return null;
    }

    $genericName = trim((string)$node->getAttribute('data-generic-name'));
    $price = (float)$node->getAttribute('data-price');
    $brandNo = trim((string)$node->getAttribute('data-brand-no'));

    $nameNode = $xpath->query(".//a[contains(@class,'listing-item__name')] | .//span[contains(@class,'listing-item__name')]", $node)->item(0);
    $nameText = blu_autodoc_node_text($nameNode);
    $nameSpanNode = $xpath->query(".//span[contains(@class,'listing-item__name-span')]", $node)->item(0);
    $nameSpan = blu_autodoc_node_text($nameSpanNode);

    $articleNode = $xpath->query(".//span[contains(@class,'listing-item__article-item')]", $node)->item(0);
    $articleText = blu_autodoc_node_text($articleNode);
    $articleNo = '';
    if (preg_match('/Num[aă]rul articolului:\s*(.+)$/ui', $articleText, $m)) {
        $articleNo = trim($m[1]);
    } elseif ($articleText !== '') {
        $articleNo = $articleText;
    }

    $oemNode = $xpath->query(".//span[contains(@class,'highlight')]", $node)->item(0);
    $oemHighlight = blu_autodoc_node_text($oemNode);

    $url = '';
    if ($nameNode instanceof DOMElement) {
        if ($nameNode->hasAttribute('href')) {
            $url = trim((string)$nameNode->getAttribute('href'));
        } elseif ($nameNode->hasAttribute('data-link')) {
            $url = trim((string)$nameNode->getAttribute('data-link'));
        }
    }
    if ($url !== '' && str_starts_with($url, '/')) {
        $url = 'https://www.autodoc24.ro' . $url;
    }

    $image = '';
    $imgNode = $xpath->query(".//a[contains(@class,'listing-item__image-product')]//img | .//span[contains(@class,'listing-item__image-product')]//img", $node)->item(0);
    if ($imgNode instanceof DOMElement) {
        $image = trim((string)($imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src')));
    }

    $inStock = !$xpath->query(".//div[contains(@class,'listing-item__available--not')]", $node)->length;

    $brand = blu_autodoc_guess_brand($nameText, $genericName);
    $title = trim(preg_replace('/\s+/u', ' ', $nameText) ?? $nameText);
    $title = preg_replace('/\s*\(OE:.*$/u', '', $title) ?? $title;
    $title = trim($title);

    $specs = blu_autodoc_parse_specs($node, $xpath);
    if ($articleNo === '' && isset($specs['Număr articol'])) {
        $articleNo = (string)$specs['Număr articol'];
    }
    if ($brand === '' && isset($specs['Producătorul'])) {
        $brand = (string)$specs['Producătorul'];
    }
    if ($price <= 0 && isset($specs['Prețul nostru'])) {
        $price = blu_autodoc_parse_price_string((string)$specs['Prețul nostru']);
    }

    return [
        'article_id' => $articleId,
        'article_no' => $articleNo,
        'brand' => $brand,
        'brand_no' => $brandNo,
        'generic_name' => $genericName,
        'title' => $title,
        'name_span' => $nameSpan,
        'price' => $price,
        'currency' => trim((string)$node->getAttribute('data-currency-origin')) ?: 'RON',
        'image' => $image,
        'url' => $url,
        'oem_highlight' => $oemHighlight,
        'in_stock' => $inStock,
        'specs' => $specs,
    ];
}

function blu_autodoc_node_text(?DOMNode $node): string
{
    if (!$node instanceof DOMNode) {
        return '';
    }
    $text = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
    return $text;
}

function blu_autodoc_guess_brand(string $nameText, string $genericName): string
{
    $nameText = trim(preg_replace('/\s*\(OE:.*$/u', '', $nameText) ?? $nameText);
    if ($genericName !== '' && str_contains($nameText, $genericName)) {
        $brand = trim(str_replace($genericName, '', $nameText));
        if ($brand !== '') {
            return $brand;
        }
    }
    $parts = preg_split('/\s+/u', $nameText) ?: [];
    return trim((string)($parts[0] ?? ''));
}

/**
 * @return array<string,string>
 */
function blu_autodoc_parse_specs(DOMElement $node, DOMXPath $xpath): array
{
    $specs = [];
    $rows = $xpath->query(".//li[contains(@class,'product-description__item')]", $node);
    if (!$rows instanceof DOMNodeList) {
        return $specs;
    }
    foreach ($rows as $row) {
        if (!$row instanceof DOMElement) {
            continue;
        }
        $labelNode = $xpath->query(".//span[contains(@class,'product-description__item-title')]", $row)->item(0);
        $valueNode = $xpath->query(".//span[contains(@class,'product-description__item-value')]", $row)->item(0);
        $label = rtrim(blu_autodoc_node_text($labelNode), ': ');
        $value = blu_autodoc_node_text($valueNode);
        if ($label !== '' && $value !== '') {
            $specs[$label] = $value;
        }
    }
    return $specs;
}

function blu_autodoc_parse_price_string(string $raw): float
{
    $clean = str_replace([' ', 'lei', 'RON'], '', $raw);
    $clean = str_replace(',', '.', $clean);
    return is_numeric($clean) ? (float)$clean : 0.0;
}

/** Importa toate variantele Autodoc (1=da, 0=doar prima). */
function blu_autodoc_import_all_enabled(): bool
{
    $val = strtolower(trim((string)blu_env('AUTODOC_IMPORT_ALL', '1')));
    return !in_array($val, ['0', 'false', 'no', 'off'], true);
}

/**
 * Filtre Autodoc din .env sau override (ex. query test API).
 *
 * @param array<string,mixed> $override
 * @return array{brands:list<string>,min_price:float,max_price:float,in_stock_only:bool}
 */
function blu_autodoc_filter_options(array $override = []): array
{
    $brandsRaw = trim((string)($override['brands'] ?? $override['brand'] ?? blu_env('AUTODOC_BRANDS', '')));
    $brands = [];
    if ($brandsRaw !== '') {
        foreach (preg_split('/[,;]+/u', $brandsRaw) as $piece) {
            $b = trim($piece);
            if ($b !== '') {
                $brands[] = mb_strtoupper($b, 'UTF-8');
            }
        }
    }

    $minPrice = $override['min_price'] ?? blu_env('AUTODOC_MIN_PRICE', '0');
    $maxPrice = $override['max_price'] ?? blu_env('AUTODOC_MAX_PRICE', '0');

    $inStockOnly = $override['in_stock_only'] ?? blu_env('AUTODOC_IN_STOCK_ONLY', '0');
    if (is_string($inStockOnly)) {
        $inStockOnly = in_array(strtolower(trim($inStockOnly)), ['1', 'true', 'yes', 'on'], true);
    }

    return [
        'brands' => $brands,
        'min_price' => is_numeric($minPrice) ? max(0.0, (float)$minPrice) : 0.0,
        'max_price' => is_numeric($maxPrice) ? max(0.0, (float)$maxPrice) : 0.0,
        'in_stock_only' => (bool)$inStockOnly,
    ];
}

/**
 * @param list<array> $items
 * @return list<array>
 */
function blu_autodoc_apply_filters(array $items, array $filters): array
{
    if ($items === []) {
        return [];
    }

    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $brand = mb_strtoupper(trim((string)($item['brand'] ?? '')), 'UTF-8');
        $price = (float)($item['price'] ?? 0);

        if ($filters['brands'] !== [] && !in_array($brand, $filters['brands'], true)) {
            continue;
        }
        if ($filters['min_price'] > 0 && ($price <= 0 || $price < $filters['min_price'])) {
            continue;
        }
        if ($filters['max_price'] > 0 && $price > $filters['max_price']) {
            continue;
        }
        if (!empty($filters['in_stock_only']) && empty($item['in_stock'])) {
            continue;
        }
        $out[] = $item;
    }

    return $out;
}

/**
 * @param list<array> $items
 * @return list<array>
 */
function blu_autodoc_items_for_import(array $items, bool $importAll): array
{
    if ($items === []) {
        return [];
    }
    if ($importAll) {
        return $items;
    }
    $primary = blu_autodoc_pick_primary_item($items);
    return $primary !== null ? [$primary] : [];
}

/**
 * Cautare Autodoc + filtre — folosit de import si endpoint test.
 *
 * @param array<string,mixed> $filterOverride
 * @return array{products:list<array>,filtered_count:int,total_count:int,error?:string,from_cache?:bool,_search_code?:string,filters:array}
 */
function blu_autodoc_search(string $oemCode, array $filterOverride = [], int $ttl = 3600): array
{
    $api = blu_autodoc_fetch_by_oem($oemCode, $ttl);
    $filters = blu_autodoc_filter_options($filterOverride);
    $all = is_array($api['products'] ?? null) ? $api['products'] : [];
    $filtered = blu_autodoc_apply_filters($all, $filters);

    return [
        'products' => $filtered,
        'filtered_count' => count($filtered),
        'total_count' => count($all),
        'error' => $api['error'] ?? null,
        'from_cache' => $api['from_cache'] ?? false,
        '_search_code' => $api['_search_code'] ?? $oemCode,
        'filters' => $filters,
    ];
}

/**
 * Cauta pe Autodoc24 dupa cod OEM (prin scrape.do).
 *
 * @return array{products:list<array>,error?:string,_search_code?:string,_search_type?:string,_tried?:list<array>,from_cache?:bool}
 */
function blu_autodoc_fetch_by_oem(string $oemCode, int $ttl = 3600): array
{
    $oemCode = preg_replace('/\s+/u', '', trim($oemCode));
    if ($oemCode === '') {
        return ['products' => [], 'error' => 'Cod OEM gol'];
    }

    $targetUrl = blu_autodoc_search_url($oemCode);
    $fetch = blu_autodoc_fetch_html($targetUrl, $ttl);
    if (!empty($fetch['error'])) {
        return ['products' => [], 'error' => (string)$fetch['error']];
    }

    $products = blu_autodoc_parse_listing_items((string)($fetch['html'] ?? ''));
    return [
        'products' => $products,
        'from_cache' => !empty($fetch['from_cache']),
        'source' => (string)($fetch['source'] ?? ''),
        '_search_code' => $oemCode,
        '_search_type' => 'AutodocOEM',
    ];
}

/**
 * Incearca fiecare cod OEM din intrarea catalog pana gaseste rezultate.
 *
 * @return array{products:list<array>,error?:string,_search_code?:string,_search_type?:string,_tried?:list<array>,from_cache?:bool}
 */
function blu_autodoc_fetch_for_entry(array $entry, int $ttl = 3600): array
{
    if (!function_exists('blu_extract_oem_codes_only')) {
        return ['products' => [], 'error' => 'Modul catalog_import indisponibil'];
    }
    $oemCodes = blu_extract_oem_codes_only((string)($entry['coduri_oem'] ?? ''));
    $tried = [];

    foreach ($oemCodes as $code) {
        $code = (string) $code;
        $api = blu_autodoc_fetch_by_oem($code, $ttl);
        $count = count($api['products'] ?? []);
        $tried[] = ['code' => $code, 'type' => 'AutodocOEM', 'count' => $count];
        if (!empty($api['error'])) {
            continue;
        }
        if ($count > 0) {
            $api['_search_code'] = $code;
            $api['_search_type'] = 'AutodocOEM';
            $api['_tried'] = $tried;
            return $api;
        }
    }

    return ['products' => [], '_tried' => $tried];
}

function blu_autodoc_item_image(array $item): string
{
    return trim((string)($item['image'] ?? ''));
}

function blu_autodoc_first_item_image(array $items): string
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $img = blu_autodoc_item_image($item);
        if ($img !== '') {
            return $img;
        }
    }
    return '';
}

/**
 * Alege produsul principal: in stoc > cu imagine > pret minim.
 *
 * @param list<array> $items
 */
function blu_autodoc_pick_primary_item(array $items): ?array
{
    if ($items === []) {
        return null;
    }

    $scored = [];
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $score = 0;
        if (!empty($item['in_stock'])) {
            $score += 1000;
        }
        if (blu_autodoc_item_image($item) !== '') {
            $score += 100;
        }
        $price = (float)($item['price'] ?? 0);
        $score -= $price > 0 ? min(99, (int)round($price / 10)) : 0;
        $scored[] = ['idx' => $idx, 'score' => $score, 'price' => $price];
    }
    if ($scored === []) {
        return null;
    }

    usort($scored, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        if ($a['price'] > 0 && $b['price'] > 0) {
            return $a['price'] <=> $b['price'];
        }
        return $a['idx'] <=> $b['idx'];
    });

    $bestIdx = (int)$scored[0]['idx'];
    return $items[$bestIdx] ?? null;
}

function blu_autodoc_build_description(array $item): string
{
    $parts = [];
    $generic = trim((string)($item['generic_name'] ?? ''));
    if ($generic !== '') {
        $parts[] = $generic;
    }
    $span = trim((string)($item['name_span'] ?? ''));
    if ($span !== '') {
        $parts[] = $span;
    }

    $specs = is_array($item['specs'] ?? null) ? $item['specs'] : [];
    foreach ($specs as $label => $value) {
        if (in_array($label, ['Prețul nostru', 'Număr articol', 'Producătorul'], true)) {
            continue;
        }
        $parts[] = $label . ': ' . $value;
    }

    $oem = trim((string)($item['oem_highlight'] ?? ''));
    if ($oem !== '') {
        $parts[] = 'OE: ' . $oem;
    }

    return implode("\n", array_filter($parts));
}

/**
 * Transforma un produs Autodoc in cartela compatibila cu restul proiectului.
 *
 * @param array<string,mixed> $item
 * @param array<string,mixed> $context
 */
function blu_autodoc_item_to_product_card(array $item, array $context, string $imageFallback = ''): array
{
    $articleId = (int)($item['article_id'] ?? 0);
    $articleNo = trim((string)($item['article_no'] ?? ''));
    $brand = trim((string)($item['brand'] ?? ''));
    $genericName = trim((string)($item['generic_name'] ?? 'Piesa auto'));
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
        $title = trim($brand . ' ' . $genericName);
    }
    $nameSpan = trim((string)($item['name_span'] ?? ''));
    if ($nameSpan !== '' && !str_contains($title, $nameSpan)) {
        $title = trim($title . ' ' . $nameSpan);
    }

    $image = blu_autodoc_item_image($item);
    if ($image === '' && $imageFallback !== '') {
        $image = $imageFallback;
    }

    $searchOem = trim((string)($context['search_code'] ?? ''));
    $oemDisplay = trim((string)($item['oem_highlight'] ?? $searchOem));
    $description = blu_autodoc_build_description($item);
    $price = (float)($item['price'] ?? 0);
    $inStock = !empty($item['in_stock']);
    $sourceUrl = trim((string)($item['url'] ?? ''));

    $productId = $articleId > 0 ? 'autodoc_' . $articleId : 'autodoc_' . md5($articleNo . '|' . $searchOem);
    $slugFn = function_exists('blu_slugify')
        ? static fn(string $text): string => blu_slugify($text)
        : static function (string $text): string {
            $text = strtolower(trim($text));
            $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
            $text = trim($text, '-');
            return $text !== '' ? substr($text, 0, 200) : 'piesa-auto';
        };
    $subCategory = $genericName !== '' ? $genericName : 'Piese auto';
    $mainCategory = 'Piese auto';
    $productCategory = $mainCategory . ' -> ' . $subCategory;

    $imagesForDb = $image !== '' ? [['url' => $image, 'remote' => true]] : [];
    $mainImageJson = $image !== '' ? json_encode([$image], JSON_UNESCAPED_UNICODE) : '';

    return [
        'id' => $productId,
        'product_id' => $productId,
        'slug' => $slugFn($title . '-' . $articleNo),
        'source_url' => $sourceUrl,
        'title' => $title,
        'title_original' => $title,
        'brand' => $brand,
        'car_brand' => (string)($context['brand'] ?? ''),
        'main_category' => $mainCategory,
        'sub_category' => $subCategory,
        'product_category' => $productCategory,
        'price_pln' => 0,
        'price_ron_final' => $price,
        'price_ron_display' => $price > 0 ? number_format($price, 2, ',', '.') . ' lei' : '',
        'description' => $description,
        'ad_title' => $title,
        'ad_description' => $description,
        'compatibility' => trim((string)($context['brand'] ?? '') . ' — ' . (string)($context['model'] ?? '')),
        'main_image' => $mainImageJson,
        'images_json' => json_encode($imagesForDb, JSON_UNESCAPED_UNICODE),
        'parameters_json' => json_encode([
            'source' => 'autodoc24',
            'article_id' => $articleId,
            'article_no' => $articleNo,
            'brand_no' => $item['brand_no'] ?? '',
            'generic_name' => $genericName,
            'price_autodoc' => $price,
            'currency' => $item['currency'] ?? 'RON',
            'in_stock' => $inStock,
            'specs' => $item['specs'] ?? [],
            'catalog' => [
                'cod_articol' => $context['cod_articol'] ?? '',
                'coduri_oem' => $context['coduri_oem'] ?? '',
                'search_code' => $searchOem,
            ],
            'autodoc_raw' => $item,
        ], JSON_UNESCAPED_UNICODE),
        'stock' => $inStock ? '1' : '0',
        'item_condition' => (string)(($item['specs']['Conditie'] ?? '') ?: 'Nou'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'admin_card' => [
            'nume' => $title,
            'cod_oem' => $articleNo !== '' ? $articleNo : ($oemDisplay !== '' ? $oemDisplay : $searchOem),
            'cod_oem_ref' => $oemDisplay !== '' ? $oemDisplay : $searchOem,
            'categorie' => $subCategory,
            'pret' => $price > 0 ? number_format($price, 2, '.', '') : '',
            'stoc' => $inStock ? '1' : '0',
            'descriere' => $description,
            'imagine' => $image,
            'marca_masina' => (string)($context['brand'] ?? ''),
        ],
    ];
}
