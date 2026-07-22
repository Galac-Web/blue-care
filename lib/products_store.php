<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

if (!function_exists('rows')) {
    function rows($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        if ($data === []) {
            return [];
        }
        return array_keys($data) === range(0, count($data) - 1) ? $data : array_values($data);
    }
}

function blu_products_json_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'products.json';
}

/** Detectează catalog_auto.json (marca → model → piese). */
function blu_json_is_catalog_format(array $data): bool
{
    if ($data === []) {
        return false;
    }
    if (array_keys($data) === range(0, count($data) - 1)) {
        $first = $data[0] ?? null;
        return is_array($first) && (isset($first['cod_articol']) || isset($first['coduri_oem']));
    }
    foreach ($data as $value) {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $parts) {
            if (is_array($parts) && isset($parts[0]['cod_articol'])) {
                return true;
            }
        }
    }
    return false;
}

/** @return list<array> */
function blu_normalize_products_from_json(array $data): array
{
    if (blu_json_is_catalog_format($data)) {
        return [];
    }

    $rows = rows($data);
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = blu_normalize_product_row($row);
        if ($normalized !== null) {
            $out[] = $normalized;
        }
    }
    return $out;
}

function blu_normalize_product_row(array $row): ?array
{
    $nume = trim((string)($row['nume'] ?? $row['name'] ?? $row['titlu'] ?? $row['title'] ?? ''));
    if ($nume === '') {
        return null;
    }

    return [
        'id' => (int)($row['id'] ?? $row['randomn_id'] ?? 0),
        'randomn_id' => (int)($row['randomn_id'] ?? $row['id'] ?? 0),
        'nume' => $nume,
        'cod_oem' => trim((string)($row['cod_oem'] ?? $row['cod'] ?? $row['cod_articol'] ?? '')),
        'categorie' => trim((string)($row['categorie'] ?? $row['category'] ?? $row['marca_masina'] ?? '')),
        'pret' => trim((string)($row['pret'] ?? $row['price'] ?? $row['price_ron_display'] ?? '')),
        'stoc' => trim((string)($row['stoc'] ?? $row['stock'] ?? '1')),
        'descriere' => trim((string)($row['descriere'] ?? $row['description'] ?? '')),
        'imagine' => trim((string)($row['imagine'] ?? $row['image'] ?? '')),
        'link' => trim((string)($row['link'] ?? $row['source_url'] ?? '')),
        'updated_at' => (string)($row['updated_at'] ?? date('Y-m-d H:i:s')),
    ];
}

