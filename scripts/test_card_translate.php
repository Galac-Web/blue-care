<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/env.php';
require_once dirname(__DIR__) . '/lib/catalog_import.php';
require_once dirname(__DIR__) . '/lib/tecdoc_product_enrich.php';
require_once dirname(__DIR__) . '/lib/ollama_client.php';

$cases = [
    'Window regulator, front left',
    'Hood',
    'Brake pad set',
    'Outside mirror right',
    'Capota Motor',
    'Shock absorber rear',
    'Tail light left',
];

echo "=== Traduceri EN→RO ===\n";
foreach ($cases as $c) {
    echo $c . ' => ' . blu_tecdoc_translate_title($c) . "\n";
}

$fmt = blu_card_template_format([
    'part_name' => blu_tecdoc_translate_title('Window regulator front left'),
    'brand' => 'MITSUBISHI',
    'model' => 'OUTLANDER 2013-2020',
    'year_range' => '2013 - 2020',
    'oem_codes' => ['5900A540', '5900A739'],
    'internal_code' => 'ABC123',
    'vehicles' => ['MITSUBISHI OUTLANDER 2013-2020'],
]);

echo "\n=== Model cartelă ===\n";
echo "TITLE: " . $fmt['title'] . "\n";
echo "TEMPLATE_OK: " . (blu_description_uses_card_template($fmt['description']) ? 'yes' : 'no') . "\n";
echo "DESC:\n" . $fmt['description'] . "\n";

$g = blu_gbg_greek_dictionary_match('ΓΡΥΛΛΟΣ ΠΑΡΑΘΥΡΟΥ ΕΜΠΡΟΣ ΑΡ Α ΠΟΙΟΤΗΤΑ');
echo "\n=== GR dict ===\n";
echo json_encode($g, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
