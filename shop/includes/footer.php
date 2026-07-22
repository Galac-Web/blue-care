<?php
declare(strict_types=1);

$company = blu_shop_company();
$shopPhone = trim((string) ($company['phone'] ?? $company['telefon'] ?? ''));
$shopEmail = trim((string) ($company['email'] ?? 'contact@blue-car.ro'));
$shopAddress = trim((string) ($company['address'] ?? $company['adresa'] ?? ''));
?>
<footer class="shop-footer">
    <div class="shop-footer-glow" aria-hidden="true"></div>
    <div class="shop-container">
        <div class="shop-footer-cta">
            <div>
                <p class="shop-footer-eyebrow"><i class="fa-solid fa-headset" aria-hidden="true"></i> Consultanță gratuită</p>
                <h3>Ai nevoie de piesa potrivită?</h3>
            </div>
            <a class="shop-btn shop-btn-glow" href="contact.php"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Scrie-ne acum</a>
        </div>
        <div class="shop-footer-grid">
            <div class="shop-footer-brand">
                <a class="shop-logo shop-logo--footer" href="index.php">
                    <?php require __DIR__ . '/logo-mark.php'; ?>
                    <span class="shop-logo-text">Blue<span class="shop-logo-accent">Car</span></span>
                </a>
                <p>Magazin privat de piese auto. OEM verificat, prețuri clare, livrare în România.</p>
            </div>
            <div>
                <h4><i class="fa-solid fa-compass" aria-hidden="true"></i> Explorează</h4>
                <ul>
                    <li><a href="catalog.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Catalog</a></li>
                    <li><a href="cos.php"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Coș</a></li>
                    <li><a href="despre.php"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Despre</a></li>
                </ul>
            </div>
            <div>
                <h4><i class="fa-solid fa-address-book" aria-hidden="true"></i> Contact</h4>
                <ul>
                    <?php if ($shopPhone !== ''): ?><li><a href="tel:<?= blu_shop_h(preg_replace('/\s+/', '', $shopPhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= blu_shop_h($shopPhone) ?></a></li><?php endif; ?>
                    <li><a href="mailto:<?= blu_shop_h($shopEmail) ?>"><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= blu_shop_h($shopEmail) ?></a></li>
                    <?php if ($shopAddress !== ''): ?><li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= blu_shop_h($shopAddress) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="shop-footer-bottom">
            <span>© <?= date('Y') ?> Blue-Car</span>
            <span class="shop-footer-tag"><i class="fa-solid fa-car" aria-hidden="true"></i> Piese auto · OEM · România</span>
            <?php if (function_exists('blu_shop_is_logged_in') && blu_shop_is_logged_in()): ?>
            <a href="admin/"><i class="fa-solid fa-gear" aria-hidden="true"></i> Admin</a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php if (function_exists('blu_cms_edit_mode') && blu_cms_edit_mode()): ?>
    <?php require __DIR__ . '/cms-edit-bar.php'; ?>
<?php else: ?>
<script src="assets/shop/shop.js?v=31"></script>
<?php endif; ?>
