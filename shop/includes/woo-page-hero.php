<?php
declare(strict_types=1);

/** @var string $wooHeroEyebrow */
/** @var string $wooHeroTitle */
/** @var string $wooHeroLead */
/** @var string $wooHeroIcon */
/** @var string $bluCmsPage */
$wooHeroEyebrow = $wooHeroEyebrow ?? '';
$wooHeroTitle = $wooHeroTitle ?? '';
$wooHeroLead = $wooHeroLead ?? '';
$wooHeroIcon = $wooHeroIcon ?? 'fa-solid fa-store';
$bluCmsPage = trim((string) ($bluCmsPage ?? ''));
$useCms = $bluCmsPage !== '' && function_exists('blu_cms_tag');
?>
<section class="shop-woo-hero shop-woo-section reveal">
    <div class="shop-woo-hero-inner">
        <?php if ($wooHeroIcon !== ''): ?>
            <span class="shop-woo-hero-icon" aria-hidden="true"><i class="<?= blu_shop_h($wooHeroIcon) ?>"></i></span>
        <?php endif; ?>
        <div class="shop-woo-hero-text">
            <?php if ($useCms || $wooHeroEyebrow !== ''): ?>
                <?php if ($useCms): ?>
                    <?php blu_cms_tag($bluCmsPage, 'hero_eyebrow', 'p', $wooHeroEyebrow, ['class' => 'shop-woo-hero-eyebrow']); ?>
                <?php else: ?>
                    <p class="shop-woo-hero-eyebrow"><?= blu_shop_h($wooHeroEyebrow) ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($useCms || $wooHeroTitle !== ''): ?>
                <?php if ($useCms): ?>
                    <?php blu_cms_tag($bluCmsPage, 'hero_title', 'h1', $wooHeroTitle); ?>
                <?php else: ?>
                    <h1><?= blu_shop_h($wooHeroTitle) ?></h1>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($useCms || $wooHeroLead !== ''): ?>
                <?php if ($useCms): ?>
                    <?php blu_cms_tag($bluCmsPage, 'hero_lead', 'p', $wooHeroLead, ['class' => 'shop-woo-hero-lead']); ?>
                <?php else: ?>
                    <p class="shop-woo-hero-lead"><?= blu_shop_h($wooHeroLead) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
