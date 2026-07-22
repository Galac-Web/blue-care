<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Robot-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/lib/robot_feed.php';

header('Content-Type: application/json; charset=utf-8');

if (!blu_robot_api_key_ok()) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'API key invalida.',
        'hint' => 'Pe server exista ROBOT_API_KEY in .env — pune aceeasi cheie in robot_config.json (robot_api_key) sau goleste ROBOT_API_KEY pe server.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'import')));

if ($action === 'feed') {
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    echo json_encode([
        'ok' => true,
        'feed' => blu_robot_get_feed($limit),
        'stats' => blu_robot_load_stats(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'stats') {
    echo json_encode(['ok' => true, 'stats' => blu_robot_load_stats()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'ping') {
    $needKey = trim((string)blu_env('ROBOT_API_KEY', '')) !== '';
    echo json_encode([
        'ok' => blu_robot_api_key_ok(),
        'key_required' => $needKey,
        'key_received' => blu_robot_provided_api_key() !== '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json)) {
        $input = $json;
    }
    $input = array_merge($input, $_POST);
} else {
    $input = $_GET;
}

$result = blu_robot_process_part($input);
http_response_code($result['ok'] ? 200 : 422);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
