<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/lib/catalog_import.php';

if (!blu_import_access_ok()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acces interzis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = min(5, max(1, (int)($_GET['limit'] ?? 1)));

echo json_encode(blu_reenrich_imported_cards_batch($offset, $limit), JSON_UNESCAPED_UNICODE);
