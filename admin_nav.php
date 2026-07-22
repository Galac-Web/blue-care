<?php

declare(strict_types=1);



/**

 * Meniu admin Blue-Car — structură ca besoiupieseauto.ro/admin (fără Analiză, Cron, Marketplace).

 *

 * @return list<array<string, mixed>>

 */

function blu_admin_nav_modules(): array

{

    return [

        ['type' => 'title', 'label' => 'Admin panel'],

        ['type' => 'link', 'page' => 'dashboard', 'label' => 'Dashboard', 'fa' => 'fa-gauge-high'],



        [

            'type' => 'group',

            'id' => 'furnizori',

            'label' => 'Furnizori',

            'fa' => 'fa-truck-field',

            'pages' => ['furnizori'],

            'children' => [

                ['page' => 'furnizori', 'label' => 'Lista furnizori'],

                ['page' => 'furnizori', 'tab' => 'robot', 'label' => 'Logică robot'],

            ],

        ],



        [

            'type' => 'group',

            'id' => 'produse',

            'label' => 'Produse',

            'fa' => 'fa-boxes-stacked',

            'pages' => ['products', 'imported', 'card-template', 'pricing', 'tools'],

            'children' => [

                ['page' => 'products', 'label' => 'Lista produse'],

                ['page' => 'imported', 'label' => 'Produse scanate'],

                ['page' => 'card-template', 'label' => 'Model cartelă'],

                ['page' => 'furnizori', 'tab' => 'adaos', 'label' => 'Adaos comercial'],

                ['page' => 'tools', 'label' => 'Import & instrumente'],

            ],

        ],



        [
            'type' => 'group',
            'id' => 'comenzi',
            'label' => 'Comenzi',
            'fa' => 'fa-cart-shopping',
            'pages' => ['orders', 'leads', 'facturi', 'livrare'],
            'children' => [
                ['page' => 'orders', 'label' => 'Toate comenzile'],
                ['page' => 'leads', 'label' => 'Cereri clienți'],
                ['page' => 'facturi', 'label' => 'Facturi'],
                ['page' => 'livrare', 'label' => 'Livrare / AWB'],
            ],
        ],



        ['type' => 'link', 'page' => 'shop-users', 'label' => 'Clienți', 'fa' => 'fa-users'],



        ['type' => 'title', 'label' => 'Automatizare'],

        ['type' => 'link', 'page' => 'robot-monitor', 'label' => 'Monitor robot', 'fa' => 'fa-satellite-dish'],

        ['type' => 'link', 'page' => 'pieseauto', 'label' => 'PieseAuto Robot', 'fa' => 'fa-robot'],

        ['type' => 'link', 'page' => 'messages', 'label' => 'Mesagerie', 'fa' => 'fa-comments'],



        [

            'type' => 'group',

            'id' => 'website',

            'label' => 'Web site',

            'fa' => 'fa-globe',

            'pages' => ['website', 'blog', '_shop'],

            'children' => [

                ['page' => 'website', 'label' => 'Pagini site'],

                ['page' => 'blog', 'label' => 'Blog'],

                ['page' => '_shop', 'label' => 'Magazin online', 'href' => '../', 'external' => true],

            ],

        ],



        ['type' => 'title', 'label' => 'Sistem'],

        ['type' => 'link', 'page' => 'users', 'label' => 'Utilizatori admin', 'fa' => 'fa-user-shield'],

        ['type' => 'link', 'page' => 'alerts', 'label' => 'Alerte', 'fa' => 'fa-bell'],

        ['type' => 'link', 'page' => 'backup', 'label' => 'Backup', 'fa' => 'fa-cloud-arrow-down'],

        ['type' => 'link', 'page' => 'api-diagnostics', 'label' => 'Diagnostic API', 'fa' => 'fa-stethoscope'],

        ['type' => 'link', 'page' => 'settings', 'label' => 'Setări', 'fa' => 'fa-gear'],

    ];

}



function blu_admin_normalize_tab(string $tab): string

{

    $legacy = ['lista' => 'conturi', 'pasi' => 'robot', 'logica' => 'robot'];

    if (isset($legacy[$tab])) {

        return $legacy[$tab];

    }

    return $tab;

}



/** @return array{title: string, module: string} */

function blu_admin_page_meta(string $page): array

{

    $tab = blu_admin_normalize_tab((string)($_GET['tab'] ?? ''));

    $map = [

        'dashboard' => ['Dashboard', 'Admin panel'],

        'products' => ['Lista produse', 'Produse'],

        'imported' => ['Produse scanate', 'Produse'],

        'card-template' => ['Model cartelă', 'Produse'],

        'furnizori' => match ($tab) {

            'adaos' => ['Adaos comercial', 'Produse'],

            'robot' => ['Logică robot', 'Furnizori'],

            default => ['Lista furnizori', 'Furnizori'],

        },

        'pricing' => ['Adaos comercial', 'Produse'],

        'pieseauto' => ['PieseAuto Robot', 'Automatizare'],

        'robot-monitor' => ['Monitor robot', 'Automatizare'],

        'messages' => ['Mesagerie', 'Automatizare'],

        'tools' => ['Import & instrumente', 'Produse'],

        'leads' => ['Cereri clienți', 'Comenzi'],

        'orders' => ['Toate comenzile', 'Comenzi'],

        'facturi' => ['Facturi', 'Comenzi'],

        'livrare' => ['Livrare / AWB', 'Comenzi'],

        'website' => ['Pagini site', 'Web site'],

        'blog' => ['Blog', 'Web site'],

        'alerts' => ['Alerte', 'Sistem'],

        'backup' => ['Backup', 'Sistem'],

        'shop-users' => ['Clienți', 'Clienți'],

        'api-diagnostics' => ['Diagnostic API', 'Sistem'],

        'settings' => ['Setări', 'Sistem'],

        'users' => ['Utilizatori admin', 'Sistem'],

    ];

    if (isset($map[$page])) {

        return ['title' => $map[$page][0], 'module' => $map[$page][1]];

    }

    return ['title' => 'Admin', 'module' => ''];

}



