<?php
declare(strict_types=1);

/** Modul MySQL dedicat Blue-Car (separat de agent_db / aibotpiese). */

const DB_PRODUCTS_SITE_BASE = '/';

function dbProductsConnect(array $settings): ?PDO
{
    $host = trim((string)($settings['db_host'] ?? 'localhost'));
    $name = trim((string)($settings['db_name'] ?? ''));
    $user = trim((string)($settings['db_user'] ?? ''));
    $pass = (string)($settings['db_pass'] ?? '');

    if ($name === '' || $user === '') {
        return null;
    }

    try {
        return new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException) {
        return null;
    }
}

function dbProductsResolveSettings(?array $settings = null): ?array
{
    if (
        is_array($settings)
        && trim((string)($settings['db_name'] ?? '')) !== ''
        && trim((string)($settings['db_user'] ?? '')) !== ''
    ) {
        return $settings;
    }
    return null;
}

function dbProductsCreateTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(64) NOT NULL,
        `slug` VARCHAR(512) DEFAULT '',
        `source_url` TEXT,
        `title` VARCHAR(512) DEFAULT '',
        `title_original` VARCHAR(512) DEFAULT '',
        `brand` VARCHAR(128) DEFAULT '',
        `car_brand` VARCHAR(128) DEFAULT '',
        `main_category` VARCHAR(255) DEFAULT '',
        `sub_category` VARCHAR(255) DEFAULT '',
        `product_category` VARCHAR(512) DEFAULT '',
        `price_pln` DECIMAL(12,2) DEFAULT 0,
        `price_ron_final` DECIMAL(12,2) DEFAULT 0,
        `price_ron_display` VARCHAR(64) DEFAULT '',
        `currency_source` VARCHAR(16) DEFAULT 'RON',
        `exchange_rate` DECIMAL(8,4) DEFAULT 1,
        `markup_percent` INT DEFAULT 0,
        `description` TEXT,
        `ad_title` VARCHAR(512) DEFAULT '',
        `ad_description` TEXT,
        `ad_disclaimer` TEXT,
        `compatibility` TEXT,
        `main_image` TEXT,
        `images_json` LONGTEXT,
        `downloaded_images_json` LONGTEXT,
        `parameters_json` LONGTEXT,
        `item_condition` VARCHAR(128) DEFAULT '',
        `stock` VARCHAR(64) DEFAULT '',
        `seller_name` VARCHAR(255) DEFAULT '',
        `cod_oem` VARCHAR(128) DEFAULT '',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `imported_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_product_id` (`product_id`),
        KEY `idx_cod_oem` (`cod_oem`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function dbProductsResolveProductId(PDO $pdo, array $product): string
{
    $pid = trim((string)($product['product_id'] ?? $product['id'] ?? ''));
    if ($pid !== '') {
        return $pid;
    }
    $oem = strtoupper(trim((string)($product['cod_oem'] ?? ($product['admin_card']['cod_oem'] ?? ''))));
    if ($oem !== '') {
        return 'oem_' . md5($oem);
    }
    return 'p_' . uniqid('', true);
}

function dbProductsPrepareProduct(array $product, int $index, bool $finalize = false): array
{
    return $product;
}

function dbProductsBuildUpsertParams(array $p, string $siteBase = DB_PRODUCTS_SITE_BASE): array
{
    $image = trim((string)($p['image'] ?? $p['admin_card']['imagine'] ?? ''));
    $imagesForDb = $image !== '' ? [['url' => $image, 'remote' => true]] : [];
    $mainImage = $image !== '' ? json_encode([$image], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

    $title = (string)($p['title'] ?? $p['admin_card']['nume'] ?? 'Piesa auto');
    $description = (string)($p['description'] ?? $p['ad_description'] ?? $p['admin_card']['descriere'] ?? '');
    $priceFinal = (float)($p['price_ron_final'] ?? 0);

    return [
        ':product_id' => dbProductsResolveProductIdFromArray($p),
        ':slug' => (string)($p['slug'] ?? ''),
        ':source_url' => (string)($p['source_url'] ?? ''),
        ':title' => $title,
        ':title_original' => (string)($p['title_original'] ?? $title),
        ':brand' => (string)($p['brand'] ?? ''),
        ':car_brand' => (string)($p['car_brand'] ?? $p['admin_card']['marca_masina'] ?? ''),
        ':main_category' => (string)($p['main_category'] ?? 'Piese auto'),
        ':sub_category' => (string)($p['sub_category'] ?? $p['admin_card']['categorie'] ?? ''),
        ':product_category' => (string)($p['product_category'] ?? ''),
        ':price_pln' => 0,
        ':price_ron_final' => $priceFinal,
        ':price_ron_display' => $priceFinal > 0 ? number_format($priceFinal, 0, ',', ' ') . ' RON' : '',
        ':currency_source' => 'RON',
        ':exchange_rate' => 1,
        ':markup_percent' => 0,
        ':description' => $description,
        ':ad_title' => (string)($p['ad_title'] ?? $title),
        ':ad_description' => $description,
        ':ad_disclaimer' => '',
        ':compatibility' => (string)($p['compatibility'] ?? ''),
        ':main_image' => $mainImage,
        ':images_json' => json_encode($imagesForDb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':downloaded_images_json' => '[]',
        ':parameters_json' => '[]',
        ':item_condition' => (string)($p['item_condition'] ?? 'Nou'),
        ':stock' => (string)($p['stock'] ?? $p['admin_card']['stoc'] ?? '1'),
        ':seller_name' => 'Blue-Car',
        ':cod_oem' => (string)($p['cod_oem'] ?? $p['admin_card']['cod_oem'] ?? ''),
        ':created_at' => (string)($p['created_at'] ?? date('Y-m-d H:i:s')),
        ':updated_at' => (string)($p['updated_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function dbProductsResolveProductIdFromArray(array $p): string
{
    $pid = trim((string)($p['product_id'] ?? ''));
    if ($pid !== '') {
        return $pid;
    }
    $id = trim((string)($p['id'] ?? ''));
    if ($id !== '') {
        return str_starts_with($id, 'tecdoc_') || str_starts_with($id, 'catalog_') ? $id : ('blu_' . $id);
    }
    return 'p_' . uniqid('', true);
}

function dbProductsUpsertSql(): string
{
    return "INSERT INTO `products`
        (`product_id`, `slug`, `source_url`, `title`, `title_original`,
         `brand`, `car_brand`, `main_category`, `sub_category`, `product_category`,
         `price_pln`, `price_ron_final`, `price_ron_display`, `currency_source`,
         `exchange_rate`, `markup_percent`, `description`,
         `ad_title`, `ad_description`, `ad_disclaimer`, `compatibility`,
         `main_image`, `images_json`, `downloaded_images_json`, `parameters_json`,
         `item_condition`, `stock`, `seller_name`, `cod_oem`, `created_at`, `updated_at`)
    VALUES
        (:product_id, :slug, :source_url, :title, :title_original,
         :brand, :car_brand, :main_category, :sub_category, :product_category,
         :price_pln, :price_ron_final, :price_ron_display, :currency_source,
         :exchange_rate, :markup_percent, :description,
         :ad_title, :ad_description, :ad_disclaimer, :compatibility,
         :main_image, :images_json, :downloaded_images_json, :parameters_json,
         :item_condition, :stock, :seller_name, :cod_oem, :created_at, :updated_at)
    ON DUPLICATE KEY UPDATE
        slug=VALUES(slug), source_url=VALUES(source_url), title=VALUES(title),
        title_original=VALUES(title_original), brand=VALUES(brand), car_brand=VALUES(car_brand),
        main_category=VALUES(main_category), sub_category=VALUES(sub_category),
        product_category=VALUES(product_category), price_ron_final=VALUES(price_ron_final),
        price_ron_display=VALUES(price_ron_display), description=VALUES(description),
        ad_title=VALUES(ad_title), ad_description=VALUES(ad_description),
        compatibility=VALUES(compatibility), main_image=VALUES(main_image),
        images_json=VALUES(images_json), item_condition=VALUES(item_condition),
        stock=VALUES(stock), cod_oem=VALUES(cod_oem), updated_at=VALUES(updated_at)";
}

function dbProductsSyncFromArray(PDO $pdo, array $products, bool $finalize = false, bool $createTable = true): array
{
    if ($createTable) {
        dbProductsCreateTable($pdo);
    }

    $stmt = $pdo->prepare(dbProductsUpsertSql());
    $imported = 0;
    $errors = 0;

    foreach ($products as $idx => $product) {
        if (!is_array($product)) {
            continue;
        }
        try {
            $p = dbProductsPrepareProduct($product, (int)$idx + 1, $finalize);
            $p['product_id'] = dbProductsResolveProductId($pdo, $p);
            $stmt->execute(dbProductsBuildUpsertParams($p));
            $imported++;
        } catch (PDOException) {
            $errors++;
        }
    }

    return [
        'ok' => $errors === 0,
        'imported' => $imported,
        'errors' => $errors,
        'total' => count($products),
    ];
}
