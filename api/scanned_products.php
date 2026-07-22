<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/scanned_products.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
    $items = blu_pieseauto_scanned_items($q, $limit);

    echo json_encode([
        'status' => 'ok',
        'total' => count($items),
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
