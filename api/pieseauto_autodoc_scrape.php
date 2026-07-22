<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/lib/autodoc_scraper.php';
require_once dirname(__DIR__) . '/lib/catalog_import.php';

$input = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode(is_string($raw) ? $raw : '', true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    } elseif (!empty($_POST)) {
        $input = array_merge($input, $_POST);
    }
}

$oem = trim((string)($input['oem'] ?? ''));
$oem = preg_replace('/\s+/u', '', $oem);

if ($oem === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Lipsește Cod OEM.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ctx = blu_autodoc_scrape_context($input + ['oem' => $oem]);

    $search = blu_autodoc_fetch_for_entry([
        'coduri_oem' => $ctx['coduri_oem'] !== '' ? $ctx['coduri_oem'] : $oem,
        'cod_articol' => $ctx['cod_articol'],
        'brand' => $ctx['brand'],
        'model' => $ctx['model'],
    ], 3600);

    $products = is_array($search['products'] ?? null) ? $search['products'] : [];

    if ($products === []) {
        $api = blu_autodoc_fetch_by_oem($oem, 3600);
        $products = is_array($api['products'] ?? null) ? $api['products'] : [];
    }

    $primary = blu_autodoc_pick_primary_item($products);
    if (!$primary) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Nu am găsit rezultat pe Autodoc24 pentru OEM dat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formatted = blu_autodoc_to_template_card($primary, $input + ['oem' => $oem]);

    echo json_encode([
        'ok' => true,
        'title' => $formatted['title'],
        'description' => $formatted['description'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Excepție în Autodoc scraper: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
