<?php
declare(strict_types=1);

session_start();

$adminDir = __DIR__;
$projectRoot = dirname($adminDir);
$isInstalledInProject = is_file($projectRoot . DIRECTORY_SEPARATOR . 'index.php')
    || is_file($projectRoot . DIRECTORY_SEPARATOR . 'leads.json')
    || is_file($projectRoot . DIRECTORY_SEPARATOR . 'company.json');

if (!$isInstalledInProject) {
    $knownLocalRoot = 'C:' . DIRECTORY_SEPARATOR . 'laragon' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'aibotpiese.online';
    $projectRoot = realpath(__DIR__ . '/../aibotpiese.online') ?: (is_dir($knownLocalRoot) ? $knownLocalRoot : $projectRoot);
}

$dataDir = $adminDir . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

$defaultUsersFile = $dataDir . DIRECTORY_SEPARATOR . 'admin_users.json';
$csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf'] = $csrf;

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function json_read(string $file, $fallback = [])
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

function json_write(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $payload !== false && file_put_contents($file, $payload . PHP_EOL, LOCK_EX) !== false;
}

function project_file(string $root, string $relative): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function text_contains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function load_admin_users(string $file): array
{
    $users = json_read($file, []);
    if (!$users) {
        $users = [[
            'id' => 1,
            'name' => 'Administrator',
            'email' => 'admin@local.test',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]];
        json_write($file, $users);
    }
    return is_array($users) ? $users : [];
}

function current_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ?page=login');
        exit;
    }
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function normalize_items($data): array
{
    if (!is_array($data)) {
        return [];
    }
    $isList = array_keys($data) === range(0, count($data) - 1);
    return $isList ? $data : array_values($data);
}

function next_id(array $items): int
{
    $max = 0;
    foreach ($items as $item) {
        $max = max($max, (int)($item['id'] ?? 0));
    }
    return $max + 1;
}

function detect_products_file(string $root): string
{
    $candidates = [
        project_file($root, 'admin/src/Controllers/Produse/produse.json'),
        project_file($root, 'products.json'),
    ];
    foreach ($candidates as $file) {
        if (is_file($file) && filesize($file) > 2) {
            return $file;
        }
    }
    return $candidates[0];
}

$users = load_admin_users($defaultUsersFile);
$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? '';

if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($action && ($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Cerere invalida.');
}

if ($page === 'login' && $action === 'login') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    foreach ($users as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $email && password_verify($password, (string)($user['password'] ?? ''))) {
            $_SESSION['admin_user'] = [
                'id' => $user['id'] ?? null,
                'name' => $user['name'] ?? 'Admin',
                'email' => $user['email'] ?? $email,
                'role' => $user['role'] ?? 'admin',
            ];
            header('Location: ?page=dashboard');
            exit;
        }
    }
    flash('Email sau parola gresita.', 'bad');
}

if ($page !== 'login') {
    require_login();
}

$productsFile = detect_products_file($projectRoot);
$leadsFile = project_file($projectRoot, 'leads.json');
$companyFile = project_file($projectRoot, 'company.json');
$settingsFile = project_file($projectRoot, 'baza_date.json');

