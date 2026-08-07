<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

const BLU_TECDOC_COUNTRY_ID = 63;
const BLU_TECDOC_TYPE_ID = 1;

/** @var array<string, string>|null */
$bluTecdocTitleTranslations = null;

function blu_tecdoc_title_translations(): array
{
    global $bluTecdocTitleTranslations;
    if ($bluTecdocTitleTranslations !== null) {
        return $bluTecdocTitleTranslations;
    }

    $bluTecdocTitleTranslations = [
        // poziții / calități
        'front left' => 'fata stanga',
        'front right' => 'fata dreapta',
        'rear left' => 'spate stanga',
        'rear right' => 'spate dreapta',
        'left front' => 'fata stanga',
        'right front' => 'fata dreapta',
        'left rear' => 'spate stanga',
        'right rear' => 'spate dreapta',
        'front' => 'fata',
        'rear' => 'spate',
        'left' => 'stanga',
        'right' => 'dreapta',
        'inner' => 'interior',
        'outer' => 'exterior',
        'upper' => 'sus',
        'lower' => 'jos',
        'electric' => 'electric',
        'manual' => 'manual',
        'with motor' => 'cu motor',
        'without motor' => 'fara motor',
        // geam / caroserie
        'window regulator' => 'Macara geam',
        'window lift' => 'Macara geam',
        'switch, window regulator' => 'Comutator macara geam',
        'switch window regulator' => 'Comutator macara geam',
        'multifunction switch/operating unit' => 'Comutator macara geam',
        'door handle' => 'Maner usa',
        'outside mirror' => 'Oglinda exterioara',
        'wing mirror' => 'Oglinda exterioara',
        'mirror glass' => 'Sticla oglinda',
        'mirror' => 'Oglinda',
        'bumper cover' => 'Bara protectie',
        'bumper' => 'Bara protectie',
        'fender' => 'Aripa',
        'wing' => 'Aripa',
        'bonnet' => 'Capota motor',
        'hood' => 'Capota motor',
        'engine hood' => 'Capota motor',
        'trunk lid' => 'Capota portbagaj',
        'boot lid' => 'Capota portbagaj',
        'tailgate' => 'Hayon',
        'grille' => 'Grila radiator',
        'radiator grille' => 'Grila radiator',
        'door panel' => 'Panou usa',
        'side panel' => 'Panou lateral',
        'seat' => 'Scaun',
        'dashboard' => 'Plansa bord',
        // frânare / suspensie
        'brake pad set' => 'Set placute frana',
        'brake pad' => 'Placute frana',
        'brake disc' => 'Disc frana',
        'brake shoe' => 'Saboti frana',
        'brake caliper' => 'Etrier frana',
        'shock absorber' => 'Amortizor',
        'coil spring' => 'Arc suspensie',
        'leaf spring' => 'Arc foi',
        'control arm' => 'Bascula',
        'wishbone' => 'Bascula',
        'tie rod end' => 'Cap bara directie',
        'tie rod' => 'Bieleta directie',
        'ball joint' => 'Pivot',
        'stabilizer link' => 'Bieleta antiruliu',
        'anti-roll bar' => 'Bara stabilizatoare',
        // motor / transmisie
        'crankshaft bearing' => 'Cuzinet vibrochen',
        'main bearing' => 'Cuzinet principal',
        'rod bearing' => 'Cuzinet biela',
        'wheel bearing' => 'Rulment roata',
        'bearing' => 'Cuzinet',
        'air filter' => 'Filtru aer',
        'oil filter' => 'Filtru ulei',
        'fuel filter' => 'Filtru combustibil',
        'cabin filter' => 'Filtru habitaclu',
        'pollen filter' => 'Filtru habitaclu',
        'spark plug' => 'Bujie',
        'glow plug' => 'Bujie incandescenta',
        'water pump' => 'Pompa apa',
        'fuel pump' => 'Pompa combustibil',
        'oil pump' => 'Pompa ulei',
        'alternator' => 'Alternator',
        'starter motor' => 'Electromotor',
        'starter' => 'Electromotor',
        'clutch kit' => 'Kit ambreiaj',
        'clutch disc' => 'Disc ambreiaj',
        'pressure plate' => 'Placa presiune ambreiaj',
        'release bearing' => 'Rulment presiune',
        'cv joint' => 'Cap planetara',
        'drive shaft' => 'Planetara',
        'half shaft' => 'Planetara',
        'timing belt kit' => 'Kit distributie',
        'timing belt' => 'Curea distributie',
        'timing chain' => 'Lant distributie',
        'v-belt' => 'Curea transmisie',
        'serpentine belt' => 'Curea transmisie',
        'tensioner' => 'Intinzator',
        'idler pulley' => 'Rola ghidaj',
        'turbocharger' => 'Turbocompresor',
        'intercooler' => 'Intercooler',
        'radiator' => 'Radiator',
        'thermostat' => 'Termostat',
        'lambda sensor' => 'Sonda lambda',
        'oxygen sensor' => 'Sonda lambda',
        'abs sensor' => 'Senzor ABS',
        'crankshaft sensor' => 'Senzor vibrochen',
        'camshaft sensor' => 'Senzor ax cu came',
        'sensor' => 'Senzor',
        // iluminare
        'headlight' => 'Far',
        'headlamp' => 'Far',
        'tail light' => 'Stop',
        'taillight' => 'Stop',
        'rear light' => 'Stop',
        'fog light' => 'Proiector ceata',
        'indicator' => 'Semnalizare',
        'turn signal' => 'Semnalizare',
        'daytime running light' => 'Lumina de zi',
        // diverse
        'wiper blade' => 'Lamela stergator',
        'wiper motor' => 'Motor stergator',
        'wiper' => 'Stergator',
        'ignition coil' => 'Bobina inductie',
        'injector' => 'Injector',
        'egr valve' => 'Supapa EGR',
        'throttle body' => 'Clapeta acceleratie',
        'catalytic converter' => 'Catalizator',
        'muffler' => 'Toba esapament',
        'exhaust pipe' => 'Teava esapament',
        'flywheel' => 'Volanta',
        'piston' => 'Piston',
        'gasket' => 'Garnitura',
        'seal' => 'Simering',
        'bush' => 'Bucsa',
        'bushing' => 'Bucsa',
        'mount' => 'Tampon motor',
        'engine mount' => 'Tampon motor',
        'hose' => 'Furtun',
        'pipe' => 'Conducta',
        'cable' => 'Cablu',
        'switch' => 'Comutator',
        'relay' => 'Releu',
        'battery' => 'Baterie',
        'compressor' => 'Compessor AC',
        'condenser' => 'Condensator AC',
        'fan' => 'Ventilator',
        'body' => 'Caroserie',
        'cover' => 'Capac',
        'panel' => 'Panou',
        'part' => 'Piesa',
    ];

    return $bluTecdocTitleTranslations;
}

