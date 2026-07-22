<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';
require_once __DIR__ . '/lib/shop/cart.php';
require_once __DIR__ . '/lib/tecdoc_product_enrich.php';

$id = (int) ($_GET['id'] ?? 0);
$product = blu_shop_find_product($id);

if ($product === null) {
    http_response_code(404);
    $shopPageTitle = 'Produs negăsit — Blue-Car';
    $shopBodyClass = 'shop-page shop-page-product';
    require __DIR__ . '/shop/includes/head.php';
    require __DIR__ . '/shop/includes/header.php';
    echo '<main class="shop-main shop-main--woo"><div class="shop-container"><div class="shop-empty shop-woo-empty"><span class="shop-woo-empty-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><h2>Produs negăsit</h2><p>Produsul nu există sau a fost eliminat din catalog.</p><a class="shop-btn shop-btn-accent" href="catalog.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Înapoi la catalog</a></div></div></main>';
    require __DIR__ . '/shop/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_cart') {
    blu_cart_add($id, max(1, (int) ($_POST['qty'] ?? 1)));
    header('Location: cos.php?added=1');
    exit;
}

$name = trim((string) ($product['nume'] ?? 'Produs'));
$img = trim((string) ($product['imagine'] ?? ''));
$desc = trim((string) ($product['descriere'] ?? ''));
$oem = trim((string) ($product['cod_oem'] ?? ''));
$cat = trim((string) ($product['categorie'] ?? ''));
$marca = trim((string) ($product['marca_masina'] ?? ''));
$inStock = blu_shop_in_stock($product);
$related = $cat !== '' ? blu_shop_filter_products('', $cat, 4) : [];
$related = array_values(array_filter($related, static fn(array $p): bool => blu_shop_product_id($p) !== $id));
$related = array_slice($related, 0, 4);