if ($action === 'save_product') {
    $items = normalize_items(json_read($productsFile, []));
    $id = (int)($_POST['id'] ?? 0);
    $product = [
        'id' => $id ?: next_id($items),
        'nume' => trim((string)($_POST['nume'] ?? '')),
        'cod_oem' => trim((string)($_POST['cod_oem'] ?? '')),
        'categorie' => trim((string)($_POST['categorie'] ?? '')),
        'pret' => trim((string)($_POST['pret'] ?? '')),
        'stoc' => trim((string)($_POST['stoc'] ?? '')),
        'descriere' => trim((string)($_POST['descriere'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($product['nume'] === '') {
        flash('Numele produsului este obligatoriu.', 'bad');
    } else {
        $found = false;
        foreach ($items as &$item) {
            if ((int)($item['id'] ?? 0) === $id) {
                $item = array_merge($item, $product);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $items[] = $product;
        }
        json_write($productsFile, $items);
        flash('Produs salvat.');
    }
    header('Location: ?page=products');
    exit;
}

if ($action === 'delete_product') {
    $id = (int)($_POST['id'] ?? 0);
    $items = array_values(array_filter(normalize_items(json_read($productsFile, [])), fn($item) => (int)($item['id'] ?? 0) !== $id));
    json_write($productsFile, $items);
    flash('Produs sters.');
    header('Location: ?page=products');
    exit;
}

if ($action === 'save_company') {
    $company = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    json_write($companyFile, $company);
    flash('Setarile companiei au fost salvate.');
    header('Location: ?page=settings');
    exit;
}

if ($action === 'save_raw_settings') {
    $raw = (string)($_POST['raw_json'] ?? '{}');
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        flash('JSON invalid: ' . json_last_error_msg(), 'bad');
    } else {
        json_write($settingsFile, $decoded);
        flash('Configuratia a fost salvata.');
    }
    header('Location: ?page=settings');
    exit;
}

if ($action === 'save_user') {
    $newUser = [
        'id' => next_id($users),
        'name' => trim((string)($_POST['name'] ?? '')),
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'password' => password_hash((string)($_POST['password'] ?? 'admin123'), PASSWORD_DEFAULT),
        'role' => trim((string)($_POST['role'] ?? 'operator')),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if ($newUser['name'] === '' || $newUser['email'] === '') {
        flash('Numele si emailul sunt obligatorii.', 'bad');
    } else {
        $users[] = $newUser;
        json_write($defaultUsersFile, $users);
        flash('Utilizator adaugat.');
    }
    header('Location: ?page=users');
    exit;
}

$products = normalize_items(json_read($productsFile, []));
$leads = normalize_items(json_read($leadsFile, []));
$company = json_read($companyFile, []);
$settings = json_read($settingsFile, []);
$q = strtolower(trim((string)($_GET['q'] ?? '')));

if ($q !== '') {
    $products = array_values(array_filter($products, function ($item) use ($q) {
        return text_contains(strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: ''), $q);
    }));
}

$editId = (int)($_GET['edit'] ?? 0);
$editProduct = null;
foreach ($products as $item) {
    if ((int)($item['id'] ?? 0) === $editId) {
        $editProduct = $item;
        break;
    }
}

$flash = flash();
$nav = [
    'dashboard' => 'Dashboard',
    'products' => 'Produse',
    'leads' => 'Cereri',
    'tools' => 'Scanner si robot',
    'settings' => 'Setari',
    'users' => 'Utilizatori',
];
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AiBotPiese Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<?php if ($page === 'login'): ?>
    <main class="login-shell">
        <section class="login-box">
            <p class="eyebrow">AiBotPiese</p>
            <h1>Admin</h1>
            <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="login">
                <label>Email <input name="email" type="email" value="admin@local.test" required></label>
                <label>Parola <input name="password" type="password" value="admin123" required></label>
                <button type="submit">Intra in admin</button>
            </form>
            <p class="muted">Cont initial: admin@local.test / admin123</p>
        </section>
    </main>
<?php else: ?>
    <aside class="sidebar">
        <div class="brand">AiBotPiese</div>
        <nav>
            <?php foreach ($nav as $key => $label): ?>
                <a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="logout" href="?page=logout">Logout</a>
    </aside>
    <main class="app">
        <header class="topbar">
            <div>
                <p class="eyebrow">Admin MVP</p>
                <h1><?= e($nav[$page] ?? 'Dashboard') ?></h1>
            </div>
            <div class="user-chip"><?= e(current_user()['name'] ?? 'Admin') ?></div>
        </header>
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <section class="metrics">
                <article><span><?= count($products) ?></span><strong>Produse</strong></article>
                <article><span><?= count($leads) ?></span><strong>Mesaje / cereri</strong></article>
                <article><span><?= count($users) ?></span><strong>Utilizatori</strong></article>
                <article><span><?= is_file($productsFile) ? 'OK' : 'Nou' ?></span><strong>Fisier produse</strong></article>
            </section>
            <section class="panel">
                <h2>Ultimele cereri</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Telefon</th><th>Sursa</th><th>Text</th><th>Ora</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice(array_reverse($leads), 0, 8) as $lead): ?>
                            <tr>
                                <td><?= e($lead['p'] ?? $lead['phone'] ?? '-') ?></td>
                                <td><?= e($lead['s'] ?? $lead['source'] ?? '-') ?></td>
                                <td><?= e($lead['t'] ?? $lead['text'] ?? '-') ?></td>
                                <td><?= e($lead['time'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif ($page === 'products'): ?>
            <section class="grid-two">
                <form method="post" class="panel stack">
                    <h2><?= $editProduct ? 'Editeaza produs' : 'Adauga produs' ?></h2>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" value="<?= e($editProduct['id'] ?? '') ?>">
                    <label>Nume <input name="nume" value="<?= e($editProduct['nume'] ?? $editProduct['name'] ?? '') ?>" required></label>
                    <label>Cod OEM <input name="cod_oem" value="<?= e($editProduct['cod_oem'] ?? $editProduct['cod'] ?? '') ?>"></label>
                    <label>Categorie <input name="categorie" value="<?= e($editProduct['categorie'] ?? $editProduct['category'] ?? '') ?>"></label>
                    <div class="row">
                        <label>Pret <input name="pret" value="<?= e($editProduct['pret'] ?? $editProduct['price'] ?? '') ?>"></label>
                        <label>Stoc <input name="stoc" value="<?= e($editProduct['stoc'] ?? $editProduct['stock'] ?? '') ?>"></label>
                    </div>
                    <label>Descriere <textarea name="descriere" rows="4"><?= e($editProduct['descriere'] ?? $editProduct['description'] ?? '') ?></textarea></label>
                    <button type="submit">Salveaza produs</button>
                </form>
                <section class="panel">
                    <div class="panel-head">
                        <h2>Lista produse</h2>
                        <form method="get" class="search">
                            <input type="hidden" name="page" value="products">
                            <input name="q" value="<?= e($q) ?>" placeholder="Cauta produs">
                        </form>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Nume</th><th>Cod</th><th>Pret</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($products, 0, 80) as $product): ?>
                                <tr>
                                    <td><?= e($product['id'] ?? '-') ?></td>
                                    <td><?= e($product['nume'] ?? $product['name'] ?? '-') ?></td>
                                    <td><?= e($product['cod_oem'] ?? $product['cod'] ?? '-') ?></td>
                                    <td><?= e($product['pret'] ?? $product['price'] ?? '-') ?></td>
                                    <td class="actions">
                                        <a href="?page=products&edit=<?= e($product['id'] ?? 0) ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?= e($product['id'] ?? 0) ?>">
                                            <button class="link-danger" type="submit">Sterge</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="muted">Sursa: <?= e($productsFile) ?></p>
                </section>
            </section>
        <?php elseif ($page === 'leads'): ?>
            <section class="panel">
                <h2>Cereri si conversatii</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Telefon</th><th>Sursa</th><th>Mesaj</th><th>Ora</th></tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($leads) as $lead): ?>
                            <tr>
                                <td><?= e($lead['p'] ?? $lead['phone'] ?? '-') ?></td>
                                <td><?= e($lead['s'] ?? $lead['source'] ?? '-') ?></td>
                                <td><?= e($lead['t'] ?? $lead['text'] ?? '-') ?></td>
                                <td><?= e($lead['time'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif ($page === 'tools'): ?>
            <section class="tools-grid">
                <?php
                $tools = [
                    ['Scanner Laragon', '../scaner_largon.php'],
                    ['Scanner produse', '../scaner_produect/'],
                    ['Robot', '../robot/'],
                    ['TecDoc', '../tecdoc.php'],
                    ['VIN', '../vin.html'],
                    ['Chat', '../chat.html'],
                ];
                foreach ($tools as [$label, $href]):
                ?>
                    <a class="tool-card" href="<?= e($href) ?>" target="_blank" rel="noopener">
                        <strong><?= e($label) ?></strong>
                        <span><?= e($href) ?></span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php elseif ($page === 'settings'): ?>
            <section class="grid-two">
                <form method="post" class="panel stack">
                    <h2>Date companie</h2>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_company">
                    <label>Nume <input name="name" value="<?= e($company['name'] ?? $company['nume'] ?? '') ?>"></label>
                    <label>Telefon <input name="phone" value="<?= e($company['phone'] ?? $company['telefon'] ?? '') ?>"></label>
                    <label>Email <input name="email" value="<?= e($company['email'] ?? '') ?>"></label>
                    <label>Adresa <textarea name="address" rows="3"><?= e($company['address'] ?? $company['adresa'] ?? '') ?></textarea></label>
                    <button type="submit">Salveaza compania</button>
                </form>
                <form method="post" class="panel stack">
                    <h2>Configuratie JSON</h2>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_raw_settings">
                    <textarea name="raw_json" rows="14"><?= e(json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
                    <button type="submit">Salveaza configuratia</button>
                </form>
            </section>
        <?php elseif ($page === 'users'): ?>
            <section class="grid-two">
                <form method="post" class="panel stack">
                    <h2>Utilizator nou</h2>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_user">
                    <label>Nume <input name="name" required></label>
                    <label>Email <input name="email" type="email" required></label>
                    <label>Parola <input name="password" type="password" value="admin123" required></label>
                    <label>Rol <input name="role" value="operator"></label>
                    <button type="submit">Adauga utilizator</button>
                </form>
                <section class="panel">
                    <h2>Utilizatori</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Nume</th><th>Email</th><th>Rol</th></tr></thead>
                            <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= e($user['id'] ?? '-') ?></td>
                                    <td><?= e($user['name'] ?? '-') ?></td>
                                    <td><?= e($user['email'] ?? '-') ?></td>
                                    <td><?= e($user['role'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        <?php endif; ?>
    </main>
<?php endif; ?>
</body>
</html>
