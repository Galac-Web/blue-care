<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/lib/shop/bootstrap.php';
require_once dirname(__DIR__) . '/lib/shop/cart.php';

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = (string) ($input['action'] ?? '');
$id = (int) ($input['id'] ?? 0);
$qty = (int) ($input['qty'] ?? 1);

switch ($action) {
    case 'add':
        if (!blu_cart_add($id, $qty)) {
            echo json_encode(['ok' => false, 'error' => 'Produs negăsit.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true, 'count' => blu_cart_count()], JSON_UNESCAPED_UNICODE);
        break;
    case 'count':
        echo json_encode(['ok' => true, 'count' => blu_cart_count()], JSON_UNESCAPED_UNICODE);
        break;
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acțiune invalidă.'], JSON_UNESCAPED_UNICODE);
}