function blu_tecdoc_translate_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return 'Piesa auto';
    }

    // deja română (diacritice / termeni comuni) → păstrează
    if (preg_match('/[ăâîșţțşĂÂÎȘŢȚŞ]/u', $title)
        || preg_match('/\b(capota|macara|filtru|placute|amortizor|oglinda|bara|aripa|far|pompa|curea|rulment|bascula|planetara)\b/ui', $title)
    ) {
        return preg_replace('/\s+/u', ' ', $title) ?? $title;
    }

    $key = mb_strtolower($title, 'UTF-8');
    $key = str_replace(['/', ',', ';', '_'], ' ', $key);
    $key = trim((string)preg_replace('/\s+/u', ' ', $key));
    $map = blu_tecdoc_title_translations();

    if (isset($map[$key])) {
        return $map[$key];
    }

    // fraze lungi întâi, înlocuire progresivă (nu pierdem stânga/dreapta/față)
    $needles = array_keys($map);
    usort($needles, static fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

    $out = $key;
    foreach ($needles as $needle) {
        if ($needle === '' || mb_strpos($out, $needle, 0, 'UTF-8') === false) {
            continue;
        }
        $out = str_replace($needle, $map[$needle], $out);
    }

    $out = trim((string)preg_replace('/\s+/u', ' ', $out));
    if ($out === '') {
        return 'Piesa auto';
    }

    // capitalizează prima literă a fiecărui segment
    $parts = preg_split('/\s+/u', $out) ?: [$out];
    $parts[0] = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8'), 'UTF-8')
        . mb_substr($parts[0], 1, null, 'UTF-8');

    return implode(' ', $parts);
}

function blu_tecdoc_categories_config(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    require_once __DIR__ . '/pieseauto_categories.php';
    $entries = blu_pieseauto_taxonomy_entries();
    $cache = [];
    foreach ($entries as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cache[] = [
            'main_category' => (string)($row['main_category'] ?? ''),
            'sub_category' => (string)($row['sub_category'] ?? ''),
            'keywords' => is_array($row['keywords'] ?? null) ? $row['keywords'] : [],
            'url' => '',
        ];
    }

    return $cache;
}

function blu_tecdoc_normalize_classify_text(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s\/\-]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function blu_tecdoc_score_keyword(string $haystack, string $keyword): int
{
    $keyword = blu_tecdoc_normalize_classify_text($keyword);
    if ($keyword === '') {
        return 0;
    }
    if ($haystack === $keyword) {
        return 1000;
    }
    if (preg_match('/(^|\s)' . preg_quote($keyword, '/') . '(\s|$)/u', $haystack)) {
        return 200 + mb_strlen($keyword, 'UTF-8');
    }
    return 0;
}

function blu_tecdoc_classify_category(string $title, array $tecdocCategoryNames, array $context = []): array
{
    $text = blu_tecdoc_normalize_classify_text(implode(' ', array_filter([
        $title,
        implode(' ', $tecdocCategoryNames),
        (string)($context['brand'] ?? ''),
        (string)($context['model'] ?? ''),
    ])));

    $best = [
        'main_category' => '',
        'sub_category' => '',
        'product_category' => '',
        'category_url' => '',
        'score' => 0,
    ];

    foreach (blu_tecdoc_categories_config() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $main = trim((string)($row['main_category'] ?? ''));
        $sub = trim((string)($row['sub_category'] ?? ''));
        $keywords = is_array($row['keywords'] ?? null) ? $row['keywords'] : [];
        if ($main === '' || $keywords === []) {
            continue;
        }

        $score = 0;
        foreach ($keywords as $keyword) {
            $score = max($score, blu_tecdoc_score_keyword($text, (string)$keyword));
        }

        if ($score > $best['score']) {
            $best = [
                'main_category' => $main,
                'sub_category' => $sub,
                'product_category' => $sub !== '' ? ($main . ' -> ' . $sub) : $main,
                'category_url' => trim((string)($row['url'] ?? '')),
                'score' => $score,
            ];
        }
    }

    if ($best['score'] > 0) {
        unset($best['score']);
        $best['sub_category'] = blu_tecdoc_translate_category_name((string)$best['sub_category']);
        $best['product_category'] = $best['main_category'] . ' -> ' . $best['sub_category'];
        return $best;
    }

    require_once __DIR__ . '/pieseauto_categories.php';
    $pa = blu_pieseauto_classify_text($text);
    if (($pa['score'] ?? 0) > 0 || !($pa['fallback'] ?? true)) {
        return [
            'main_category' => (string)$pa['main_category'],
            'sub_category' => (string)$pa['sub_category'],
            'product_category' => (string)$pa['product_category'],
            'category_url' => '',
        ];
    }

    $fallbackSub = '';
    foreach (array_reverse($tecdocCategoryNames) as $name) {
        $name = trim((string)$name);
        if ($name !== '') {
            $fallbackSub = $name;
            break;
        }
    }
    if ($fallbackSub === '') {
        $fallbackSub = blu_tecdoc_translate_title($title);
    }
    $fallbackSub = blu_tecdoc_translate_category_name($fallbackSub);

    $paFallback = blu_pieseauto_apply_fallback([
        'main_category' => '',
        'sub_category' => '',
        'product_category' => '',
        'score' => 0,
        'fallback' => true,
    ], $text);

    return [
        'main_category' => (string)$paFallback['main_category'],
        'sub_category' => (string)$paFallback['sub_category'],
        'product_category' => (string)$paFallback['product_category'],
        'category_url' => '',
    ];
}

function blu_tecdoc_api_url(string $path): string
{
    return 'https://' . BLU_RAPIDAPI_HOST . $path;
}

function blu_tecdoc_category_translations(): array
{
    // aceleași mapări ca la titlu — categorii TecDoc vin deseori în EN
    return blu_tecdoc_title_translations();
}

function blu_tecdoc_translate_category_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $key = mb_strtolower($name, 'UTF-8');
    $map = blu_tecdoc_category_translations();
    if (isset($map[$key])) {
        return $map[$key];
    }
    foreach ($map as $needle => $ro) {
        if (mb_strpos($key, $needle, 0, 'UTF-8') !== false) {
            return $ro;
        }
    }
    return $name;
}

