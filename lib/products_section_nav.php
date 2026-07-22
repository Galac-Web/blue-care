<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_section_nav.php';

/** @param string $active products|imported|card-template|adaos|tools */
function blu_render_products_section_nav(string $active): void
{
    blu_render_admin_section_nav('produse', $active);
}
