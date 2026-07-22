<?php
declare(strict_types=1);

require_once __DIR__ . '/../catalog_import.php';

function blu_gbg_structure_brand_slug(string $name): string
{
    $slug = mb_strtolower(trim($name));
    $slug = str_replace(['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', 'ë', 'ö', 'ü'], ['a', 'a', 'i', 's', 't', 's', 't', 'e', 'o', 'u'], $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'brand';
}

/** @return array<string, array{label:string,query:string}> */
function blu_gbg_special_category_ro_map(): array
{
    return [
        '300' => ['label' => 'Sisteme răcire', 'query' => 'racire'],
        '600' => ['label' => 'Tuning', 'query' => 'tuning'],
        '400' => ['label' => 'Amortizoare', 'query' => 'amortizor'],
        '700' => ['label' => 'Rezervoare apă', 'query' => 'rezervor'],
        '800' => ['label' => 'Contacte', 'query' => 'contact'],
        '200' => ['label' => 'Lămpi', 'query' => 'lampa'],
        '900' => ['label' => 'Kit montaj', 'query' => 'kit'],
        '100' => ['label' => 'Lanțuri', 'query' => 'lant'],
        '910' => ['label' => 'Cutii filtru aer', 'query' => 'filtru aer'],
        '920' => ['label' => 'Oprire ușă', 'query' => 'usa'],
        '930' => ['label' => 'Ghidaje ușă culisantă', 'query' => 'ghidaj'],
    ];
}

function blu_gbg_structure_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'gbg_structure.json';
}

/** @return array<string, mixed> */
function blu_gbg_load_structure(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $file = blu_gbg_structure_file();
    if (!is_file($file)) {
        $cache = [];
        return $cache;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    $cache = is_array($raw) ? $raw : [];
    return $cache;
}

/** @param array<string, mixed> $structure */
function blu_gbg_save_structure(array $structure): bool
{
    $structure['version'] = (int) ($structure['version'] ?? 1);
    $structure['updated_at'] = date('c');
    $dir = blu_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents(blu_gbg_structure_file(), $json) !== false;
}

/** @return list<array{id:string,name:string,slug:string,logo_src?:string}> */
function blu_gbg_structure_brands(): array
{
    $structure = blu_gbg_load_structure();
    $brands = $structure['brands'] ?? [];
    if (!is_array($brands) || $brands === []) {
        require_once __DIR__ . '/gbg_finder.php';
        return blu_gbg_car_brands_static_list();
    }

    $out = [];
    foreach ($brands as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = str_pad((string) count($out), 3, '0', STR_PAD_LEFT);
        }
        $item = [
            'id' => $id,
            'name' => $name,
            'slug' => blu_gbg_structure_brand_slug($name),
        ];
        $logoSrc = trim((string) ($row['logo_src'] ?? ''));
        if ($logoSrc !== '') {
            $item['logo_src'] = $logoSrc;
        }
        $out[] = $item;
    }

    return $out !== [] ? $out : (function () {
        require_once __DIR__ . '/gbg_finder.php';
        return blu_gbg_car_brands_static_list();
    })();
}

/** @return list<array{id:string,label:string,img:string,query:string,label_gbg?:string}> */
function blu_gbg_structure_special_categories(): array
{
    $structure = blu_gbg_load_structure();
    $scraped = $structure['special_categories'] ?? [];
    $roMap = blu_gbg_special_category_ro_map();
    if (!is_array($scraped) || $scraped === []) {
        require_once __DIR__ . '/gbg_finder.php';
        return blu_gbg_special_categories_static_fallback();
    }

    $out = [];
    foreach ($scraped as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $labelGbg = trim((string) ($row['label_gbg'] ?? $row['label'] ?? ''));
        $ro = $roMap[$id] ?? null;
        $out[] = [
            'id' => $id,
            'label' => $ro['label'] ?? ($labelGbg !== '' ? $labelGbg : $id),
            'img' => $id,
            'query' => $ro['query'] ?? blu_gbg_structure_brand_slug($labelGbg),
            'label_gbg' => $labelGbg,
        ];
    }

    return $out !== [] ? $out : (function () {
        require_once __DIR__ . '/gbg_finder.php';
        return blu_gbg_special_categories_static_fallback();
    })();
}

/**
 * @return array<string, list<array{group:string,group_id?:string,models:list<array{id:string,label:string,years:string,img?:string}>}>>
 */
function blu_gbg_structure_model_groups(): array
{
    $structure = blu_gbg_load_structure();
    $groups = $structure['model_groups'] ?? [];
    if (!is_array($groups)) {
        $groups = [];
    }
    $seed = blu_gbg_models_seed();
    foreach ($seed as $brand => $brandGroups) {
        if (empty($groups[$brand]) && is_array($brandGroups) && $brandGroups !== []) {
            $groups[$brand] = $brandGroups;
        }
    }
    return $groups;
}

/** @return array<string, list<array{group:string,group_id?:string,models:list<array{id:string,label:string,years:string,img?:string}>}>> */
function blu_gbg_models_seed(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $file = blu_data_dir() . DIRECTORY_SEPARATOR . 'gbg_models_seed.json';
    $raw = blu_read_json_file($file, []);
    $cache = is_array($raw) ? $raw : [];
    return $cache;
}

