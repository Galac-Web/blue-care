<?php
declare(strict_types=1);

/** @var array<string, list<array{id:string,label:string,years:string}>> $vehicleCatalog */
/** @var array<string, array<string, int>> $brandCategories */
/** @var list<array{id:int,name:string,cat:string,brand:string,oem:string,price:string,url:string,img:string,inStock:bool}> $productsCompact */
/** @var list<array{id:string,name:string,slug:string}> $gbgBrands */

require_once dirname(__DIR__, 2) . '/lib/shop/gbg_finder.php';
require_once dirname(__DIR__, 2) . '/lib/shop/gbg_structure.php';

$gbgBrands = blu_gbg_static_brands();
$gbgMainCategories = blu_gbg_main_categories();
$gbgSpecialCategories = blu_gbg_special_categories();
$gbgModelGroups = blu_gbg_structure_model_groups();
$gbgMainCategoriesByModel = blu_gbg_structure_main_categories_by_model();
?>
<div id="middleFrame" class="blu-middle-frame">
    <input type="hidden" id="gbgSelectedBrand" value="">
    <input type="hidden" id="gbgSelectedModel" value="">
    <input type="hidden" id="gbgSelectedMainCat" value="">
    <input type="hidden" id="gbgSelectedSpecialCat" value="">

    <div id="searchMenu" class="blu-gbg-search-menu" role="toolbar" aria-label="Căutare piese după vehicul">
        <div class="blu-gbg-search-row">
            <button type="button" class="blu-gbg-search-cell is-active" data-gbg-panel="allform" id="gbgCellBrand">
                <span class="blu-gbg-search-cell__head">Marcă</span>
                <span class="blu-gbg-search-cell__value" id="gbgLabelBrand">Selectează marca</span>
                <i class="fa-solid fa-chevron-down blu-gbg-search-cell__arrow" aria-hidden="true"></i>
            </button>
            <button type="button" class="blu-gbg-search-cell" data-gbg-panel="model" id="gbgCellModel">
                <span class="blu-gbg-search-cell__head">Model</span>
                <span class="blu-gbg-search-cell__value" id="gbgLabelModel">Selectează modelul</span>
                <i class="fa-solid fa-chevron-down blu-gbg-search-cell__arrow" aria-hidden="true"></i>
            </button>
            <button type="button" class="blu-gbg-search-cell" data-gbg-panel="form1" id="gbgCellMainCat">
                <span class="blu-gbg-search-cell__head">Categorii principale</span>
                <span class="blu-gbg-search-cell__value" id="gbgLabelMainCat">Selectează categoria</span>
                <i class="fa-solid fa-chevron-down blu-gbg-search-cell__arrow" aria-hidden="true"></i>
            </button>
            <button type="button" class="blu-gbg-search-cell" data-gbg-panel="formint" id="gbgCellSpecialCat">
                <span class="blu-gbg-search-cell__head">Categorii speciale</span>
                <span class="blu-gbg-search-cell__value" id="gbgLabelSpecialCat">Selectează categoria</span>
                <i class="fa-solid fa-chevron-down blu-gbg-search-cell__arrow" aria-hidden="true"></i>
            </button>
            <div class="blu-gbg-search-cell blu-gbg-search-cell--static">
                <span class="blu-gbg-search-cell__head">Catalog</span>
                <span class="blu-gbg-search-cell__value blu-gbg-search-cell__value--strong"><?= (int) count($productsCompact) ?> piese</span>
            </div>
        </div>
    </div>

    <div id="divNewCenter" class="blu-gbg-center">
        <div id="divAllform" class="blu-gbg-panel blu-gbg-panel--allform" data-gbg-panel="allform">
            <div class="blu-gbg-brands-wrap">
                <table class="blu-gbg-brands-table" role="presentation">
                    <tbody>
                        <?php
                        $cols = 11;
                        $chunks = array_chunk($gbgBrands, $cols);
                        foreach ($chunks as $rowBrands):
                        ?>
                        <tr>
                            <?php foreach ($rowBrands as $brand):
                                $logo = blu_gbg_brand_logo_url($brand['slug'], $brand['name']);
                            ?>
                            <td>
                                <button type="button" class="blu-gbg-brand-link" data-brand="<?= blu_shop_h($brand['name']) ?>" data-brand-id="<?= blu_shop_h($brand['id']) ?>">
                                    <?php if ($logo !== ''): ?>
                                        <img src="<?= blu_shop_h($logo) ?>" alt="<?= blu_shop_h($brand['name']) ?>" class="blu-gbg-brand-logo" loading="lazy" width="72" height="40">
                                    <?php else: ?>
                                        <span class="blu-gbg-brand-logo blu-gbg-brand-logo--fallback" aria-hidden="true"><?= blu_shop_h(blu_gbg_brand_initials($brand['name'])) ?></span>
                                    <?php endif; ?>
                                    <span class="blu-gbg-brand-name"><?= blu_shop_h($brand['name']) ?></span>
                                </button>
                            </td>
                            <?php endforeach; ?>
                            <?php for ($i = count($rowBrands); $i < $cols; $i++): ?>
                            <td class="blu-gbg-brands-table__empty"></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="blu-gbg-special-head">Categorii speciale</div>
            <div class="blu-gbg-special-grid blu-gbg-special-grid--home">
                <?php foreach ($gbgSpecialCategories as $spec): ?>
                <button type="button" class="blu-gbg-special-tile" data-special-cat="<?= blu_shop_h($spec['id']) ?>" data-special-query="<?= blu_shop_h($spec['query']) ?>" data-special-label="<?= blu_shop_h($spec['label']) ?>">
                    <img src="<?= blu_shop_h(blu_gbg_special_category_image_url((string) $spec['img'])) ?>" alt="<?= blu_shop_h($spec['label']) ?>" loading="lazy">
                    <span><?= blu_shop_h($spec['label']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="divModel" class="blu-gbg-panel blu-gbg-panel--model is-hidden" data-gbg-panel="model" hidden>
            <p class="blu-gbg-panel-hint is-hidden" id="gbgModelHint" hidden></p>
            <div id="gbgModelList" class="blu-gbg-model-list"></div>
        </div>

        <div id="divForm1" class="blu-gbg-panel blu-gbg-panel--form1 is-hidden" data-gbg-panel="form1" hidden>
            <p class="blu-gbg-panel-hint" id="gbgMainCatHint">Selectează mai întâi modelul, apoi categoria principală.</p>
            <div id="gbgMainCatList" class="blu-gbg-form1-dynamic"></div>
            <div id="gbgMainCatFallback" class="blu-gbg-cat-grid blu-gbg-cat-grid--fallback">
                <?php foreach ($gbgMainCategories as $cat): ?>
                <button type="button" class="blu-gbg-cat-tile" data-main-cat="<?= blu_shop_h($cat['id']) ?>" data-main-label="<?= blu_shop_h($cat['label']) ?>">
                    <i class="<?= blu_shop_h($cat['icon']) ?>" aria-hidden="true"></i>
                    <span><?= blu_shop_h($cat['label']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="divFormint" class="blu-gbg-panel blu-gbg-panel--formint is-hidden" data-gbg-panel="formint" hidden>
            <div class="blu-gbg-special-head">Categorii speciale</div>
            <div class="blu-gbg-special-grid">
                <?php foreach ($gbgSpecialCategories as $spec): ?>
                <button type="button" class="blu-gbg-special-tile" data-special-cat="<?= blu_shop_h($spec['id']) ?>" data-special-query="<?= blu_shop_h($spec['query']) ?>" data-special-label="<?= blu_shop_h($spec['label']) ?>">
                    <img src="<?= blu_shop_h(blu_gbg_special_category_image_url((string) $spec['img'])) ?>" alt="<?= blu_shop_h($spec['label']) ?>" loading="lazy">
                    <span><?= blu_shop_h($spec['label']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="divSearchInfo" class="blu-gbg-search-info is-hidden" hidden></div>

        <div id="divGrid" class="blu-gbg-grid-wrap is-hidden" hidden>
            <div class="blu-gbg-grid-head">
                <button type="button" class="blu-model-flow__back" id="gbgProductsBack" hidden>
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Înapoi la variante
                </button>
                <div class="blu-gbg-grid-head__main">
                    <h2>Produse găsite</h2>
                    <span id="gbgResultCount" class="blu-gbg-result-count"></span>
                </div>
            </div>
            <div class="shop-table-wrap">
                <table class="shop-product-table" id="gbg-products-table">
                    <thead>
                        <tr>
                            <th scope="col">Produs</th>
                            <th scope="col">Categorie</th>
                            <th scope="col">OEM</th>
                            <th scope="col">Preț</th>
                            <th scope="col" class="shop-product-col-cart">Coș</th>
                        </tr>
                    </thead>
                    <tbody id="gbg-products-body"></tbody>
                </table>
            </div>
            <nav class="shop-vehicle-pagination is-hidden" id="gbg-pagination" aria-label="Paginare produse" hidden></nav>
            <p class="shop-vehicle-empty is-hidden" id="gbg-empty" hidden>Nu am găsit produse pentru selecția curentă. <a href="catalog.php">Vezi tot catalogul</a></p>
        </div>
    </div>
</div>

<script type="application/json" id="shop-vehicle-data"><?= json_encode([
    'catalog' => $vehicleCatalog ?? [],
    'modelGroups' => $gbgModelGroups ?? [],
    'mainCategoriesByModel' => $gbgMainCategoriesByModel ?? [],
    'brandCategories' => $brandCategories ?? [],
    'products' => $productsCompact ?? [],
    'mainCategories' => $gbgMainCategories,
    'specialCategories' => $gbgSpecialCategories,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
