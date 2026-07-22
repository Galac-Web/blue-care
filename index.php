<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';
require_once __DIR__ . '/lib/shop/gbg_structure.php';

$bluCmsPage = 'index';

$shopPageTitle = 'Blue-Car — Piese auto online';
$shopPageDescription = 'Piese auto pentru toate mărcile. Caută după OEM, comandă online sau solicită ofertă.';
$shopBodyClass = 'shop-page shop-page-home shop-ui-v10';

$homeOilPopularProducts = [];
$homeOilRecommendedProducts = [];
foreach (blu_load_products() as $homeProductRow) {
    $homeProductName = mb_strtolower(trim((string) ($homeProductRow['nume'] ?? '')));
    $homeProductName = str_replace(['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'], ['a', 'a', 'i', 's', 't', 's', 't'], $homeProductName);
    if (!preg_match('/filtru ulei|ulei motor|ulei cutie|ulei transmisie|baie ulei|buson.*umplere ulei|umplere ulei|antigel|lichid fr|lichid de frana|lubrifiant|adblue|coolant|ulei hidraulic|drena ulei/i', $homeProductName)) {
        continue;
    }
    if (preg_match('/radiator|amortizor|vas de expansiune|vas expansiune|nivel lichid|presiune ulei|senzor nivel|cuzinet|biela|lagar|disc fran|ambreiaj|racire motor|macara geam|comutator/i', $homeProductName)) {
        continue;
    }
    if (preg_match('/filtru ulei|ulei motor|antigel|lichid fr|lichid de frana|lubrifiant|baie ulei|umplere ulei/i', $homeProductName)) {
        $homeOilPopularProducts[] = $homeProductRow;
    } else {
        $homeOilRecommendedProducts[] = $homeProductRow;
    }
}
$featured = [];
foreach (array_slice($homeOilPopularProducts, 0, 8) as $homePopularProduct) {
    $featured[] = [
        'product' => $homePopularProduct,
        'promoBadge' => 'Produse populare',
        'promoBadgeClass' => 'is-popular',
    ];
}
foreach (array_slice($homeOilRecommendedProducts, 0, max(0, 12 - count($featured))) as $homeRecommendedProduct) {
    $featured[] = [
        'product' => $homeRecommendedProduct,
        'promoBadge' => 'Produse recomandate',
        'promoBadgeClass' => 'is-recommended',
    ];
}
$totalProducts = count(blu_load_products());
$catCount = count(blu_shop_categories());

$brandCounts = [];
$brandCategories = [];
$productsCompact = [];
$rawBrandsById = [];
$productsJsonFile = __DIR__ . '/data/products.json';
if (is_file($productsJsonFile)) {
    $rawProducts = json_decode((string) file_get_contents($productsJsonFile), true);
    if (is_array($rawProducts)) {
        foreach ($rawProducts as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }
            $rawId = (int) ($rawRow['id'] ?? $rawRow['randomn_id'] ?? 0);
            $rawBrand = trim((string) ($rawRow['marca_masina'] ?? ''));
            if ($rawId > 0 && $rawBrand !== '') {
                $rawBrandsById[$rawId] = $rawBrand;
            }
        }
    }
}

foreach (blu_load_products() as $product) {
    $productId = blu_shop_product_id($product);
    $brand = $rawBrandsById[$productId] ?? trim((string) ($product['marca_masina'] ?? ''));
    $cat = trim((string) ($product['categorie'] ?? ''));
    if ($brand !== '') {
        $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
        if ($cat !== '') {
            $brandCategories[$brand][$cat] = ($brandCategories[$brand][$cat] ?? 0) + 1;
        }
    }
    $productsCompact[] = [
        'id' => $productId,
        'name' => trim((string) ($product['nume'] ?? '')),
        'cat' => $cat,
        'brand' => $brand,
        'oem' => trim((string) ($product['cod_oem'] ?? '')),
        'price' => blu_shop_price_label($product),
        'url' => blu_shop_product_url($product),
        'img' => trim((string) ($product['imagine'] ?? '')),
        'inStock' => blu_shop_in_stock($product),
    ];
}
ksort($brandCounts);

$vehicleCatalog = blu_gbg_build_vehicle_catalog();

if ($brandCounts === [] && $vehicleCatalog !== []) {
    foreach (array_keys($vehicleCatalog) as $catalogBrand) {
        $brandCounts[$catalogBrand] = $totalProducts > 0 ? $totalProducts : count($vehicleCatalog[$catalogBrand]);
    }
    ksort($brandCounts);
}

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--home">
    <?php require __DIR__ . '/shop/includes/gbg-finder.php'; ?>

    <?php blu_builder_render_zone('index', 'after_hero'); ?>

    <div class="shop-container">
        <div class="shop-section-head reveal">
            <?php blu_cms_tag('index', 'home_section_title', 'h2', 'Produse recomandate'); ?>
            <a href="catalog.php"><?php blu_cms_tag('index', 'home_section_link', 'span', 'Catalog complet'); ?></a>
        </div>
        <?php if ($featured): ?>
            <div class="shop-grid shop-grid-home-featured">
                <?php foreach ($featured as $featuredItem): ?>
                    <?php
                    $product = is_array($featuredItem['product'] ?? null) ? $featuredItem['product'] : $featuredItem;
                    $shopCardPromoBadge = (string) ($featuredItem['promoBadge'] ?? '');
                    $shopCardPromoBadgeClass = (string) ($featuredItem['promoBadgeClass'] ?? '');
                    require __DIR__ . '/shop/includes/product-card.php';
                    unset($shopCardPromoBadge, $shopCardPromoBadgeClass);
                    ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="shop-empty reveal">
                <i class="fa-solid fa-box-open fa-2x" aria-hidden="true"></i>
                <?php blu_cms_tag('index', 'home_empty_text', 'p', 'Catalogul se populează din panoul de administrare.'); ?>
            </div>
        <?php endif; ?>

        <?php blu_builder_render_zone('index', 'before_footer'); ?>
    </div>
</main>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
