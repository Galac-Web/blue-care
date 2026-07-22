<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

/**
 * Taxonomie și clasificare categorii PieseAuto.ro.
 */

function blu_pieseauto_taxonomy_path(): string
{
    return blu_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pieseauto_taxonomy.json';
}

/** @return array{version?:int,fallbacks?:array<string,string>,entries?:list<array<string,mixed>>} */
function blu_pieseauto_taxonomy(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $data = blu_read_json_file(blu_pieseauto_taxonomy_path(), []);
    $cache = is_array($data) ? $data : [];

    return $cache;
}

/** @return list<array{main_category:string,sub_category:string,keywords:list<string>}> */
function blu_pieseauto_taxonomy_entries(): array
{
    $entries = blu_pieseauto_taxonomy()['entries'] ?? [];
    return is_array($entries) ? $entries : [];
}

/** @return array<string, string> */
function blu_pieseauto_taxonomy_fallbacks(): array
{
    $fallbacks = blu_pieseauto_taxonomy()['fallbacks'] ?? [];
    return is_array($fallbacks) ? $fallbacks : [];
}

function blu_pieseauto_normalize_text(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = str_replace(
        ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ', 'ł', 'ś', 'ż', 'ź', 'ć', 'ń', 'ó', 'ą', 'ę'],
        ['a', 'a', 'i', 's', 's', 't', 't', 'l', 's', 'z', 'z', 'c', 'n', 'o', 'a', 'e'],
        $text
    );
    $text = preg_replace('/[^a-z0-9\s\/\-]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function blu_pieseauto_score_keyword(string $haystack, string $keyword): int
{
    $keyword = blu_pieseauto_normalize_text($keyword);
    if ($keyword === '') {
        return 0;
    }
    if ($haystack === $keyword) {
        return 1000 + mb_strlen($keyword, 'UTF-8');
    }
    if (preg_match('/(^|\s)' . preg_quote($keyword, '/') . '(\s|$)/u', $haystack)) {
        return 200 + mb_strlen($keyword, 'UTF-8');
    }
    return 0;
}

function blu_pieseauto_subcategory_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }

    $index = [];
    foreach (blu_pieseauto_taxonomy_entries() as $entry) {
        $sub = trim((string)($entry['sub_category'] ?? ''));
        if ($sub !== '') {
            $index[blu_pieseauto_normalize_text($sub)] = $entry;
        }
    }

    return $index;
}

function blu_pieseauto_is_valid_subcategory(string $subCategory): bool
{
    $subCategory = trim($subCategory);
    if ($subCategory === '') {
        return false;
    }

    return isset(blu_pieseauto_subcategory_index()[blu_pieseauto_normalize_text($subCategory)]);
}

/**
 * @return array{main_category:string,sub_category:string,product_category:string,score:int,fallback:bool}
 */
function blu_pieseauto_classify_text(string $text): array
{
    $haystack = blu_pieseauto_normalize_text($text);
    $best = [
        'main_category' => '',
        'sub_category' => '',
        'product_category' => '',
        'score' => 0,
        'fallback' => false,
    ];

    if ($haystack === '') {
        return blu_pieseauto_apply_fallback($best);
    }

    $partNormBest = '';
    foreach (blu_pieseauto_taxonomy_entries() as $entry) {
        $main = trim((string)($entry['main_category'] ?? ''));
        $sub = trim((string)($entry['sub_category'] ?? ''));
        $keywords = is_array($entry['keywords'] ?? null) ? $entry['keywords'] : [];
        if ($main === '' || $sub === '') {
            continue;
        }

        $score = 0;
        $partNorm = blu_pieseauto_normalize_text($sub);
        foreach ($keywords as $keyword) {
            $kwScore = blu_pieseauto_score_keyword($haystack, (string)$keyword);
            if ($kwScore > 0 && blu_pieseauto_normalize_text((string)$keyword) === $partNorm) {
                $kwScore += 150;
            }
            $score = max($score, $kwScore);
        }

        if ($score <= 0) {
            continue;
        }

        if ($score > $best['score'] || ($score === $best['score'] && mb_strlen($partNorm, 'UTF-8') > mb_strlen($partNormBest, 'UTF-8'))) {
            $partNormBest = $partNorm;
            $best = [
                'main_category' => $main,
                'sub_category' => $sub,
                'product_category' => $main . ' -> ' . $sub,
                'score' => $score,
                'fallback' => false,
            ];
        }
    }

    if ($best['score'] > 0) {
        return $best;
    }

    return blu_pieseauto_apply_fallback($best, $text);
}

