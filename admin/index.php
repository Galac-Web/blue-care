<?php
declare(strict_types=1);

define('BLU_ADMIN_CONTEXT', true);

// Versiune unica pentru cache-busting la toate asset-urile adminului.
define('BLU_ADMIN_ASSET_VER', '20260706a');

// Cookie de sesiune securizat: HttpOnly + SameSite, Secure pe HTTPS.
$bluSecureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $bluSecureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Headere de securitate pentru tot adminul.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$adminRoot = __DIR__;
$projectRoot = dirname($adminRoot);
$resolvedRoot = realpath($projectRoot);
if ($resolvedRoot !== false) {
    $projectRoot = $resolvedRoot;
}

$assetBase = '../assets';
$GLOBALS['blu_admin_web_base'] = '../';
$adminSelf = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin')), '/');
if ($adminSelf === '' || $adminSelf === '.') {
    $adminSelf = '/admin';
}

$dataDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
require_once $projectRoot . '/lib/admin_web_base.php';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

$csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf'] = $csrf;

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function path_join(string $base, string $relative): string
{
    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function read_json(string $file, $fallback = [])
{
    if (!is_file($file)) {
        return $fallback;
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $fallback;
}

function write_json(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    // Scriere atomica (tmp + rename) — protejeaza users.json/products.json de corupere.
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $file)) {
        @unlink($file);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
    }
    return true;
}

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

function next_id(array $items): int
{
    $max = 0;
    foreach ($items as $item) {
        $max = max($max, (int)($item['id'] ?? $item['randomn_id'] ?? 0));
    }
    return $max + 1;
}

function contains_text(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function admin_users_file(string $dataDir): string
{
    return $dataDir . DIRECTORY_SEPARATOR . 'users.json';
}

function load_users(string $dataDir): array
{
    $file = admin_users_file($dataDir);
    $users = rows(read_json($file, []));
    if (!$users) {
        $users = [[
            'id' => 1,
            'name' => 'Administrator',
            'email' => 'admin@local.test',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'permissions' => blu_admin_all_permission_keys(),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]];
        write_json($file, $users);
    }
    return array_map('blu_admin_normalize_user', $users);
}

function blu_admin_parse_permissions_post(): array
{
    $perms = $_POST['permissions'] ?? [];
    if (!is_array($perms)) {
        $perms = [$perms];
    }
    $valid = blu_admin_all_permission_keys();
    return array_values(array_intersect($valid, array_map('strval', $perms)));
}

function blu_admin_find_user_by_id(array $users, int $id): ?array
{
    foreach ($users as $user) {
        if ((int) ($user['id'] ?? 0) === $id) {
            return $user;
        }
    }
    return null;
}

function product_file(string $projectRoot): string
{
    return blu_products_json_file();
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): void
{
    if (!current_admin()) {
        header('Location: ?page=login');
        exit;
    }
}

function redirect_to(string $page, array $query = []): void
{
    $params = array_merge(['page' => $page], $query);
    header('Location: ?' . http_build_query($params));
    exit;
}

function product_display_name(array $product): string
{
    $name = trim((string)($product['nume'] ?? $product['name'] ?? $product['titlu'] ?? $product['title'] ?? ''));
    return $name !== '' ? $name : '-';
}

function product_display_code(array $product): string
{
    $code = trim((string)($product['cod_oem'] ?? $product['cod'] ?? $product['cod_articol'] ?? ''));
    if ($code !== '') {
        return $code;
    }
    $titlu = (string)($product['titlu'] ?? '');
    if (preg_match('/OEM\s+([A-Z0-9]+)/i', $titlu, $m)) {
        return $m[1];
    }
    return '-';
}

function product_display_price(array $product): string
{
    $price = trim((string)($product['pret'] ?? $product['price'] ?? $product['price_ron_display'] ?? ''));
    return $price !== '' ? $price : '-';
}

function product_display_link(array $product): string
{
    return trim((string)($product['link'] ?? $product['source_url'] ?? ''));
}

require_once $projectRoot . '/lib/products_store.php';
require_once $projectRoot . '/lib/products_admin_panel.php';
require_once $projectRoot . '/lib/pieseauto_accounts.php';
require_once $projectRoot . '/lib/gbg_suppliers.php';
require_once $projectRoot . '/lib/admin_nav.php';
require_once $projectRoot . '/lib/admin_section_nav.php';
require_once $projectRoot . '/lib/products_section_nav.php';
require_once $projectRoot . '/lib/commerce_store.php';
require_once $projectRoot . '/lib/commerce_admin_panel.php';
require_once $projectRoot . '/lib/dashboard_admin_panel.php';
require_once $projectRoot . '/lib/messages_admin_panel.php';
require_once $projectRoot . '/lib/admin_permissions.php';
require_once $projectRoot . '/lib/admin_users_panel.php';

$usersFile = admin_users_file($dataDir);
$users = load_users($dataDir);
$productsFile = blu_products_json_file();
$leadsFile = path_join($projectRoot, 'leads.json');
$companyFile = path_join($projectRoot, 'company.json');
$settingsFile = path_join($projectRoot, 'baza_date.json');

$page = (string)($_GET['page'] ?? 'dashboard');
if ($page === 'monitor-robot') {
    $page = 'robot-monitor';
}
if ($page === 'pricing') {
    header('Location: ?page=furnizori&tab=adaos');
    exit;
}
if ($page === 'supplier-search') {
    header('Location: ?page=orders');
    exit;
}
$action = (string)($_POST['action'] ?? '');

if ($page === 'logout') {
    // Logout doar cu token valid — un link extern nu poate deconecta operatorul.
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_GET['t'] ?? ''))) {
        header('Location: ?page=dashboard');
        exit;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cp = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cp['path'], $cp['domain'], $cp['secure'], $cp['httponly']);
    }
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($action !== '' && !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Cerere invalidă.');
}

// ── Protectie brute-force: max 8 incercari esuate / 15 min per IP ──
function blu_admin_login_attempts_file(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'blu_admin_login_attempts.json';
}

function blu_admin_login_blocked(string $ip): bool
{
    $file = blu_admin_login_attempts_file();
    $all = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    $entry = $all[$ip] ?? null;
    if (!is_array($entry)) {
        return false;
    }
    if ((int)($entry['reset_at'] ?? 0) < time()) {
        return false;
    }
    return (int)($entry['count'] ?? 0) >= 8;
}

function blu_admin_login_record_failure(string $ip): void
{
    $file = blu_admin_login_attempts_file();
    $all = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    // Curata intrarile expirate ca fisierul sa nu creasca la nesfarsit.
    foreach ($all as $k => $v) {
        if ((int)($v['reset_at'] ?? 0) < time()) {
            unset($all[$k]);
        }
    }
    $entry = $all[$ip] ?? ['count' => 0, 'reset_at' => time() + 900];
    if ((int)($entry['reset_at'] ?? 0) < time()) {
        $entry = ['count' => 0, 'reset_at' => time() + 900];
    }
    $entry['count'] = (int)($entry['count'] ?? 0) + 1;
    $all[$ip] = $entry;
    @file_put_contents($file, json_encode($all), LOCK_EX);
}

function blu_admin_login_clear_failures(string $ip): void
{
    $file = blu_admin_login_attempts_file();
    $all = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    if (isset($all[$ip])) {
        unset($all[$ip]);
        @file_put_contents($file, json_encode($all), LOCK_EX);
    }
}

