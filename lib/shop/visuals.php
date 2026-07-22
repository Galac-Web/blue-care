<?php
declare(strict_types=1);

/** Cale relativă la rădăcina site-ului pentru asset-uri shop. */
function blu_shop_asset(string $path): string
{
    return 'assets/shop/' . ltrim($path, '/');
}

/**
 * Icon Font Awesome 6 (clasă completă) pentru o categorie de piese.
 */
function blu_shop_category_icon(string $categoryName): string
{
    $key = blu_shop_normalize_category_key($categoryName);
    $map = [
        'frana' => 'fa-solid fa-circle-stop',
        'franare' => 'fa-solid fa-circle-stop',
        'placute' => 'fa-solid fa-circle-stop',
        'disc' => 'fa-solid fa-compact-disc',
        'geam' => 'fa-solid fa-window-maximize',
        'actionare_geam' => 'fa-solid fa-window-maximize',
        'motor' => 'fa-solid fa-gear',
        'suport_motor' => 'fa-solid fa-gears',
        'suspensie' => 'fa-solid fa-arrows-up-down',
        'brat' => 'fa-solid fa-arrows-up-down',
        'amortizor' => 'fa-solid fa-car-side',
        'portbagaj' => 'fa-solid fa-car-rear',
        'radiator' => 'fa-solid fa-fan',
        'temperatura' => 'fa-solid fa-temperature-half',
        'ventilator' => 'fa-solid fa-fan',
        'tapiterie' => 'fa-solid fa-couch',
        'usi' => 'fa-solid fa-door-open',
        'electrica' => 'fa-solid fa-bolt',
        'ulei' => 'fa-solid fa-oil-can',
        'filtru' => 'fa-solid fa-filter',
        'baterie' => 'fa-solid fa-car-battery',
        'faruri' => 'fa-solid fa-lightbulb',
        'anvelope' => 'fa-solid fa-circle',
        'transmisie' => 'fa-solid fa-gears',
        'clima' => 'fa-solid fa-snowflake',
        'caroserie' => 'fa-solid fa-car',
    ];

    foreach ($map as $needle => $icon) {
        if (str_contains($key, $needle)) {
            return $icon;
        }
    }

    return 'fa-solid fa-screwdriver-wrench';
}

/**
 * Imagine banner pentru categorie (SVG local, clar la orice rezoluție).
 */
function blu_shop_category_banner(string $categoryName): string
{
    $key = blu_shop_normalize_category_key($categoryName);
    $banners = [
        'frana' => 'cat-brake.svg',
        'geam' => 'cat-glass.svg',
        'motor' => 'cat-engine.svg',
        'amortizor' => 'cat-suspension.svg',
        'temperatura' => 'cat-cooling.svg',
        'tapiterie' => 'cat-interior.svg',
        'suspensie' => 'cat-suspension.svg',
        'placute' => 'cat-brake.svg',
    ];

    foreach ($banners as $needle => $file) {
        if (str_contains($key, $needle)) {
            return blu_shop_asset('img/categories/' . $file);
        }
    }

    return blu_shop_asset('img/categories/cat-default.svg');
}

function blu_shop_normalize_category_key(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = str_replace(['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'], ['a', 'a', 'i', 's', 't', 's', 't'], $name);
    return preg_replace('/[^a-z0-9]+/', '_', $name) ?? '';
}

/** Bannere pagini */
function blu_shop_page_banner(string $page): string
{
    return match ($page) {
        'catalog' => blu_shop_asset('img/banners/catalog.svg'),
        'contact' => blu_shop_asset('img/banners/contact.svg'),
        'despre' => blu_shop_asset('img/banners/about.svg'),
        'cos' => blu_shop_asset('img/banners/cart.svg'),
        default => blu_shop_asset('img/banners/hero-parts.svg'),
    };
}

/** @return array{icon:string,label:string} */
function blu_shop_nav_icon(string $page): array
{
    return match ($page) {
        'index.php' => ['icon' => 'fa-solid fa-house', 'label' => 'Acasă'],
        'catalog.php' => ['icon' => 'fa-solid fa-boxes-stacked', 'label' => 'Catalog'],
        'despre.php' => ['icon' => 'fa-solid fa-circle-info', 'label' => 'Despre'],
        'contact.php' => ['icon' => 'fa-solid fa-envelope', 'label' => 'Contact'],
        'cos.php' => ['icon' => 'fa-solid fa-cart-shopping', 'label' => 'Coș'],
        default => ['icon' => 'fa-solid fa-link', 'label' => ''],
    };
}

function blu_shop_stat_icon(string $type): string
{
    return match ($type) {
        'products' => 'fa-solid fa-box-open',
        'categories' => 'fa-solid fa-layer-group',
        'delivery' => 'fa-solid fa-truck-fast',
        default => 'fa-solid fa-chart-simple',
    };
}
