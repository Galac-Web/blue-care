<?php

declare(strict_types=1);



require_once __DIR__ . '/robot_workflow.php';

require_once __DIR__ . '/gbg_suppliers.php';

require_once __DIR__ . '/products_section_nav.php';
require_once __DIR__ . '/admin_section_nav.php';



/**

 * Furnizori — cartele + popup (fără tab-uri interne).

 * Logică robot / adaos rămân accesibile din Automatizare / Produse.

 */

function blu_render_furnizori_admin_panel(string $csrf, array $gbgSuppliers): void

{

    $tab = blu_admin_normalize_tab((string)($_GET['tab'] ?? ''));
    $selectedCont = trim((string)($_GET['cont_id'] ?? ''));

    if (in_array($tab, ['pieseauto', 'conturi', 'lista'], true)) {
        header('Location: ?page=furnizori' . ($selectedCont !== '' ? '&cont_id=' . rawurlencode($selectedCont) : ''));
        exit;
    }

    if ($tab === 'robot') {
        blu_render_admin_section_nav('furnizori', 'robot');
        echo '<p class="text-muted small mb-3"><a href="?page=furnizori"><i class="fa-solid fa-arrow-left"></i> Înapoi la furnizori</a></p>';

        require_once __DIR__ . '/furnizori_robot_panel.php';

        blu_render_furnizori_robot_panel($csrf, $gbgSuppliers, $selectedCont);

        return;

    }



    if ($tab === 'adaos') {

        blu_render_products_section_nav('adaos');

        require_once __DIR__ . '/pricing_panel.php';

        blu_render_pricing_panel($csrf);

        return;
    }

    require_once __DIR__ . '/furnizori_suppliers_list.php';
    blu_render_admin_section_nav('furnizori', 'lista');
    blu_render_furnizori_suppliers_list($gbgSuppliers, $selectedCont);
}

