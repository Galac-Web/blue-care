<?php
declare(strict_types=1);

/** @var string $bannerTitle */
/** @var string $bannerSubtitle */
/** @var string|null $bannerIcon */
$bannerTitle = $bannerTitle ?? '';
$bannerSubtitle = $bannerSubtitle ?? '';
$bannerIcon = $bannerIcon ?? 'fa-solid fa-screwdriver-wrench';
?>
<div class="shop-page-head reveal">
    <?php if ($bannerIcon !== ''): ?>
        <span class="shop-page-head-icon" aria-hidden="true"><i class="<?= blu_shop_h($bannerIcon) ?>"></i></span>
    <?php endif; ?>
    <div class="shop-page-head-text">
        <?php if ($bannerTitle !== ''): ?><h1><?= blu_shop_h($bannerTitle) ?></h1><?php endif; ?>
        <?php if ($bannerSubtitle !== ''): ?><p><?= blu_shop_h($bannerSubtitle) ?></p><?php endif; ?>
    </div>
</div>
