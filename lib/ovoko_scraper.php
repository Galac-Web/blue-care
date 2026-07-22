<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
// Folosește helpers gen `blu_read_json_file()` prin tecdoc_product_enrich / template settings.
require_once __DIR__ . '/catalog_import.php';
require_once __DIR__ . '/tecdoc_product_enrich.php';

/**
 * Ovoko.ro scraper (prin scrape.do) pentru completarea câmpurilor cardului
 * în formatul strict din Admin → Model cartelă.
 */

function blu_ovoko_cache_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_cache' . DIRECTORY_SEPARATOR . 'ovoko';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_ovoko_scrape_do_token(): string
{
    return trim((string)blu_env('SCRAPE_DO_TOKEN', ''));
}

/**
 * @return array{html?:string,error?:string,from_cache?:bool,http_code?:int}
 */
function blu_ovoko_scrape_do_fetch(string $targetUrl, int $ttl = 3600): array
{
    $token = blu_ovoko_scrape_do_token();
    if ($token === '') {
        return ['error' => 'SCRAPE_DO_TOKEN lipseste din .env'];
    }

    $cacheDir = blu_ovoko_cache_dir() . DIRECTORY_SEPARATOR . 'html';
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
        return ['error' => ($curlErr !== '' ? $curlErr : 'Raspuns gol de la scrape.do'), 'http_code' => $httpCode];
    }
    if ($httpCode >= 400) {
        return ['error' => 'scrape.do HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    @file_put_contents($cacheFile, $body, LOCK_EX);
    return ['html' => $body, 'from_cache' => false, 'http_code' => $httpCode];
}

function blu_ovoko_norm_text(string $s): string
{
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

function blu_ovoko_dom_load(string $html): ?DOMDocument
{
    $html = (string)$html;
    if (trim($html) === '') {
        return null;
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    return $loaded ? $dom : null;
}

/**
 * @return array{url:string,href:string}
 */
function blu_ovoko_pick_first_detail_link(string $searchHtml): array
{
    $dom = blu_ovoko_dom_load($searchHtml);
    if ($dom === null) {
        return ['url' => '', 'href' => ''];
    }
    $xp = new DOMXPath($dom);

    $a = $xp->query("//a[@data-testid='item-card-horizontal-container' and @href][1]");
    if ($a instanceof DOMNodeList && $a->length > 0) {
        $href = (string)$a->item(0)->attributes->getNamedItem('href')->nodeValue;
        return ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
    }

    $a2 = $xp->query("//a[contains(@href,'/piesa-uzata/') and @href][1]");
    if ($a2 instanceof DOMNodeList && $a2->length > 0) {
        $href = (string)$a2->item(0)->attributes->getNamedItem('href')->nodeValue;
        return ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
    }

    // Fallback: Ovoko poate întoarce o pagină mai “agilă” (JS / fragment) și DOMXPath nu găsește anchor-ele.
    // Atunci extragem primul href care conține "/piesa-uzata/" direct din HTML.
    $re = '/href\s*=\s*([\'"])(\/piesa-uzata\/[^\'"]+)\1/i';
    if (preg_match($re, $searchHtml, $m) && !empty($m[2])) {
        $href = (string)$m[2];
        return ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
    }

    $re2 = '/(https?:\/\/)?ovoko\.ro(\/piesa-uzata\/[^\'"\s]+)/i';
    if (preg_match($re2, $searchHtml, $m) && !empty($m[2])) {
        $href = (string)$m[2];
        if (!str_starts_with($href, '/')) {
            $href = '/' . ltrim($href, '/');
        }
        return ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
    }

    return ['url' => '', 'href' => ''];
}

/**
 * Ia până la $limit link-uri candidate către pagini de detaliu.
 * Ovoko poate întoarce rezultate ne-ordonate; filtrăm după codul OEM în detail.
 *
 * @return array<int,array{url:string,href:string}>
 */
function blu_ovoko_pick_candidate_detail_links(string $searchHtml, int $limit = 4): array
{
    $dom = blu_ovoko_dom_load($searchHtml);
    if ($dom === null) {
        return [];
    }
    $xp = new DOMXPath($dom);

    $out = [];
    $seen = [];

    $nodes = $xp->query("//a[@data-testid='item-card-horizontal-container' and @href]");
    if ($nodes instanceof DOMNodeList) {
        foreach ($nodes as $n) {
            if (!($n instanceof DOMElement)) continue;
            $hrefNode = $n->attributes->getNamedItem('href');
            if ($hrefNode === null) continue;
            $href = (string)$hrefNode->nodeValue;
            if ($href === '') continue;
            if (isset($seen[$href])) continue;
            $seen[$href] = true;
            $out[] = ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
            if (count($out) >= $limit) return $out;
        }
    }

    $nodes2 = $xp->query("//a[contains(@href,'/piesa-uzata/') and @href]");
    if ($nodes2 instanceof DOMNodeList) {
        foreach ($nodes2 as $n) {
            if (!($n instanceof DOMElement)) continue;
            $hrefNode = $n->attributes->getNamedItem('href');
            if ($hrefNode === null) continue;
            $href = (string)$hrefNode->nodeValue;
            if ($href === '') continue;
            if (isset($seen[$href])) continue;
            $seen[$href] = true;
            $out[] = ['url' => 'https://ovoko.ro' . $href, 'href' => $href];
            if (count($out) >= $limit) return $out;
        }
    }

    return $out;
}

function blu_ovoko_norm_code_for_match(string $s): string
{
    $s = trim($s);
    $s = preg_replace('/\s+/u', '', $s) ?? $s;
    if ($s !== '' && preg_match('/^\d+(?:\.\d+)+$/u', $s)) {
        $s = str_replace('.', '', $s);
    }
    return $s;
}

/**
 * Verifică dacă detail-ul Ovoko conține oricare din codurile OEM dorite.
 */
function blu_ovoko_detail_contains_any_oem(DOMXPath $xp, array $desiredOems): bool
{
    // Ovoko poate pune codurile OEM în tabele diferite (ex. "Descrierea articolului"
    // vs "Detalii mașină"), deci căutăm în tot textul paginii.
    $root = $xp->documentElement;
    $text = $root instanceof DOMElement ? (string)$root->textContent : '';
    // Normalizăm ca să potrivim și coduri cu puncte / spații / slash.
    $hayNorm = preg_replace('/[\s\.\/]+/u', '', $text) ?? '';

    foreach ($desiredOems as $oem) {
        $oemN = blu_ovoko_norm_code_for_match((string)$oem);
        if ($oemN === '') continue;
        if (stripos($hayNorm, $oemN) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * @return string|null
 */
function blu_ovoko_xpath_first_text(DOMXPath $xp, string $expr): ?string
{
    $nodes = $xp->query($expr);
    if (!$nodes instanceof DOMNodeList || $nodes->length === 0) {
        return null;
    }
    $txt = $nodes->item(0) ? $nodes->item(0)->textContent : '';
    return $txt !== null ? blu_ovoko_norm_text($txt) : null;
}

/**
 * Extrage valori dintr-un tabel MuiTable în care prima coloană (td[1]) conține un label.
 * @return array<string,string>
 */
function blu_ovoko_parse_info_table(DOMXPath $xp, string $tableContainsLabel = ''): array
{
    // Găsim un tabel care are un row ce conține label în td[1]
    $tableExpr = $tableContainsLabel !== ''
        ? "//table[.//tr[.//td[1][contains(normalize-space(.), " . xpath_literal($tableContainsLabel) . ")]]][1]"
        : "//table[1]";

    $tableNode = $xp->query($tableExpr);
    if (!$tableNode instanceof DOMNodeList || $tableNode->length === 0) {
        return [];
    }
    $table = $tableNode->item(0);
    if (!$table instanceof DOMElement) {
        return [];
    }

    $out = [];

    $rows = $xp->query(".//tr[td]", $table);
    if (!$rows instanceof DOMNodeList) {
        return [];
    }

    /** @var DOMElement $row */
    foreach ($rows as $row) {
        if (!$row instanceof DOMElement) {
            continue;
        }
        $tds = $xp->query("./td", $row);
        if (!$tds instanceof DOMNodeList || $tds->length < 2) {
            continue;
        }
        $label = blu_ovoko_norm_text($tds->item(0)?->textContent ?? '');
        $val = blu_ovoko_norm_text($tds->item(1)?->textContent ?? '');
        if ($label !== '') {
            $out[$label] = $val;
        }
    }

    return $out;
}

function xpath_literal(string $s): string
{
    // Construcție XPath literala (safe pentru ghilimele)
    if (strpos($s, "'") === false) {
        return "'" . $s . "'";
    }
    if (strpos($s, '"') === false) {
        return '"' . $s . '"';
    }
    // Fallback: split pe apostrof
    $parts = explode("'", $s);
    $expr = "concat(";
    foreach ($parts as $i => $p) {
        if ($i > 0) {
            $expr .= ",\"'\",";
        }
        $expr .= "'" . $p . "'";
    }
    $expr .= ")";
    return $expr;
}

/**
 * Construiește title/description în formatul strict Model cartelă.
 * @param array{title:string,description:string} $out
 */
function blu_ovoko_format_card_from_detail(DOMXPath $xp, string $detailHtml, array $oemCodesQuery): array
{
    $h1 = blu_ovoko_xpath_first_text($xp, "(//h1)[1]") ?? '';
    $h1 = blu_ovoko_norm_text($h1);

    $internal = '';
    // SKU row
    $sku = blu_ovoko_xpath_first_text($xp, "//tr[td[1][contains(normalize-space(.),'SKU')]]/td[2]");
    if ($sku !== null) {
        $internal = $sku;
    }

    // Tabel compatibilitate (Detalii mașină) — căutăm label "Marcă"
    $compat = blu_ovoko_parse_info_table($xp, 'Marcă');
    $brand = $compat['Marcă'] ?? '';
    $serie = $compat['Serie'] ?? '';
    $model = $compat['Model'] ?? '';
    $an = $compat['An'] ?? '';

    $brand = blu_ovoko_norm_text($brand);
    $serie = blu_ovoko_norm_text($serie);
    $model = blu_ovoko_norm_text($model);
    $an = blu_ovoko_norm_text($an);

    // year_range + vehicles line
    preg_match_all('/(19|20)\d{2}/', $an, $m);
    $years = array_map('intval', $m[0] ?? []);
    $yearRange = '';
    if (count($years) >= 2) {
        sort($years);
        $yearRange = $years[0] . ' - ' . $years[count($years) - 1];
    } elseif (count($years) === 1) {
        $yearRange = (string)$years[0];
    }

    $vehicles = [];
    $modelForCompat = $model !== '' ? $model : $serie;
    if ($brand !== '' && $modelForCompat !== '' && $yearRange !== '') {
        $vehicles[] = mb_strtoupper(trim($brand . ' ' . $modelForCompat . ' ' . $yearRange), 'UTF-8');
    }

    // OEM codes for title
    $oemCodes = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $oemCodesQuery))));
    $oemCodesText = $oemCodes[0] ?? '';

    // part_name heuristic: din h1 scoatem prefix brand + serie/model și codul OEM
    $part = $h1;
    if ($oemCodesText !== '') {
        // Varianta robustă: dacă h1 conține "60573516 / 850005", vrem să păstrăm doar partea dinainte de "60573516".
        $escaped = preg_quote($oemCodesText, '/');
        $part2 = preg_replace('/\s*' . $escaped . '\b.*$/iu', '', $part) ?? $part;
        $part2 = trim($part2);
        if ($part2 !== '') {
            $part = $part2;
        } else {
            // fallback: tăiere la prima apariție
            $pos = stripos($part, $oemCodesText);
            if ($pos !== false) $part = trim(substr($part, 0, $pos));
        }
    }

    $prefixBrandModel = '';
    if ($brand !== '' && $serie !== '') {
        $prefixBrandModel = $brand . ' ' . $serie;
    } elseif ($brand !== '' && $model !== '') {
        $prefixBrandModel = $brand . ' ' . $model;
    }

    if ($prefixBrandModel !== '' && stripos($part, $prefixBrandModel) === 0) {
        $part = trim(substr($part, strlen($prefixBrandModel)));
    } elseif ($brand !== '' && stripos($part, $brand) === 0) {
        $part = trim(substr($part, strlen($brand)));
    }

    if ($part === '' && $h1 !== '') {
        $part = $h1;
    }
    // curățare finală
    $part = blu_ovoko_norm_text($part);

    // model_short (pentru titlu) - folosim model/serie
    $modelShort = $modelForCompat !== '' ? $modelForCompat : $serie;

    $card = blu_card_template_format([
        'part_name' => $part,
        'brand' => $brand,
        'model' => $modelShort,
        'year_range' => $yearRange,
        'oem_codes' => $oemCodes,
        'internal_code' => $internal,
        'vehicles' => $vehicles,
    ]);

    return [
        'title' => $card['title'] ?? '',
        'description' => $card['description'] ?? '',
        'debug' => [
            'h1' => $h1,
            'brand' => $brand,
            'serie' => $serie,
            'model' => $model,
            'an' => $an,
            'internal' => $internal,
            'yearRange' => $yearRange,
            'vehicles' => $vehicles,
            'oemCodes' => $oemCodes,
        ],
    ];
}

/**
 * @return array{ok:bool,title?:string,description?:string,error?:string,debug?:mixed}
 */
function blu_ovoko_scrape_card(string $oemQuery): array
{
    $oemQuery = trim($oemQuery);
    if ($oemQuery === '') {
        return ['ok' => false, 'error' => 'Lipsește Cod OEM.'];
    }

    // Normalizare: dacă stringul conține "Cod OE: 60573687 / 82.641",
    // folosim doar primul cod dinainte de "/".
    // (Restul poate conține cod secundar/alt format și strică potrivirea căutării.)
    if (str_contains($oemQuery, '/')) {
        $oemQuery = preg_split('/\s*\/\s*/u', $oemQuery, 2)[0] ?? $oemQuery;
        $oemQuery = trim($oemQuery);
    }

    // Uneori după cod mai apare text. Extragem primul token “cod” oriunde apare în șir.
    $first = preg_match('/([0-9A-Za-z][0-9A-Za-z.\-]*)/u', $oemQuery, $m) ? (string)$m[1] : $oemQuery;
    $first = trim($first);

    // Păstrăm “doar primul cod” (cel cerut).
    $first = $first !== '' ? $first : '';
    // Pentru coduri numerice cu puncte (ex 60.12020.0) eliminăm punctele.
    if ($first !== '' && preg_match('/^\d+(?:\.\d+)+$/u', $first)) {
        $first = str_replace('.', '', $first);
    }

    $oemCodes = $first !== '' ? [$first] : [];

    if ($oemCodes === []) {
        return ['ok' => false, 'error' => 'Cod OEM invalid.'];
    }

    // 1) Cautare
    // exact=1 ajută de multe ori la păstrarea listării server-side.
    $searchUrl = 'https://ovoko.ro/cautare?q=' . rawurlencode($oemCodes[0]) . '&exact=1';
    $search = blu_ovoko_scrape_do_fetch($searchUrl, 3600);
    $searchHtml = (string)($search['html'] ?? '');
    if (!empty($search['error'])) {
        return [
            'ok' => false,
            'error' => (string)$search['error'],
            'debug' => ['searchUrl' => $searchUrl, 'from_cache' => (bool)($search['from_cache'] ?? false), 'http_code' => (int)($search['http_code'] ?? 0)],
        ];
    }

    // 2) Detail: alegem o pagină care chiar conține OEM-ul cerut.
    // Ovoko poate întoarce primul rezultat “greșit” (altă piesă), deci filtrăm pe câteva candidate.
    $candidates = blu_ovoko_pick_candidate_detail_links($searchHtml, 4);
    if ($candidates === []) {
        $snippet = trim(preg_replace('/\s+/u', ' ', strip_tags(mb_substr($searchHtml, 0, 900))) ?? '');
        return [
            'ok' => false,
            'error' => 'Nu am găsit rezultate Ovoko (structura nu se potrivește).',
            'debug' => [
                'searchUrl' => $searchUrl,
                'from_cache' => (bool)($search['from_cache'] ?? false),
                'snippet' => $snippet,
            ],
        ];
    }

    $pickedDom = null;
    $pickedHtml = '';
    $pickedHref = '';
    $pickErrors = [];

    foreach ($candidates as $cand) {
        $detail = blu_ovoko_scrape_do_fetch($cand['url'], 3600);
        $detailHtml = (string)($detail['html'] ?? '');
        if (!empty($detail['error'])) {
            $pickErrors[] = ['url' => $cand['url'], 'error' => (string)$detail['error']];
            continue;
        }
        $dom = blu_ovoko_dom_load($detailHtml);
        if ($dom === null) continue;
        $xp = new DOMXPath($dom);

        if (blu_ovoko_detail_contains_any_oem($xp, $oemCodes)) {
            $pickedDom = $dom;
            $pickedHtml = $detailHtml;
            $pickedHref = $cand['href'] ?? '';
            break;
        }
    }

    // fallback: dacă nu găsim match strict, folosim primul candidat disponibil
    if ($pickedDom === null) {
        $cand = $candidates[0];
        $detail = blu_ovoko_scrape_do_fetch($cand['url'], 3600);
        $pickedHtml = (string)($detail['html'] ?? '');
        $pickedDom = blu_ovoko_dom_load($pickedHtml);
        $pickedHref = $cand['href'] ?? '';
    }

    if ($pickedDom === null) {
        $snippet = trim(preg_replace('/\s+/u', ' ', strip_tags(mb_substr($pickedHtml, 0, 900))) ?? '');
        return [
            'ok' => false,
            'error' => 'Nu am putut parse HTML-ul Ovoko (detail).',
            'debug' => [
                'detailHref' => $pickedHref,
                'snippet' => $snippet,
                'pickErrors' => $pickErrors,
            ],
        ];
    }

    $xp = new DOMXPath($pickedDom);
    $card = blu_ovoko_format_card_from_detail($xp, $pickedHtml, $oemCodes);
    $title = (string)($card['title'] ?? '');
    $desc = (string)($card['description'] ?? '');

    if ($title === '' && $desc === '') {
        $snippet = trim(preg_replace('/\s+/u', ' ', strip_tags(mb_substr($detailHtml, 0, 900))) ?? '');
        return [
            'ok' => false,
            'error' => 'Ovoko a răspuns, dar nu am putut extrage date (titlu/descriere goale).',
            'debug' => ['detailUrl' => $pick['url'], 'snippet' => $snippet, 'oem' => $oemCodes],
        ];
    }

    return [
        'ok' => true,
        'title' => $title,
        'description' => $desc,
        'debug' => $card['debug'] ?? null,
    ];
}