function blu_tecdoc_fetch_article_enrichment(int $articleId, bool $fetchDetails = true, int $cacheTtl = 86400): array
{
    if ($articleId <= 0) {
        return ['ok' => false, 'error' => 'articleId invalid'];
    }

    $langId = BLU_RAPIDAPI_LANG_ID;
    $countryId = BLU_TECDOC_COUNTRY_ID;
    $typeId = BLU_TECDOC_TYPE_ID;

    $endpoints = [
        'criteria' => "/articles/selection-of-all-specifications-criterias-for-the-article/article-id/$articleId/lang-id/$langId/country-filter-id/$countryId",
        'category' => "/articles/get-article-category/article-id/$articleId/lang-id/$langId",
        'cross' => "/artlookup/select-article-cross-references/article-id/$articleId/lang-id/$langId",
        'complete' => "/articles/article-complete-details/type-id/$typeId/article-id/$articleId/lang-id/$langId/country-filter-id/$countryId",
    ];
    if ($fetchDetails) {
        $endpoints = ['details' => "/articles/details/article-id/$articleId/lang-id/$langId"] + $endpoints;
    }

    $data = ['ok' => true, 'article_id' => $articleId, 'errors' => []];
    foreach ($endpoints as $key => $path) {
        usleep(BLU_API_DELAY_MS * 1000);
        $resp = blu_api_request(blu_tecdoc_api_url($path), $cacheTtl);
        if (!empty($resp['error'])) {
            $data['errors'][$key] = (string)$resp['error'];
            $data[$key] = [];
            continue;
        }
        $data[$key] = $resp;
    }

    return $data;
}

function blu_tecdoc_first_list(array $payload, array $keys): array
{
    foreach ($keys as $key) {
        if (!empty($payload[$key]) && is_array($payload[$key])) {
            return $payload[$key];
        }
    }

    if (array_keys($payload) === range(0, max(0, count($payload) - 1))) {
        return $payload;
    }

    return [];
}

function blu_tecdoc_extract_category_names(array $categoryPayload): array
{
    $names = [];

    $walk = static function ($node) use (&$walk, &$names): void {
        if (!is_array($node)) {
            return;
        }
        foreach (['categoryName', 'text', 'name', 'assemblyGroupName', 'productGroupName'] as $k) {
            if (!empty($node[$k]) && is_string($node[$k])) {
                $val = trim($node[$k]);
                if ($val !== '') {
                    $names[] = $val;
                }
            }
        }
        foreach (['categories', 'children', 'subCategories', 'assemblyGroupFacets'] as $k) {
            if (!empty($node[$k]) && is_array($node[$k])) {
                foreach ($node[$k] as $child) {
                    $walk($child);
                }
            }
        }
    };

    $walk($categoryPayload);
    return array_values(array_unique(array_filter($names)));
}

function blu_tecdoc_extract_criteria_lines(array $criteriaPayload): array
{
    $items = blu_tecdoc_first_list($criteriaPayload, [
        'articleCriteria',
        'criteria',
        'specifications',
        'articleSpecifications',
        'data',
    ]);

    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string)(
            $item['criteriaName']
            ?? $item['criteriaDescription']
            ?? $item['name']
            ?? $item['key']
            ?? ''
        ));
        $value = trim((string)(
            $item['formattedValue']
            ?? $item['criteriaValue']
            ?? $item['value']
            ?? $item['rawValue']
            ?? ''
        ));

        if ($name === '' && $value === '') {
            continue;
        }
        if ($name === '') {
            $name = 'Specificatie';
        }
        $lines[] = ['name' => $name, 'value' => $value];
    }

    return $lines;
}

function blu_tecdoc_extract_eans(array ...$payloads): array
{
    $eans = [];
    $walk = static function ($node) use (&$walk, &$eans): void {
        if (!is_array($node)) {
            return;
        }
        foreach (['ean', 'eanNumber', 'gtin', 'tradeNumber', 'tradeNumbers'] as $k) {
            if (empty($node[$k])) {
                continue;
            }
            $val = $node[$k];
            if (is_array($val)) {
                foreach ($val as $v) {
                    $v = trim((string)$v);
                    if ($v !== '') {
                        $eans[] = $v;
                    }
                }
            } else {
                $v = trim((string)$val);
                if ($v !== '') {
                    $eans[] = $v;
                }
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $walk($v);
            }
        }
    };

    foreach ($payloads as $payload) {
        $walk($payload);
    }

    return array_values(array_unique(array_filter($eans)));
}

function blu_tecdoc_extract_oem_numbers(array ...$payloads): array
{
    $oems = [];
    $walk = static function ($node) use (&$walk, &$oems): void {
        if (!is_array($node)) {
            return;
        }
        foreach (['oemNumber', 'oemNo', 'articleSearchNo', 'searchNumber', 'referenceNumber'] as $k) {
            if (!empty($node[$k])) {
                $val = trim((string)$node[$k]);
                if ($val !== '') {
                    $oems[] = $val;
                }
            }
        }
        if (!empty($node['oemNumbers']) && is_array($node['oemNumbers'])) {
            foreach ($node['oemNumbers'] as $row) {
                if (is_array($row)) {
                    $val = trim((string)($row['oemNumber'] ?? $row['oemNo'] ?? $row['articleSearchNo'] ?? ''));
                    if ($val !== '') {
                        $oems[] = $val;
                    }
                } else {
                    $val = trim((string)$row);
                    if ($val !== '') {
                        $oems[] = $val;
                    }
                }
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $walk($v);
            }
        }
    };

    foreach ($payloads as $payload) {
        $walk($payload);
    }

    return array_values(array_unique(array_filter($oems)));
}

function blu_tecdoc_extract_engine_codes(array $completePayload, array $context = []): array
{
    $codes = [];
    $walk = static function ($node) use (&$walk, &$codes): void {
        if (!is_array($node)) {
            return;
        }
        foreach (['engCodes', 'engineCode', 'motorCode'] as $k) {
            if (!empty($node[$k])) {
                $raw = preg_split('/[\s,;|\/]+/u', (string)$node[$k]) ?: [];
                foreach ($raw as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $codes[] = $part;
                    }
                }
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $walk($v);
            }
        }
    };

    $walk($completePayload);
    return array_values(array_unique(array_filter($codes)));
}