$shopPageTitle = $name . ' — Blue-Car';
$shopPageDescription = $oem !== '' ? 'Cod OEM ' . $oem . '. ' . $name : $name;
$shopBodyClass = 'shop-page shop-page-product';

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--woo">
    <div class="shop-container">
        <nav class="shop-breadcrumb reveal" aria-label="Breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Acasă</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <a href="catalog.php">Catalog</a>
            <?php if ($cat !== ''): ?>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <a href="catalog.php?categorie=<?= rawurlencode($cat) ?>"><?= blu_shop_h($cat) ?></a>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span><?= blu_shop_h($name) ?></span>
        </nav>

        <div class="shop-product-detail shop-product-detail--woo shop-woo-section reveal">
            <div class="shop-product-gallery shop-woo-gallery">
                <?php if ($img !== ''): ?>
                    <img src="<?= blu_shop_h($img) ?>" alt="<?= blu_shop_h($name) ?>">
                <?php else: ?>
                    <span class="shop-card-placeholder"><i class="fa-solid fa-image fa-3x" aria-hidden="true"></i> Imagine indisponibilă</span>
                <?php endif; ?>
                <?php if ($inStock): ?>
                    <span class="shop-woo-stock-badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> În stoc</span>
                <?php endif; ?>
            </div>
            <div class="shop-product-info shop-woo-product-info">
                <?php if ($cat !== ''): ?>
                    <div class="shop-card-cat"><i class="<?= blu_shop_h(blu_shop_category_icon($cat)) ?>" aria-hidden="true"></i> <?= blu_shop_h($cat) ?></div>
                <?php endif; ?>
                <h1><?= blu_shop_h($name) ?></h1>
                <div class="shop-woo-product-meta">
                    <?php if ($marca !== ''): ?><span><i class="fa-solid fa-car" aria-hidden="true"></i> <?= blu_shop_h($marca) ?></span><?php endif; ?>
                    <?php if ($oem !== ''): ?><span><i class="fa-solid fa-barcode" aria-hidden="true"></i> OEM <?= blu_shop_h($oem) ?></span><?php endif; ?>
                </div>
                <div class="shop-product-price-lg"><?= blu_shop_h(blu_shop_price_label($product)) ?></div>
                <p class="shop-stock-label"><?= $inStock ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> Disponibil — expediere rapidă' : '<i class="fa-solid fa-clock" aria-hidden="true"></i> La comandă — confirmăm termenul telefonic' ?></p>
                <?php if ($inStock): ?>
                <form method="post" class="shop-qty-row shop-woo-buy-row">
                    <input type="hidden" name="action" value="add_cart">
                    <label for="qty"><i class="fa-solid fa-hashtag" aria-hidden="true"></i> Cantitate</label>
                    <input type="number" id="qty" name="qty" value="1" min="1" max="99">
                    <button type="submit" class="shop-btn shop-btn-accent shop-btn-glow"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Adaugă în coș</button>
                </form>
                <?php else: ?>
                    <a class="shop-btn shop-btn-accent shop-btn-glow" href="contact.php?produs=<?= (int) $id ?>"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicită ofertă</a>
                <?php endif; ?>
                <ul class="shop-woo-product-perks">
                    <li><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Livrare 24–72h din stoc</li>
                    <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Compatibilitate verificată OEM</li>
                    <li><i class="fa-solid fa-headset" aria-hidden="true"></i> Suport înainte de comandă</li>
                </ul>
            </div>
        </div>

        <div class="shop-woo-panel shop-woo-section reveal">
            <div class="shop-woo-tabs" data-woo-tabs>
                <div class="shop-woo-tablist" role="tablist" aria-label="Detalii produs">
                    <button type="button" class="shop-woo-tab is-active" role="tab" data-tab="desc" aria-selected="true" aria-controls="product-panel-desc">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i> Descriere
                    </button>
                    <button type="button" class="shop-woo-tab" role="tab" data-tab="specs" aria-selected="false" aria-controls="product-panel-specs" tabindex="-1">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i> Specificații
                    </button>
                    <button type="button" class="shop-woo-tab" role="tab" data-tab="ship" aria-selected="false" aria-controls="product-panel-ship" tabindex="-1">
                        <i class="fa-solid fa-truck" aria-hidden="true"></i> Livrare & retur
                    </button>
                </div>
                <div class="shop-woo-tabpanel is-active" role="tabpanel" id="product-panel-desc" data-panel="desc">
                    <?php if ($desc !== ''): ?>
                        <?php if (function_exists('blu_description_uses_card_template') && blu_description_uses_card_template($desc)): ?>
                            <div class="shop-product-card-template">
                                <div class="shop-product-card-template__title"><?= blu_shop_h($name) ?></div>
                                <div class="shop-product-card-template__body"><?= blu_shop_h($desc) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="shop-product-desc shop-woo-prose"><?= nl2br(blu_shop_h($desc)) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="shop-woo-prose"><?= blu_shop_h($name) ?> — piesă auto din categoria <?= blu_shop_h($cat !== '' ? $cat : 'generale') ?>, disponibilă prin magazinul Blue-Car. Contactează-ne pentru detalii tehnice suplimentare sau confirmare compatibilitate cu vehiculul tău.</p>
                    <?php endif; ?>
                </div>
                <div class="shop-woo-tabpanel" role="tabpanel" id="product-panel-specs" data-panel="specs" hidden>
                    <dl class="shop-woo-spec-table">
                        <div><dt>Categorie</dt><dd><?= blu_shop_h($cat !== '' ? $cat : '—') ?></dd></div>
                        <div><dt>Cod OEM</dt><dd><?= blu_shop_h($oem !== '' ? $oem : '—') ?></dd></div>
                        <div><dt>Marcă vehicul</dt><dd><?= blu_shop_h($marca !== '' ? $marca : 'Universal / verifică compatibilitatea') ?></dd></div>
                        <div><dt>Disponibilitate</dt><dd><?= $inStock ? 'În stoc' : 'La comandă' ?></dd></div>
                        <div><dt>ID catalog</dt><dd>#<?= (int) $id ?></dd></div>
                    </dl>
                </div>
                <div class="shop-woo-tabpanel" role="tabpanel" id="product-panel-ship" data-panel="ship" hidden>
                    <div class="shop-woo-prose">
                        <p><strong>Livrare:</strong> Curier național, 24–72 ore lucrătoare pentru produsele din stoc. Comenzile plasate după ora 14:00 pot fi procesate a doua zi lucrătoare.</p>
                        <p><strong>Plată:</strong> Confirmare telefonică — transfer bancar, ramburs sau factură firmă.</p>
                        <p><strong>Retur:</strong> 14 zile pentru produse neutilizate, ambalaj intact. Piesele montate nu se returnează.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($related): ?>
        <section class="shop-woo-related shop-woo-section reveal" aria-labelledby="related-title">
            <div class="shop-section-head">
                <h2 id="related-title"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Produse similare</h2>
                <?php if ($cat !== ''): ?>
                    <a href="catalog.php?categorie=<?= rawurlencode($cat) ?>">Vezi toate <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
            <div class="shop-grid shop-catalog-grid">
                <?php foreach ($related as $relProduct):
                    $product = $relProduct;
                    require __DIR__ . '/shop/includes/product-card.php';
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php require __DIR__ . '/shop/includes/woo-trust-strip.php'; ?>
    </div>
</main>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
