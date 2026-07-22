<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';

const BLU_CATALOG_PAGE_SIZE = 8;

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['categorie'] ?? ''));
$page = max(1, (int) ($_GET['pagina'] ?? 1));
$pretMin = max(0.0, (float) str_replace(',', '.', (string) ($_GET['pret_min'] ?? '0')));
$pretMax = max(0.0, (float) str_replace(',', '.', (string) ($_GET['pret_max'] ?? '0')));
$onlySale = isset($_GET['reducere']) && (string) $_GET['reducere'] === '1';
$onlyStock = isset($_GET['stoc']) && (string) $_GET['stoc'] === '1';
$sort = trim((string) ($_GET['sort'] ?? ''));

/** @return array{min: float, max: float, sale: float} */
function blu_catalog_price_bounds(): array
{
    static $bounds = null;
    if ($bounds !== null) {
        return $bounds;
    }

    $prices = [];
    foreach (blu_load_products() as $product) {
        $value = blu_shop_price_value($product);
        if ($value > 0) {
            $prices[] = $value;
        }
    }

    sort($prices);
    $count = count($prices);
    $bounds = [
        'min' => $count > 0 ? $prices[0] : 0.0,
        'max' => $count > 0 ? $prices[$count - 1] : 0.0,
        'sale' => $count > 0 ? $prices[(int) floor($count * 0.25)] : 0.0,
    ];

    return $bounds;
}

$priceBounds = blu_catalog_price_bounds();
$catalogFilters = [
    'q' => $q,
    'categorie' => $category,
    'pret_min' => $pretMin,
    'pret_max' => $pretMax,
    'reducere' => $onlySale,
    'stoc' => $onlyStock,
    'sort' => $sort,
];

$allProducts = blu_shop_filter_products($q, $category !== '' ? $category : null);

if ($pretMin > 0 || $pretMax > 0) {
    $allProducts = array_values(array_filter($allProducts, static function (array $product) use ($pretMin, $pretMax): bool {
        $price = blu_shop_price_value($product);
        if ($pretMin > 0 && $price < $pretMin) {
            return false;
        }
        if ($pretMax > 0 && $price > $pretMax) {
            return false;
        }

        return true;
    }));
}

if ($onlySale) {
    $saleCap = $priceBounds['sale'];
    $allProducts = array_values(array_filter($allProducts, static function (array $product) use ($saleCap): bool {
        $price = blu_shop_price_value($product);

        return $price > 0 && $price <= $saleCap;
    }));
}

if ($onlyStock) {
    $allProducts = array_values(array_filter($allProducts, 'blu_shop_in_stock'));
}

if ($sort === 'pret_asc') {
    usort($allProducts, static fn (array $a, array $b): int => blu_shop_price_value($a) <=> blu_shop_price_value($b));
} elseif ($sort === 'pret_desc') {
    usort($allProducts, static fn (array $a, array $b): int => blu_shop_price_value($b) <=> blu_shop_price_value($a));
} elseif ($sort === 'nume') {
    usort($allProducts, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['nume'] ?? ''), (string) ($b['nume'] ?? ''));
    });
}
$totalProducts = count($allProducts);
$totalPages = max(1, (int) ceil($totalProducts / BLU_CATALOG_PAGE_SIZE));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * BLU_CATALOG_PAGE_SIZE;
$products = array_slice($allProducts, $offset, BLU_CATALOG_PAGE_SIZE);
$categories = blu_shop_categories();
$catCount = count($categories);
$catalogTotalAll = count(blu_load_products());

function blu_catalog_page_url(int $targetPage, array $filters): string
{
    $params = [];
    if (($filters['categorie'] ?? '') !== '') {
        $params['categorie'] = $filters['categorie'];
    }
    if (($filters['q'] ?? '') !== '') {
        $params['q'] = $filters['q'];
    }
    if (($filters['pret_min'] ?? 0) > 0) {
        $params['pret_min'] = (string) $filters['pret_min'];
    }
    if (($filters['pret_max'] ?? 0) > 0) {
        $params['pret_max'] = (string) $filters['pret_max'];
    }
    if (!empty($filters['reducere'])) {
        $params['reducere'] = '1';
    }
    if (!empty($filters['stoc'])) {
        $params['stoc'] = '1';
    }
    if (($filters['sort'] ?? '') !== '') {
        $params['sort'] = $filters['sort'];
    }
    if ($targetPage > 1) {
        $params['pagina'] = (string) $targetPage;
    }
    $query = http_build_query($params);

    return 'catalog.php' . ($query !== '' ? '?' . $query : '');
}

