<?php
declare(strict_types=1);

/** @var array $product */
$id = blu_shop_product_id($product);
$img = trim((string) ($product['imagine'] ?? ''));
$name = trim((string) ($product['nume'] ?? 'Produs'));
$cat = trim((string) ($product['categorie'] ?? ''));
$oem = trim((string) ($product['cod_oem'] ?? ''));
$url = blu_shop_product_url($product);
$inStock = blu_shop_in_stock($product);
$catIcon = $cat !== '' ? blu_shop_category_icon($cat) : 'fa-solid fa-screwdriver-wrench';
$promoBadge = trim((string) ($shopCardPromoBadge ?? ''));
$promoBadgeClass = trim((string) ($shopCardPromoBadgeClass ?? ''));
?>
<article class="shop-card<?= empty($shopCardPlain) ? ' reveal' : '' ?>">
    <a href="<?= blu_shop_h($url) ?>" class="shop-card-img<?= $promoBadge !== '' ? ' shop-card-img--has-promo' : '' ?>">
        <?php if ($img !== ''): ?>
            <img src="<?= blu_shop_h($img) ?>" alt="<?= blu_shop_h($name) ?>" loading="lazy">
        <?php else: ?>
            <span class="shop-card-placeholder"><i class="fa-solid fa-image" aria-hidden="true"></i></span>
        <?php endif; ?>
        <?php if ($promoBadge !== ''): ?>
            <span class="shop-card-promo-badge<?= $promoBadgeClass !== '' ? ' ' . blu_shop_h($promoBadgeClass) : '' ?>"><?= blu_shop_h($promoBadge) ?></span>
        <?php endif; ?>
        <?php if ($inStock): ?><span class="shop-card-badge"><i class="fa-solid fa-check" aria-hidden="true"></i> În stoc</span><?php endif; ?>
        <?php if ($cat !== '' && $promoBadge === ''): ?>
            <span class="shop-card-cat-icon" title="<?= blu_shop_h($cat) ?>"><i class="<?= blu_shop_h($catIcon) ?>" aria-hidden="true"></i></span>
        <?php endif; ?>
    </a>
    <div class="shop-card-body">
        <?php if ($cat !== ''): ?><div class="shop-card-cat"><?= blu_shop_h($cat) ?></div><?php endif; ?>
        <h3 class="shop-card-title"><a href="<?= blu_shop_h($url) ?>"><?= blu_shop_h($name) ?></a></h3>
        <?php if ($oem !== ''): ?>
            <div class="shop-card-oem"><span><i class="fa-solid fa-barcode" aria-hidden="true"></i> OEM</span> <?= blu_shop_h($oem) ?></div>
        <?php endif; ?>
        <div class="shop-card-foot">
            <div class="shop-card-price"><?= blu_shop_h(blu_shop_price_label($product)) ?></div>
            <div class="shop-card-actions">
                <a class="shop-btn shop-btn-ghost" href="<?= blu_shop_h($url) ?>" aria-label="Detalii"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <?php if ($inStock): ?>
                    <button type="button" class="shop-btn shop-btn-accent" data-add-cart="<?= (int) $id ?>"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Coș</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>
