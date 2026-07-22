<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/gbg_suppliers.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = strtolower(trim((string)($data['action'] ?? '')));
if ($action === 'delete') {
    $result = blu_delete_gbg_supplier((string)($data['randomn_id'] ?? $data['ridusers'] ?? ''));
} elseif ($action === 'import_catalog' || $action === 'seed') {
    $result = blu_import_web_suppliers_catalog(true);
} else {
    $result = blu_save_gbg_supplier($data);
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
