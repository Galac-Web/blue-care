<?php
declare(strict_types=1);

/**
 * Permisiuni admin Blue-Car — module de lucru delegabile per utilizator.
 */

/** @return array<string, array{label: string, desc: string, color: string, pages: list<string>}> */
function blu_admin_permission_modules(): array
{
    return [
        'dashboard' => [
            'label' => 'Dashboard',
            'desc' => 'Panou principal, statistici generale',
            'color' => 'primary',
            'pages' => ['dashboard'],
        ],
        'furnizori' => [
            'label' => 'Furnizori',
            'desc' => 'Lista furnizori, logică robot, adaos',
            'color' => 'info',
            'pages' => ['furnizori'],
        ],
        'produse' => [
            'label' => 'Produse',
            'desc' => 'Catalog, scanări, import, model cartelă',
            'color' => 'success',
            'pages' => ['products', 'imported', 'card-template', 'tools', 'pricing'],
        ],
        'comenzi' => [
            'label' => 'Comenzi',
            'desc' => 'Comenzi, cereri, facturi, livrare AWB',
            'color' => 'warning',
            'pages' => ['orders', 'leads', 'facturi', 'livrare'],
        ],
        'clienti' => [
            'label' => 'Clienți magazin',
            'desc' => 'Conturi clienți online (shop)',
            'color' => 'secondary',
            'pages' => ['shop-users'],
         ],
        'automatizare' => [
            'label' => 'Automatizare',
            'desc' => 'Robot, PieseAuto, mesagerie',
            'color' => 'dark',
            'pages' => ['robot-monitor', 'pieseauto', 'messages'],
        ],
        'website' => [
            'label' => 'Web site',
            'desc' => 'Pagini site, blog',
            'color' => 'primary',
            'pages' => ['website', 'blog'],
        ],
        'sistem' => [
            'label' => 'Sistem',
            'desc' => 'Alerte, backup, diagnostic API, setări',
            'color' => 'danger',
            'pages' => ['alerts', 'backup', 'api-diagnostics', 'settings'],
        ],
        'utilizatori' => [
            'label' => 'Utilizatori admin',
            'desc' => 'Creare conturi staff, delegare acces',
            'color' => 'danger',
            'pages' => ['users'],
        ],
    ];
}

/** @return array<string, array{label: string, desc: string, permissions: list<string>}> */
function blu_admin_role_presets(): array
{
    return [
        'admin' => [
            'label' => 'Administrator',
            'desc' => 'Acces complet la tot panoul',
            'permissions' => array_keys(blu_admin_permission_modules()),
        ],
        'manager' => [
            'label' => 'Manager',
            'desc' => 'Operațiuni zilnice, fără gestiune utilizatori',
            'permissions' => ['dashboard', 'furnizori', 'produse', 'comenzi', 'clienti', 'automatizare', 'website', 'sistem'],
        ],
        'operator_comenzi' => [
            'label' => 'Operator comenzi',
            'desc' => 'Comenzi, clienți, mesagerie',
            'permissions' => ['dashboard', 'comenzi', 'clienti', 'automatizare'],
        ],
        'operator_produse' => [
            'label' => 'Operator produse',
            'desc' => 'Catalog, import, furnizori',
            'permissions' => ['dashboard', 'produse', 'furnizori'],
        ],
        'operator_robot' => [
            'label' => 'Operator robot',
            'desc' => 'Monitor robot, PieseAuto, mesagerie',
            'permissions' => ['dashboard', 'automatizare', 'furnizori'],
        ],
        'custom' => [
            'label' => 'Personalizat',
            'desc' => 'Alege manual modulele de mai jos',
            'permissions' => [],
        ],
    ];
}

/** @return list<string> */
function blu_admin_all_permission_keys(): array
{
    return array_keys(blu_admin_permission_modules());
}

/** @return list<string> */
function blu_admin_preset_permissions(string $role): array
{
    $presets = blu_admin_role_presets();
    if (isset($presets[$role])) {
        return $presets[$role]['permissions'];
    }
    return $presets['operator_comenzi']['permissions'];
}

function blu_admin_page_permission(string $page): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (blu_admin_permission_modules() as $key => $meta) {
            foreach ($meta['pages'] as $p) {
                $map[$p] = $key;
            }
        }
    }
    return $map[$page] ?? null;
}

/** @param array<string,mixed>|null $admin */
function blu_admin_is_super(?array $admin): bool
{
    if (!$admin) {
        return false;
    }
    if ((int) ($admin['id'] ?? 0) === 1) {
        return true;
    }
    $role = (string) ($admin['role'] ?? '');
    return $role === 'admin';
}

/** @param array<string,mixed> $user */
function blu_admin_normalize_user(array $user): array
{
    $perms = $user['permissions'] ?? null;
    if (!is_array($perms) || $perms === []) {
        $role = (string) ($user['role'] ?? 'operator_comenzi');
        $perms = blu_admin_preset_permissions($role);
    }
    $valid = blu_admin_all_permission_keys();
    $user['permissions'] = array_values(array_intersect($valid, array_map('strval', $perms)));
    if (($user['role'] ?? '') === 'admin' || (int) ($user['id'] ?? 0) === 1) {
        $user['permissions'] = $valid;
        $user['role'] = 'admin';
    }
    $user['status'] = in_array(($user['status'] ?? 'active'), ['active', 'disabled'], true)
        ? (string) $user['status']
        : 'active';
    return $user;
}