function blu_admin_nav_href(array $item): string

{

    if (!empty($item['href'])) {

        return (string)$item['href'];

    }

    $q = ['page' => (string)($item['page'] ?? 'dashboard')];

    if (!empty($item['tab'])) {

        $q['tab'] = (string)$item['tab'];

    }

    return '?' . http_build_query($q);

}



function blu_admin_nav_item_active(string $page, array $item): bool

{

    $itemPage = (string)($item['page'] ?? '');

    if ($itemPage === '' || str_starts_with($itemPage, '_')) {

        return false;

    }

    if ($page === 'pricing' && $itemPage === 'furnizori' && ($item['tab'] ?? '') === 'adaos') {

        return true;

    }

    $curTab = blu_admin_normalize_tab((string)($_GET['tab'] ?? ''));

    $wantTab = (string)($item['tab'] ?? '');



    if ($page !== $itemPage) {

        return false;

    }

    if ($wantTab === '') {

        if ($itemPage === 'furnizori') {

            return in_array($curTab, ['', 'conturi', 'pieseauto'], true);

        }

        return true;

    }

    return $curTab === $wantTab;

}



function blu_admin_nav_group_active(string $page, array $group): bool

{

    $groupId = (string)($group['id'] ?? '');

    $curTab = blu_admin_normalize_tab((string)($_GET['tab'] ?? ''));



    if ($groupId === 'furnizori') {

        if ($page !== 'furnizori') {

            return false;

        }

        return $curTab !== 'adaos';

    }



    if ($groupId === 'produse') {

        if (in_array($page, ['products', 'imported', 'card-template', 'tools'], true)) {

            return true;

        }

        if ($page === 'pricing' || ($page === 'furnizori' && $curTab === 'adaos')) {

            return true;

        }

        return false;

    }



    if ($groupId === 'comenzi') {

        return in_array($page, ['orders', 'leads', 'facturi', 'livrare'], true);

    }



    if ($groupId === 'website') {

        return in_array($page, ['website', 'blog'], true);

    }



    foreach ($group['pages'] ?? [] as $p) {

        if ($page === $p) {

            return true;

        }

    }



    return false;

}



function blu_admin_nav_fa_icon(array $item): string
{
    $fa = trim((string)($item['fa'] ?? ''));
    if ($fa === '') {
        $fa = 'fa-circle';
    }
    if (!str_starts_with($fa, 'fa-')) {
        $fa = 'fa-' . $fa;
    }
    return $fa;
}



function blu_admin_render_sidebar(string $page, string $assetBase, ?array $admin = null): void

{

    $e = static function ($v): string {

        return htmlspecialchars((string)$v, ENT_QUOTES);

    };

    if ($admin === null) {
        $admin = $_SESSION['admin'] ?? null;
    }

    foreach (blu_admin_filter_nav_modules($admin) as $entry) {

        $type = (string)($entry['type'] ?? '');

        if ($type === 'title') {

            $pt = ($entry['label'] ?? '') === 'Admin panel' ? '' : ' pt-3';

            echo '<li class="sidebar-main-title blu-nav-section"><div><h5 class="f-w-700 sidebar-title' . $pt . '">' . $e($entry['label'] ?? '') . '</h5></div></li>';

            continue;

        }



        if ($type === 'link') {

            $href = blu_admin_nav_href($entry);

            $active = blu_admin_nav_item_active($page, $entry);

            $ext = !empty($entry['external']) ? ' target="_blank" rel="noopener"' : '';

            $fa = $e(blu_admin_nav_fa_icon($entry));

            echo '<li class="sidebar-list">';

            echo '<a class="sidebar-link' . ($active ? ' active' : '') . '" href="' . $e($href) . '"' . $ext . '>';

            echo '<span class="blu-nav-icon" aria-hidden="true"><i class="fa-solid ' . $fa . '"></i></span>';

            echo '<h6 class="f-w-600">' . $e($entry['label'] ?? '') . '</h6></a></li>';

            continue;

        }



        if ($type === 'group') {

            $groupOpen = blu_admin_nav_group_active($page, $entry);

            $fa = $e(blu_admin_nav_fa_icon($entry));

            echo '<li class="sidebar-list blu-nav-group' . ($groupOpen ? ' active' : '') . '">';

            echo '<details class="blu-nav-details"' . ($groupOpen ? ' open' : '') . '>';

            echo '<summary class="sidebar-link blu-nav-summary">';

            echo '<span class="blu-nav-icon" aria-hidden="true"><i class="fa-solid ' . $fa . '"></i></span>';

            echo '<h6 class="f-w-600">' . $e($entry['label'] ?? '') . '</h6>';

            echo '<i class="fa-solid fa-chevron-down blu-nav-chevron" aria-hidden="true"></i></summary>';

            echo '<ul class="sidebar-submenu">';

            foreach ($entry['children'] ?? [] as $child) {

                $href = blu_admin_nav_href($child);

                $active = blu_admin_nav_item_active($page, $child);

                $ext = !empty($child['external']) ? ' target="_blank" rel="noopener"' : '';

                echo '<li><a class="' . ($active ? 'active' : '') . '" href="' . $e($href) . '"' . $ext . '>' . $e($child['label'] ?? '') . '</a></li>';

            }

            echo '</ul></details></li>';

        }

    }

}

