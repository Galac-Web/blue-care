<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/pieseauto_accounts.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
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
if ($action === 'delete' || ($data['type_product'] ?? '') === 'delete') {
    $randomnId = (string)($data['randomn_id'] ?? $data['ridusers'] ?? '');
    $result = blu_delete_pieseauto_account($randomnId);
} else {
    $result = blu_save_pieseauto_account($data);
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
