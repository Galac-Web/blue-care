<?php
declare(strict_types=1);

session_start();

$adminRoot = __DIR__;
$projectRoot = dirname($adminRoot);
$knownProject = 'C:' . DIRECTORY_SEPARATOR . 'laragon' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'aibotpiese.online';
if (!is_file($projectRoot . DIRECTORY_SEPARATOR . 'leads.json') && is_dir($knownProject)) {
    $projectRoot = $knownProject;
}

$assetBase = 'assets';

$dataDir = $adminRoot . DIRECTORY_SEPARATOR . 'data';
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
    return $json !== false && file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
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
            'created_at' => date('Y-m-d H:i:s'),
        ]];
        write_json($file, $users);
    }
    return $users;
}

function product_file(string $projectRoot): string
{
    $files = [
        path_join($projectRoot, 'admin/src/Controllers/Produse/produse.json'),
        path_join($projectRoot, 'products.json'),
    ];
    foreach ($files as $file) {
        if (is_file($file) && filesize($file) > 2) {
            return $file;
        }
    }
    return $files[0];
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

function redirect_to(string $page): void
{
    header('Location: ?page=' . rawurlencode($page));
    exit;
}

$usersFile = admin_users_file($dataDir);
$users = load_users($dataDir);
$productsFile = product_file($projectRoot);
$leadsFile = path_join($projectRoot, 'leads.json');
$companyFile = path_join($projectRoot, 'company.json');
$settingsFile = path_join($projectRoot, 'baza_date.json');

$page = (string)($_GET['page'] ?? 'dashboard');
$action = (string)($_POST['action'] ?? '');

if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($action !== '' && ($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Cerere invalida.');
}

if ($page === 'login' && $action === 'login') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    foreach ($users as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $email && password_verify($password, (string)($user['password'] ?? ''))) {
            $_SESSION['admin'] = [
                'id' => $user['id'] ?? 0,
                'name' => $user['name'] ?? 'Admin',
                'email' => $user['email'] ?? $email,
                'role' => $user['role'] ?? 'admin',
            ];
            redirect_to('dashboard');
        }
    }
    flash('Email sau parola gresita.', 'danger');
}

if ($page !== 'login') {
    require_admin();
}

