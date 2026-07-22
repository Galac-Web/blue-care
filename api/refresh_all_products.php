<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/lib/catalog_import.php';

if (!blu_import_access_ok()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acces interzis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mode = strtolower(trim((string)($_GET['mode'] ?? 'refresh_all')));
if ($mode === 'sync') {
    echo json_encode(blu_sync_all_product_sources(), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(blu_refresh_all_imported_products(), JSON_UNESCAPED_UNICODE);