function blu_tecdoc_extract_supplement_info(array $detailsPayload, array $criteriaPayload): string
{
    foreach (['additionalDescription', 'articleAdditionalDescription', 'info', 'description'] as $k) {
        if (!empty($detailsPayload[$k]) && is_string($detailsPayload[$k])) {
            $val = trim($detailsPayload[$k]);
            if ($val !== '') {
                return $val;
            }
        }
    }

    foreach (blu_tecdoc_extract_criteria_lines($criteriaPayload) as $line) {
        $name = mb_strtolower($line['name'], 'UTF-8');
        if (
            str_contains($name, 'info suplimentar')
            || str_contains($name, 'articol completare')
            || str_contains($name, 'completare')
        ) {
            return trim($line['value']);
        }
    }

    return '';
}

function blu_tecdoc_resolve_part_name(array $article, array $context = []): string
{
    $name = trim((string)($article['articleProductName'] ?? $article['articleName'] ?? ''));
    if ($name !== '' && !str_contains($name, ',')) {
        return blu_tecdoc_translate_title($name);
    }
    if ($name !== '' && str_contains($name, ',')) {
        return blu_tecdoc_translate_title(trim(explode(',', $name)[0]));
    }
    return blu_tecdoc_translate_title('Piesa auto');
}

function blu_tecdoc_build_pieseauto_title(array $article, array $context, array $categoryMeta): string
{
    $part = blu_tecdoc_resolve_part_name($article, $context);
    if ($part === 'Piesa auto' && !empty($categoryMeta['sub_category'])) {
        $part = (string)$categoryMeta['sub_category'];
    }

    $brand = trim((string)($context['brand'] ?? ''));
    $model = trim((string)($context['model'] ?? ''));

    $modelShort = $model;
    if (preg_match('/^[A-Z0-9\s\-]+\s+([A-Z0-9\s\-\(\)\/]+)\s+(\d{4}-\d{4}.*)$/u', $model, $m)) {
        $modelShort = trim($m[1] . ' ' . $m[2]);
    }

    $parts = array_filter([$part, $brand, $modelShort], static fn($v) => trim((string)$v) !== '');
    $parts = array_values(array_unique($parts));
    $title = implode(', ', $parts);
    if (mb_strlen($title, 'UTF-8') >= 15) {
        return $title;
    }

    $fallback = trim($part . ' ' . $brand . ' ' . $modelShort);
    return $fallback !== '' ? $fallback : $part;
}

/* ===========================================================================
 * MODEL CARTELĂ PRODUS (format fix, date din TecDoc)
 * Titlu + Cod OE + cod INT + COMPATIBIL CU + PRODUS NOU + bloc standard firmă.
 * ===========================================================================*/

function blu_card_template_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'card_template.json';
}

function blu_card_template_defaults(): array
{
    return [
        'status_label' => 'PRODUS NOU',
        'title_suffix' => 'NOUA',
        'boilerplate' => [
            'Preturile pieselor afisate includ TVA',
            'Produs nou cu factura si garantie.',
            'Pentru orice alta piesa, nu ezitati sa ne contactati.',
            'Comercializam piese auto noi aftermarket, OE si OEM fabricate de producatori de renume.',
            'Pentru a beneficia de garantie, piesa trebuie montata intr un service autorizat RAR.',
            'Returul pieselor se accepta in maxim 15 zile calendaristice.',
            'Livrarea pieselor se face prin curier.',
            'Pentru informatii suplimentare nu ezitati sa ne contactati',
            'Program de lucru:08.00 - 20.00',
            'Program online - Non Stop',
        ],
    ];
}

function blu_card_template_settings(): array
{
    $defaults = blu_card_template_defaults();
    $data = blu_read_json_file(blu_card_template_file(), []);
    if (!is_array($data)) {
        return $defaults;
    }
    $out = $defaults;
    if (!empty($data['status_label'])) {
        $out['status_label'] = trim((string)$data['status_label']);
    }
    if (!empty($data['title_suffix'])) {
        $out['title_suffix'] = trim((string)$data['title_suffix']);
    }
    if (!empty($data['boilerplate']) && is_array($data['boilerplate'])) {
        $lines = [];
        foreach ($data['boilerplate'] as $l) {
            $l = trim((string)$l);
            if ($l !== '') {
                $lines[] = $l;
            }
        }
        if ($lines !== []) {
            $out['boilerplate'] = $lines;
        }
    }
    return $out;
}

function blu_card_template_save(array $settings): bool
{
    $defaults = blu_card_template_defaults();
    $payload = [
        'status_label' => trim((string)($settings['status_label'] ?? $defaults['status_label'])),
        'title_suffix' => trim((string)($settings['title_suffix'] ?? $defaults['title_suffix'])),
        'boilerplate' => [],
    ];
    $bp = $settings['boilerplate'] ?? $defaults['boilerplate'];
    if (is_string($bp)) {
        $bp = preg_split('/\r\n|\r|\n/', $bp) ?: [];
    }
    foreach ((array)$bp as $l) {
        $l = trim((string)$l);
        if ($l !== '') {
            $payload['boilerplate'][] = $l;
        }
    }
    if ($payload['boilerplate'] === []) {
        $payload['boilerplate'] = $defaults['boilerplate'];
    }
    return blu_write_json_file(blu_card_template_file(), $payload);
}

/** Extrage un an (4 cifre) dintr-un text gen "201301", "2013-01", "2013". */
function blu_year_from_text(string $s): string
{
    if (preg_match('/(19|20)\d{2}/', $s, $m)) {
        return $m[0];
    }
    return '';
}

/** Curăță numele modelului (scoate motorizarea/anii) și îl pune cu majuscule. */
function blu_card_clean_model(string $model): string
{
    $model = trim($model);
    if ($model === '') {
        return '';
    }
    $model = preg_replace('/\b(19|20)\d{2}\s*[-–]\s*(19|20)?\d{2,4}\b.*$/u', '', $model) ?? $model;
    $model = preg_replace('/\b\d\.\d+\b.*$/u', '', $model) ?? $model;
    $model = preg_replace('/\s+/u', ' ', $model) ?? $model;
    return mb_strtoupper(trim($model), 'UTF-8');
}

/**
 * Extrage lista de vehicule compatibile + anii din payload-urile TecDoc.
 * Best-effort: caută noduri de tip vehicul (producător/model/tip + ani).
 *
 * @return array{vehicles:list<string>,years:list<int>}
 */
