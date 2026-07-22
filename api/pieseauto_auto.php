<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/pieseauto_auto.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_GET;
}

$action = strtolower(trim((string)($data['action'] ?? $_GET['action'] ?? 'status')));

if ($action === 'status' && $method === 'GET') {
    $contId = blu_pieseauto_auto_cont_id();
    $online = blu_robot_ping('pieseauto');
    $browser = false;
    if ($online) {
        $state = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($contId), null, 'GET', 6);
        $browser = !empty($state['browser_active']);
    }

    echo json_encode([
        'success' => true,
        'config' => blu_pieseauto_auto_config(),
        'online' => $online,
        'browser_active' => $browser,
        'published_count' => count(blu_pieseauto_published_map()),
        'robot_base' => blu_robot_pieseauto_base_url(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'config' && $method === 'POST') {
    $cfg = blu_pieseauto_auto_config();
    if (array_key_exists('enabled', $data)) {
        $cfg['enabled'] = !in_array(strtolower(trim((string)$data['enabled'])), ['0', 'false', 'no', 'off'], true);
    }
    if (!empty($data['cont_id'])) {
        $cfg['cont_id'] = preg_replace('/[^a-zA-Z0-9]/', '', (string)$data['cont_id']) ?: $cfg['cont_id'];
    }
    if (isset($data['default_price'])) {
        $cfg['default_price'] = max(1.0, (float)$data['default_price']);
    }

    $ok = blu_write_json_file(blu_pieseauto_auto_config_file(), [
        'enabled' => $cfg['enabled'],
        'cont_id' => $cfg['cont_id'],
        'default_price' => $cfg['default_price'],
        'min_title_len' => $cfg['min_title_len'],
        'stare_produs' => $cfg['stare_produs'],
    ]);

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Config salvat.' : 'Nu am putut salva config-ul.',
        'config' => blu_pieseauto_auto_config(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'prepare' && in_array($method, ['GET', 'POST'], true)) {
    $wait = max(0, min(30, (int)($data['wait_browser_sec'] ?? $_GET['wait_browser_sec'] ?? 8)));
    $openBrowser = !in_array(
        strtolower(trim((string)($data['open_browser'] ?? $_GET['open_browser'] ?? '0'))),
        ['0', 'false', 'no', 'off'],
        true
    );
    $result = blu_pieseauto_auto_prepare($wait, $openBrowser);
    http_response_code($result['success'] ? 200 : 503);
    echo json_encode(array_merge(['success' => $result['success']], $result), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Acțiune necunoscută.'], JSON_UNESCAPED_UNICODE);