$hasActiveFilters = $pretMin > 0 || $pretMax > 0 || $onlySale || $onlyStock || $sort !== '';
$rangeMinValue = $pretMin > 0 ? $pretMin : $priceBounds['min'];
$rangeMaxValue = $pretMax > 0 ? $pretMax : $priceBounds['max'];
if ($rangeMaxValue < $rangeMinValue) {
    $rangeMaxValue = $rangeMinValue;
}

/** @return list<int|string> */
function blu_catalog_page_list(int $current, int $total): array
{
    if ($total <= 1) {
        return [1];
    }
    if ($total <= 7) {
        $all = [];
        for ($i = 1; $i <= $total; $i++) {
            $all[] = $i;
        }

        return $all;
    }

    $pages = [1];

    if ($current <= 4) {
        for ($i = 2; $i <= min(4, $total - 1); $i++) {
            $pages[] = $i;
        }
        if ($total > 5) {
            $pages[] = 'ellipsis';
        }
        $pages[] = $total;

        return $pages;
    }

    if ($current >= $total - 3) {
        $pages[] = 'ellipsis';
        for ($i = max(2, $total - 4); $i <= $total; $i++) {
            $pages[] = $i;
        }

        return array_values(array_unique($pages, SORT_REGULAR));
    }

    $pages[] = 'ellipsis';
    for ($i = $current - 1; $i <= $current + 1; $i++) {
        $pages[] = $i;
    }
    $pages[] = 'ellipsis';
    $pages[] = $total;

    return $pages;
}

