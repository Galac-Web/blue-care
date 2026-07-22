<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/lib/shop/gbg_structure.php';

$brand = trim((string) ($_GET['brand'] ?? ''));
if ($brand === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Parametrul brand lipsește.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$groups = blu_gbg_models_for_brand($brand);
echo json_encode([
    'ok' => true,
    'brand' => $brand,
    'groups' => $groups,
    'count' => count($groups),
], JSON_UNESCAPED_UNICODE);
