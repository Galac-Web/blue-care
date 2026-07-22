<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/robot_workflow.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$contId = trim((string)($_GET['cont_id'] ?? $_POST['cont_id'] ?? ''));

// Robot Python citește workflow cu cheie API
$apiKey = trim((string)($_GET['api_key'] ?? $_SERVER['HTTP_X_ROBOT_API_KEY'] ?? ''));
$expectedKey = trim((string)(getenv('ROBOT_API_KEY') ?: ''));
if ($expectedKey === '') {
    require_once dirname(__DIR__) . '/lib/env.php';
    $expectedKey = trim((string)blu_env('ROBOT_API_KEY', '19921705'));
}

$isRobot = $apiKey !== '' && hash_equals($expectedKey, $apiKey);

if (!$isRobot && !($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    if ($contId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lipsește cont_id.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success' => true,
        'workflow' => blu_load_robot_workflow($contId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($isRobot) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Robotul poate doar citi workflow (GET).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$contId = trim((string)($data['cont_id'] ?? $contId));
$workflow = $data['workflow'] ?? $data;
if (!is_array($workflow)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Date invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = blu_save_robot_workflow($contId, $workflow);
http_response_code($result['success'] ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
