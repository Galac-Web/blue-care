<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
// Evită ca warnings/notices PHP să strice JSON-ul returnat.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/lib/ovoko_scraper.php';

$oem = trim((string)($_GET['oem'] ?? $_POST['oem'] ?? ''));
try {
    $result = blu_ovoko_scrape_card($oem);
} catch (Throwable $e) {
    http_response_code(500);
    $result = [
        'ok' => false,
        'error' => 'Excepție în scraper: ' . $e->getMessage(),
    ];
}

if (empty($result['ok'])) {
    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