function blu_tecdoc_extract_compatibility(array ...$payloads): array
{
    $vehicles = [];
    $years = [];

    $walk = static function ($node) use (&$walk, &$vehicles, &$years): void {
        if (!is_array($node)) {
            return;
        }
        $mfr = trim((string)(
            $node['mfrName'] ?? $node['manuName'] ?? $node['manufacturerName']
            ?? $node['carManufacturerName'] ?? $node['brandName'] ?? ''
        ));
        $model = trim((string)(
            $node['vehicleModelSeriesName'] ?? $node['modelName'] ?? $node['modelSeriesName']
            ?? $node['carModelName'] ?? ''
        ));
        $type = trim((string)(
            $node['typeName'] ?? $node['vehicleTypeName'] ?? $node['carTypeName'] ?? ''
        ));
        $from = blu_year_from_text((string)(
            $node['yearOfConstrFrom'] ?? $node['constructedFrom'] ?? $node['beginYearMonth']
            ?? $node['kmodTypePcFromYear'] ?? ''
        ));
        $to = blu_year_from_text((string)(
            $node['yearOfConstrTo'] ?? $node['constructedTo'] ?? $node['endYearMonth']
            ?? $node['kmodTypePcToYear'] ?? ''
        ));

        if ($mfr !== '' || $model !== '') {
            if ($from !== '') {
                $years[] = (int)$from;
            }
            if ($to !== '') {
                $years[] = (int)$to;
            }
            $range = '';
            if ($from !== '') {
                $range = $from . '-' . ($to !== '' ? $to : 'prezent');
            }
            $line = trim($mfr . ' ' . $model . ' ' . $type . ($range !== '' ? ' ' . $range : ''));
            $line = trim((string)preg_replace('/\s+/u', ' ', $line));
            if ($line !== '' && mb_strlen($line, 'UTF-8') > 2) {
                $vehicles[mb_strtoupper($line, 'UTF-8')] = true;
            }
        }

        foreach ($node as $v) {
            if (is_array($v)) {
                $walk($v);
            }
        }
    };

    foreach ($payloads as $p) {
        $walk($p);
    }

    return [
        'vehicles' => array_slice(array_keys($vehicles), 0, 40),
        'years' => array_values(array_unique($years)),
    ];
}

/**
 * Formatează titlul + descrierea strict după modelul din Admin → Model cartelă.
 *
 * @param array{
 *   part_name?:string,
 *   brand?:string,
 *   model?:string,
 *   year_range?:string,
 *   years?:list<int>,
 *   oem_codes?:list<string>|string,
 *   internal_code?:string,
 *   vehicles?:list<string>
 * } $fields
 * @return array{title:string,description:string}
 */
function blu_card_template_format(array $fields): array
{
    $tpl = blu_card_template_settings();

    $part = trim((string)($fields['part_name'] ?? ''));
    $brand = mb_strtoupper(trim((string)($fields['brand'] ?? '')), 'UTF-8');
    $model = blu_card_clean_model((string)($fields['model'] ?? ''));

    $oems = $fields['oem_codes'] ?? [];
    if (is_string($oems)) {
        $oems = preg_split('/[\s,\/;]+/u', $oems) ?: [];
    }
    $oems = array_values(array_unique(array_filter(array_map('trim', (array)$oems))));
    $oemText = implode(' / ', array_slice($oems, 0, 8));

    $internal = trim((string)($fields['internal_code'] ?? ''));

    $vehicles = $fields['vehicles'] ?? [];
    if (!is_array($vehicles)) {
        $vehicles = [];
    }
    $vehicles = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $vehicles)));

    $years = $fields['years'] ?? [];
    if (!is_array($years)) {
        $years = [];
    }
    $yearRange = trim((string)($fields['year_range'] ?? ''));
    if ($yearRange === '' && $years !== []) {
        $minY = min($years);
        $maxY = max($years);
        $yearRange = $minY === $maxY ? (string)$minY : ($minY . ' - ' . $maxY);
    }
    if ($yearRange === '' && preg_match_all('/(19|20)\d{2}/', (string)($fields['model'] ?? ''), $mm)) {
        $parsedYears = array_map('intval', $mm[0]);
        $minY = min($parsedYears);
        $maxY = max($parsedYears);
        $yearRange = $minY === $maxY ? (string)$minY : ($minY . ' - ' . $maxY);
    }

    if ($vehicles === []) {
        $fallback = trim($brand . ' ' . $model . ($yearRange !== '' ? ' ' . $yearRange : ''));
        if ($fallback !== '') {
            $vehicles[] = $fallback;
        }
    }

    $titleParts = array_filter([
        $part,
        $brand,
        $model,
        $yearRange !== '' ? 'AN FAB ' . $yearRange : '',
        $oemText !== '' ? 'Cod OE ' . $oemText : '',
        $tpl['title_suffix'],
    ], static fn($v) => trim((string)$v) !== '');
    $title = trim((string)preg_replace('/\s+/u', ' ', implode(' ', $titleParts)));

    $lines = [];
    if ($oemText !== '') {
        $lines[] = 'Cod OE: ' . $oemText;
    }
    if ($internal !== '') {
        $lines[] = 'cod INT: ' . $internal;
    }
    if ($vehicles !== []) {
        $lines[] = '';
        $lines[] = 'COMPATIBIL CU:';
        foreach ($vehicles as $v) {
            $lines[] = $v;
        }
    }
    $lines[] = '';
    $lines[] = $tpl['status_label'];
    $lines[] = '';
    foreach ($tpl['boilerplate'] as $b) {
        $lines[] = $b;
    }

    return ['title' => $title, 'description' => implode("\n", $lines)];
}

/** Verifică dacă descrierea respectă formatul modelului de cartelă. */
function blu_description_uses_card_template(string $text): bool
{
    if ($text === '') {
        return false;
    }
    $tpl = blu_card_template_settings();
    return str_contains($text, 'Cod OE:')
        && (str_contains($text, 'COMPATIBIL CU:') || str_contains($text, "cod INT:"))
        && str_contains($text, $tpl['status_label']);
}

/** Previzualizare HTML identică cu Admin → Model cartelă. */
function blu_render_card_template_preview(string $title, string $description, bool $compact = false): void
{
    $pad = $compact ? '10px' : '14px';
    $font = $compact ? '.82rem' : '.9rem';
    echo '<div class="blu-card-template-preview" style="border:1px solid #e2e8f0;border-radius:10px;padding:' . $pad . ';background:#fff;">';
    echo '<div class="fw-bold mb-2" style="line-height:1.3;">' . e($title) . '</div>';
    echo '<div style="white-space:pre-line;font-size:' . $font . ';color:#334155;">' . e($description) . '</div>';
    echo '</div>';
}