function blu_admin_client_ip(): string
{
    // In spatele Cloudflare, REMOTE_ADDR e IP-ul proxy-ului — folosim IP-ul real al clientului.
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

if ($page === 'login' && $action === 'login') {
    $clientIp = blu_admin_client_ip();
    if (blu_admin_login_blocked($clientIp)) {
        flash('Prea multe încercări eșuate. Reîncearcă peste 15 minute.', 'danger');
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        foreach ($users as $user) {
            if (strtolower((string)($user['email'] ?? '')) !== $email) {
                continue;
            }
            // Cont dezactivat → acelasi mesaj generic (fara enumerare de conturi).
            if (($user['status'] ?? 'active') === 'disabled') {
                break;
            }
            if (!password_verify($password, (string)($user['password'] ?? ''))) {
                continue;
            }
            $user = blu_admin_normalize_user($user);
            // Previne session fixation: ID nou de sesiune + token CSRF nou dupa login.
            session_regenerate_id(true);
            $csrf = bin2hex(random_bytes(16));
            $_SESSION['csrf'] = $csrf;
            $_SESSION['admin'] = [
                'id' => $user['id'] ?? 0,
                'name' => $user['name'] ?? 'Admin',
                'email' => $user['email'] ?? $email,
                'role' => $user['role'] ?? 'admin',
                'permissions' => $user['permissions'] ?? blu_admin_all_permission_keys(),
                'status' => $user['status'] ?? 'active',
            ];
            blu_admin_login_clear_failures($clientIp);
            redirect_to(blu_admin_first_allowed_page($_SESSION['admin']));
        }
        if (!current_admin()) {
            blu_admin_login_record_failure($clientIp);
            flash('Email sau parolă greșită.', 'danger');
        }
    }
}

if ($page !== 'login') {
    require_admin();
    $curAdmin = current_admin();
    if ($curAdmin) {
        $freshUser = blu_admin_find_user_by_id($users, (int) ($curAdmin['id'] ?? 0));
        if ($freshUser !== null) {
            $freshUser = blu_admin_normalize_user($freshUser);
            if (($freshUser['status'] ?? 'active') === 'disabled') {
                session_destroy();
                header('Location: ?page=login');
                exit;
            }
            $_SESSION['admin']['permissions'] = $freshUser['permissions'];
            $_SESSION['admin']['role'] = $freshUser['role'];
            $_SESSION['admin']['name'] = $freshUser['name'];
            $_SESSION['admin']['status'] = $freshUser['status'];
        }
    }
    if (($page !== 'logout') && !blu_admin_can_page($page)) {
        flash('Nu ai acces la această secțiune.', 'danger');
        redirect_to(blu_admin_first_allowed_page(current_admin()));
    }
}

$apiAction = (string)($_GET['api_action'] ?? '');
if ($apiAction !== '') {
    // Toate API-urile interne cer autentificare — indiferent de pagina din URL.
    if (!current_admin()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Actiunile care modifica date cer POST + token CSRF valid.
    $mutatingApi = in_array($apiAction, ['enrich_pending', 'finalize_pending', 'robot_clear_no_oem'], true);
    if ($mutatingApi) {
        $apiCsrf = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $apiCsrf)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Cerere invalidă (CSRF).'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if ($apiAction === 'enrich_pending') {
    header('Content-Type: application/json; charset=utf-8');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 5)));
    echo json_encode(blu_enrich_pending_cards($offset, $limit), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($apiAction === 'finalize_pending') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'finalized' => blu_finalize_pending_after_enrich(),
        'pending_remaining' => blu_count_pending_import_cards(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($apiAction === 'robot_feed') {
    require_once $projectRoot . '/lib/robot_feed.php';
    header('Content-Type: application/json; charset=utf-8');
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 60)));
    echo json_encode([
        'ok' => true,
        'feed' => blu_robot_get_feed($limit),
        'stats' => blu_robot_load_stats(),
        'no_oem' => blu_robot_get_no_oem(200),
        'no_oem_count' => blu_robot_count_no_oem(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($apiAction === 'robot_clear_no_oem') {
    require_once $projectRoot . '/lib/robot_feed.php';
    header('Content-Type: application/json; charset=utf-8');
    blu_robot_clear_no_oem();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($apiAction === 'diag_run') {
    require_once $projectRoot . '/lib/api_diagnostics.php';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(blu_diag_run_all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'commerce_export_zip') {
    $export = blu_commerce_export_zip($projectRoot);
    if (!empty($export['ok']) && !empty($export['path']) && is_file($export['path'])) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename((string)$export['path']) . '"');
        header('Content-Length: ' . (string)filesize((string)$export['path']));
        readfile((string)$export['path']);
        exit;
    }
    flash($export['message'] ?? 'Export eșuat.', 'danger');
    redirect_to('backup');
}

if ($action === 'save_api_env') {
    require_once $projectRoot . '/lib/api_diagnostics.php';
    $envInput = is_array($_POST['env'] ?? null) ? $_POST['env'] : [];
    $res = blu_diag_save_env($envInput);
    flash($res['message'], $res['ok'] ? 'success' : 'danger');
    redirect_to('api-diagnostics');
}

if ($action === 'clear_all_products') {
    $clear = blu_clear_all_product_data();
    flash($clear['message'], $clear['ok'] ? 'success' : 'danger');
    $redirectPage = (string)($_POST['redirect_page'] ?? 'products');
    if (!in_array($redirectPage, ['products', 'imported', 'robot-monitor', 'dashboard'], true)) {
        $redirectPage = 'products';
    }
    redirect_to($redirectPage);
}

if ($action === 'import_products_json') {
    if (empty($_FILES['products_json']['tmp_name']) || !is_uploaded_file((string)$_FILES['products_json']['tmp_name'])) {
        flash('Selectează un fișier JSON.', 'danger');
        redirect_to('products');
    }
    if (($_FILES['products_json']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        flash('Eroare la încărcarea fișierului (cod ' . (int)$_FILES['products_json']['error'] . ').', 'danger');
        redirect_to('products');
    }
    $maxImportBytes = 50 * 1024 * 1024; // 50 MB — suficient pentru orice catalog real
    if ((int)($_FILES['products_json']['size'] ?? 0) > $maxImportBytes) {
        flash('Fișierul depășește limita de 50 MB.', 'danger');
        redirect_to('products');
    }
    $raw = file_get_contents((string)$_FILES['products_json']['tmp_name']);
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        flash('JSON invalid: ' . json_last_error_msg(), 'danger');
        redirect_to('products');
    }
    $replace = !empty($_POST['replace_products']);
    if ($replace) {
        blu_write_json_file(blu_products_json_file(), []);
    }
    $result = blu_import_products_json($data, $replace);
    if (!empty($result['catalog']) && !empty($result['job'])) {
        $_SESSION['pending_catalog_job'] = $result['job'];
        flash('Catalog JSON incarcat (' . (int)$result['total'] . ' piese). Continua cu RapidAPI.', 'success');
        header('Location: ../import-catalog.php?autostart=' . rawurlencode((string)$result['job']));
        exit;
    }
    flash($result['message'], $result['ok'] ? 'success' : 'danger');
    redirect_to('products');
}

if ($action === 'sync_imported_products') {
    if (function_exists('blu_sync_all_product_sources')) {
        $sync = blu_sync_all_product_sources();
        flash($sync['message'] ?? 'Sincronizare finalizată.', !empty($sync['ok']) ? 'success' : 'warning');
    } else {
        $n = blu_sync_imported_cards_to_products();
        flash($n > 0 ? "Sincronizate {$n} produse in lista." : 'Nu sunt cartele importate de sincronizat.', $n > 0 ? 'success' : 'warning');
    }
    redirect_to('products');
}

if ($action === 'save_product') {
    $products = blu_load_products();
    $id = (int)($_POST['id'] ?? 0);

    $imagine = trim((string)($_POST['imagine'] ?? ''));
    if (!empty($_FILES['imagine_file']['tmp_name']) && is_uploaded_file($_FILES['imagine_file']['tmp_name'])) {
        $uploaded = blu_save_uploaded_product_image($_FILES['imagine_file'], $id);
        if ($uploaded['ok']) {
            $imagine = $uploaded['url'];
        } else {
            flash($uploaded['message'], 'warning');
        }
    }

    $newId = $id ?: next_id($products);
    $payload = [
        'id' => $newId,
        'randomn_id' => $newId,
        'nume' => trim((string)($_POST['nume'] ?? '')),
        'cod_oem' => trim((string)($_POST['cod_oem'] ?? '')),
        'categorie' => trim((string)($_POST['categorie'] ?? '')),
        'pret' => trim((string)($_POST['pret'] ?? '')),
        'stoc' => trim((string)($_POST['stoc'] ?? '')),
        'descriere' => trim((string)($_POST['descriere'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($imagine !== '') {
        $payload['imagine'] = $imagine;
    }
    if ($payload['nume'] === '') {
        flash('Completeaza numele produsului.', 'danger');
    } else {
        $updated = false;
        foreach ($products as &$product) {
            $pid = (int)($product['id'] ?? $product['randomn_id'] ?? 0);
            if ($pid === $id && $id > 0) {
                $product = array_merge($product, $payload);
                $updated = true;
                break;
            }
        }
        unset($product);
        if (!$updated) {
            $products[] = $payload;
        }
        blu_save_products($products);
        flash('Produs salvat cu succes.');
    }
    redirect_to('products');
}

if ($action === 'delete_product') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('ID produs invalid.', 'danger');
        redirect_to('products');
    }
    $res = blu_delete_product_everywhere($id);
    if ($res['removed_products'] > 0 || $res['removed_cards'] > 0 || $res['removed_db'] > 0) {
        $detalii = [];
        if ($res['removed_cards'] > 0) {
            $detalii[] = $res['removed_cards'] . ' cartelă importată';
        }
        if ($res['removed_db'] > 0) {
            $detalii[] = $res['removed_db'] . ' rând MySQL';
        }
        $sufix = $detalii ? (' (+ ' . implode(', ', $detalii) . ')') : '';
        flash('Produs șters definitiv' . $sufix . '.');
    } else {
        flash('Nu am găsit produsul de șters (poate a fost deja eliminat).', 'warning');
    }
    redirect_to('products');
}

if ($action === 'save_pricing') {
    require_once $projectRoot . '/lib/pricing.php';
    $res = blu_pricing_save($_POST);
    flash($res['message'], $res['ok'] ? 'success' : 'danger');
    redirect_to('furnizori', ['tab' => 'adaos']);
}

if ($action === 'save_card_template') {
    require_once $projectRoot . '/lib/tecdoc_product_enrich.php';
    $ok = blu_card_template_save($_POST);
    flash($ok ? 'Model cartelă salvat. Folosește „Reîmprospătează TecDoc" pentru a regenera cartelele existente.' : 'Eroare la salvarea modelului.', $ok ? 'success' : 'danger');
    redirect_to('card-template');
}

if ($action === 'save_company') {
    write_json($companyFile, [
        'name' => trim((string)($_POST['name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    flash('Datele companiei au fost salvate.');
    redirect_to('settings');
}

if ($action === 'save_settings') {
    $decoded = json_decode((string)($_POST['raw_json'] ?? '{}'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        flash('JSON invalid: ' . json_last_error_msg(), 'danger');
    } else {
        write_json($settingsFile, $decoded);
        flash('Configuratia a fost salvata.');
    }
    redirect_to('settings');
}

if ($action === 'save_user') {
    if (!blu_admin_can_manage_users(current_admin())) {
        flash('Nu ai permisiunea de a crea utilizatori.', 'danger');
        redirect_to('dashboard');
    }
    $role = trim((string) ($_POST['role'] ?? 'operator_comenzi'));
    $perms = blu_admin_parse_permissions_post();
    if ($perms === [] && $role !== 'custom') {
        $perms = blu_admin_preset_permissions($role);
    }
    if ($perms === []) {
        flash('Selectează cel puțin un modul de acces.', 'danger');
        redirect_to('users');
    }
    $new = [
        'id' => next_id($users),
        'name' => trim((string)($_POST['name'] ?? '')),
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'password' => password_hash((string)($_POST['password'] ?? ''), PASSWORD_DEFAULT),
        'role' => $role === 'custom' ? 'custom' : $role,
        'permissions' => $perms,
        'status' => in_array(($_POST['status'] ?? 'active'), ['active', 'disabled'], true) ? (string) $_POST['status'] : 'active',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if ($new['name'] === '' || $new['email'] === '' || trim((string) ($_POST['password'] ?? '')) === '') {
        flash('Numele, emailul și parola sunt obligatorii.', 'danger');
    } elseif (strlen((string) ($_POST['password'] ?? '')) < 6) {
        flash('Parola trebuie să aibă minim 6 caractere.', 'danger');
    } else {
        foreach ($users as $existing) {
            if (strtolower((string) ($existing['email'] ?? '')) === $new['email']) {
                flash('Există deja un utilizator cu acest email.', 'danger');
                redirect_to('users');
            }
        }
        $users[] = blu_admin_normalize_user($new);
        write_json($usersFile, $users);
        flash('Utilizator adăugat cu acces delegat.');
    }
    redirect_to('users');
}

if ($action === 'update_user') {
    if (!blu_admin_can_manage_users(current_admin())) {
        flash('Nu ai permisiunea de a edita utilizatori.', 'danger');
        redirect_to('dashboard');
    }
    $userId = (int) ($_POST['user_id'] ?? 0);
    $target = blu_admin_find_user_by_id($users, $userId);
    if ($target === null) {
        flash('Utilizator negăsit.', 'danger');
        redirect_to('users');
    }
    $role = trim((string) ($_POST['role'] ?? (string) ($target['role'] ?? 'operator_comenzi')));
    $perms = blu_admin_parse_permissions_post();
    if ($userId !== 1 && $perms === [] && $role !== 'custom') {
        $perms = blu_admin_preset_permissions($role);
    }
    if ($userId !== 1 && $perms === []) {
        flash('Selectează cel puțin un modul de acces.', 'danger');
        redirect_to('users', ['edit_user' => $userId]);
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $passwordRaw = trim((string) ($_POST['password'] ?? ''));
    if ($name === '' || $email === '') {
        flash('Numele și emailul sunt obligatorii.', 'danger');
        redirect_to('users', ['edit_user' => $userId]);
    }
    foreach ($users as $existing) {
        if ((int) ($existing['id'] ?? 0) !== $userId && strtolower((string) ($existing['email'] ?? '')) === $email) {
            flash('Există deja un utilizator cu acest email.', 'danger');
            redirect_to('users', ['edit_user' => $userId]);
        }
    }
    foreach ($users as $i => $user) {
        if ((int) ($user['id'] ?? 0) !== $userId) {
            continue;
        }
        $users[$i]['name'] = $name;
        if ($userId !== 1) {
            $users[$i]['email'] = $email;
        }
        if ($passwordRaw !== '') {
            if (strlen($passwordRaw) < 6) {
                flash('Parola trebuie să aibă minim 6 caractere.', 'danger');
                redirect_to('users', ['edit_user' => $userId]);
            }
            $users[$i]['password'] = password_hash($passwordRaw, PASSWORD_DEFAULT);
        }
        if ($userId !== 1) {
            $users[$i]['role'] = $role === 'custom' ? 'custom' : $role;
            $users[$i]['permissions'] = $perms;
            $users[$i]['status'] = in_array(($_POST['status'] ?? 'active'), ['active', 'disabled'], true)
                ? (string) $_POST['status']
                : 'active';
        }
        $users[$i] = blu_admin_normalize_user($users[$i]);
        if ((int) (current_admin()['id'] ?? 0) === $userId) {
            $_SESSION['admin'] = [
                'id' => $users[$i]['id'],
                'name' => $users[$i]['name'],
                'email' => $users[$i]['email'],
                'role' => $users[$i]['role'],
                'permissions' => $users[$i]['permissions'],
                'status' => $users[$i]['status'],
            ];
        }
        break;
    }
    write_json($usersFile, $users);
    flash('Utilizator actualizat.');
    redirect_to('users');
}

if ($action === 'delete_user') {
    if (!blu_admin_can_manage_users(current_admin())) {
        flash('Nu ai permisiunea de a sterge utilizatori.', 'danger');
        redirect_to('dashboard');
    }
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 1) {
        flash('Nu poți șterge acest cont.', 'danger');
        redirect_to('users');
    }
    if ((int) (current_admin()['id'] ?? 0) === $userId) {
        flash('Nu poți șterge propriul cont.', 'danger');
        redirect_to('users');
    }
    $before = count($users);
    $users = array_values(array_filter($users, static fn(array $u): bool => (int) ($u['id'] ?? 0) !== $userId));
    if (count($users) === $before) {
        flash('Utilizator negăsit.', 'danger');
    } else {
        write_json($usersFile, $users);
        flash('Utilizator șters.');
    }
    redirect_to('users');
}

if (in_array($action, ['shop_user_block', 'shop_user_unblock', 'shop_user_delete', 'shop_user_create', 'shop_user_reset_password'], true)) {
    require_once $projectRoot . '/lib/shop/auth.php';
    if ($action === 'shop_user_create') {
        $res = blu_shop_admin_create_user(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['status'] ?? 'active')
        );
        flash($res['message'], !empty($res['ok']) ? 'success' : 'danger');
        redirect_to('shop-users');
    }
    if ($action === 'shop_user_reset_password') {
        $res = blu_shop_admin_reset_password(
            (int) ($_POST['user_id'] ?? 0),
            (string) ($_POST['password'] ?? '')
        );
        flash($res['message'], !empty($res['ok']) ? 'success' : 'danger');
        redirect_to('shop-users');
    }
    $shopUserId = (int) ($_POST['user_id'] ?? 0);
    if ($shopUserId <= 0) {
        flash('ID utilizator invalid.', 'danger');
        redirect_to('shop-users');
    }
    if ($action === 'shop_user_delete') {
        $res = blu_shop_delete_user($shopUserId);
    } elseif ($action === 'shop_user_block') {
        $res = blu_shop_set_user_status($shopUserId, 'blocked');
    } else {
        $res = blu_shop_set_user_status($shopUserId, 'active');
    }
    flash($res['message'], !empty($res['ok']) ? 'success' : 'danger');
    redirect_to('shop-users');
}

// Sincronizarea cartelelor e costisitoare — ruleaza doar pe paginile care o folosesc,
// nu la fiecare vizualizare de pagina din admin.
if (in_array($page, ['products', 'imported', 'dashboard'], true)) {
    blu_sync_imported_cards_to_products();
}
$products = blu_load_products();
$leads = rows(read_json($leadsFile, []));
$company = read_json($companyFile, []);
$settings = read_json($settingsFile, []);

/**
 * Text cautabil dintr-un produs: doar campurile relevante pentru operator,
 * nu tot JSON-ul intern (mai rapid si fara rezultate false).
 */
function blu_admin_product_haystack(array $product): string
{
    $fields = [
        $product['nume'] ?? $product['name'] ?? $product['titlu'] ?? '',
        $product['cod_oem'] ?? $product['cod'] ?? $product['cod_articol'] ?? '',
        $product['categorie'] ?? '',
        $product['marca_masina'] ?? '',
        $product['pret'] ?? '',
        $product['descriere'] ?? '',
        (string)($product['id'] ?? $product['randomn_id'] ?? ''),
    ];
    return strtolower(implode(' ', array_map('strval', $fields)));
}

$query = strtolower(trim((string)($_GET['q'] ?? '')));
if ($query !== '') {
    $products = array_values(array_filter($products, function ($product) use ($query) {
        return contains_text(blu_admin_product_haystack((array)$product), $query);
    }));
}

$editId = (int)($_GET['edit'] ?? 0);
$editProduct = null;
foreach ($products as $product) {
    if ((int)($product['id'] ?? $product['randomn_id'] ?? 0) === $editId) {
        $editProduct = $product;
        break;
    }
}

$importedCards = [];
$pendingImportCount = 0;
$rapidApiConfigured = blu_rapidapi_key() !== '';
if ($page === 'imported') {
    $pendingImportCount = blu_count_pending_import_cards();
    $importedCards = blu_load_imported_cards_for_admin();
    if ($query !== '') {
        $importedCards = array_values(array_filter($importedCards, function ($card) use ($query) {
            return contains_text(strtolower(json_encode($card, JSON_UNESCAPED_UNICODE) ?: ''), $query);
        }));
    }
}

$pieseautoAccounts = [];
if ($page === 'pieseauto') {
    $pieseautoAccounts = blu_pieseauto_accounts_for_ui();
}

$gbgSuppliers = [];
if (in_array($page, ['robot-monitor', 'furnizori'], true)) {
    $gbgSuppliers = blu_gbg_suppliers_for_ui();
}

if ($page === 'furnizori') {
    $fTab = trim((string)($_GET['tab'] ?? ''));
    if ($fTab === 'pieseauto') {
        header('Location: ?page=furnizori');
        exit;
    }
}

$pageMeta = blu_admin_page_meta($page);
$flash = flash();
?>
<!DOCTYPE html>
<html lang="ro" class="blu-admin-shell blu-admin-root">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>Blue-Car Admin</title>
    <script>try{localStorage.removeItem("compactMenu");}catch(e){}</script>
    <link rel="icon" href="<?= e($assetBase) ?>/images/favicon.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ($page === 'login'): ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/fontawesome-min.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/shop/shop.css?v=47">
    <style id="blu-admin-login-critical">
        html.blu-admin-root, body.blu-admin-neon.shop-auth-v2 { background: #f8fafc !important; }
        body.blu-admin-neon.shop-auth-v2::before { display: none !important; }
    </style>
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/bootstrap.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/flag-icon.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/iconly-icon.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/bulk-style.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/themify.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/fontawesome-min.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/scrollbar.css">
    <link id="color" rel="stylesheet" href="<?= e($assetBase) ?>/css/color-1.css" media="screen">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/style.css">
    <link rel="stylesheet" href="<?= e(blu_admin_web_base()) ?>admin-lite.css?v=<?= e(BLU_ADMIN_ASSET_VER) ?>">
    <link rel="stylesheet" href="<?= e($adminSelf) ?>/admin-neon-conflicts.css?v=<?= e(BLU_ADMIN_ASSET_VER) ?>">
    <link rel="stylesheet" href="<?= e($adminSelf) ?>/admin-shell.css?v=<?= e(BLU_ADMIN_ASSET_VER) ?>">
    <link rel="stylesheet" href="<?= e($adminSelf) ?>/admin-2026.css?v=<?= e(BLU_ADMIN_ASSET_VER) ?>">
    <style id="blu-admin-2026-critical">
        body.blu-admin-2026 { --blu-sidebar-w: 292px; --blu-header-h: 72px; }
        /* Toate dimensiunile de layout folosesc variabilele de mai sus */
        html.blu-admin-root,
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) {
            margin: 0 !important;
            padding: 0 !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-wrapper.compact-wrapper {
            margin: 0 !important;
            padding: 0 !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-header.blu-admin-topbar {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            left: auto !important;
            z-index: 1100 !important;
            margin: 0 0 0 var(--blu-sidebar-w) !important;
            width: calc(100% - var(--blu-sidebar-w)) !important;
            border-radius: 0 !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-body-wrapper {
            padding-top: var(--blu-header-h) !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-header .logo-wrapper {
            display: none !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-sidebar {
            top: 0 !important;
            height: 100vh !important;
            z-index: 1050 !important;
        }
        body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-sidebar .main-sidebar {
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
            margin-top: 0 !important;
            overflow: hidden !important;
        }
        body.blu-admin-2026 .blu-sidebar-nav-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }
        @media (max-width: 1199px) {
            body.blu-admin-2026.blu-admin-neon:not(.shop-auth-body) .page-header.blu-admin-topbar {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    <style id="blu-admin-neon-critical">
        html.blu-admin-root{--theme-default:#2563eb;--body-color:#f8fafc;--blu-primary:#2563eb}
        body.blu-admin-neon::before{display:block;pointer-events:none;z-index:0}
        body.blu-admin-neon .loader-wrapper{display:none!important;pointer-events:none!important;z-index:-1!important}
        body.blu-admin-neon .page-header,body.blu-admin-neon .page-sidebar,body.blu-admin-neon .nav-right,body.blu-admin-neon .btn,body.blu-admin-neon a{pointer-events:auto}
    </style>
    <?php endif; ?>
</head>
<body class="blu-admin-neon blu-admin-2026<?= $page === 'login' ? ' shop-auth-body shop-auth-v2' : '' ?><?= $page !== 'login' ? ' blu-admin-page-' . preg_replace('/[^a-z0-9-]/', '', $page) : '' ?>">

<?php if ($page === 'login'): ?>
    <div class="auth-v2">
        <div class="auth-v2__cosmos" aria-hidden="true">
            <div class="auth-v2__mesh"></div>
            <div class="auth-v2__beam"></div>
            <div class="auth-v2__ring auth-v2__ring--outer"></div>
            <div class="auth-v2__ring auth-v2__ring--inner"></div>
            <div class="auth-v2__grid"></div>
            <div class="auth-v2__watermark">ADMIN</div>
        </div>

        <div class="auth-v2__shell">
            <header class="auth-v2__hero">
                <div class="auth-v2__emblem" aria-hidden="true">
                    <span class="auth-v2__emblem-core"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                </div>
                <p class="auth-v2__eyebrow"><span class="auth-v2__pulse"></span> Panou intern Blue-Car</p>
                <h1 class="auth-v2__brand">Blue<span>Car</span></h1>
                <p class="auth-v2__tagline">Gestiune produse, cereri și import catalog — acces restricționat.</p>
            </header>

            <section class="auth-v2__portal" aria-labelledby="auth-admin-title">
                <div class="auth-v2__portal-glow" aria-hidden="true"></div>
                <div class="auth-v2__portal-inner">
                    <div class="auth-v2__portal-head">
                        <span class="auth-v2__badge"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Autentificare admin</span>
                        <h2 id="auth-admin-title">Blue-Car Admin</h2>
                        <p>Introdu datele de administrator pentru gestiunea internă.</p>
                    </div>

                    <?php if ($flash): ?>
                        <div class="shop-alert shop-alert-<?= $flash['type'] === 'danger' ? 'error' : 'success' ?> auth-v2__alert" role="alert">
                            <i class="fa-solid fa-<?= $flash['type'] === 'danger' ? 'triangle-exclamation' : 'circle-check' ?>" aria-hidden="true"></i>
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="auth-v2__form shop-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="login">

                        <div class="auth-v2__field shop-field">
                            <label for="admin-email">Email</label>
                            <div class="auth-v2__input-wrap">
                                <i class="fa-solid fa-at" aria-hidden="true"></i>
                                <input type="email" id="admin-email" name="email" required autocomplete="username" placeholder="admin@local.test">
                            </div>
                        </div>

                        <div class="auth-v2__field shop-field">
                            <label for="admin-password">Parolă</label>
                            <div class="auth-v2__input-wrap">
                                <i class="fa-solid fa-key" aria-hidden="true"></i>
                                <input type="password" id="admin-password" name="password" required autocomplete="current-password" placeholder="Parola ta">
                            </div>
                        </div>

                        <button type="submit" class="auth-v2__submit shop-btn shop-btn-accent">
                            <span class="auth-v2__submit-shine" aria-hidden="true"></span>
                            <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                            Intră în admin
                        </button>
                    </form>

                    <p class="auth-v2__footnote auth-v2__footnote--muted">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Acces doar pentru personalul autorizat Blue-Car.
                    </p>
                </div>
            </section>
        </div>
    </div>
<?php else: ?>
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <header class="page-header blu-admin-topbar">
            <div class="page-main-header col-12">
                <button type="button" class="blu-mobile-menu toggle-sidebar d-xl-none" aria-label="Deschide meniul">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="header-left">
                    <form class="blu-admin-search d-none d-lg-block" action="" method="get" role="search">
                        <input type="hidden" name="page" value="products">
                        <label class="blu-admin-search__wrap">
                            <i class="fa-solid fa-magnifying-glass blu-admin-search__icon" aria-hidden="true"></i>
                            <input type="search" class="blu-admin-search__input" name="q" placeholder="Caută în Blue-Car…" autocomplete="off" aria-label="Caută produs">
                        </label>
                    </form>
                </div>
                <div class="nav-right">
                    <ul class="header-right">
                        <li><a href="../" target="_blank" rel="noopener" title="Magazin online" aria-label="Deschide magazinul online"><i class="fa-solid fa-store" aria-hidden="true"></i></a></li>
                        <li class="profile-nav custom-dropdown">
                            <div class="user-wrap">
                                <div class="user-img bg-primary text-white"><?= e(substr((string)(current_admin()['name'] ?? 'A'), 0, 1)) ?></div>
                                <div><span><?= e(current_admin()['name'] ?? 'Admin') ?></span><p class="mb-0"><?= e(blu_admin_role_label((string) (current_admin()['role'] ?? 'admin'))) ?></p></div>
                            </div>
                            <div class="custom-menu overflow-hidden">
                                <ul class="profile-body">
                                    <li class="d-flex"><a href="?page=logout&amp;t=<?= e($csrf) ?>">Logout</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <div class="page-body-wrapper">
            <div class="blu-admin-cosmos" aria-hidden="true">
                <div class="blu-admin-cosmos__mesh"></div>
                <div class="blu-admin-cosmos__grid"></div>
                <div class="blu-admin-cosmos__watermark">BLUE-CAR</div>
            </div>
            <aside class="page-sidebar">
                <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
                <div class="main-sidebar" id="main-sidebar">
                    <div class="blu-sidebar-brand">
                        <a href="?page=dashboard" class="blu-sidebar-brand__link">
                            <div class="blu-sidebar-brand__emblem"><i class="fa-solid fa-shield-halved"></i></div>
                            <p class="blu-sidebar-brand__title">Blue<span>Car</span></p>
                            <p class="blu-sidebar-brand__sub">Admin portal 2026</p>
                        </a>
                    </div>
                    <div class="blu-sidebar-nav-scroll" id="blu-sidebar-nav-scroll">
                    <ul class="sidebar-menu" id="blu-admin-sidebar-menu">
                        <?php blu_admin_render_sidebar($page, $assetBase); ?>
                    </ul>
                    </div>
                </div>
                <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
            </aside>
            <div class="page-body">
                <div class="container-fluid">
                    <div class="blu-page-hero-wrap">
                        <div class="blu-page-hero">
                            <div class="row align-items-center g-3">
                                <div class="col-lg-7 col-12">
                                    <span class="blu-page-hero__badge"><span class="blu-page-hero__pulse"></span> <?= e($pageMeta['module'] !== '' ? $pageMeta['module'] : 'Admin panel') ?></span>
                                    <h2><?= e($pageMeta['title']) ?></h2>
                                    <p class="blu-page-hero__sub"><?php if ($page === 'dashboard'): ?>Rezumat magazin — comenzi, facturi, robot și cereri clienți.<?php else: ?>Panou intern Blue-Car — catalog, comenzi, facturi AWB și robot automatizat.<?php endif; ?></p>
                                </div>
                                <div class="col-lg-5 col-12">
                                    <ol class="breadcrumb justify-content-lg-end mb-0">
                                        <li class="breadcrumb-item"><a href="?page=dashboard"><i class="fa-solid fa-house"></i></a></li>
                                        <?php if ($pageMeta['module'] !== ''): ?>
                                            <li class="breadcrumb-item"><?= e($pageMeta['module']) ?></li>
                                        <?php endif; ?>
                                        <li class="breadcrumb-item active"><?= e($pageMeta['title']) ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container-fluid">
                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?>">
                            <i class="fa-solid fa-<?= $flash['type'] === 'danger' ? 'triangle-exclamation' : 'circle-check' ?>"></i>
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($page === 'dashboard'): ?>
                        <?php blu_render_dashboard_admin_panel($products, $leads, current_admin()); ?>
                    <?php elseif ($page === 'products'): ?>
                        <?php blu_render_products_section_nav('products'); ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card border-primary" id="importProducts">
                                    <div class="card-body">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-lg-6">
                                                <h4 class="h5 mb-2">Import produse din JSON</h4>
                                                <p class="text-muted small mb-0">Lista simpla (nume, cod_oem…) sau catalog_auto.json (marca → model → coduri). Catalogul continua in RapidAPI.</p>
                                            </div>
                                            <div class="col-lg-6">
                                                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="action" value="import_products_json">
                                                    <div class="col-sm-7">
                                                        <input class="form-control" type="file" name="products_json" accept=".json,application/json" required>
                                                    </div>
                                                    <div class="col-sm-5">
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="checkbox" name="replace_products" value="1" id="replaceProducts">
                                                            <label class="form-check-label small" for="replaceProducts">Înlocuiește tot</label>
                                                        </div>
                                                        <button class="btn btn-primary w-100" type="submit">Importă JSON</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <hr class="my-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="../import-catalog.php">Import catalog + RapidAPI</a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="action" value="sync_imported_products">
                                                <button class="btn btn-outline-secondary btn-sm" type="submit">Sincronizează cartele importate</button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Reset complet?\n\nSe sterg:\n- Toate produsele scanate/importate\n- Magazia PieseAuto\n- Lista „deja scanate” de la furnizor\n- Jurnal robot + cache\n\nConturile PieseAuto si furnizorii raman.\n\nContinui?');">
                                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="action" value="clear_all_products">
                                                <input type="hidden" name="redirect_page" value="products">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Reset complet scanare</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <?php if ($editProduct): ?>
                                    <?php blu_render_product_detail_panel($editProduct, $csrf); ?>
                                <?php endif; ?>
                                <div class="card mb-3">
                                    <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <h5 class="mb-1">Catalog produse</h5>
                                            <p class="text-muted mb-0 small">Carduri cu date TecDoc — apasă <strong>Edit</strong> pentru descrierea completă.</p>
                                        </div>
                                        <a class="btn btn-outline-primary btn-sm" href="#importProducts"><i class="fa-solid fa-file-import me-1"></i> Import JSON</a>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <h4>Produse <span class="badge bg-light text-dark"><?= count($products) ?></span></h4>
                                        <form method="get" class="mvp-search"><input type="hidden" name="page" value="products"><input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Caută produs..."></form>
                                    </div>
                                    <div class="card-body">
                                        <?php blu_render_products_grid($products, $csrf, $editId > 0 ? $editId : null); ?>
                                        <p class="text-muted mb-0 mt-3"><a href="?page=imported">Cartele importate</a> · <a href="../api/refresh_all_products.php" target="_blank" rel="noopener">Reîmprospătează TecDoc</a> · <a href="../" target="_blank" rel="noopener">Magazin</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($page === 'imported'): ?>
                        <?php blu_render_products_section_nav('imported'); ?>
                        <div class="card">
                            <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <h4>Cartele produse importate</h4>
                                    <p class="text-muted mb-0 small">Piese din catalog JSON + RapidAPI (imagine, denumire, OEM).</p>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <form method="get" class="mvp-search">
                                        <input type="hidden" name="page" value="imported">
                                        <input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Caută">
                                    </form>
                                    <a class="btn btn-primary btn-sm" href="../import-catalog.php">Import nou</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Reset complet?\n\nSe sterg toate produsele scanate/importate, magazia PieseAuto si lista de la furnizor.\n\nContinui?');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="clear_all_products">
                                        <input type="hidden" name="redirect_page" value="imported">
                                        <button class="btn btn-danger btn-sm" type="submit">Reset complet scanare</button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($pendingImportCount > 0): ?>
                                    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" id="enrichAlert">
                                        <div>
                                            <strong><?= (int)$pendingImportCount ?></strong> piese in asteptare RapidAPI.
                                            <span class="d-block small mt-1">Apasa butonul — nu ruleaza automat (evita scanare infinita).</span>
                                            <?php if (!$rapidApiConfigured): ?>
                                                <span class="text-danger d-block small mt-1">Lipseste RAPIDAPI_AUTOPARTS_KEY in .env pe serverul live.</span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm" id="btnEnrichPending" <?= $rapidApiConfigured ? '' : 'disabled' ?>>
                                            Completeaza cu RapidAPI
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnStopStuck" title="Opreste scanarea infinita">
                                            Opreste blocate
                                        </button>
                                    </div>
                                    <div class="mb-3" id="enrichProgress" style="display:none;">
                                        <div class="progress mb-2" style="height:1.25rem;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="enrichBar" style="width:0%">0%</div>
                                        </div>
                                        <p class="small text-muted mb-0" id="enrichText">Pornire...</p>
                                    </div>
                                <?php endif; ?>
                                <?php render_imported_cards_grid($importedCards, false); ?>
                            </div>
                        </div>
                        <?php if ($pendingImportCount > 0 && $rapidApiConfigured): ?>
                        <script>
                        (function () {
                            const bar = document.getElementById('enrichBar');
                            const text = document.getElementById('enrichText');
                            const progress = document.getElementById('enrichProgress');
                            const btn = document.getElementById('btnEnrichPending');
                            const alertBox = document.getElementById('enrichAlert');
                            let offset = 0;
                            let total = 0;
                            let found = 0;
                            let running = false;

                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                            async function runBatch() {
                                const limit = 2;
                                const url = '?page=imported&api_action=enrich_pending&offset=' + offset + '&limit=' + limit;
                                const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
                                const raw = await res.text();
                                try {
                                    return JSON.parse(raw);
                                } catch (e) {
                                    return { ok: false, error: 'Raspuns invalid (sesiune expirata?)' };
                                }
                            }

                            function showDiag(d) {
                                if (!d || d.ok) return '';
                                return ' RapidAPI: ' + (d.error || 'esuat');
                            }

                            async function enrichAll() {
                                if (running) return;
                                running = true;
                                if (btn) btn.disabled = true;
                                progress.style.display = 'block';
                                offset = 0;
                                found = 0;
                                total = 0;
                                let lastErrors = [];
                                try {
                                    let done = false;
                                    let loops = 0;
                                    while (!done && loops < 200) {
                                        loops++;
                                        const data = await runBatch();
                                        if (!data.ok) {
                                            text.textContent = 'Eroare: ' + (data.error || 'necunoscuta');
                                            break;
                                        }
                                        if (data.rapidapi_diagnostic && !data.rapidapi_diagnostic.ok) {
                                            text.textContent = 'RapidAPI indisponibil:' + showDiag(data.rapidapi_diagnostic);
                                            bar.classList.remove('progress-bar-animated');
                                            break;
                                        }
                                        total = data.total || total;
                                        offset = data.next_offset ?? offset;
                                        found += (data.batch && data.batch.found) || 0;
                                        lastErrors = (data.batch && data.batch.errors) || lastErrors;
                                        const pct = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
                                        bar.style.width = pct + '%';
                                        bar.textContent = pct + '%';
                                        text.textContent = 'RapidAPI: ' + offset + ' / ' + total + ' | gasite: ' + found
                                            + (lastErrors[0] ? ' | ' + lastErrors[0] : '');
                                        done = !!data.done;
                                        if (!done) await new Promise(r => setTimeout(r, 600));
                                    }
                                    bar.classList.remove('progress-bar-animated');
                                    if (done) {
                                        if (found > 0) {
                                            text.textContent = 'Gata: ' + found + ' produse. Reincarcare...';
                                            setTimeout(() => location.reload(), 1000);
                                        } else {
                                            text.textContent = 'Finalizat: 0 gasite in TecDoc.'
                                                + (lastErrors[0] ? ' ' + lastErrors[0] : '')
                                                + ' Verifica RAPIDAPI_AUTOPARTS_KEY pe server.';
                                            if (alertBox) {
                                                alertBox.classList.remove('alert-warning');
                                                alertBox.classList.add('alert-danger');
                                            }
                                            setTimeout(() => location.reload(), 2500);
                                        }
                                    } else if (loops >= 200) {
                                        text.textContent = 'Oprit: prea multe iteratii. Reincarca pagina.';
                                    }
                                } catch (e) {
                                    text.textContent = 'Eroare retea: ' + e.message;
                                }
                                running = false;
                                if (btn) btn.disabled = false;
                            }

                            if (btn) btn.addEventListener('click', () => enrichAll());

                            const btnStop = document.getElementById('btnStopStuck');
                            if (btnStop) {
                                btnStop.addEventListener('click', async function () {
                                    if (!confirm('Marchezi piese blocate ca Fara TecDoc (nu mai reincearca automat)?')) return;
                                    const res = await fetch('?page=imported&api_action=finalize_pending', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
                                    const data = await res.json();
                                    alert('Actualizat: ' + (data.finalized || 0) + ' piese.');
                                    location.reload();
                                });
                            }
                        })();
                        </script>
                        <?php endif; ?>
                    <?php elseif ($page === 'robot-monitor'): ?>
                        <?php blu_render_admin_section_nav('automatizare', 'robot-monitor'); ?>
                        <?php
                        require_once $projectRoot . '/lib/robot_monitor_panel.php';
                        ?>
                        <div class="card">
                            <div class="card-header card-no-border pb-0">
                                <h4>Monitor Robot → TecDoc → Site</h4>
                                <p class="text-muted mb-0 small">Live: scanare gbg-eshop → RapidAPI → produse in baza de date</p>
                            </div>
                            <div class="card-body">
                                <?php blu_render_robot_monitor_panel($gbgSuppliers); ?>
                            </div>
                        </div>
                    <?php elseif ($page === 'pieseauto'): ?>
                        <?php blu_render_admin_section_nav('automatizare', 'pieseauto'); ?>
                        <?php
                        require_once $projectRoot . '/lib/pieseauto_panel.php';
                        blu_render_pieseauto_panel($pieseautoAccounts);
                        ?>
                    <?php elseif ($page === 'messages'): ?>
                        <?php blu_render_messages_admin_panel(); ?>
                    <?php elseif ($page === 'orders'): ?>
                        <?php blu_render_orders_admin_panel(); ?>
                    <?php elseif ($page === 'facturi'): ?>
                        <?php blu_render_facturi_admin_panel(); ?>
                    <?php elseif ($page === 'livrare'): ?>
                        <?php blu_render_livrare_admin_panel(); ?>
                    <?php elseif ($page === 'website'): ?>
                        <?php blu_render_website_admin_panel(); ?>
                    <?php elseif ($page === 'blog'): ?>
                        <?php blu_render_blog_admin_panel(); ?>
                    <?php elseif ($page === 'alerts'): ?>
                        <?php blu_render_alerts_admin_panel(); ?>
                    <?php elseif ($page === 'backup'): ?>
                        <?php blu_render_backup_admin_panel($csrf); ?>
                    <?php elseif ($page === 'leads'): ?>
                        <?php blu_render_admin_section_nav('comenzi', 'leads'); ?>
                        <div class="card">
                            <div class="card-header card-no-border pb-0"><h4>Cereri si conversatii</h4></div>
                            <div class="card-body"><?php render_leads_table(array_reverse($leads)); ?></div>
                        </div>
                    <?php elseif ($page === 'tools'): ?>
                        <?php blu_render_products_section_nav('tools'); ?>
                        <div class="row">
                            <?php
                            $tools = [
                                ['Import catalog JSON', '../import-catalog.php', 'Bag'],
                                ['Scanner Laragon', '../scaner_largon.php', 'Rule'],
                                ['Scanner produse', '../scaner_produect/', 'Scan'],
                                ['Robot local', '../robot/', 'Chat'],
                                ['TecDoc', '../tecdoc.php', 'Document'],
                                ['VIN', '../vin.html', 'Ticket'],
                            ];
                            foreach ($tools as $tool):
                            ?>
                                <div class="col-xl-4 col-md-6">
                                    <a class="card tool-link" href="<?= e($tool[1]) ?>" target="_blank" rel="noopener">
                                        <div class="card-body d-flex align-items-center gap-3">
                                            <div class="stat-icon bg-light-primary font-primary"><i class="iconly-<?= e($tool[2]) ?> icli"></i></div>
                                            <div><h5><?= e($tool[0]) ?></h5><p><?= e($tool[1]) ?></p></div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($page === 'furnizori'): ?>
                        <?php
                        require_once $projectRoot . '/lib/furnizori_admin_panel.php';
                        blu_render_furnizori_admin_panel($csrf, $gbgSuppliers);
                        ?>
                    <?php elseif ($page === 'card-template'): ?>
                        <?php blu_render_products_section_nav('card-template'); ?>
                        <?php
                        require_once $projectRoot . '/lib/card_template_panel.php';
                        blu_render_card_template_panel($csrf);
                        ?>
                    <?php elseif ($page === 'api-diagnostics'): ?>
                        <?php blu_render_admin_section_nav('sistem', 'api-diagnostics'); ?>
                        <?php
                        require_once $projectRoot . '/lib/api_diagnostics_panel.php';
                        blu_render_api_diagnostics_panel($csrf);
                        ?>
                    <?php elseif ($page === 'settings'): ?>
                        <?php blu_render_admin_section_nav('sistem', 'settings'); ?>
                        <div class="row">
                            <div class="col-xl-5">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4>Date companie</h4></div>
                                    <div class="card-body">
                                        <form method="post" class="theme-form">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="save_company">
                                            <div class="mb-3"><label class="form-label">Nume</label><input class="form-control" name="name" value="<?= e($company['name'] ?? $company['nume'] ?? '') ?>"></div>
                                            <div class="mb-3"><label class="form-label">Telefon</label><input class="form-control" name="phone" value="<?= e($company['phone'] ?? $company['telefon'] ?? '') ?>"></div>
                                            <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= e($company['email'] ?? '') ?>"></div>
                                            <div class="mb-3"><label class="form-label">Adresa</label><textarea class="form-control" name="address" rows="3"><?= e($company['address'] ?? $company['adresa'] ?? '') ?></textarea></div>
                                            <button class="btn btn-primary" type="submit">Salveaza compania</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-7">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4>Configuratie JSON</h4></div>
                                    <div class="card-body">
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="save_settings">
                                            <textarea class="form-control code-area" name="raw_json" rows="16"><?= e(json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
                                            <button class="btn btn-primary mt-3" type="submit">Salveaza configuratia</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($page === 'shop-users'): ?>
                        <?php
                        require_once $projectRoot . '/lib/shop_users_panel.php';
                        blu_render_shop_users_panel($csrf);
                        ?>
                    <?php elseif ($page === 'users'): ?>
                        <?php blu_render_admin_users_panel($csrf, $users, current_admin()); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
function render_imported_cards_grid(array $cards, bool $enrichRunning = false): void
{
    if (!$cards) {
        ?>
        <div class="text-center py-5 text-muted">
            <p class="mb-2">Nu exista inca cartele importate.</p>
            <a class="btn btn-primary" href="../import-catalog.php">Importă catalog JSON</a>
            <p class="small mt-3 mb-0">Daca ai importat deja si ai vazut 0 gasite, ruleaza din nou importul — API-ul a fost corectat.</p>
        </div>
        <?php
        return;
    }
    if ($enrichRunning) {
        echo '<p class="text-muted small">Se completeaza automat datele TecDoc (RapidAPI)...</p>';
    }
    ?>
    <div class="row g-3">
        <?php foreach ($cards as $card): ?>
            <?php
            $img = trim((string)($card['image'] ?? ''));
            $status = (string)($card['status'] ?? 'imported');
            $badgeClass = match ($status) {
                'imported' => 'bg-success',
                'pending' => 'bg-warning text-dark',
                'empty' => 'bg-secondary',
                'no_oem' => 'bg-danger',
                default => 'bg-secondary',
            };
            $badgeLabel = match ($status) {
                'imported' => 'Importat',
                'pending' => 'In asteptare API',
                'empty' => 'Fara TecDoc',
                'no_oem' => 'Fara date API',
                default => $status,
            };
            $showNoOemBadge = blu_card_missing_oem($card);
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <article class="card blu-product-card h-100">
                    <?php if ($img !== ''): ?>
                        <img src="<?= e($img) ?>" class="card-img-top blu-product-card-img" alt="">
                    <?php else: ?>
                        <div class="blu-product-card-img blu-product-card-img--empty">Fara imagine</div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
                            <?php if ($showNoOemBadge): ?>
                                <span class="badge bg-danger">OEM nu e</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="h6 card-title"><?= e($card['title'] ?? 'Piesa') ?></h3>
                        <?php if (!empty($card['brand'])): ?><p class="small text-muted mb-1"><?= e($card['brand']) ?></p><?php endif; ?>
                        <p class="small mb-2"><?= e($card['car'] ?? '') ?></p>
                        <div class="mt-auto">
                            <div class="small"><strong>OEM:</strong> <?= e($card['cod_oem'] ?? '-') ?></div>
                            <?php if (!empty($card['cod_articol'])): ?>
                                <div class="small text-muted">Art: <?= e($card['cod_articol']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted mt-3 mb-0"><?= count($cards) ?> cartele afisate</p>
    <?php
}

function render_leads_table(array $leads): void
{
    ?>
    <div class="table-responsive theme-scrollbar">
        <table class="table table-hover">
            <thead><tr><th>Telefon</th><th>Sursa</th><th>Mesaj</th><th>Ora</th></tr></thead>
            <tbody>
            <?php if (!$leads): ?>
                <tr><td colspan="4" class="text-muted">Nu sunt cereri inca.</td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $lead): ?>
                <tr>
                    <td><?= e($lead['p'] ?? $lead['phone'] ?? '-') ?></td>
                    <td><span class="badge badge-light-primary"><?= e($lead['s'] ?? $lead['source'] ?? '-') ?></span></td>
                    <td><?= e($lead['t'] ?? $lead['text'] ?? '-') ?></td>
                    <td><?= e($lead['time'] ?? $lead['created_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<?php if ($page === 'login'): ?>
<?php else: ?>
<script src="<?= e($assetBase) ?>/js/vendors/jquery/jquery.min.js"></script>
<script src="<?= e($assetBase) ?>/js/vendors/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= e($assetBase) ?>/js/vendors/bootstrap/dist/js/popper.min.js" defer></script>
<script src="<?= e($assetBase) ?>/js/vendors/font-awesome/fontawesome-min.js"></script>
<script src="<?= e($adminSelf) ?>/admin-sidebar.js?v=<?= e(BLU_ADMIN_ASSET_VER) ?>"></script>
<?php endif; ?>
</body>
</html>
