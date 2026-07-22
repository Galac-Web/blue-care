<?php
declare(strict_types=1);

/**
 * Extrage structura GBG (mărci + categorii speciale) din cache-ul Chrome al robotului.
 * Usage: php scripts/seed_gbg_structure.php
 */

require_once dirname(__DIR__) . '/lib/shop/gbg_structure.php';

$cacheCandidates = [
    dirname(__DIR__) . '/robot/profil_gbg_user_01/Default/Cache/Cache_Data/f_000177',
    dirname(__DIR__) . '/robot/profil_gbg_gbg_user_01/Default/Cache/Cache_Data/f_000177',
];

$html = '';
foreach ($cacheCandidates as $file) {
    if (is_file($file)) {
        $html = (string) file_get_contents($file);
        break;
    }
}

if ($html === '') {
    fwrite(STDERR, "Nu am găsit cache HTML GBG.\n");
    exit(1);
}

$brands = [];
if (preg_match_all("/BrandClick\\('([^']+)'[^>]*>.*?<span>([^<]+)<\\/span>/s", $html, $brandMatches, PREG_SET_ORDER)) {
    foreach ($brandMatches as $match) {
        $name = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($name === '') {
            continue;
        }
        $brands[] = [
            'id' => trim($match[1]),
            'name' => $name,
            'logo_src' => '',
        ];
    }
}

$special = [];
if (preg_match_all("/FormIntClick1\\('','','(\\d+)'[^>]*>.*?<span[^>]*FormIntClick1[^>]*>([^<]+)<\\/span>/s", $html, $specMatches, PREG_SET_ORDER)) {
    foreach ($specMatches as $match) {
        $special[] = [
            'id' => trim($match[1]),
            'label_gbg' => html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
}

if ($brands === [] && $special === []) {
    fwrite(STDERR, "Nu am putut parsa structura din cache.\n");
    exit(1);
}

$merged = blu_gbg_merge_structure([
    'brands' => $brands,
    'special_categories' => $special,
    'source' => 'chrome_cache_seed',
]);

echo 'Salvat: ' . blu_gbg_structure_file() . PHP_EOL;
echo 'Mărci: ' . count($merged['brands'] ?? []) . PHP_EOL;
echo 'Categorii speciale: ' . count($merged['special_categories'] ?? []) . PHP_EOL;