/** @param array<string,mixed>|null $admin @return list<string> */
function blu_admin_permissions(?array $admin): array
{
    if (!$admin) {
        return [];
    }
    if (blu_admin_is_super($admin)) {
        return blu_admin_all_permission_keys();
    }
    $perms = $admin['permissions'] ?? [];
    if (!is_array($perms) || $perms === []) {
        return blu_admin_preset_permissions((string) ($admin['role'] ?? 'operator_comenzi'));
    }
    $valid = blu_admin_all_permission_keys();
    return array_values(array_intersect($valid, array_map('strval', $perms)));
}

/** @param array<string,mixed>|null $admin */
function blu_admin_can(string $permission, ?array $admin = null): bool
{
    $admin ??= $_SESSION['admin'] ?? null;
    if (!$admin) {
        return false;
    }
    if (blu_admin_is_super($admin)) {
        return true;
    }
    return in_array($permission, blu_admin_permissions($admin), true);
}

/** @param array<string,mixed>|null $admin */
function blu_admin_can_page(string $page, ?array $admin = null): bool
{
    if (in_array($page, ['login', 'logout'], true)) {
        return true;
    }
    $perm = blu_admin_page_permission($page);
    if ($perm === null) {
        return true;
    }
    return blu_admin_can($perm, $admin);
}

/** @param list<string> $allowed */
function blu_admin_nav_entry_allowed(array $entry, array $allowed): bool
{
    $type = (string) ($entry['type'] ?? '');
    if ($type === 'title') {
        return true;
    }
    if (!empty($entry['external'])) {
        return true;
    }
    if ($type === 'link') {
        $page = (string) ($entry['page'] ?? '');
        $perm = blu_admin_page_permission($page);
        return $perm === null || in_array($perm, $allowed, true);
    }
    if ($type === 'group') {
        foreach ($entry['children'] ?? [] as $child) {
            if (!empty($child['external'])) {
                return true;
            }
            $page = (string) ($child['page'] ?? '');
            $perm = blu_admin_page_permission($page);
            if ($perm === null || in_array($perm, $allowed, true)) {
                return true;
            }
        }
        return false;
    }
    return true;
}

/** @return list<array<string,mixed>> */
function blu_admin_filter_nav_modules(?array $admin): array
{
    if (blu_admin_is_super($admin)) {
        return blu_admin_nav_modules();
    }
    $allowed = blu_admin_permissions($admin);
    $raw = blu_admin_nav_modules();
    $filtered = [];
    $pendingTitle = null;

    foreach ($raw as $entry) {
        $type = (string) ($entry['type'] ?? '');
        if ($type === 'title') {
            $pendingTitle = $entry;
            continue;
        }
        if (!blu_admin_nav_entry_allowed($entry, $allowed)) {
            continue;
        }
        if ($pendingTitle !== null) {
            $filtered[] = $pendingTitle;
            $pendingTitle = null;
        }
        if ($type === 'group') {
            $children = [];
            foreach ($entry['children'] ?? [] as $child) {
                if (!empty($child['external'])) {
                    $children[] = $child;
                    continue;
                }
                $page = (string) ($child['page'] ?? '');
                $perm = blu_admin_page_permission($page);
                if ($perm === null || in_array($perm, $allowed, true)) {
                    $children[] = $child;
                }
            }
            if ($children === []) {
                continue;
            }
            $entry['children'] = $children;
        }
        $filtered[] = $entry;
    }
    return $filtered;
}

/** @param list<string> $permissions */
function blu_admin_permissions_summary(array $permissions): string
{
    $modules = blu_admin_permission_modules();
    $labels = [];
    foreach ($permissions as $key) {
        if (isset($modules[$key])) {
            $labels[] = $modules[$key]['label'];
        }
    }
    return $labels !== [] ? implode(', ', $labels) : '—';
}

/** @param array<string,mixed>|null $admin */
function blu_admin_first_allowed_page(?array $admin): string
{
    foreach (blu_admin_filter_nav_modules($admin) as $entry) {
        $type = (string) ($entry['type'] ?? '');
        if ($type === 'link' && empty($entry['external'])) {
            return (string) ($entry['page'] ?? 'dashboard');
        }
        if ($type === 'group') {
            foreach ($entry['children'] ?? [] as $child) {
                if (empty($child['external'])) {
                    return (string) ($child['page'] ?? 'dashboard');
                }
            }
        }
    }
    return 'dashboard';
}

function blu_admin_role_label(string $role): string
{
    $presets = blu_admin_role_presets();
    if (isset($presets[$role])) {
        return $presets[$role]['label'];
    }
    return ucfirst(str_replace('_', ' ', $role));
}

/** @param array<string,mixed>|null $admin */
function blu_admin_require_page(string $page, ?array $admin = null): void
{
    $admin ??= $_SESSION['admin'] ?? null;
    if (blu_admin_can_page($page, $admin)) {
        return;
    }
    if (function_exists('flash') && function_exists('redirect_to')) {
        flash('Nu ai acces la această secțiune.', 'danger');
        redirect_to(blu_admin_first_allowed_page($admin));
    }
    http_response_code(403);
    exit('Acces interzis.');
}

/** @param array<string,mixed>|null $admin */
function blu_admin_can_manage_users(?array $admin = null): bool
{
    return blu_admin_can('utilizatori', $admin);
}
