<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/catalog_import.php';

function blu_gbg_suppliers_json_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'gbg_suppliers.json';
}

function blu_gbg_suppliers_pdo(): ?PDO
{
    static $pdo = null;
    static $checked = false;
    if ($checked) {
        return $pdo;
    }
    $checked = true;

    $settings = blu_db_settings_from_env();
    if (!$settings) {
        return null;
    }

    $dbFile = blu_project_root() . DIRECTORY_SEPARATOR . 'prd' . DIRECTORY_SEPARATOR . 'db_products.php';
    if (is_file($dbFile)) {
        require_once $dbFile;
        if (function_exists('dbProductsConnect')) {
            $pdo = dbProductsConnect($settings);
        }
    }

    if (!$pdo) {
        try {
            $pdo = new PDO(
                'mysql:host=' . $settings['db_host'] . ';dbname=' . $settings['db_name'] . ';charset=utf8mb4',
                $settings['db_user'],
                $settings['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `gbg_suppliers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `randomn_id` INT UNSIGNED NOT NULL,
            `id_users` INT UNSIGNED NOT NULL DEFAULT 0,
            `supplier_name` VARCHAR(255) NOT NULL DEFAULT '',
            `site_url` VARCHAR(512) NOT NULL DEFAULT '',
            `cont_id` VARCHAR(64) NOT NULL DEFAULT '',
            `user` VARCHAR(255) NOT NULL DEFAULT '',
            `pas` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_randomn_id` (`randomn_id`),
            KEY `idx_cont_id` (`cont_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $pdo->exec('ALTER TABLE `gbg_suppliers` ADD COLUMN `site_url` VARCHAR(512) NOT NULL DEFAULT \'\' AFTER `supplier_name`');
        } catch (Throwable $e) {
            // coloana există deja
        }
    }

    return $pdo;
}

/** Catalog furnizori web (site + login + parolă) — import inițial. */
function blu_web_suppliers_catalog(): array
{
    return [
        [
            'randomn_id' => 1481,
            'supplier_name' => 'GBG e-shop',
            'site_url' => 'http://www.gbg-eshop.gr/demo/Authenticate3.aspx',
            'cont_id' => 'gbg_user_01',
            'user' => '21253',
            'pas' => 'h5MXjFP9',
        ],
        [
            'randomn_id' => 2001,
            'supplier_name' => 'Oepiesa / Bardia Auto',
            'site_url' => 'https://www.bardiaauto.ro',
            'cont_id' => 'furnizor_oepiesa',
            'user' => 'mariusdum25@gmail.com',
            'pas' => 'Bardiaauto2006',
        ],
        [
            'randomn_id' => 2002,
            'supplier_name' => 'Autonet',
            'site_url' => 'http://dvsews.autonet.ro',
            'cont_id' => 'furnizor_autonet',
            'user' => 'bluecar1',
            'pas' => '',
        ],
        [
            'randomn_id' => 2003,
            'supplier_name' => 'Webcat Solutions (Car Solution)',
            'site_url' => 'https://www.webcat-solutions.com',
            'cont_id' => 'furnizor_webcat',
            'user' => 'bluecar178131738.dm',
            'pas' => '',
        ],
        [
            'randomn_id' => 2004,
            'supplier_name' => 'Materom (portal)',
            'site_url' => 'https://portal.materom.ro',
            'cont_id' => 'furnizor_materom',
            'user' => '8490178131738dm',
            'pas' => '',
        ],
        [
            'randomn_id' => 2005,
            'supplier_name' => 'Materom Shop',
            'site_url' => 'https://shop.materom.ro',
            'cont_id' => 'furnizor_materom_shop',
            'user' => 'mariusdum25@gmail.com',
            'pas' => 'Marius2021.',
        ],
        [
            'randomn_id' => 2006,
            'supplier_name' => 'InterCars',
            'site_url' => 'https://account.intercars.eu',
            'cont_id' => 'furnizor_intercars',
            'user' => 'mariusdum25@gmail.com',
            'pas' => '78131738.Dm!',
        ],
        [
            'randomn_id' => 2007,
            'supplier_name' => 'Euroestcar',
            'site_url' => 'http://eshop.euroestcar.ro',
            'cont_id' => 'furnizor_euroestcar',
            'user' => 'bluecar',
            'pas' => '',
        ],
        [
            'randomn_id' => 2008,
            'supplier_name' => 'Conexdist',
            'site_url' => 'https://eshop.conexdist.ro',
            'cont_id' => 'furnizor_conexdist',
            'user' => 'mariusdum25@gmail.com',
            'pas' => '78131738',
        ],
        [
            'randomn_id' => 2009,
            'supplier_name' => 'Ajsparts (cont 000078)',
            'site_url' => 'https://www.ajsparts.pl',
            'cont_id' => 'furnizor_ajsparts_1',
            'user' => '000078',
            'pas' => 'EXPORT',
        ],
        [
            'randomn_id' => 2010,
            'supplier_name' => 'Ajsparts (cont 006376)',
            'site_url' => 'https://www.ajsparts.pl',
            'cont_id' => 'furnizor_ajsparts_2',
            'user' => '006376',
            'pas' => '',
        ],
        [
            'randomn_id' => 2011,
            'supplier_name' => 'Elite (Elit)',
            'site_url' => 'https://www.elit.ro',
            'cont_id' => 'furnizor_elite',
            'user' => '64717087',
            'pas' => 'wJ7EHJZ',
        ],
    ];
}

/** Importă / actualizează furnizorii din catalog (după cont_id). */
function blu_import_web_suppliers_catalog(bool $overwritePasswords = true): array
{
    $adminId = blu_gbg_admin_user_id();
    $catalog = blu_web_suppliers_catalog();
    $existing = [];
    foreach (blu_load_gbg_suppliers_json() as $row) {
        $cid = trim((string)($row['cont_id'] ?? ''));
        if ($cid !== '') {
            $existing[$cid] = $row;
        }
    }

    $imported = 0;
    foreach ($catalog as $item) {
        $contId = trim((string)($item['cont_id'] ?? ''));
        if ($contId === '') {
            continue;
        }
        $prev = $existing[$contId] ?? null;
        $row = [
            'randomn_id' => (int)($item['randomn_id'] ?? ($prev['randomn_id'] ?? random_int(2000, 9999))),
            'id_users' => $adminId > 0 ? $adminId : (int)($prev['id_users'] ?? 1),
            'supplier_name' => (string)($item['supplier_name'] ?? ''),
            'site_url' => (string)($item['site_url'] ?? ''),
            'cont_id' => $contId,
            'user' => (string)($item['user'] ?? ''),
            'pas' => (string)($item['pas'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($prev && !$overwritePasswords && ($row['pas'] === '' || $row['pas'] === '(nu este vizibilă)')) {
            $row['pas'] = (string)($prev['pas'] ?? '');
        }
        if (!$prev) {
            $row['created_at'] = date('Y-m-d H:i:s');
            $imported++;
        }
        $existing[$contId] = $row;
    }

    $rows = array_values($existing);
    if (!blu_write_json_file(blu_gbg_suppliers_json_file(), $rows)) {
        return ['success' => false, 'message' => 'Nu s-a putut scrie gbg_suppliers.json', 'count' => 0];
    }

    $pdo = blu_gbg_suppliers_pdo();
    if ($pdo) {
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO gbg_suppliers (randomn_id, id_users, supplier_name, site_url, cont_id, user, pas)
                     VALUES (:randomn_id, :id_users, :supplier_name, :site_url, :cont_id, :user, :pas)
                     ON DUPLICATE KEY UPDATE
                       supplier_name = VALUES(supplier_name),
                       site_url = VALUES(site_url),
                       cont_id = VALUES(cont_id),
                       user = VALUES(user),
                       pas = IF(VALUES(pas) = \'\', pas, VALUES(pas)),
                       updated_at = NOW()'
                );
                $stmt->execute([
                    'randomn_id' => (int)$row['randomn_id'],
                    'id_users' => (int)$row['id_users'],
                    'supplier_name' => $row['supplier_name'],
                    'site_url' => $row['site_url'] ?? '',
                    'cont_id' => $row['cont_id'],
                    'user' => $row['user'],
                    'pas' => $row['pas'],
                ]);
            } catch (Throwable $e) {
                // JSON e sursa principală pe Laragon fără MySQL
            }
        }
    }

    foreach ($catalog as $item) {
        $cid = trim((string)($item['cont_id'] ?? ''));
        $url = trim((string)($item['site_url'] ?? ''));
        if ($cid !== '' && $url !== '' && is_file(__DIR__ . '/robot_workflow.php')) {
            require_once __DIR__ . '/robot_workflow.php';
            blu_sync_supplier_workflow_urls($cid, $url);
        }
    }

    return [
        'success' => true,
        'message' => 'Importate/actualizate ' . count($catalog) . ' furnizori web (' . $imported . ' noi).',
        'count' => count($catalog),
    ];
}

function blu_sync_supplier_workflow_urls(string $contId, string $siteUrl): void
{
    $siteUrl = trim($siteUrl);
    if ($siteUrl === '' || !is_file(__DIR__ . '/robot_workflow.php')) {
        return;
    }
    require_once __DIR__ . '/robot_workflow.php';
    $wf = blu_load_robot_workflow($contId);
    $wf['login_url'] = $siteUrl;
    if (empty($wf['html_pages'])) {
        $wf = blu_normalize_robot_workflow($wf);
    }
    foreach ($wf['html_pages'] as &$page) {
        if (($page['key'] ?? '') === 'login' && !empty($page['url'])) {
            $page['url'] = $siteUrl;
        }
    }
    unset($page);
    blu_save_robot_workflow($contId, $wf);
}

function blu_gbg_admin_user_id(): int
{
    $admin = $_SESSION['admin'] ?? null;
    return is_array($admin) ? (int)($admin['id'] ?? 0) : 0;
}

/** @return list<array> */
function blu_load_gbg_suppliers_json(): array
{
    $rows = blu_read_json_file(blu_gbg_suppliers_json_file(), []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

/** @return list<array> */
function blu_load_gbg_suppliers(): array
{
    $adminId = blu_gbg_admin_user_id();
    $pdo = blu_gbg_suppliers_pdo();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                'SELECT randomn_id, supplier_name, site_url, cont_id, user, pas, created_at, updated_at
                 FROM gbg_suppliers WHERE id_users = :uid ORDER BY updated_at DESC, id DESC'
            );
            $stmt->execute(['uid' => $adminId]);
            $rows = $stmt->fetchAll();
            if ($rows) {
                return $rows;
            }
        } catch (Throwable $e) {
            // fallback JSON
        }
    }

    return array_values(array_filter(
        blu_load_gbg_suppliers_json(),
        static fn(array $row): bool => (int)($row['id_users'] ?? 0) === 0 || (int)($row['id_users'] ?? 0) === $adminId
    ));
}

/** @return array{scanned_count:int,urls_count:int,coduri_count:int,updated_at:string,last_status:string,last_message:string,last_at:string} */
function blu_supplier_scan_progress(string $contId): array
{
    $contId = trim($contId);
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $contId) ?: 'default';
    $robotDir = blu_project_root() . DIRECTORY_SEPARATOR . 'robot';
    $scanFile = $robotDir . DIRECTORY_SEPARATOR . 'scanate_' . $safe . '.json';

    $urlsCount = 0;
    $coduriCount = 0;
    $updatedAt = '';
    if (is_file($scanFile)) {
        $scanData = blu_read_json_file($scanFile, []);
        if (is_array($scanData)) {
            $urlsCount = is_array($scanData['urls'] ?? null) ? count($scanData['urls']) : 0;
            $coduriCount = is_array($scanData['coduri'] ?? null) ? count($scanData['coduri']) : 0;
            $updatedAt = (string)($scanData['updated_at'] ?? '');
        }
    }

    $lastStatus = '';
    $lastMessage = '';
    $lastAt = '';
    $feed = blu_read_json_file(blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_feed.json', []);
    if (is_array($feed)) {
        foreach ($feed as $event) {
            if (!is_array($event)) {
                continue;
            }
            if (trim((string)($event['cont_id'] ?? '')) !== $contId) {
                continue;
            }
            $lastStatus = (string)($event['status'] ?? '');
            $lastMessage = (string)($event['message'] ?? '');
            $lastAt = (string)($event['time'] ?? '');
            break;
        }
    }

    return [
        'scanned_count' => max($urlsCount, $coduriCount),
        'urls_count' => $urlsCount,
        'coduri_count' => $coduriCount,
        'updated_at' => $updatedAt,
        'last_status' => $lastStatus,
        'last_message' => $lastMessage,
        'last_at' => $lastAt,
    ];
}

/** @return list<array> */
function blu_gbg_suppliers_for_ui(): array
{
    $items = [];
    foreach (blu_load_gbg_suppliers() as $row) {
        $rid = $row['randomn_id'] ?? null;
        if ($rid === null || $rid === '') {
            continue;
        }
        $name = trim((string)($row['supplier_name'] ?? ''));
        $contId = trim((string)($row['cont_id'] ?? ''));
        $siteUrl = trim((string)($row['site_url'] ?? ''));
        $pass = (string)($row['pas'] ?? '');
        $progress = $contId !== '' ? blu_supplier_scan_progress($contId) : [
            'scanned_count' => 0,
            'urls_count' => 0,
            'coduri_count' => 0,
            'updated_at' => '',
            'last_status' => '',
            'last_message' => '',
            'last_at' => '',
        ];
        $items[] = [
            'id' => (string)$rid,
            'label' => $name !== '' ? $name : ('Furnizor ' . $contId),
            'supplier_name' => $name,
            'site_url' => $siteUrl,
            'cont_id' => $contId,
            'user' => (string)($row['user'] ?? ''),
            'pass' => $pass,
            'has_password' => $pass !== '',
            'scan' => $progress,
        ];
    }
    return $items;
}

function blu_save_gbg_supplier(array $data): array
{
    $name = trim((string)($data['supplier_name'] ?? $data['company_name'] ?? ''));
    $siteUrl = trim((string)($data['site_url'] ?? $data['website'] ?? $data['site'] ?? ''));
    $contId = trim((string)($data['cont_id'] ?? ''));
    $user = trim((string)($data['user'] ?? $data['email'] ?? $data['login'] ?? ''));
    $pass = (string)($data['pas'] ?? $data['pass'] ?? $data['password'] ?? '');
    $rid = trim((string)($data['ridusers'] ?? $data['randomn_id'] ?? ''));
    $adminId = blu_gbg_admin_user_id();

    if ($name === '') {
        return ['success' => false, 'message' => 'Introdu denumirea furnizorului.'];
    }
    if ($siteUrl === '') {
        return ['success' => false, 'message' => 'Introdu site-ul web (URL login sau magazin).'];
    }
    if ($contId === '') {
        return ['success' => false, 'message' => 'Introdu cont_id (ex: furnizor_autonet).'];
    }
    if ($user === '') {
        return ['success' => false, 'message' => 'Introdu login-ul (user sau email).'];
    }
    if ($siteUrl !== '' && !preg_match('#^https?://#i', $siteUrl)) {
        $siteUrl = 'https://' . ltrim($siteUrl, '/');
    }

    $randomnId = $rid !== '' ? (int)$rid : random_int(100, 9999);
    $payload = [
        'randomn_id' => $randomnId,
        'id_users' => $adminId,
        'supplier_name' => $name,
        'site_url' => $siteUrl,
        'cont_id' => $contId,
        'user' => $user,
        'pas' => $pass,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $pdo = blu_gbg_suppliers_pdo();
    if ($pdo) {
        try {
            if ($rid !== '') {
                $check = $pdo->prepare('SELECT randomn_id FROM gbg_suppliers WHERE randomn_id = :rid AND id_users = :uid LIMIT 1');
                $check->execute(['rid' => $randomnId, 'uid' => $adminId]);
                if (!$check->fetchColumn()) {
                    return ['success' => false, 'message' => 'Furnizorul nu a fost găsit.'];
                }
                $stmt = $pdo->prepare(
                    'UPDATE gbg_suppliers SET supplier_name = :supplier_name, site_url = :site_url, cont_id = :cont_id, user = :user, pas = :pas, updated_at = NOW()
                     WHERE randomn_id = :rid AND id_users = :uid'
                );
                $stmt->execute([
                    'supplier_name' => $name,
                    'site_url' => $siteUrl,
                    'cont_id' => $contId,
                    'user' => $user,
                    'pas' => $pass,
                    'rid' => $randomnId,
                    'uid' => $adminId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO gbg_suppliers (randomn_id, id_users, supplier_name, site_url, cont_id, user, pas)
                     VALUES (:randomn_id, :id_users, :supplier_name, :site_url, :cont_id, :user, :pas)'
                );
                $stmt->execute([
                    'randomn_id' => $randomnId,
                    'id_users' => $adminId,
                    'supplier_name' => $name,
                    'site_url' => $siteUrl,
                    'cont_id' => $contId,
                    'user' => $user,
                    'pas' => $pass,
                ]);
            }
            if ($siteUrl !== '' && is_file(__DIR__ . '/robot_workflow.php')) {
                require_once __DIR__ . '/robot_workflow.php';
                blu_sync_supplier_workflow_urls($contId, $siteUrl);
            }
            return ['success' => true, 'message' => 'Furnizor salvat.', 'randomn_id' => $randomnId];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Eroare MySQL: ' . $e->getMessage()];
        }
    }

    $rows = blu_load_gbg_suppliers_json();
    $updated = false;
    foreach ($rows as &$row) {
        if ((int)($row['randomn_id'] ?? 0) === $randomnId && (int)($row['id_users'] ?? 0) === $adminId) {
            $row = array_merge($row, $payload);
            $updated = true;
            break;
        }
    }
    unset($row);
    if (!$updated) {
        $payload['created_at'] = date('Y-m-d H:i:s');
        $rows[] = $payload;
    }
    if (!blu_write_json_file(blu_gbg_suppliers_json_file(), $rows)) {
        return ['success' => false, 'message' => 'Nu s-a putut salva fișierul local.'];
    }
    if ($siteUrl !== '' && is_file(__DIR__ . '/robot_workflow.php')) {
        require_once __DIR__ . '/robot_workflow.php';
        blu_sync_supplier_workflow_urls($contId, $siteUrl);
    }
    return ['success' => true, 'message' => 'Furnizor salvat.', 'randomn_id' => $randomnId];
}

function blu_delete_gbg_supplier(string $randomnId): array
{
    $rid = (int)trim($randomnId);
    if ($rid <= 0) {
        return ['success' => false, 'message' => 'Furnizor invalid.'];
    }
    $adminId = blu_gbg_admin_user_id();
    $pdo = blu_gbg_suppliers_pdo();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('DELETE FROM gbg_suppliers WHERE randomn_id = :rid AND id_users = :uid');
            $stmt->execute(['rid' => $rid, 'uid' => $adminId]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Furnizorul nu a fost găsit.'];
            }
            return ['success' => true, 'message' => 'Furnizor șters.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Eroare MySQL: ' . $e->getMessage()];
        }
    }

    $rows = blu_load_gbg_suppliers_json();
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function (array $row) use ($rid, $adminId): bool {
        $match = (int)($row['randomn_id'] ?? 0) === $rid && ((int)($row['id_users'] ?? 0) === $adminId || (int)($row['id_users'] ?? 0) === 0);
        return !$match;
    }));
    if ($before === count($rows)) {
        return ['success' => false, 'message' => 'Furnizorul nu a fost găsit.'];
    }
    if (!blu_write_json_file(blu_gbg_suppliers_json_file(), $rows)) {
        return ['success' => false, 'message' => 'Nu s-a putut actualiza fișierul.'];
    }
    return ['success' => true, 'message' => 'Furnizor șters.'];
}

function blu_robot_base_url(): string
{
    require_once __DIR__ . '/robot_config.php';
    return blu_robot_furnizori_base_url();
}