if ($action === 'save_product') {
    $products = rows(read_json($productsFile, []));
    $id = (int)($_POST['id'] ?? 0);
    $payload = [
        'id' => $id ?: next_id($products),
        'randomn_id' => $id ?: next_id($products),
        'nume' => trim((string)($_POST['nume'] ?? '')),
        'cod_oem' => trim((string)($_POST['cod_oem'] ?? '')),
        'categorie' => trim((string)($_POST['categorie'] ?? '')),
        'pret' => trim((string)($_POST['pret'] ?? '')),
        'stoc' => trim((string)($_POST['stoc'] ?? '')),
        'descriere' => trim((string)($_POST['descriere'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
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
        write_json($productsFile, $products);
        flash('Produs salvat cu succes.');
    }
    redirect_to('products');
}

if ($action === 'delete_product') {
    $id = (int)($_POST['id'] ?? 0);
    $products = array_values(array_filter(rows(read_json($productsFile, [])), function ($product) use ($id) {
        return (int)($product['id'] ?? $product['randomn_id'] ?? 0) !== $id;
    }));
    write_json($productsFile, $products);
    flash('Produs sters.');
    redirect_to('products');
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
    $new = [
        'id' => next_id($users),
        'name' => trim((string)($_POST['name'] ?? '')),
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'password' => password_hash((string)($_POST['password'] ?? 'admin123'), PASSWORD_DEFAULT),
        'role' => trim((string)($_POST['role'] ?? 'operator')),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if ($new['name'] === '' || $new['email'] === '') {
        flash('Numele si emailul sunt obligatorii.', 'danger');
    } else {
        $users[] = $new;
        write_json($usersFile, $users);
        flash('Utilizator adaugat.');
    }
    redirect_to('users');
}

$products = rows(read_json($productsFile, []));
$leads = rows(read_json($leadsFile, []));
$company = read_json($companyFile, []);
$settings = read_json($settingsFile, []);
$query = strtolower(trim((string)($_GET['q'] ?? '')));
if ($query !== '') {
    $products = array_values(array_filter($products, function ($product) use ($query) {
        return contains_text(strtolower(json_encode($product, JSON_UNESCAPED_UNICODE) ?: ''), $query);
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

$nav = [
    'dashboard' => ['Dashboard', 'Home'],
    'products' => ['Produse', 'Bag'],
    'leads' => ['Cereri', 'Chat'],
    'tools' => ['Instrumente', 'Setting'],
    'settings' => ['Setari', 'Document'],
    'users' => ['Utilizatori', 'User'],
];
$flash = flash();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue-Car Admin</title>
    <link rel="icon" href="<?= e($assetBase) ?>/images/favicon.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,300;6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/bootstrap.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/flag-icon.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/iconly-icon.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/bulk-style.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/themify.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/fontawesome-min.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/vendors/scrollbar.css">
    <link id="color" rel="stylesheet" href="<?= e($assetBase) ?>/css/color-1.css" media="screen">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/style.css">
    <link rel="stylesheet" href="admin-lite.css?v=admiro-bluecar-1">
</head>
<body>

<?php if ($page === 'login'): ?>
    <div class="container-fluid p-0">
        <div class="row m-0">
            <div class="col-12 p-0">
                <div class="login-card login-dark">
                    <div>
                        <div class="login-main">
                            <form class="theme-form" method="post">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="login">
                                <h2 class="text-center">Blue-Car Admin</h2>
                                <p class="text-center">Autentificare pentru gestiunea interna Blue-Car.</p>
                                <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
                                <div class="form-group">
                                    <label class="col-form-label">Email</label>
                                    <input class="form-control" type="email" name="email" value="admin@local.test" required>
                                </div>
                                <div class="form-group">
                                    <label class="col-form-label">Parola</label>
                                    <input class="form-control" type="password" name="password" value="admin123" required>
                                </div>
                                <div class="form-group mb-0">
                                    <button class="btn btn-primary btn-block w-100" type="submit">Intra in admin</button>
                                </div>
                                <p class="mt-4 mb-0 text-center text-muted">Cont initial: admin@local.test / admin123</p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <header class="page-header row">
            <div class="logo-wrapper d-flex align-items-center col-auto">
                <a href="?page=dashboard" class="blue-logo">BLUE-CAR</a>
                <a class="close-btn toggle-sidebar" href="javascript:void(0)">
                    <svg class="svg-color"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Category"></use></svg>
                </a>
            </div>
            <div class="page-main-header col">
                <div class="header-left">
                    <form class="form-inline search-full col" action="" method="get">
                        <input type="hidden" name="page" value="products">
                        <div class="form-group w-100">
                            <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text" placeholder="Cauta produs..." name="q">
                        </div>
                    </form>
                    <div class="form-group-header d-lg-block d-none">
                        <div class="Typeahead Typeahead--twitterUsers">
                          <div class="u-posRelative d-flex align-items-center">
                            <i class="search-bg iconly-Search icli"></i>
                            <span class="ms-2 text-muted">Cauta in Blue-Car...</span>
                          </div>
                        </div>
                    </div>
                </div>
                <div class="nav-right">
                    <ul class="header-right">
                        <li><a class="dark-mode" href="javascript:void(0)"><svg><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#moondark"></use></svg></a></li>
                        <li class="profile-nav custom-dropdown">
                            <div class="user-wrap">
                                <div class="user-img bg-primary text-white"><?= e(substr((string)(current_admin()['name'] ?? 'A'), 0, 1)) ?></div>
                                <div><span><?= e(current_admin()['name'] ?? 'Admin') ?></span><p class="mb-0"><?= e(current_admin()['role'] ?? 'admin') ?></p></div>
                            </div>
                            <div class="custom-menu overflow-hidden">
                                <ul class="profile-body">
                                    <li class="d-flex"><a href="?page=logout">Logout</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <div class="page-body-wrapper">
            <aside class="page-sidebar">
                <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
                <div class="main-sidebar" id="main-sidebar">
                    <ul class="sidebar-menu" id="simple-bar">
                        <li class="sidebar-main-title">
                            <div><h5 class="f-w-700 sidebar-title">General</h5></div>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Home-dashboard"></use></svg>
                                <h6 class="f-w-600">Dashboard</h6>
                            </a>
                        </li>
                        <li class="sidebar-main-title">
                            <div><h5 class="f-w-700 sidebar-title pt-3">Blue-Car</h5></div>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= in_array($page, ['products'], true) ? 'active' : '' ?>" href="?page=products">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Bag"></use></svg>
                                <h6 class="f-w-600">Produse</h6>
                            </a>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= $page === 'leads' ? 'active' : '' ?>" href="?page=leads">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Chat"></use></svg>
                                <h6 class="f-w-600">Cereri</h6>
                            </a>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= $page === 'tools' ? 'active' : '' ?>" href="?page=tools">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Setting"></use></svg>
                                <h6 class="f-w-600">Instrumente</h6>
                            </a>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Document"></use></svg>
                                <h6 class="f-w-600">Setari</h6>
                            </a>
                        </li>
                        <li class="sidebar-list">
                            <i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link <?= $page === 'users' ? 'active' : '' ?>" href="?page=users">
                                <svg class="stroke-icon"><use href="<?= e($assetBase) ?>/svg/iconly-sprite.svg#Profile"></use></svg>
                                <h6 class="f-w-600">Utilizatori</h6>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
            </aside>
            <div class="page-body">
                <div class="container-fluid">
                    <div class="page-title">
                        <div class="row">
                            <div class="col-sm-6 col-12"><h2><?= e($nav[$page][0] ?? 'Dashboard') ?></h2></div>
                            <div class="col-sm-6 col-12">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="?page=dashboard"><i class="iconly-Home icli"></i></a></li>
                                    <li class="breadcrumb-item active"><?= e($nav[$page][0] ?? 'Admin') ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container-fluid">
                    <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

                    <?php if ($page === 'dashboard'): ?>
                        <div class="row">
                            <?php
                            $cards = [
                                ['Produse', count($products), 'bg-light-primary', 'font-primary'],
                                ['Cereri', count($leads), 'bg-light-secondary', 'font-secondary'],
                                ['Utilizatori', count($users), 'bg-light-success', 'font-success'],
                                ['Sursa date', is_file($productsFile) ? 'OK' : 'Nou', 'bg-light-warning', 'font-warning'],
                            ];
                            foreach ($cards as $card):
                            ?>
                                <div class="col-xl-3 col-sm-6">
                                    <div class="card mvp-stat">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <h3><?= e($card[1]) ?></h3>
                                                    <p><?= e($card[0]) ?></p>
                                                </div>
                                                <div class="stat-icon <?= e($card[2]) ?> <?= e($card[3]) ?>"><i class="iconly-Chart icli"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4>Ultimele cereri</h4></div>
                                    <div class="card-body">
                                        <?php render_leads_table(array_slice(array_reverse($leads), 0, 10)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($page === 'products'): ?>
                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4><?= $editProduct ? 'Editeaza produs' : 'Adauga produs' ?></h4></div>
                                    <div class="card-body">
                                        <form method="post" class="theme-form">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="save_product">
                                            <input type="hidden" name="id" value="<?= e($editProduct['id'] ?? $editProduct['randomn_id'] ?? '') ?>">
                                            <div class="mb-3"><label class="form-label">Nume</label><input class="form-control" name="nume" value="<?= e($editProduct['nume'] ?? $editProduct['name'] ?? '') ?>" required></div>
                                            <div class="mb-3"><label class="form-label">Cod OEM</label><input class="form-control" name="cod_oem" value="<?= e($editProduct['cod_oem'] ?? $editProduct['cod'] ?? '') ?>"></div>
                                            <div class="mb-3"><label class="form-label">Categorie</label><input class="form-control" name="categorie" value="<?= e($editProduct['categorie'] ?? $editProduct['category'] ?? '') ?>"></div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3"><label class="form-label">Pret</label><input class="form-control" name="pret" value="<?= e($editProduct['pret'] ?? $editProduct['price'] ?? '') ?>"></div>
                                                <div class="col-md-6 mb-3"><label class="form-label">Stoc</label><input class="form-control" name="stoc" value="<?= e($editProduct['stoc'] ?? $editProduct['stock'] ?? '') ?>"></div>
                                            </div>
                                            <div class="mb-3"><label class="form-label">Descriere</label><textarea class="form-control" name="descriere" rows="4"><?= e($editProduct['descriere'] ?? $editProduct['description'] ?? '') ?></textarea></div>
                                            <button class="btn btn-primary w-100" type="submit">Salveaza</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-8">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0 d-flex justify-content-between align-items-center">
                                        <h4>Produse</h4>
                                        <form method="get" class="mvp-search"><input type="hidden" name="page" value="products"><input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Cauta"></form>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive theme-scrollbar">
                                            <table class="table table-hover">
                                                <thead><tr><th>ID</th><th>Nume</th><th>Cod</th><th>Pret</th><th>Actiuni</th></tr></thead>
                                                <tbody>
                                                <?php foreach (array_slice($products, 0, 100) as $product): $pid = (int)($product['id'] ?? $product['randomn_id'] ?? 0); ?>
                                                    <tr>
                                                        <td><?= e($pid) ?></td>
                                                        <td><?= e($product['nume'] ?? $product['name'] ?? '-') ?></td>
                                                        <td><?= e($product['cod_oem'] ?? $product['cod'] ?? '-') ?></td>
                                                        <td><?= e($product['pret'] ?? $product['price'] ?? '-') ?></td>
                                                        <td class="table-actions">
                                                            <a class="btn btn-sm btn-outline-primary" href="?page=products&edit=<?= e($pid) ?>">Edit</a>
                                                            <form method="post">
                                                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                                <input type="hidden" name="action" value="delete_product">
                                                                <input type="hidden" name="id" value="<?= e($pid) ?>">
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Sterge</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="text-muted mb-0 mt-3">Fisier produse: <?= e($productsFile) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($page === 'leads'): ?>
                        <div class="card">
                            <div class="card-header card-no-border pb-0"><h4>Cereri si conversatii</h4></div>
                            <div class="card-body"><?php render_leads_table(array_reverse($leads)); ?></div>
                        </div>
                    <?php elseif ($page === 'tools'): ?>
                        <div class="row">
                            <?php
                            $tools = [
                                ['Scanner Laragon', '../scaner_largon.php', 'Rule'],
                                ['Scanner produse', '../scaner_produect/', 'Scan'],
                                ['Robot', '../robot/', 'Chat'],
                                ['TecDoc', '../tecdoc.php', 'Document'],
                                ['VIN', '../vin.html', 'Ticket'],
                                ['Chat client', '../chat.html', 'Message'],
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
                    <?php elseif ($page === 'settings'): ?>
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
                    <?php elseif ($page === 'users'): ?>
                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4>Utilizator nou</h4></div>
                                    <div class="card-body">
                                        <form method="post" class="theme-form">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="save_user">
                                            <div class="mb-3"><label class="form-label">Nume</label><input class="form-control" name="name" required></div>
                                            <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                                            <div class="mb-3"><label class="form-label">Parola</label><input class="form-control" type="password" name="password" value="admin123" required></div>
                                            <div class="mb-3"><label class="form-label">Rol</label><input class="form-control" name="role" value="operator"></div>
                                            <button class="btn btn-primary w-100" type="submit">Adauga</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-8">
                                <div class="card">
                                    <div class="card-header card-no-border pb-0"><h4>Utilizatori</h4></div>
                                    <div class="card-body">
                                        <div class="table-responsive theme-scrollbar">
                                            <table class="table table-hover">
                                                <thead><tr><th>ID</th><th>Nume</th><th>Email</th><th>Rol</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($users as $user): ?>
                                                    <tr><td><?= e($user['id'] ?? '-') ?></td><td><?= e($user['name'] ?? '-') ?></td><td><?= e($user['email'] ?? '-') ?></td><td><?= e($user['role'] ?? '-') ?></td></tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
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
<script src="<?= e($assetBase) ?>/js/vendors/jquery/jquery.min.js"></script>
<script src="<?= e($assetBase) ?>/js/vendors/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= e($assetBase) ?>/js/vendors/bootstrap/dist/js/popper.min.js" defer></script>
<script src="<?= e($assetBase) ?>/js/vendors/font-awesome/fontawesome-min.js"></script>
<script src="<?= e($assetBase) ?>/js/sidebar.js"></script>
<script src="<?= e($assetBase) ?>/js/config.js"></script>
<script src="<?= e($assetBase) ?>/js/scrollbar/simplebar.js"></script>
<script src="<?= e($assetBase) ?>/js/scrollbar/custom.js"></script>
<script src="<?= e($assetBase) ?>/js/script.js"></script>
</body>
</html>