$shopPageTitle = 'Catalog piese auto — Blue-Car';
$shopPageDescription = 'Răsfoiește catalogul Blue-Car: filtrează după categorie sau caută după OEM.';
$shopBodyClass = 'shop-page shop-page-catalog shop-ui-v10';

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--catalog shop-main--woo">
    <div class="shop-container">
        <nav class="shop-breadcrumb" aria-label="Breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Acasă</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span>Catalog</span>
        </nav>

        <header class="shop-catalog-intro">
            <h1><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Catalog piese auto</h1>
            <p><?= (int) $totalProducts ?> produse<?= $category !== '' ? ' · ' . blu_shop_h($category) : '' ?><?= $q !== '' ? ' · căutare: ' . blu_shop_h($q) : '' ?> — <strong><?= BLU_CATALOG_PAGE_SIZE ?> pe pagină</strong></p>
        </header>

        <div class="shop-layout">
            <aside class="shop-sidebar">
                <h3><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtre &amp; categorii</h3>

                <form class="shop-catalog-filter-form" method="get" action="catalog.php">
                    <?php if ($q !== ''): ?>
                        <input type="hidden" name="q" value="<?= blu_shop_h($q) ?>">
                    <?php endif; ?>
                    <?php if ($category !== ''): ?>
                        <input type="hidden" name="categorie" value="<?= blu_shop_h($category) ?>">
                    <?php endif; ?>

                    <fieldset class="shop-filter-group">
                        <legend><i class="fa-solid fa-tags" aria-hidden="true"></i> Preț (lei)</legend>
                        <div class="shop-filter-range-sliders">
                            <input type="range" class="shop-filter-range" id="catalog-pret-min-range" min="<?= (int) floor($priceBounds['min']) ?>" max="<?= (int) ceil($priceBounds['max']) ?>" step="1" value="<?= (int) floor($rangeMinValue) ?>" aria-label="Preț minim">
                            <input type="range" class="shop-filter-range" id="catalog-pret-max-range" min="<?= (int) floor($priceBounds['min']) ?>" max="<?= (int) ceil($priceBounds['max']) ?>" step="1" value="<?= (int) ceil($rangeMaxValue) ?>" aria-label="Preț maxim">
                        </div>
                        <div class="shop-filter-range-inputs">
                            <label class="shop-filter-range-field">
                                <span>Min</span>
                                <input type="number" name="pret_min" id="catalog-pret-min" min="0" max="<?= (int) ceil($priceBounds['max']) ?>" step="1" value="<?= $pretMin > 0 ? blu_shop_h((string) (int) floor($pretMin)) : '' ?>" placeholder="<?= (int) floor($priceBounds['min']) ?>">
                            </label>
                            <span class="shop-filter-range-sep" aria-hidden="true">—</span>
                            <label class="shop-filter-range-field">
                                <span>Max</span>
                                <input type="number" name="pret_max" id="catalog-pret-max" min="0" max="<?= (int) ceil($priceBounds['max']) ?>" step="1" value="<?= $pretMax > 0 ? blu_shop_h((string) (int) ceil($pretMax)) : '' ?>" placeholder="<?= (int) ceil($priceBounds['max']) ?>">
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="shop-filter-group">
                        <legend><i class="fa-solid fa-sliders" aria-hidden="true"></i> Opțiuni</legend>
                        <label class="shop-filter-check">
                            <input type="checkbox" name="reducere" value="1"<?= $onlySale ? ' checked' : '' ?>>
                            <span><i class="fa-solid fa-percent" aria-hidden="true"></i> Doar la reducere</span>
                        </label>
                        <label class="shop-filter-check">
                            <input type="checkbox" name="stoc" value="1"<?= $onlyStock ? ' checked' : '' ?>>
                            <span><i class="fa-solid fa-box-open" aria-hidden="true"></i> Doar în stoc</span>
                        </label>
                    </fieldset>

                    <fieldset class="shop-filter-group">
                        <legend><i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i> Sortare</legend>
                        <label class="shop-filter-select-wrap">
                            <span class="visually-hidden">Sortare produse</span>
                            <select name="sort" class="shop-filter-select">
                                <option value=""<?= $sort === '' ? ' selected' : '' ?>>Relevanță</option>
                                <option value="pret_asc"<?= $sort === 'pret_asc' ? ' selected' : '' ?>>Preț crescător</option>
                                <option value="pret_desc"<?= $sort === 'pret_desc' ? ' selected' : '' ?>>Preț descrescător</option>
                                <option value="nume"<?= $sort === 'nume' ? ' selected' : '' ?>>Nume A–Z</option>
                            </select>
                        </label>
                    </fieldset>

                    <div class="shop-filter-actions">
                        <button type="submit" class="shop-btn shop-btn-accent shop-btn-sm-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Aplică</button>
                        <?php if ($hasActiveFilters): ?>
                            <a class="shop-btn shop-btn-ghost shop-btn-sm-ghost" href="<?= blu_shop_h(blu_catalog_page_url(1, ['q' => $q, 'categorie' => $category])) ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Resetează</a>
                        <?php endif; ?>
                    </div>
                </form>

                <h4 class="shop-sidebar-subtitle"><i class="fa-solid fa-table-cells" aria-hidden="true"></i> Categorii</h4>
                <ul class="shop-cat-list shop-pill-tablist" role="tablist" aria-label="Categorii catalog">
                    <li>
                        <a href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['categorie' => '']))) ?>" class="<?= $category === '' ? 'is-active' : '' ?>">
                            <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                            <span>Toate</span>
                            <em><?= (int) $catalogTotalAll ?></em>
                        </a>
                    </li>
                    <?php foreach ($categories as $catName => $count): ?>
                        <li>
                            <a href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['categorie' => $catName]))) ?>" class="<?= $category === $catName ? 'is-active' : '' ?>">
                                <i class="<?= blu_shop_h(blu_shop_category_icon($catName)) ?>" aria-hidden="true"></i>
                                <span><?= blu_shop_h($catName) ?></span>
                                <em><?= (int) $count ?></em>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <div class="shop-catalog-main">
                <div class="shop-catalog-toolbar">
                    <p class="shop-catalog-result">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        Pagina <strong><?= (int) $page ?></strong> din <strong><?= (int) $totalPages ?></strong>
                        <?php if ($totalProducts > 0): ?>
                            · afișez <strong><?= $offset + 1 ?>–<?= min($offset + BLU_CATALOG_PAGE_SIZE, $totalProducts) ?></strong> din <?= (int) $totalProducts ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($category !== '' || $q !== '' || $hasActiveFilters): ?>
                        <div class="shop-catalog-filters">
                            <?php if ($category !== ''): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['categorie' => '']))) ?>">
                                    <i class="<?= blu_shop_h(blu_shop_category_icon($category)) ?>" aria-hidden="true"></i>
                                    <?= blu_shop_h($category) ?>
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($q !== ''): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['q' => '']))) ?>">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                    <?= blu_shop_h($q) ?>
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($pretMin > 0 || $pretMax > 0): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['pret_min' => 0, 'pret_max' => 0]))) ?>">
                                    <i class="fa-solid fa-tags" aria-hidden="true"></i>
                                    <?= $pretMin > 0 && $pretMax > 0 ? blu_shop_h((string) (int) floor($pretMin) . '–' . (int) ceil($pretMax) . ' lei') : ($pretMin > 0 ? '≥ ' . (int) floor($pretMin) . ' lei' : '≤ ' . (int) ceil($pretMax) . ' lei') ?>
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($onlySale): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['reducere' => false]))) ?>">
                                    <i class="fa-solid fa-percent" aria-hidden="true"></i> La reducere
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($onlyStock): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['stoc' => false]))) ?>">
                                    <i class="fa-solid fa-box-open" aria-hidden="true"></i> În stoc
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($sort !== ''): ?>
                                <a class="shop-catalog-tag" href="<?= blu_shop_h(blu_catalog_page_url(1, array_merge($catalogFilters, ['sort' => '']))) ?>">
                                    <i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i>
                                    <?php
                                    $sortLabels = ['pret_asc' => 'Preț ↑', 'pret_desc' => 'Preț ↓', 'nume' => 'Nume A–Z'];
                                    echo blu_shop_h($sortLabels[$sort] ?? $sort);
                                    ?>
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($products): ?>
                    <div class="shop-grid shop-catalog-grid">
                        <?php
                        $shopCardPlain = true;
                        foreach ($products as $product):
                            require __DIR__ . '/shop/includes/product-card.php';
                        endforeach;
                        unset($shopCardPlain);
                        ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="shop-vehicle-pagination shop-catalog-pagination" aria-label="Paginare catalog">
                            <?php if ($page <= 1): ?>
                                <span class="shop-vehicle-page-btn shop-vehicle-page-btn--nav is-disabled" aria-disabled="true" aria-label="Pagina anterioară">
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                </span>
                            <?php else: ?>
                                <a class="shop-vehicle-page-btn shop-vehicle-page-btn--nav" href="<?= blu_shop_h(blu_catalog_page_url($page - 1, $catalogFilters)) ?>" aria-label="Pagina anterioară">
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <?php foreach (blu_catalog_page_list($page, $totalPages) as $entry): ?>
                                <?php if ($entry === 'ellipsis'): ?>
                                    <span class="shop-vehicle-page-ellipsis" aria-hidden="true">…</span>
                                <?php elseif ((int) $entry === $page): ?>
                                    <span class="shop-vehicle-page-btn is-active" aria-current="page" aria-label="Pagina <?= (int) $entry ?>"><?= (int) $entry ?></span>
                                <?php else: ?>
                                    <a class="shop-vehicle-page-btn" href="<?= blu_shop_h(blu_catalog_page_url((int) $entry, $catalogFilters)) ?>" aria-label="Pagina <?= (int) $entry ?>"><?= (int) $entry ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($page >= $totalPages): ?>
                                <span class="shop-vehicle-page-btn shop-vehicle-page-btn--nav is-disabled" aria-disabled="true" aria-label="Pagina următoare">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                </span>
                            <?php else: ?>
                                <a class="shop-vehicle-page-btn shop-vehicle-page-btn--nav" href="<?= blu_shop_h(blu_catalog_page_url($page + 1, $catalogFilters)) ?>" aria-label="Pagina următoare">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="shop-empty">
                        <i class="fa-solid fa-magnifying-glass fa-2x" aria-hidden="true"></i>
                        <p>Nu am găsit produse pentru criteriile selectate.</p>
                        <a class="shop-btn shop-btn-accent" href="catalog.php"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Resetează filtrele</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var minRange = document.getElementById('catalog-pret-min-range');
    var maxRange = document.getElementById('catalog-pret-max-range');
    var minInput = document.getElementById('catalog-pret-min');
    var maxInput = document.getElementById('catalog-pret-max');
    if (!minRange || !maxRange || !minInput || !maxInput) {
        return;
    }

    function clampRanges() {
        var minVal = parseInt(minRange.value, 10);
        var maxVal = parseInt(maxRange.value, 10);
        if (minVal > maxVal) {
            if (document.activeElement === minRange || document.activeElement === minInput) {
                maxVal = minVal;
                maxRange.value = String(maxVal);
            } else {
                minVal = maxVal;
                minRange.value = String(minVal);
            }
        }
        minInput.value = minVal > parseInt(minRange.min, 10) ? String(minVal) : '';
        maxInput.value = maxVal < parseInt(maxRange.max, 10) ? String(maxVal) : '';
    }

    minRange.addEventListener('input', clampRanges);
    maxRange.addEventListener('input', clampRanges);
    minInput.addEventListener('input', function () {
        var val = parseInt(minInput.value, 10);
        if (!isNaN(val)) {
            minRange.value = String(val);
        }
        clampRanges();
    });
    maxInput.addEventListener('input', function () {
        var val = parseInt(maxInput.value, 10);
        if (!isNaN(val)) {
            maxRange.value = String(val);
        }
        clampRanges();
    });
})();
</script>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
