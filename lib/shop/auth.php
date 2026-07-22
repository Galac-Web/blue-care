<?php
declare(strict_types=1);

function blu_shop_users_file(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'shop_users.json';
}

/** @return list<array> */
function blu_shop_load_users(): array
{
    $file = blu_shop_users_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        return [];
    }
    return array_values($data);
}

function blu_shop_save_users(array $users): bool
{
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents(blu_shop_users_file(), $json . PHP_EOL, LOCK_EX) !== false;
}

function blu_shop_next_user_id(array $users): int
{
    $max = 0;
    foreach ($users as $user) {
        $max = max($max, (int) ($user['id'] ?? 0));
    }
    return $max + 1;
}

function blu_shop_find_user_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    foreach (blu_shop_load_users() as $user) {
        if (strtolower(trim((string) ($user['email'] ?? ''))) === $email) {
            return $user;
        }
    }
    return null;
}

function blu_shop_find_user_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    foreach (blu_shop_load_users() as $user) {
        if ((int) ($user['id'] ?? 0) === $id) {
            return $user;
        }
    }
    return null;
}

function blu_shop_user_status(array $user): string
{
    $status = strtolower(trim((string) ($user['status'] ?? 'active')));
    return $status === 'blocked' ? 'blocked' : 'active';
}

function blu_shop_user_is_blocked(array $user): bool
{
    return blu_shop_user_status($user) === 'blocked';
}

function blu_shop_sync_session(): void
{
    $cur = blu_shop_current_user();
    if ($cur === null) {
        return;
    }
    $user = blu_shop_find_user_by_id((int) ($cur['id'] ?? 0));
    if ($user === null || blu_shop_user_is_blocked($user)) {
        blu_shop_logout_with_reason('Contul tău a fost blocat de administrator. Contactează magazinul pentru acces.');
    }
}

/**
 * @return array{ok:bool,message:string,user?:array}
 */
function blu_shop_set_user_status(int $id, string $status): array
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['active', 'blocked'], true)) {
        return ['ok' => false, 'message' => 'Status invalid.'];
    }

    $users = blu_shop_load_users();
    $found = false;
    foreach ($users as &$user) {
        if ((int) ($user['id'] ?? 0) !== $id) {
            continue;
        }
        $user['status'] = $status;
        $user['updated_at'] = date('Y-m-d H:i:s');
        if ($status === 'blocked') {
            $user['blocked_at'] = date('Y-m-d H:i:s');
        } else {
            unset($user['blocked_at']);
        }
        $found = true;
        break;
    }
    unset($user);

    if (!$found) {
        return ['ok' => false, 'message' => 'Utilizatorul nu a fost găsit.'];
    }
    if (!blu_shop_save_users($users)) {
        return ['ok' => false, 'message' => 'Nu am putut salva modificarea.'];
    }

    return ['ok' => true, 'message' => $status === 'blocked' ? 'Cont blocat.' : 'Cont deblocat.', 'user' => blu_shop_find_user_by_id($id)];
}

/**
 * @return array{ok:bool,message:string}
 */
function blu_shop_delete_user(int $id): array
{
    $users = blu_shop_load_users();
    $before = count($users);
    $users = array_values(array_filter($users, static fn(array $u): bool => (int) ($u['id'] ?? 0) !== $id));
    if (count($users) === $before) {
        return ['ok' => false, 'message' => 'Utilizatorul nu a fost găsit.'];
    }
    if (!blu_shop_save_users($users)) {
        return ['ok' => false, 'message' => 'Nu am putut șterge contul.'];
    }
    return ['ok' => true, 'message' => 'Cont șters definitiv.'];
}

function blu_shop_current_user(): ?array
{
    $session = $_SESSION['shop_user'] ?? null;
    return is_array($session) ? $session : null;
}

function blu_shop_is_logged_in(): bool
{
    return blu_shop_current_user() !== null;
}

function blu_shop_public_pages(): array
{
    return [
        'login.php',
        'logout.php',
        'register.php',
    ];
}

/** Pagini magazin accesibile fără cont (ca besoiupieseauto). */
function blu_shop_guest_pages(): array
{
    return [
        'index.php',
        'catalog.php',
        'produs.php',
        'cos.php',
        'contact.php',
        'despre.php',
    ];
}

