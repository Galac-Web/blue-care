<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/robot_launcher.php';

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

$robot = strtolower(trim((string)($data['robot'] ?? $_GET['robot'] ?? 'furnizori')));
if (!in_array($robot, ['furnizori', 'pieseauto', 'all'], true)) {
    $robot = 'furnizori';
}

if ($method === 'GET') {
    if ($robot === 'all') {
        echo json_encode([
            'success' => true,
            'furnizori' => [
                'online' => blu_robot_ping('furnizori'),
                'base_url' => blu_robot_furnizori_base_url(),
            ],
            'pieseauto' => [
                'online' => blu_robot_ping('pieseauto'),
                'base_url' => blu_robot_pieseauto_base_url(),
            ],
            'auto_start' => blu_robot_auto_start_enabled(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $profile = blu_robot_profile($robot);
    echo json_encode([
        'success' => true,
        'robot' => $robot,
        'online' => blu_robot_ping($robot),
        'auto_start' => blu_robot_auto_start_enabled(),
        'local_target' => in_array(
            strtolower((string)(parse_url($profile['base_url'], PHP_URL_HOST) ?? '')),
            ['127.0.0.1', 'localhost', '::1'],
            true
        ),
        'robot_base' => $profile['base_url'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = strtolower(trim((string)($data['action'] ?? 'start')));
if ($action === 'ping') {
    if ($robot === 'all') {
        echo json_encode([
            'success' => true,
            'furnizori' => blu_robot_ping('furnizori'),
            'pieseauto' => blu_robot_ping('pieseauto'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'online' => blu_robot_ping($robot), 'robot' => $robot], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($robot === 'all') {
    $results = blu_robot_ensure_all_running(true);
    http_response_code(200);
    echo json_encode(['success' => true, 'robots' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = blu_robot_ensure_running($robot, true);
$result['online'] = blu_robot_ping($robot);
http_response_code($result['success'] ? 200 : 503);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
