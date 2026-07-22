<?php
declare(strict_types=1);

/**
 * Tab-uri orizontale pe secțiuni (ca besoiupieseauto.ro) — blocuri de navigare în pagină.
 */
function blu_render_admin_section_nav(string $section, string $active): void
{
    $sections = match ($section) {
        'produse' => [
            'products' => ['label' => 'Lista produse', 'href' => '?page=products'],
            'imported' => ['label' => 'Produse scanate', 'href' => '?page=imported'],
            'card-template' => ['label' => 'Model cartelă', 'href' => '?page=card-template'],
            'adaos' => ['label' => 'Adaos comercial', 'href' => '?page=furnizori&tab=adaos'],
            'tools' => ['label' => 'Import & instrumente', 'href' => '?page=tools'],
        ],
        'furnizori' => [
            'lista' => ['label' => 'Lista furnizori', 'href' => '?page=furnizori'],
            'robot' => ['label' => 'Logică robot', 'href' => '?page=furnizori&tab=robot'],
        ],
        'automatizare' => [
            'robot-monitor' => ['label' => 'Monitor robot', 'href' => '?page=robot-monitor'],
            'pieseauto' => ['label' => 'PieseAuto Robot', 'href' => '?page=pieseauto'],
            'messages' => ['label' => 'Mesagerie', 'href' => '?page=messages'],
        ],
        'comenzi' => [
            'orders' => ['label' => 'Toate comenzile', 'href' => '?page=orders'],
            'leads' => ['label' => 'Cereri clienți', 'href' => '?page=leads'],
            'facturi' => ['label' => 'Facturi', 'href' => '?page=facturi'],
            'livrare' => ['label' => 'Livrare / AWB', 'href' => '?page=livrare'],
        ],
        'website' => [
            'website' => ['label' => 'Pagini site', 'href' => '?page=website'],
            'blog' => ['label' => 'Blog', 'href' => '?page=blog'],
        ],
        'sistem' => [
            'users' => ['label' => 'Utilizatori admin', 'href' => '?page=users'],
            'alerts' => ['label' => 'Alerte', 'href' => '?page=alerts'],
            'backup' => ['label' => 'Backup', 'href' => '?page=backup'],
            'api-diagnostics' => ['label' => 'Diagnostic API', 'href' => '?page=api-diagnostics'],
            'settings' => ['label' => 'Setări', 'href' => '?page=settings'],
        ],
        default => [],
    };

    if ($sections === []) {
        return;
    }

    if (function_exists('blu_admin_can_page')) {
        $sections = array_filter($sections, static function (array $item): bool {
            $href = (string) ($item['href'] ?? '');
            parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
            $pageKey = (string) ($query['page'] ?? '');
            return $pageKey === '' || blu_admin_can_page($pageKey);
        });
    }

    if ($sections === []) {
        return;
    }

    $aria = match ($section) {
        'produse' => 'Secțiuni produse',
        'furnizori' => 'Secțiuni furnizori',
        'automatizare' => 'Automatizare',
        'comenzi' => 'Comenzi',
        'website' => 'Web site',
        'sistem' => 'Sistem',
        default => 'Navigare secțiune',
    };
    ?>
    <nav class="blu-section-nav" aria-label="<?= htmlspecialchars($aria, ENT_QUOTES) ?>">
        <?php foreach ($sections as $key => $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"
               class="blu-section-nav__tab<?= $active === $key ? ' is-active' : '' ?>"
               <?= $active === $key ? ' aria-current="page"' : '' ?>>
                <?= htmlspecialchars($item['label'], ENT_QUOTES) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
