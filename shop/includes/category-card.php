<?php
declare(strict_types=1);

/** @var string $catName */
/** @var int $count */
$icon = blu_shop_category_icon($catName);
?>
<a class="shop-cat-card reveal" href="catalog.php?categorie=<?= rawurlencode($catName) ?>">
    <div class="shop-cat-card-visual">
        <i class="<?= blu_shop_h($icon) ?>" aria-hidden="true"></i>
    </div>
    <div class="shop-cat-card-body">
        <h3><?= blu_shop_h($catName) ?></h3>
        <span><?= (int) $count ?> produse</span>
    </div>
</a>