/** @return list<array{group:string,group_id?:string,models:list<array{id:string,label:string,years:string,img?:string}>}> */
function blu_gbg_models_for_brand(string $brand): array
{
    $brand = trim($brand);
    if ($brand === '') {
        return [];
    }
    $all = blu_gbg_structure_model_groups();
    if (!empty($all[$brand]) && is_array($all[$brand])) {
        return array_values($all[$brand]);
    }
    $seed = blu_gbg_models_seed();
    return is_array($seed[$brand] ?? null) ? array_values($seed[$brand]) : [];
}

/**
 * Catalog flat pentru JS — din structură + catalog_auto.json (piese scanate).
 *
 * @return array<string, list<array{id:string,label:string,years:string}>>
 */
function blu_gbg_build_vehicle_catalog(): array
{
    $catalog = [];
    $groups = blu_gbg_structure_model_groups();

    foreach ($groups as $brand => $brandGroups) {
        if (!is_array($brandGroups)) {
            continue;
        }
        $brandKey = trim((string) $brand);
        $catalog[$brandKey] = [];
        foreach ($brandGroups as $groupRow) {
            if (!is_array($groupRow)) {
                continue;
            }
            foreach (($groupRow['models'] ?? []) as $modelRow) {
                if (!is_array($modelRow)) {
                    continue;
                }
                $id = trim((string) ($modelRow['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $catalog[$brandKey][] = [
                    'id' => $id,
                    'label' => trim((string) ($modelRow['label'] ?? $id)),
                    'years' => trim((string) ($modelRow['years'] ?? '')),
                    'group' => trim((string) ($groupRow['group'] ?? '')),
                    'img' => trim((string) ($modelRow['img'] ?? '')),
                ];
            }
        }
    }

    $catalogFile = blu_project_root() . DIRECTORY_SEPARATOR . 'robot' . DIRECTORY_SEPARATOR . 'catalog_auto.json';
    if (is_file($catalogFile)) {
        $raw = json_decode((string) file_get_contents($catalogFile), true);
        if (is_array($raw)) {
            foreach ($raw as $brandName => $models) {
                if (!is_array($models)) {
                    continue;
                }
                $brandKey = trim((string) $brandName);
                if ($brandKey === '') {
                    continue;
                }
                if (!isset($catalog[$brandKey])) {
                    $catalog[$brandKey] = [];
                }
                $existingIds = [];
                foreach ($catalog[$brandKey] as $existing) {
                    $existingIds[(string) ($existing['id'] ?? '')] = true;
                }
                foreach (array_keys($models) as $modelKey) {
                    $modelId = (string) $modelKey;
                    if (isset($existingIds[$modelId])) {
                        continue;
                    }
                    $years = '';
                    if (preg_match('/(\d{4})\s*-\s*(\d{4})/', $modelId, $yearMatch)) {
                        $years = $yearMatch[1] . '–' . $yearMatch[2];
                    }
                    $short = preg_replace('/^' . preg_quote($brandKey, '/') . '\s*/iu', '', $modelId);
                    $catalog[$brandKey][] = [
                        'id' => $modelId,
                        'label' => trim($short) !== '' ? trim($short) : $modelId,
                        'years' => $years,
                        'group' => '',
                    ];
                }
            }
        }
    }

    return $catalog;
}

/** @return array<string, list<array{group_id:string,group:string,items:list<array{id:string,label:string}>}>> */
function blu_gbg_structure_main_categories_by_model(): array
{
    $structure = blu_gbg_load_structure();
    $rows = $structure['main_categories_by_model'] ?? [];
    return is_array($rows) ? $rows : [];
}

/** @param array<string, mixed> $incoming */
function blu_gbg_merge_structure(array $incoming): array
{
    $current = blu_gbg_load_structure();
    if ($current === []) {
        $current = [
            'version' => 1,
            'brands' => [],
            'special_categories' => [],
            'model_groups' => [],
            'main_categories_by_model' => [],
        ];
    }

    if (!empty($incoming['brands']) && is_array($incoming['brands'])) {
        $current['brands'] = $incoming['brands'];
    }
    if (!empty($incoming['special_categories']) && is_array($incoming['special_categories'])) {
        $current['special_categories'] = $incoming['special_categories'];
    }

    if (!empty($incoming['model_groups']) && is_array($incoming['model_groups'])) {
        $merged = is_array($current['model_groups'] ?? null) ? $current['model_groups'] : [];
        foreach ($incoming['model_groups'] as $brand => $groups) {
            if (is_array($groups) && $groups !== []) {
                $merged[(string) $brand] = $groups;
            }
        }
        $current['model_groups'] = $merged;
    }

    if (!empty($incoming['main_categories_by_model']) && is_array($incoming['main_categories_by_model'])) {
        $merged = is_array($current['main_categories_by_model'] ?? null) ? $current['main_categories_by_model'] : [];
        foreach ($incoming['main_categories_by_model'] as $modelId => $cats) {
            if (is_array($cats) && $cats !== []) {
                $merged[(string) $modelId] = $cats;
            }
        }
        $current['main_categories_by_model'] = $merged;
    }

    if (!empty($incoming['cont_id'])) {
        $current['last_cont_id'] = (string) $incoming['cont_id'];
    }

    blu_gbg_save_structure($current);
    return $current;
}