function blu_shop_guest_browse_enabled(): bool
{
    $v = strtolower(trim((string) blu_env('SHOP_GUEST_BROWSE', '1')));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function blu_shop_is_guest_page(): bool
{
    if (!blu_shop_guest_browse_enabled()) {
        return false;
    }
    return in_array(blu_shop_current_page(), blu_shop_guest_pages(), true);
}

function blu_shop_is_guest_api(): bool
{
    if (!blu_shop_guest_browse_enabled()) {
        return false;
    }
    $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    return str_ends_with($script, '/api/shop-cart.php');
}

function blu_shop_registration_enabled(): bool
{
    $v = strtolower(trim((string) blu_env('SHOP_ALLOW_PUBLIC_REGISTER', '0')));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function blu_shop_is_public_page(): bool
{
    return in_array(blu_shop_current_page(), blu_shop_public_pages(), true);
}

function blu_shop_is_auth_exempt_uri(string $uri): bool
{
    $path = strtolower(parse_url($uri, PHP_URL_PATH) ?: $uri);
    if ($path === '' || $path === '/') {
        return false;
    }
    if (str_contains($path, '/admin') || str_contains($path, '\\admin\\')) {
        return true;
    }
    if (preg_match('#/(?:login|logout|register)(?:\.php)?(?:/|$|\?)#i', $path)) {
        return true;
    }
    return (bool) preg_match('#(?:^|/)login\.php#i', $path);
}

function blu_shop_sanitize_login_redirect(string $target): string
{
    $target = trim($target);
    if ($target === '') {
        return 'index.php';
    }
    if (blu_shop_is_auth_exempt_uri($target)) {
        return 'index.php';
    }
    if (preg_match('#login\.php#i', $target)) {
        return 'index.php';
    }
    if ($target[0] !== '/' || str_contains($target, '//')) {
        return 'index.php';
    }
    return $target;
}

function blu_shop_pull_flash(string $key): ?string
{
    if (!isset($_SESSION['shop_flash'][$key])) {
        return null;
    }
    $msg = (string) $_SESSION['shop_flash'][$key];
    unset($_SESSION['shop_flash'][$key]);
    return $msg !== '' ? $msg : null;
}

function blu_shop_set_flash(string $key, string $message): void
{
    if (!isset($_SESSION['shop_flash']) || !is_array($_SESSION['shop_flash'])) {
        $_SESSION['shop_flash'] = [];
    }
    $_SESSION['shop_flash'][$key] = $message;
}

function blu_shop_require_login(): void
{
    if (blu_shop_is_logged_in()) {
        return;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/api/')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Autentificare necesară.',
            'login_url' => 'login.php',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $target = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    if (blu_shop_is_auth_exempt_uri($target)) {
        return;
    }
    $safeRedirect = blu_shop_sanitize_login_redirect($target);
    header('Location: login.php?redirect=' . rawurlencode($safeRedirect) . '&required=1');
    exit;
}

function blu_shop_logout_with_reason(string $reason = ''): void
{
    if ($reason !== '') {
        blu_shop_set_flash('auth_notice', $reason);
    }
    blu_shop_logout();
}

function blu_shop_redirect_if_logged_in(string $fallback = 'index.php'): void
{
    if (!blu_shop_is_logged_in()) {
        return;
    }
    $redirect = trim((string) ($_GET['redirect'] ?? ''));
    if ($redirect !== '' && $redirect[0] === '/' && !str_contains($redirect, '//')) {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: ' . $fallback);
    exit;
}

function blu_shop_login_user(array $user): void
{
    $_SESSION['shop_user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ];
}

function blu_shop_logout(): void
{
    unset($_SESSION['shop_user']);
}

/**
 * @return array{ok:bool,error?:string,user?:array}
 */
function blu_shop_register(string $name, string $email, string $password): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    $password = (string) $password;

    if ($name === '' || mb_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'Introdu un nume valid (minim 2 caractere).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Adresa de email nu este validă.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Parola trebuie să aibă minim 6 caractere.'];
    }
    if (blu_shop_find_user_by_email($email) !== null) {
        return ['ok' => false, 'error' => 'Există deja un cont cu acest email.'];
    }

    $users = blu_shop_load_users();
    $user = [
        'id' => blu_shop_next_user_id($users),
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $users[] = $user;

    if (!blu_shop_save_users($users)) {
        return ['ok' => false, 'error' => 'Nu am putut salva contul. Încearcă din nou.'];
    }

    blu_shop_login_user($user);
    return ['ok' => true, 'user' => $user];
}

/**
 * @return array{ok:bool,error?:string}
 */
function blu_shop_attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $user = blu_shop_find_user_by_email($email);
    if ($user === null || !password_verify($password, (string) ($user['password'] ?? ''))) {
        return ['ok' => false, 'error' => 'Email sau parolă incorectă.'];
    }
    if (blu_shop_user_is_blocked($user)) {
        return [
            'ok' => false,
            'error' => 'Contul tău a fost blocat de administrator. Nu poți accesa magazinul.',
            'blocked' => true,
        ];
    }
    blu_shop_login_user($user);
    return ['ok' => true];
}

/**
 * Creare cont de către administrator (Clienți site).
 *
 * @return array{ok:bool,message:string,user?:array}
 */
function blu_shop_admin_create_user(string $name, string $email, string $password, string $status = 'active'): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    $password = (string) $password;
    $status = strtolower(trim($status)) === 'blocked' ? 'blocked' : 'active';

    if ($name === '' || mb_strlen($name) < 2) {
        return ['ok' => false, 'message' => 'Numele trebuie să aibă minim 2 caractere.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Email invalid.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Parola trebuie să aibă minim 6 caractere.'];
    }
    if (blu_shop_find_user_by_email($email) !== null) {
        return ['ok' => false, 'message' => 'Există deja un cont cu acest email.'];
    }

    $users = blu_shop_load_users();
    $user = [
        'id' => blu_shop_next_user_id($users),
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => 'admin',
    ];
    $users[] = $user;

    if (!blu_shop_save_users($users)) {
        return ['ok' => false, 'message' => 'Nu am putut salva contul.'];
    }

    return [
        'ok' => true,
        'message' => 'Cont creat. Clientul se poate autentifica cu emailul și parola setată.',
        'user' => $user,
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function blu_shop_admin_reset_password(int $id, string $password): array
{
    $password = (string) $password;
    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Parola nouă trebuie să aibă minim 6 caractere.'];
    }

    $users = blu_shop_load_users();
    $found = false;
    foreach ($users as &$user) {
        if ((int) ($user['id'] ?? 0) !== $id) {
            continue;
        }
        $user['password'] = password_hash($password, PASSWORD_DEFAULT);
        $user['updated_at'] = date('Y-m-d H:i:s');
        $user['password_reset_by'] = 'admin';
        $found = true;
        break;
    }
    unset($user);

    if (!$found) {
        return ['ok' => false, 'message' => 'Utilizatorul nu a fost găsit.'];
    }
    if (!blu_shop_save_users($users)) {
        return ['ok' => false, 'message' => 'Nu am putut salva parola.'];
    }

    return ['ok' => true, 'message' => 'Parola a fost resetată.'];
}