/**
 * Aplică modelul de cartelă pe baza datelor disponibile (fără apel TecDoc).
 *
 * @param array<string,mixed> $card
 * @param array<string,mixed> $context
 * @return array<string,mixed>
 */
function blu_apply_card_template_to_card(array $card, array $context): array
{
    $admin = is_array($card['admin_card'] ?? null) ? $card['admin_card'] : [];

    $partName = trim((string)($card['title_original'] ?? ''));
    if ($partName === '') {
        $partName = trim((string)($admin['categorie'] ?? $card['sub_category'] ?? ''));
    }
    if ($partName === '') {
        $rawTitle = trim((string)($card['title'] ?? $admin['nume'] ?? ''));
        $partName = preg_split('/\s+/u', $rawTitle)[0] ?? 'Piesa auto';
    }
    $partName = blu_tecdoc_translate_title($partName);

    $oems = [];
    foreach (blu_extract_oem_codes_only((string)($context['coduri_oem'] ?? $card['coduri_oem'] ?? '')) as $oem) {
        $oems[] = $oem;
    }
    $codOem = trim((string)($admin['cod_oem'] ?? $card['cod_oem'] ?? ''));
    if ($codOem !== '') {
        $oems[] = $codOem;
    }
    $oemRef = trim((string)($admin['cod_oem_ref'] ?? ''));
    if ($oemRef !== '') {
        $oems[] = $oemRef;
    }
    $oems = array_values(array_unique(array_filter(array_map('trim', $oems))));

    $internal = trim((string)($context['cod_articol'] ?? $card['cod_articol'] ?? ''));
    if ($internal === '') {
        $params = $card['parameters_json'] ?? '';
        if (is_string($params) && $params !== '') {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $internal = trim((string)($decoded['article_no'] ?? $decoded['catalog']['cod_articol'] ?? ''));
            }
        }
    }

    $formatted = blu_card_template_format([
        'part_name' => $partName,
        'brand' => (string)($context['brand'] ?? $card['marca_masina'] ?? $card['car_brand'] ?? ''),
        'model' => (string)($context['model'] ?? $card['model'] ?? ''),
        'oem_codes' => $oems,
        'internal_code' => $internal,
    ]);

    $card['title'] = $formatted['title'];
    $card['ad_title'] = $formatted['title'];
    $card['description'] = $formatted['description'];
    $card['ad_description'] = $formatted['description'];
    if (!isset($card['admin_card']) || !is_array($card['admin_card'])) {
        $card['admin_card'] = [];
    }
    $card['admin_card']['nume'] = $formatted['title'];
    $card['admin_card']['descriere'] = $formatted['description'];

    return $card;
}

/**
 * Construiește cartela în format fix (ca modelul cerut):
 * @return array{title:string,description:string}
 */
function blu_tecdoc_build_card_text(array $article, array $context, array $enrichment): array
{
    $details = is_array($enrichment['details'] ?? null) ? $enrichment['details'] : [];
    $criteriaPayload = is_array($enrichment['criteria'] ?? null) ? $enrichment['criteria'] : [];
    $complete = is_array($enrichment['complete'] ?? null) ? $enrichment['complete'] : [];
    $cross = is_array($enrichment['cross'] ?? null) ? $enrichment['cross'] : [];

    $part = blu_tecdoc_resolve_part_name($article, $context);
    $brand = mb_strtoupper(trim((string)($context['brand'] ?? '')), 'UTF-8');
    $model = blu_card_clean_model((string)($context['model'] ?? ''));

    $oems = [];
    if (!empty($context['coduri_oem'])) {
        foreach (blu_extract_oem_codes_only((string)$context['coduri_oem']) as $oem) {
            $oems[] = $oem;
        }
    }
    foreach (blu_tecdoc_extract_oem_numbers($details, $complete, $cross, $article) as $oem) {
        $oems[] = $oem;
    }
    $oems = array_values(array_unique(array_filter(array_map('trim', $oems))));

    $internal = trim((string)($context['cod_articol'] ?? ''));
    if ($internal === '') {
        $internal = trim((string)($article['articleNo'] ?? ''));
    }

    $compat = blu_tecdoc_extract_compatibility($complete, $cross, $details);
    $vehicles = $compat['vehicles'];
    $years = $compat['years'];

    if ($years === [] && preg_match_all('/(19|20)\d{2}/', (string)($context['model'] ?? ''), $mm)) {
        foreach ($mm[0] as $y) {
            $years[] = (int)$y;
        }
    }

    return blu_card_template_format([
        'part_name' => $part,
        'brand' => $brand,
        'model' => $model,
        'years' => $years,
        'oem_codes' => $oems,
        'internal_code' => $internal,
        'vehicles' => $vehicles,
    ]);
}

function blu_tecdoc_format_description_block(string $label, string $value): string
{
    $label = trim($label);
    $value = trim($value);
    if ($label === '' || $value === '') {
        return '';
    }
    return $label . ":\n" . $value;
}

