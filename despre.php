<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';

$bluCmsPage = 'despre';
$totalProducts = count(blu_load_products());
$catCount = count(blu_shop_categories());

$shopPageTitle = 'Despre Blue-Car — Piese auto online';
$shopPageDescription = 'Blue-Car — magazin de piese auto cu catalog OEM, suport dedicat și livrare în România.';
$shopBodyClass = 'shop-page shop-page-about shop-ui-v10';

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--woo">
    <div class="shop-container">
        <nav class="shop-breadcrumb reveal" aria-label="Breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Acasă</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span>Despre</span>
        </nav>

        <?php
        $wooHeroEyebrow = blu_cms_get('despre', 'hero_eyebrow');
        $wooHeroTitle = blu_cms_get('despre', 'hero_title');
        $wooHeroLead = blu_cms_get('despre', 'hero_lead');
        $wooHeroIcon = 'fa-solid fa-circle-info';
        require __DIR__ . '/shop/includes/woo-page-hero.php';
        ?>

        <div class="shop-woo-stats shop-woo-section reveal" aria-label="Statistici">
            <article class="shop-woo-stat"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i><strong><?= (int) $totalProducts ?>+</strong><?php blu_cms_tag('despre', 'stat1_label', 'span', 'Piese indexate'); ?></article>
            <article class="shop-woo-stat"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><strong><?= (int) $catCount ?></strong><?php blu_cms_tag('despre', 'stat2_label', 'span', 'Categorii active'); ?></article>
            <article class="shop-woo-stat"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><?php blu_cms_tag('despre', 'stat3_value', 'strong', '24–72h'); ?><?php blu_cms_tag('despre', 'stat3_label', 'span', 'Livrare din stoc'); ?></article>
            <article class="shop-woo-stat"><i class="fa-solid fa-headset" aria-hidden="true"></i><?php blu_cms_tag('despre', 'stat4_value', 'strong', '100%'); ?><?php blu_cms_tag('despre', 'stat4_label', 'span', 'Suport compatibilitate'); ?></article>
        </div>

        <?php require __DIR__ . '/shop/includes/woo-trust-strip.php'; ?>

        <?php blu_builder_render_zone('despre', 'after_hero'); ?>

        <div class="shop-woo-panel shop-woo-section reveal">
            <div class="shop-woo-tabs" data-woo-tabs>
                <div class="shop-woo-tablist shop-pill-tablist" role="tablist" aria-label="Despre Blue-Car">
                    <button type="button" class="shop-woo-tab shop-pill-tab is-active" role="tab" data-tab="story" aria-selected="true" aria-controls="about-panel-story">
                        <i class="fa-solid fa-book-open" aria-hidden="true"></i> Povestea noastră
                    </button>
                    <button type="button" class="shop-woo-tab shop-pill-tab" role="tab" data-tab="values" aria-selected="false" aria-controls="about-panel-values" tabindex="-1">
                        <i class="fa-solid fa-gem" aria-hidden="true"></i> Valori & garanții
                    </button>
                    <button type="button" class="shop-woo-tab shop-pill-tab" role="tab" data-tab="process" aria-selected="false" aria-controls="about-panel-process" tabindex="-1">
                        <i class="fa-solid fa-route" aria-hidden="true"></i> Cum comand
                    </button>
                </div>

                <div class="shop-woo-tabpanel is-active" role="tabpanel" id="about-panel-story" data-panel="story">
                    <div class="shop-woo-prose">
                        <?php blu_cms_tag('despre', 'story_p1', 'p', null, [], true); ?>
                        <?php blu_cms_tag('despre', 'story_p2', 'p', null, [], true); ?>
                        <?php blu_cms_tag('despre', 'story_p3', 'p', null, [], true); ?>
                    </div>
                    <div class="shop-woo-value-grid">
                        <?php for ($v = 1; $v <= 3; $v++): ?>
                            <article class="shop-woo-value-card">
                                <i class="fa-solid <?= $v === 1 ? 'fa-magnifying-glass' : ($v === 2 ? 'fa-tags' : 'fa-warehouse') ?>" aria-hidden="true"></i>
                                <?php blu_cms_tag('despre', 'value' . $v . '_title', 'h3', null); ?>
                                <?php blu_cms_tag('despre', 'value' . $v . '_text', 'p', null); ?>
                            </article>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="shop-woo-tabpanel" role="tabpanel" id="about-panel-values" data-panel="values" hidden>
                    <ul class="shop-woo-checklist">
                        <?php for ($li = 1; $li <= 5; $li++): ?>
                            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> <?php blu_cms_tag('despre', 'values_li' . $li, 'span', null); ?></li>
                        <?php endfor; ?>
                    </ul>
                    <div class="shop-woo-prose">
                        <?php blu_cms_tag('despre', 'values_p1', 'p', null, [], true); ?>
                    </div>
                </div>

                <div class="shop-woo-tabpanel" role="tabpanel" id="about-panel-process" data-panel="process" hidden>
                    <ol class="shop-woo-process-list">
                        <?php for ($p = 1; $p <= 4; $p++): ?>
                            <li>
                                <span class="shop-woo-process-num"><?= $p ?></span>
                                <div>
                                    <?php blu_cms_tag('despre', 'process' . $p . '_title', 'strong', null); ?>
                                    <?php blu_cms_tag('despre', 'process' . $p . '_text', 'p', null); ?>
                                </div>
                            </li>
                        <?php endfor; ?>
                    </ol>
                    <p class="shop-woo-prose" style="margin-top:1.25rem">
                        <a class="shop-btn shop-btn-accent" href="catalog.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Începe cumpărăturile</a>
                    </p>
                </div>
            </div>
        </div>

        <section class="shop-woo-cta-band shop-woo-section reveal" aria-label="Call to action">
            <div class="shop-woo-cta-band-inner">
                <div>
                    <?php blu_cms_tag('despre', 'cta_title', 'h2', null); ?>
                    <?php blu_cms_tag('despre', 'cta_text', 'p', null); ?>
                </div>
                <a class="shop-btn shop-btn-glow" href="contact.php"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Contactează-ne</a>
            </div>
        </section>

        <?php blu_builder_render_zone('despre', 'before_footer'); ?>
    </div>
</main>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