/** @return list<array> */
function blu_load_products(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $file = blu_products_json_file();
    $rows = rows(blu_read_json_file($file, []));
    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $n = blu_normalize_product_row($row);
        if ($n === null) {
            $n = [
                'id' => (int)($row['id'] ?? $row['randomn_id'] ?? 0),
                'randomn_id' => (int)($row['randomn_id'] ?? $row['id'] ?? 0),
                'nume' => trim((string)($row['titlu'] ?? $row['title'] ?? 'Produs')),
                'cod_oem' => trim((string)($row['cod_oem'] ?? $row['cod'] ?? '')),
                'categorie' => '',
                'pret' => '',
                'stoc' => '1',
                'descriere' => '',
                'imagine' => '',
                'link' => trim((string)($row['link'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        if ((int)($n['id'] ?? 0) <= 0 && (int)($n['randomn_id'] ?? 0) <= 0) {
            continue;
        }
        if ((int)($n['id'] ?? 0) <= 0) {
            $n['id'] = (int)$n['randomn_id'];
        }
        if ((int)($n['randomn_id'] ?? 0) <= 0) {
            $n['randomn_id'] = (int)$n['id'];
        }
        $normalized[] = $n;
    }
    $cache = $normalized;

    return $cache;
}

function blu_save_products(array $products): bool
{
    return blu_write_json_file(blu_products_json_file(), array_values($products));
}

/**
 * Salveaza o imagine de produs incarcata manual in data/uploads si intoarce URL-ul relativ.
 * @param array{name?:string,tmp_name?:string,size?:int,error?:int} $file
 * @return array{ok:bool, url:string, message:string}
 */
function blu_save_uploaded_product_image(array $file, int $productId = 0): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'message' => 'Eroare la încărcarea imaginii.'];
    }
    if ((int)($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['ok' => false, 'url' => '', 'message' => 'Imaginea depășește 8 MB.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    if ($info === false || !isset($allowed[$info[2]])) {
        return ['ok' => false, 'url' => '', 'message' => 'Fișierul nu este o imagine validă (jpg/png/webp/gif).'];
    }
    $ext = $allowed[$info[2]];

    $dir = blu_data_dir() . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = 'prod_' . ($productId > 0 ? $productId . '_' : '') . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;

    if (!@move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'url' => '', 'message' => 'Nu am putut salva imaginea pe disc.'];
    }

    return ['ok' => true, 'url' => 'data/uploads/' . $name, 'message' => 'Imagine încărcată.'];
}

function blu_assign_product_ids(array $products, array $existing = []): array
{
    $maxId = 0;
    foreach ($existing as $row) {
        $maxId = max($maxId, (int)($row['id'] ?? $row['randomn_id'] ?? 0));
    }
    foreach ($products as &$product) {
        if ((int)($product['id'] ?? 0) <= 0) {
            $maxId++;
            $product['id'] = $maxId;
            $product['randomn_id'] = $maxId;
        }
    }
    unset($product);
    return $products;
}

/**
 * Importa lista de produse in products.json.
 *
 * @return array{ok:bool,imported:int,total:int,message:string,catalog:bool,job?:string}
 */
function blu_import_products_json(array $data, bool $replace = false): array
{
    if (blu_json_is_catalog_format($data)) {
        $jobId = blu_create_import_job($data);
        $entries = blu_catalog_flatten_entries($data);
        return [
            'ok' => true,
            'imported' => 0,
            'total' => count($entries),
            'message' => 'Catalog detectat. Continua importul cu RapidAPI.',
            'catalog' => true,
            'job' => $jobId,
        ];
    }

    $incoming = blu_normalize_products_from_json($data);
    if ($incoming === []) {
        return [
            'ok' => false,
            'imported' => 0,
            'total' => 0,
            'message' => 'Nu s-au gasit produse valide in JSON.',
            'catalog' => false,
        ];
    }

    $existing = $replace ? [] : blu_load_products();
    $incoming = blu_assign_product_ids($incoming, $existing);

    $byKey = [];
    foreach ($existing as $row) {
        $k = (string)($row['id'] ?? $row['randomn_id'] ?? '');
        if ($k !== '') {
            $byKey[$k] = $row;
        }
    }
    $added = 0;
    foreach ($incoming as $row) {
        $k = (string)($row['id'] ?? '');
        if (!isset($byKey[$k])) {
            $added++;
        }
        $byKey[$k] = array_merge($byKey[$k] ?? [], $row, ['updated_at' => date('Y-m-d H:i:s')]);
    }

    blu_save_products(array_values($byKey));

    return [
        'ok' => true,
        'imported' => $added,
        'total' => count($byKey),
        'message' => "Import JSON: {$added} produse noi, " . count($byKey) . " total.",
        'catalog' => false,
    ];
}

/** Sincronizeaza cartele importate (cu API) in products.json pentru pagina Produse. */
function blu_sync_imported_cards_to_products(): int
{
    $cards = blu_read_json_file(blu_imported_cards_file(), []);
    if (!is_array($cards)) {
        $cards = [];
    }

    $existing = blu_load_products();
    $byOem = [];
    foreach ($existing as $row) {
        $k = strtoupper(trim((string)($row['cod_oem'] ?? '')));
        if ($k !== '') {
            $byOem[$k] = $row;
        }
    }

    $maxId = 0;
    foreach ($existing as $row) {
        $maxId = max($maxId, (int)($row['id'] ?? $row['randomn_id'] ?? 0));
    }

    $updated = 0;
    foreach ($cards as $card) {
        if (!is_array($card) || ($card['status'] ?? '') !== 'imported') {
            continue;
        }
        $oem = strtoupper(trim((string)($card['cod_oem'] ?? '')));
        if ($oem === '') {
            continue;
        }
        $subCategory = trim((string)($card['sub_category'] ?? $card['pieseauto_category'] ?? $card['category_name'] ?? ''));

        // Forteaza formatul 1:1 (model cartela) pe titlu + descriere, daca lipseste
        // sau descrierea nu respecta modelul standard Blue-Car.
        $nume = (string)($card['title'] ?? 'Piesa auto');
        $descriere = (string)($card['descriere'] ?? '');
        if (
            function_exists('blu_card_template_format')
            && function_exists('blu_description_uses_card_template')
            && !blu_description_uses_card_template($descriere)
        ) {
            $formatted = blu_card_template_format([
                'part_name' => $subCategory !== '' ? $subCategory : ($nume !== '' ? $nume : 'Piesa auto'),
                'brand' => (string)($card['marca_masina'] ?? ''),
                'model' => (string)($card['model'] ?? ''),
                'oem_codes' => (string)($card['coduri_oem'] ?? $card['cod_oem'] ?? ''),
                'internal_code' => (string)($card['cod_articol'] ?? ''),
            ]);
            if (trim($nume) === '' || $nume === 'Piesa auto') {
                $nume = $formatted['title'];
            }
            $descriere = $formatted['description'];
        }

        $row = [
            'nume' => $nume,
            'cod_oem' => (string)($card['cod_oem'] ?? ''),
            'categorie' => $subCategory,
            'pret' => '',
            'stoc' => '1',
            'descriere' => $descriere,
            'imagine' => (string)($card['image'] ?? ''),
            'marca_masina' => (string)($card['marca_masina'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($byOem[$oem])) {
            // Nu suprascrie valorile existente (pret de baza, imagine manuala, etc.)
            // cu valori goale venite din cartela importata.
            foreach ($row as $k => $v) {
                if ($v === '' || $v === null) {
                    continue;
                }
                $byOem[$oem][$k] = $v;
            }
            $updated++;
        } else {
            $maxId++;
            $byOem[$oem] = array_merge($row, ['id' => $maxId, 'randomn_id' => $maxId]);
            $updated++;
        }
    }

    if ($updated > 0) {
        blu_save_products(array_values($byOem));
    }
    return $updated;
}

/**
 * Sterge un produs din TOATE sursele (products.json + cartele importate dupa OEM
 * + MySQL daca e disponibil). Necesar pentru ca sincronizarea automata
 * (blu_sync_imported_cards_to_products) sa NU re-adauge produsul sters.
 *
 * @return array{removed_products:int,removed_cards:int,removed_db:int,oems:array<int,string>}
 */
function blu_delete_product_everywhere(int $id): array
{
    $removedProducts = 0;
    $removedCards = 0;
    $removedDb = 0;
    $oems = [];

    // 1) products.json — sterge dupa id si retine codul OEM.
    $kept = [];
    foreach (blu_load_products() as $p) {
        $pid = (int)($p['id'] ?? $p['randomn_id'] ?? 0);
        if ($pid === $id) {
            $removedProducts++;
            $oem = strtoupper(trim((string)($p['cod_oem'] ?? '')));
            if ($oem !== '') {
                $oems[$oem] = true;
            }
        } else {
            $kept[] = $p;
        }
    }
    blu_save_products($kept);

    // 2) catalog_imported_cards.json — sterge cardurile cu acelasi OEM,
    //    altfel sincronizarea le re-creeaza la urmatoarea incarcare.
    if ($oems && function_exists('blu_imported_cards_file')) {
        $file = blu_imported_cards_file();
        $cards = blu_read_json_file($file, []);
        if (is_array($cards) && $cards) {
            $keptCards = [];
            foreach ($cards as $card) {
                $oem = is_array($card) ? strtoupper(trim((string)($card['cod_oem'] ?? ''))) : '';
                if ($oem !== '' && isset($oems[$oem])) {
                    $removedCards++;
                    continue;
                }
                $keptCards[] = $card;
            }
            if ($removedCards > 0) {
                blu_write_json_file($file, array_values($keptCards));
            }
        }
    }

    // 3) MySQL (best-effort, doar daca e configurat si accesibil).
    if ($oems && function_exists('blu_db_products_bootstrap')) {
        try {
            $db = blu_db_products_bootstrap();
            if ($db && !empty($db['pdo']) && $db['pdo'] instanceof PDO) {
                $stmt = $db['pdo']->prepare('DELETE FROM `products` WHERE UPPER(TRIM(`cod_oem`)) = :oem');
                foreach (array_keys($oems) as $oem) {
                    $stmt->execute([':oem' => $oem]);
                    $removedDb += $stmt->rowCount();
                }
            }
        } catch (Throwable $e) {
            // DB indisponibil sau alta coloana — ignoram, sursa principala e JSON.
        }
    }

    return [
        'removed_products' => $removedProducts,
        'removed_cards' => $removedCards,
        'removed_db' => $removedDb,
        'oems' => array_keys($oems),
    ];
}

/**
 * Goleste fisierele de scanare robot (lista „deja scanate”, stare, snapshot-uri).
 */
function blu_clear_robot_scan_data(): array
{
    $messages = [];
    $robotDir = blu_project_root() . DIRECTORY_SEPARATOR . 'robot';

    $scanRemoved = 0;
    foreach (glob($robotDir . DIRECTORY_SEPARATOR . 'scanate_*.json') ?: [] as $file) {
        if (@unlink($file)) {
            $scanRemoved++;
        }
    }
    if ($scanRemoved > 0) {
        $messages[] = "Robot scanate: {$scanRemoved} liste sterse";
    }

    $stateFile = $robotDir . DIRECTORY_SEPARATOR . 'robot_state.json';
    blu_write_json_file($stateFile, [
        'status_clienti' => [],
        'jurnal_clienti' => [],
        'step_counters' => [],
        'running' => [],
        'scan_active' => [],
        'active_cont_id' => '',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $messages[] = 'Robot: stare resetata';

    $snapBase = blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_snapshots';
    $snapRemoved = 0;
    if (is_dir($snapBase)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($snapBase, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && @unlink($item->getPathname())) {
                $snapRemoved++;
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }
    }
    if ($snapRemoved > 0) {
        $messages[] = "Snapshot-uri robot: {$snapRemoved} fisiere sterse";
    }

    return $messages;
}

/**
 * Goleste tot: MySQL, cartele importate, produse JSON, jurnal robot, cache API, job-uri catalog.
 *
 * @return array{ok:bool,message:string}
 */
function blu_clear_all_product_data(): array
{
    $messages = [];

    $db = blu_db_products_bootstrap();
    if ($db) {
        try {
            $db['pdo']->exec('DELETE FROM `products`');
            $messages[] = 'MySQL: tabel products golit';
        } catch (Throwable $e) {
            $messages[] = 'MySQL: ' . $e->getMessage();
        }
    } else {
        $messages[] = 'MySQL: neconfigurat (doar JSON sters)';
    }

    $files = [
        blu_products_json_file(),
        blu_admin_products_file(),
        blu_imported_cards_file(),
        blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_feed.json',
        blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_stats.json',
        blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_no_oem.json',
        blu_data_dir() . DIRECTORY_SEPARATOR . 'pieseauto_published.json',
    ];
    foreach ($files as $file) {
        blu_write_json_file($file, []);
    }
    $messages[] = 'JSON: produse, importate, robot, PieseAuto publicate';

    $catalogAuto = blu_project_root() . DIRECTORY_SEPARATOR . 'robot' . DIRECTORY_SEPARATOR . 'catalog_auto.json';
    if (is_file($catalogAuto)) {
        blu_write_json_file($catalogAuto, []);
        $messages[] = 'Robot: catalog_auto.json golit';
    }

    $messages = array_merge($messages, blu_clear_robot_scan_data());

    $uploadDir = blu_uploads_dir();
    $jobsRemoved = 0;
    foreach (glob($uploadDir . DIRECTORY_SEPARATOR . 'job_*.json') ?: [] as $jobFile) {
        if (@unlink($jobFile)) {
            $jobsRemoved++;
        }
    }
    if ($jobsRemoved > 0) {
        $messages[] = "Job-uri catalog: {$jobsRemoved} sterse";
    }

    $cacheDir = blu_cache_dir();
    $cacheRemoved = 0;
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        if (@unlink($file)) {
            $cacheRemoved++;
        }
    }
    $autodocDir = blu_cache_dir() . DIRECTORY_SEPARATOR . 'autodoc';
    if (is_dir($autodocDir)) {
        foreach (glob($autodocDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $cacheRemoved++;
            }
        }
    }
    $messages[] = 'Cache RapidAPI/Autodoc: ' . $cacheRemoved . ' fisiere';

    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['pending_catalog_job']);
    }

    return [
        'ok' => true,
        'message' => implode('. ', $messages),
    ];
}
