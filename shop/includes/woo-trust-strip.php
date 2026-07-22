<?php
declare(strict_types=1);

/** @var string $bluCmsPage */
$bluCmsPage = trim((string) ($bluCmsPage ?? ''));
$useCms = $bluCmsPage !== '' && function_exists('blu_cms_tag');
?>
<section class="shop-woo-trust shop-woo-section reveal" aria-label="Beneficii Blue-Car">
    <ul class="shop-woo-trust-list">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <li>
                <span class="shop-woo-trust-icon"><i class="fa-solid <?= $i === 1 ? 'fa-certificate' : ($i === 2 ? 'fa-truck-fast' : ($i === 3 ? 'fa-shield-halved' : 'fa-headset')) ?>" aria-hidden="true"></i></span>
                <span class="shop-woo-trust-text">
                    <?php if ($useCms): ?>
                        <?php blu_cms_tag($bluCmsPage, 'trust' . $i . '_title', 'strong', blu_cms_get($bluCmsPage, 'trust' . $i . '_title')); ?>
                        <?php blu_cms_tag($bluCmsPage, 'trust' . $i . '_sub', 'em', blu_cms_get($bluCmsPage, 'trust' . $i . '_sub')); ?>
                    <?php else: ?>
                        <?php
                        $titles = ['Piese OEM verificate', 'Livrare rapidă RO', 'Plată securizată', 'Suport dedicat'];
                        $subs = ['Coduri clare, compatibilitate confirmată', 'Expediere din stoc sau la comandă', 'Confirmare telefonică, fără surprize', 'Consultanță compatibilitate gratuită'];
                        ?>
                        <strong><?= blu_shop_h($titles[$i - 1] ?? '') ?></strong>
                        <em><?= blu_shop_h($subs[$i - 1] ?? '') ?></em>
                    <?php endif; ?>
                </span>
            </li>
        <?php endfor; ?>
    </ul>
</section>