function blu_tecdoc_build_full_description(array $article, array $context, array $enrichment): string
{
    $titleRo = blu_tecdoc_resolve_part_name($article, $context);
    $articleNo = trim((string)($article['articleNo'] ?? ''));
    $supplier = trim((string)($article['supplierName'] ?? $article['brand'] ?? ''));

    $details = is_array($enrichment['details'] ?? null) ? $enrichment['details'] : [];
    $criteriaPayload = is_array($enrichment['criteria'] ?? null) ? $enrichment['criteria'] : [];
    $complete = is_array($enrichment['complete'] ?? null) ? $enrichment['complete'] : [];
    $cross = is_array($enrichment['cross'] ?? null) ? $enrichment['cross'] : [];

    $criteriaLines = blu_tecdoc_extract_criteria_lines($criteriaPayload);
    $supplement = blu_tecdoc_extract_supplement_info($details, $criteriaPayload);
    $eans = blu_tecdoc_extract_eans($details, $complete, $article, $cross);
    $oems = blu_tecdoc_extract_oem_numbers($details, $complete, $cross, $article);
    $engineCodes = blu_tecdoc_extract_engine_codes($complete, $context);

    if (!empty($context['coduri_oem'])) {
        foreach (blu_extract_oem_codes_only((string)$context['coduri_oem']) as $oem) {
            $oems[] = $oem;
        }
    }
    $oems = array_values(array_unique(array_filter($oems)));

    $blocks = [$titleRo];

    if ($supplement !== '') {
        $blocks[] = blu_tecdoc_format_description_block('Articol completare / Info suplimentar 2', $supplement);
    }

    $skipCriteriaNames = ['info suplimentar', 'articol completare'];
    foreach ($criteriaLines as $line) {
        $nameLower = mb_strtolower($line['name'], 'UTF-8');
        $skip = false;
        foreach ($skipCriteriaNames as $needle) {
            if (str_contains($nameLower, $needle)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $blocks[] = blu_tecdoc_format_description_block($line['name'], $line['value']);
    }

    if ($articleNo !== '') {
        $blocks[] = blu_tecdoc_format_description_block('Număr articol', $articleNo);
    }
    if ($supplier !== '') {
        $blocks[] = blu_tecdoc_format_description_block('Producătorul', $supplier);
    }
    if ($eans !== []) {
        $blocks[] = blu_tecdoc_format_description_block('Numere EAN', implode(', ', $eans));
    }
    if ($oems !== []) {
        $blocks[] = blu_tecdoc_format_description_block('Cod OEM', implode(', ', array_slice($oems, 0, 20)));
    }

    $engineText = $engineCodes !== []
        ? implode(', ', array_slice($engineCodes, 0, 30))
        : 'Această caracteristică variază în funcție de modelul de automobil.';
    $blocks[] = blu_tecdoc_format_description_block('Numar motor TECDOC', $engineText);

    $compat = trim((string)($context['brand'] ?? '') . ' ' . (string)($context['model'] ?? ''));
    if ($compat !== '') {
        $blocks[] = blu_tecdoc_format_description_block('Compatibilitate', $compat);
    }
    if (!empty($context['cod_articol'])) {
        $blocks[] = blu_tecdoc_format_description_block('Cod articol catalog', (string)$context['cod_articol']);
    }
    if (!empty($context['coduri_oem'])) {
        $blocks[] = blu_tecdoc_format_description_block('OEM catalog', (string)$context['coduri_oem']);
    }
    if (!empty($context['search_code'])) {
        $blocks[] = blu_tecdoc_format_description_block('Cautat dupa', (string)$context['search_code']);
    }

    return implode("\n", array_values(array_filter($blocks)));
}

function blu_tecdoc_build_enriched_product_meta(array $article, array $context): array
{
    $articleId = (int)($article['articleId'] ?? 0);
    $cacheTtl = !empty($context['force_refresh']) ? 0 : 86400;
    $enrichment = $articleId > 0 ? blu_tecdoc_fetch_article_enrichment($articleId, true, $cacheTtl) : ['ok' => false];
    $categoryNames = blu_tecdoc_extract_category_names(is_array($enrichment['category'] ?? null) ? $enrichment['category'] : []);
    $categoryMeta = blu_tecdoc_classify_category(
        (string)($article['articleProductName'] ?? $article['articleName'] ?? ''),
        $categoryNames,
        $context
    );

    $card = blu_tecdoc_build_card_text($article, $context, $enrichment);
    $titleRo = $card['title'] !== '' ? $card['title'] : blu_tecdoc_build_pieseauto_title($article, $context, $categoryMeta);
    $description = $card['description'];
    $criteriaLines = blu_tecdoc_extract_criteria_lines(is_array($enrichment['criteria'] ?? null) ? $enrichment['criteria'] : []);
    $eans = blu_tecdoc_extract_eans(
        is_array($enrichment['details'] ?? null) ? $enrichment['details'] : [],
        is_array($enrichment['complete'] ?? null) ? $enrichment['complete'] : [],
        $article
    );
    $engineCodes = blu_tecdoc_extract_engine_codes(
        is_array($enrichment['complete'] ?? null) ? $enrichment['complete'] : [],
        $context
    );

    return [
        'title_ro' => $titleRo,
        'description' => $description,
        'main_category' => $categoryMeta['main_category'],
        'sub_category' => $categoryMeta['sub_category'],
        'product_category' => $categoryMeta['product_category'],
        'category_url' => $categoryMeta['category_url'],
        'tecdoc_categories' => $categoryNames,
        'criteria_count' => count($criteriaLines),
        'eans' => $eans,
        'engine_codes' => $engineCodes,
        'enrichment' => $enrichment,
    ];
}

/**
 * Re-enrich o carte importata existenta (dupa product_id tecdoc_*).
 */
function blu_reenrich_imported_card(array $card): array
{
    $productId = (string)($card['product_id'] ?? '');
    if ($productId === '') {
        return $card;
    }

    if (str_starts_with($productId, 'tecdoc_')) {
        $articleId = (int)substr($productId, strlen('tecdoc_'));
        if ($articleId <= 0) {
            return $card;
        }

        $article = [
            'articleId' => $articleId,
            'articleNo' => (string)($card['cod_oem'] ?? ''),
            'articleProductName' => (string)($card['title_original'] ?? blu_tecdoc_resolve_part_name([
                'articleProductName' => (string)($card['title'] ?? ''),
            ])),
            'supplierName' => (string)($card['brand'] ?? ''),
        ];

        $context = [
            'brand' => (string)($card['marca_masina'] ?? ''),
            'model' => (string)($card['model'] ?? ''),
            'cod_articol' => (string)($card['cod_articol'] ?? ''),
            'coduri_oem' => (string)($card['coduri_oem'] ?? $card['cod_oem'] ?? ''),
            'search_code' => (string)($card['coduri_oem'] ?? $card['cod_oem'] ?? ''),
            'force_refresh' => true,
        ];

        $meta = blu_tecdoc_build_enriched_product_meta($article, $context);

        $card['title'] = $meta['title_ro'];
        $card['title_original'] = trim((string)($article['articleProductName'] ?? $card['title_original'] ?? ''));
        $card['descriere'] = $meta['description'];
        $card['main_category'] = $meta['main_category'];
        $card['sub_category'] = $meta['sub_category'];
        $card['product_category'] = $meta['product_category'];
        $card['category_name'] = $meta['sub_category'];
        $card['category_full'] = $meta['product_category'];
        $card['pieseauto_category'] = $meta['sub_category'];
        $card['car'] = trim($context['brand'] . ' — ' . $context['model']);
        $card['tecdoc_enriched'] = true;
        $card['tecdoc_criteria_count'] = $meta['criteria_count'];
        $card['updated_at'] = date('Y-m-d H:i:s');

        return $card;
    }

    $context = [
        'brand' => (string)($card['marca_masina'] ?? $card['car_brand'] ?? ''),
        'model' => (string)($card['model'] ?? ''),
        'cod_articol' => (string)($card['cod_articol'] ?? ''),
        'coduri_oem' => (string)($card['coduri_oem'] ?? $card['cod_oem'] ?? ''),
    ];
    $card = blu_apply_card_template_to_card($card, $context);
    $card['descriere'] = (string)($card['description'] ?? $card['descriere'] ?? '');
    $card['updated_at'] = date('Y-m-d H:i:s');

    return $card;
}

/**
 * Re-enrich toate cartele importate (lot).
 *
 * @return array{ok:bool,total:int,offset:int,next_offset:int,done:bool,updated:int,errors:list<string>}
 */
function blu_reenrich_imported_cards_batch(int $offset, int $limit): array
{
    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    if (!is_array($rows)) {
        $rows = [];
    }

    $targets = [];
    foreach ($rows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['status'] ?? '') !== 'imported') {
            continue;
        }
        if (!str_starts_with((string)($row['product_id'] ?? ''), 'tecdoc_')) {
            continue;
        }
        $targets[] = $idx;
    }

    $total = count($targets);
    $slice = array_slice($targets, $offset, $limit);
    $updated = 0;
    $errors = [];

    foreach ($slice as $idx) {
        try {
            $rows[$idx] = blu_reenrich_imported_card($rows[$idx]);
            $updated++;
        } catch (Throwable $e) {
            $errors[] = (string)($rows[$idx]['product_id'] ?? $idx) . ': ' . $e->getMessage();
        }
    }

    if ($updated > 0) {
        blu_write_json_file(blu_imported_cards_file(), $rows);
    }

    $nextOffset = $offset + count($slice);
    $done = $nextOffset >= $total;
    $sync = null;
    if ($done && $updated > 0) {
        $sync = blu_sync_all_product_sources();
    }

    return [
        'ok' => true,
        'total' => $total,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'done' => $done,
        'updated' => $updated,
        'errors' => $errors,
        'sync' => $sync,
    ];
}