/**
 * @param array{main_category?:string,sub_category?:string,product_category?:string,score?:int,fallback?:bool} $best
 * @return array{main_category:string,sub_category:string,product_category:string,score:int,fallback:bool}
 */
function blu_pieseauto_apply_fallback(array $best, string $text = ''): array
{
    $fallbacks = blu_pieseauto_taxonomy_fallbacks();
    $mainGuess = '';

    if ($text !== '') {
        $haystack = blu_pieseauto_normalize_text($text);
        foreach ($fallbacks as $main => $fallbackSub) {
            $mainNorm = blu_pieseauto_normalize_text((string)$main);
            if ($mainNorm !== '' && preg_match('/(^|\s)' . preg_quote($mainNorm, '/') . '(\s|$)/u', $haystack)) {
                $mainGuess = (string)$main;
                break;
            }
        }
    }

    $defaultMain = 'Caroserie';
    $defaultSub = 'Alte piese de caroserie';

    if ($mainGuess === '' || !isset($fallbacks[$mainGuess])) {
        $mainGuess = $defaultMain;
    }

    $sub = trim((string)($fallbacks[$mainGuess] ?? $defaultSub));
    if ($sub === '' || !blu_pieseauto_is_valid_subcategory($sub)) {
        $sub = $defaultSub;
        $mainGuess = $defaultMain;
    }

    return [
        'main_category' => $mainGuess,
        'sub_category' => $sub,
        'product_category' => $mainGuess . ' -> ' . $sub,
        'score' => 0,
        'fallback' => true,
    ];
}

function blu_pieseauto_build_search_text(array $row): string
{
    return implode(' ', array_filter([
        (string)($row['title'] ?? ''),
        (string)($row['ad_title'] ?? ''),
        (string)($row['title_original'] ?? ''),
        (string)($row['description'] ?? ''),
        (string)($row['ad_description'] ?? ''),
        (string)($row['descriere'] ?? ''),
        (string)($row['nume'] ?? ''),
        (string)($row['categorie'] ?? ''),
        (string)($row['sub_category'] ?? ''),
        (string)($row['pieseauto_category'] ?? ''),
        (string)($row['product_category'] ?? ''),
        (string)($row['category_name'] ?? ''),
        (string)($row['category_full'] ?? ''),
        (string)($row['main_category'] ?? ''),
        is_array($row['tecdoc_categories'] ?? null) ? implode(' ', $row['tecdoc_categories']) : '',
    ]));
}

/**
 * Clasifică un produs scanat/importat în taxonomia PieseAuto.ro.
 *
 * @return array{main_category:string,sub_category:string,product_category:string,score:int,fallback:bool}
 */
function blu_pieseauto_classify_product(array $row): array
{
    $existingSub = trim((string)($row['pieseauto_category'] ?? $row['sub_category'] ?? ''));
    if ($existingSub !== '' && blu_pieseauto_is_valid_subcategory($existingSub)) {
        $main = trim((string)($row['main_category'] ?? ''));
        if ($main === '') {
            $idx = blu_pieseauto_subcategory_index()[blu_pieseauto_normalize_text($existingSub)] ?? null;
            $main = is_array($idx) ? trim((string)($idx['main_category'] ?? '')) : '';
        }
        if ($main === '') {
            $idx = blu_pieseauto_subcategory_index()[blu_pieseauto_normalize_text($existingSub)] ?? null;
            $main = is_array($idx) ? trim((string)($idx['main_category'] ?? '')) : '';
        }
        if ($main === '') {
            $main = 'Caroserie';
        }

        return [
            'main_category' => $main,
            'sub_category' => $existingSub,
            'product_category' => $main . ' -> ' . $existingSub,
            'score' => 1000,
            'fallback' => false,
        ];
    }

    return blu_pieseauto_classify_text(blu_pieseauto_build_search_text($row));
}

/** Numele subcategoriei folosit de robotul PieseAuto la căutare rapidă. */
function blu_pieseauto_robot_category_name(array $row): string
{
    $meta = blu_pieseauto_classify_product($row);
    $sub = trim((string)($meta['sub_category'] ?? ''));
    if ($sub !== '' && blu_pieseauto_is_valid_subcategory($sub)) {
        return $sub;
    }

    return 'Alte piese de caroserie';
}
