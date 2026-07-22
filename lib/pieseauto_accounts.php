<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/catalog_import.php';

function blu_pieseauto_accounts_json_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'pieseauto_accounts.json';
}

function blu_pieseauto_pdo(): ?PDO
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
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo) {
        blu_pieseauto_ensure_table($pdo);
    }

    return $pdo;
}

function blu_pieseauto_ensure_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pieseauto` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `randomn_id` INT UNSIGNED NOT NULL,
        `id_users` INT UNSIGNED NOT NULL DEFAULT 0,
        `company_name` VARCHAR(255) NULL DEFAULT NULL,
        `email` VARCHAR(255) NOT NULL DEFAULT '',
        `pas` VARCHAR(255) NOT NULL DEFAULT '',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_randomn_id` (`randomn_id`),
        KEY `idx_id_users` (`id_users`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function blu_pieseauto_admin_user_id(): int
{
    $admin = $_SESSION['admin'] ?? null;
    return is_array($admin) ? (int)($admin['id'] ?? 0) : 0;
}

/** @return list<array> */
function blu_load_pieseauto_accounts_json(): array
{
    $rows = blu_read_json_file(blu_pieseauto_accounts_json_file(), []);
    if (!is_array($rows)) {
        return [];
    }
    return array_values(array_filter($rows, 'is_array'));
}

/** @return list<array> */
function blu_load_pieseauto_accounts(): array
{
    $pdo = blu_pieseauto_pdo();
    $adminId = blu_pieseauto_admin_user_id();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                'SELECT randomn_id, id_users, company_name, email, pas, created_at, updated_at
                 FROM pieseauto WHERE id_users = :uid ORDER BY updated_at DESC, id DESC'
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
        blu_load_pieseauto_accounts_json(),
        static function (array $row) use ($adminId): bool {
            $uid = (int)($row['id_users'] ?? 0);
            return $uid === 0 || $uid === $adminId;
        }
    ));
}

function blu_resolve_pieseauto_company_name(array $row): string
{
    foreach (['company_name', 'name', 'denumire', 'firma'] as $key) {
        $val = trim((string)($row[$key] ?? ''));
        if ($val !== '') {
            return $val;
        }
    }
    return '';
}

/** @return array<string, array> */
function blu_pieseauto_accounts_for_ui(): array
{
    $accounts = [];
    foreach (blu_load_pieseauto_accounts() as $row) {
        $accountId = $row['randomn_id'] ?? $row['id'] ?? null;
        if ($accountId === null || $accountId === '') {
            continue;
        }
        $companyName = blu_resolve_pieseauto_company_name($row);
        $accounts['client_' . $accountId] = [
            'id' => (string)$accountId,
            'label' => $companyName !== '' ? $companyName : ('Cont: ' . ($row['email'] ?? '')),
            'name' => $companyName,
            'email' => (string)($row['email'] ?? ''),
            'pass' => (string)($row['pas'] ?? ''),
        ];
    }
    return $accounts;
}

function blu_save_pieseauto_account(array $data): array
{
    $companyName = trim((string)($data['company_name'] ?? $data['name'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $pass = (string)($data['pas'] ?? $data['password'] ?? '');
    $rid = trim((string)($data['ridusers'] ?? $data['randomn_id'] ?? ''));
    $adminId = blu_pieseauto_admin_user_id();

    if ($companyName === '') {
        return ['success' => false, 'message' => 'Introdu denumirea firmei.'];
    }
    if ($email === '' || $pass === '') {
        return ['success' => false, 'message' => 'Completează emailul și parola site-ului.'];
    }

    $randomnId = $rid !== '' ? (int)$rid : random_int(100, 9999);
    $payload = [
        'randomn_id' => $randomnId,
        'id_users' => $adminId,
        'company_name' => $companyName,
        'email' => $email,
        'pas' => $pass,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $pdo = blu_pieseauto_pdo();
    if ($pdo) {
        try {
            if ($rid !== '') {
                $check = $pdo->prepare(
                    'SELECT randomn_id FROM pieseauto WHERE randomn_id = :rid AND id_users = :uid LIMIT 1'
                );
                $check->execute(['rid' => $randomnId, 'uid' => $adminId]);
                if (!$check->fetchColumn()) {
                    return ['success' => false, 'message' => 'Contul nu a fost găsit.'];
                }

                $stmt = $pdo->prepare(
                    'UPDATE pieseauto SET company_name = :company_name, email = :email, pas = :pas, updated_at = NOW()
                     WHERE randomn_id = :rid AND id_users = :uid'
                );
                $stmt->execute([
                    'company_name' => $companyName,
                    'email' => $email,
                    'pas' => $pass,
                    'rid' => $randomnId,
                    'uid' => $adminId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO pieseauto (randomn_id, id_users, company_name, email, pas)
                     VALUES (:randomn_id, :id_users, :company_name, :email, :pas)'
                );
                $stmt->execute([
                    'randomn_id' => $randomnId,
                    'id_users' => $adminId,
                    'company_name' => $companyName,
                    'email' => $email,
                    'pas' => $pass,
                ]);
            }
            return ['success' => true, 'message' => 'Cont salvat cu succes.', 'randomn_id' => $randomnId];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Eroare MySQL: ' . $e->getMessage()];
        }
    }

    $rows = blu_load_pieseauto_accounts_json();
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

    if (!blu_write_json_file(blu_pieseauto_accounts_json_file(), $rows)) {
        return ['success' => false, 'message' => 'Nu s-a putut salva fișierul local.'];
    }

    return ['success' => true, 'message' => 'Cont salvat cu succes.', 'randomn_id' => $randomnId];
}

function blu_delete_pieseauto_account(string $randomnId): array
{
    $rid = (int)trim($randomnId);
    if ($rid <= 0) {
        return ['success' => false, 'message' => 'Cont invalid.'];
    }

    $adminId = blu_pieseauto_admin_user_id();
    $pdo = blu_pieseauto_pdo();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM pieseauto WHERE randomn_id = :rid AND id_users = :uid'
            );
            $stmt->execute(['rid' => $rid, 'uid' => $adminId]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Contul nu a fost găsit.'];
            }
            return ['success' => true, 'message' => 'Cont șters cu succes.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Eroare MySQL: ' . $e->getMessage()];
        }
    }

    $rows = blu_load_pieseauto_accounts_json();
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function (array $row) use ($rid, $adminId): bool {
        $matchId = (int)($row['randomn_id'] ?? 0) === $rid;
        $matchUser = (int)($row['id_users'] ?? 0) === $adminId || (int)($row['id_users'] ?? 0) === 0;
        return !($matchId && $matchUser);
    }));

    if ($before === count($rows)) {
        return ['success' => false, 'message' => 'Contul nu a fost găsit.'];
    }

    if (!blu_write_json_file(blu_pieseauto_accounts_json_file(), $rows)) {
        return ['success' => false, 'message' => 'Nu s-a putut actualiza fișierul local.'];
    }

    return ['success' => true, 'message' => 'Cont șters cu succes.'];
}
