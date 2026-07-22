<?php
declare(strict_types=1);

if (!defined('BLU_SHOP_BOOTSTRAP')) {
    define('BLU_SHOP_BOOTSTRAP', true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($bluProjectRoot) || $bluProjectRoot === '') {
    $bluProjectRoot = dirname(__DIR__, 2);
    $resolvedRoot = realpath($bluProjectRoot);
    if ($resolvedRoot !== false) {
        $bluProjectRoot = $resolvedRoot;
    }
}

require_once $bluProjectRoot . '/lib/env.php';
require_once $bluProjectRoot . '/lib/website_cms.php';
require_once $bluProjectRoot . '/lib/website_builder.php';
require_once $bluProjectRoot . '/lib/products_store.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/visuals.php';
require_once __DIR__ . '/prices.php';

blu_shop_sync_session();

function blu_shop_current_page(): string
{
    return basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
}

function blu_shop_should_require_login(): bool
{
    if (defined('BLU_ADMIN_CONTEXT') && BLU_ADMIN_CONTEXT) {
        return false;
    }
    if (!empty($_GET['blu_cms_edit']) && (string) $_GET['blu_cms_edit'] === '1' && !empty($_SESSION['admin'])) {
        return false;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($script, '/admin/') || str_contains($script, '\\admin\\')) {
        return false;
    }
    if (function_exists('blu_shop_is_auth_exempt_uri') && blu_shop_is_auth_exempt_uri($uri)) {
        return false;
    }
    if (function_exists('blu_shop_is_guest_api') && blu_shop_is_guest_api()) {
        return false;
    }
    if (function_exists('blu_shop_is_guest_page') && blu_shop_is_guest_page()) {
        return false;
    }
    return !blu_shop_is_public_page();
}

if (blu_shop_should_require_login()) {
    blu_shop_require_login();
}

function blu_shop_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function blu_shop_company(): array
{
    static $company = null;
    if ($company !== null) {
        return $company;
    }
    $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'company.json';
    $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    $company = is_array($raw) ? $raw : [];
    return $company;
}

function blu_shop_product_id(array $product): int
{
    return (int) ($product['id'] ?? $product['randomn_id'] ?? 0);
}

function blu_shop_product_slug(array $product): string
{
    $id = blu_shop_product_id($product);
    $name = trim((string) ($product['nume'] ?? $product['name'] ?? 'produs'));
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? 'produs');
    $slug = trim($slug, '-') ?: 'produs';
    return $slug . '-' . $id;
}

function blu_shop_product_url(array $product): string
{
    return 'produs.php?id=' . rawurlencode((string) blu_shop_product_id($product));
}

function blu_shop_in_stock(array $product): bool
{
    $stoc = trim((string) ($product['stoc'] ?? '1'));
    if ($stoc === '' || $stoc === '0') {
        return false;
    }
    return stripos($stoc, 'nu') === false && stripos($stoc, 'epuizat') === false;
}

function blu_shop_find_product(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    foreach (blu_load_products() as $product) {
        if (blu_shop_product_id($product) === $id) {
            return $product;
        }
    }
    return null;
}

function blu_shop_filter_products(string $q = '', ?string $category = null, int $limit = 0): array
{
    $items = blu_load_products();
    $q = trim($q);
    $category = $category !== null ? trim($category) : null;

    if ($q !== '') {
        $qLower = mb_strtolower($q);
        $items = array_values(array_filter($items, static function (array $p) use ($qLower): bool {
            $hay = mb_strtolower(implode(' ', [
                (string) ($p['nume'] ?? ''),
                (string) ($p['cod_oem'] ?? ''),
                (string) ($p['categorie'] ?? ''),
                (string) ($p['marca_masina'] ?? ''),
            ]));
            return str_contains($hay, $qLower);
        }));
    }

    if ($category !== null && $category !== '') {
        $catLower = mb_strtolower($category);
        $items = array_values(array_filter($items, static function (array $p) use ($catLower): bool {
            $cat = mb_strtolower(trim((string) ($p['categorie'] ?? '')));
            return $cat !== '' && (str_contains($cat, $catLower) || str_contains($catLower, $cat));
        }));
    }

    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    return $items;
}

function blu_shop_categories(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $counts = [];
    foreach (blu_load_products() as $product) {
        $cat = trim((string) ($product['categorie'] ?? ''));
        if ($cat === '') {
            continue;
        }
        $counts[$cat] = ($counts[$cat] ?? 0) + 1;
    }
    arsort($counts);
    $cache = $counts;

    return $cache;
}
