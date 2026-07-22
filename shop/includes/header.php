<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/shop/cart.php';

$company = blu_shop_company();
$shopPhone = trim((string) ($company['phone'] ?? $company['telefon'] ?? '0726 000 000'));
$currentPage = blu_shop_current_page();
$cartCount = blu_cart_count();
$shopUser = blu_shop_current_user();

$nav = [
    ['label' => 'Acasă', 'href' => 'index.php', 'page' => 'index.php'],
    ['label' => 'Catalog', 'href' => 'catalog.php', 'page' => 'catalog.php'],
    ['label' => 'Despre', 'href' => 'despre.php', 'page' => 'despre.php'],
    ['label' => 'Contact', 'href' => 'contact.php', 'page' => 'contact.php'],
];
?>
<div class="shop-grain" aria-hidden="true"></div>
<header class="shop-header" id="shop-header">
    <div class="shop-container shop-header-shell">
        <div class="shop-header-row">
            <div class="shop-header-brand">
                <button type="button" class="shop-nav-toggle" id="shop-nav-toggle" aria-label="Meniu" aria-expanded="false">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <a class="shop-logo" href="index.php">
                    <?php require __DIR__ . '/logo-mark.php'; ?>
                    <span class="shop-logo-text">Blue<span class="shop-logo-accent">Car</span></span>
                </a>
            </div>
            <form class="shop-search" action="catalog.php" method="get" role="search">
                <i class="fa-solid fa-magnifying-glass shop-search-icon" aria-hidden="true"></i>
                <input type="search" name="q" placeholder="Cod OEM sau denumire piesă..." value="<?= blu_shop_h((string) ($_GET['q'] ?? '')) ?>">
                <button type="submit"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span>Caută</span></button>
            </form>
            <div class="shop-header-actions">
                <a class="shop-action-pill shop-action-phone" href="tel:<?= blu_shop_h(preg_replace('/\s+/', '', $shopPhone)) ?>">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <span class="shop-action-user-text">
                        <span class="shop-action-label">Suport</span>
                        <strong><?= blu_shop_h($shopPhone) ?></strong>
                    </span>
                </a>
                <?php if ($shopUser): ?>
                <a class="shop-action-pill shop-action-user" href="logout.php" title="Deconectare">
                    <span class="shop-action-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span class="shop-action-user-text">
                        <strong><?= blu_shop_h(explode(' ', trim((string) ($shopUser['name'] ?? 'Cont')))[0]) ?></strong>
                        <em><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Ieșire</em>
                    </span>
                </a>
                <?php endif; ?>
                <a class="shop-action-pill shop-action-cart" href="cos.php">
                    <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                    <span>Coș</span>
                    <?php if ($cartCount > 0): ?><em class="shop-cart-badge"><?= (int) $cartCount ?></em><?php endif; ?>
                </a>
            </div>
        </div>
        <nav class="shop-nav" id="shop-nav" aria-label="Principal">
            <?php foreach ($nav as $item):
                $navIcon = blu_shop_nav_icon($item['page']);
            ?>
                <a href="<?= blu_shop_h($item['href']) ?>" class="<?= $currentPage === $item['page'] ? 'is-active' : '' ?>">
                    <i class="<?= blu_shop_h($navIcon['icon']) ?>" aria-hidden="true"></i>
                    <span><?= blu_shop_h($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
