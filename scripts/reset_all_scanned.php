<?php
declare(strict_types=1);

/**
 * Reset complet: produse scanate/importate + stare robot furnizor.
 * Rulare: php scripts/reset_all_scanned.php
 */

$root = dirname(__DIR__);
require_once $root . '/lib/products_store.php';

$result = blu_clear_all_product_data();
echo ($result['ok'] ? 'OK' : 'ERR') . ': ' . ($result['message'] ?? '') . PHP_EOL;
exit($result['ok'] ? 0 : 1);