/**
 * Sincronizează cartele importate în products.json, admin merge și MySQL.
 *
 * @return array{ok:bool,products_json:int,db:int,db_errors:list<string>,message:string}
 */
function blu_sync_all_product_sources(): array
{
    require_once __DIR__ . '/products_store.php';

    $updatedJson = blu_sync_imported_cards_to_products();
    $dbSaved = 0;
    $dbErrors = [];

    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    $dbProducts = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['status'] ?? '') !== 'imported') {
                continue;
            }
            $productId = (string)($row['product_id'] ?? '');
            if ($productId === '') {
                continue;
            }
            $subCategory = blu_tecdoc_translate_category_name(trim((string)(
                $row['sub_category'] ?? $row['pieseauto_category'] ?? $row['category_name'] ?? ''
            )));
            $mainCategory = trim((string)($row['main_category'] ?? 'Piese auto'));
            $title = (string)($row['title'] ?? 'Piesa auto');
            $description = (string)($row['descriere'] ?? '');
            $image = trim((string)($row['image'] ?? ''));
            $imagesForDb = $image !== '' ? [['url' => $image, 'remote' => true]] : [];
            $mainImageJson = $image !== '' ? json_encode([$image], JSON_UNESCAPED_UNICODE) : '';

            $dbProducts[] = [
                'id' => $productId,
                'product_id' => $productId,
                'slug' => blu_slugify($title . '-' . (string)($row['cod_oem'] ?? '')),
                'title' => $title,
                'title_original' => $title,
                'brand' => (string)($row['brand'] ?? ''),
                'car_brand' => (string)($row['marca_masina'] ?? ''),
                'main_category' => $mainCategory,
                'sub_category' => $subCategory,
                'product_category' => (string)($row['product_category'] ?? ($mainCategory . ' -> ' . $subCategory)),
                'description' => $description,
                'ad_title' => $title,
                'ad_description' => $description,
                'compatibility' => (string)($row['car'] ?? ''),
                'main_image' => $mainImageJson,
                'images_json' => json_encode($imagesForDb, JSON_UNESCAPED_UNICODE),
                'stock' => '1',
                'item_condition' => 'Nou',
                'updated_at' => date('Y-m-d H:i:s'),
                'admin_card' => [
                    'nume' => $title,
                    'cod_oem' => (string)($row['cod_oem'] ?? ''),
                    'categorie' => $subCategory,
                    'pret' => '',
                    'stoc' => '1',
                    'descriere' => $description,
                    'imagine' => $image,
                    'marca_masina' => (string)($row['marca_masina'] ?? ''),
                ],
            ];
        }
    }

    if ($dbProducts !== []) {
        blu_merge_admin_products($dbProducts);
    }

    $db = blu_db_products_bootstrap();
    if ($db && $dbProducts !== []) {
        $result = blu_save_products_to_db($db['pdo'], $dbProducts);
        $dbSaved = (int)($result['imported'] ?? 0);
        if (!empty($result['errors'])) {
            $dbErrors[] = (string)$result['errors'];
        }
        if (!($result['ok'] ?? true)) {
            $dbErrors[] = (string)($result['message'] ?? 'Eroare DB');
        }
    } elseif ($db === null) {
        $settings = blu_db_settings_from_env();
        if ($settings === null) {
            $dbErrors[] = 'MySQL neconfigurat in .env';
        } else {
            $dbErrors[] = 'MySQL: conexiune esuata (verifica DB_HOST/DB_USER/DB_PASS)';
        }
    }

    return [
        'ok' => true,
        'products_json' => $updatedJson,
        'db' => $dbSaved,
        'db_errors' => $dbErrors,
        'message' => "products.json: {$updatedJson} actualizate, DB: {$dbSaved} sincronizate.",
    ];
}

/**
 * Re-enrich + sincronizare completă pentru toate cartele importate.
 */
function blu_refresh_all_imported_products(): array
{
    $totalUpdated = 0;
    $offset = 0;
    $limit = 1;
    $errors = [];
    $sync = null;

    do {
        $batch = blu_reenrich_imported_cards_batch($offset, $limit);
        $totalUpdated += (int)($batch['updated'] ?? 0);
        if (!empty($batch['errors'])) {
            $errors = array_merge($errors, $batch['errors']);
        }
        if (!empty($batch['sync'])) {
            $sync = $batch['sync'];
        }
        $offset = (int)($batch['next_offset'] ?? 0);
        $done = !empty($batch['done']);
    } while (!$done);

    if ($sync === null) {
        $sync = blu_sync_all_product_sources();
    }

    return [
        'ok' => true,
        'updated' => $totalUpdated,
        'errors' => $errors,
        'sync' => $sync,
    ];
}
