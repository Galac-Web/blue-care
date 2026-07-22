<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Robot-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/lib/robot_feed.php';
require_once dirname(__DIR__) . '/lib/shop/gbg_structure.php';

header('Content-Type: application/json; charset=utf-8');

if (!blu_robot_api_key_ok()) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'API key invalida.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'structure' => blu_gbg_load_structure(),
        'brands_count' => count(blu_gbg_structure_brands()),
        'model_brands_count' => count(blu_gbg_structure_model_groups()),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode((string) $raw, true);
    if (is_array($json)) {
        $input = $json;
    }
    $input = array_merge($input, $_POST);
}

if ($input === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Payload gol.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$merged = blu_gbg_merge_structure($input);
echo json_encode([
    'ok' => true,
    'structure' => $merged,
    'brands_count' => count($merged['brands'] ?? []),
    'model_brands_count' => count($merged['model_groups'] ?? []),
], JSON_UNESCAPED_UNICODE);
