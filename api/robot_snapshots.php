<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/robot_snapshots.php';

$contId = trim((string)($_GET['cont_id'] ?? ''));
$snapshotId = trim((string)($_GET['id'] ?? ''));
$view = ($_GET['view'] ?? '') === 'html';

if (!($_SESSION['admin'] ?? null)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($view && $contId !== '' && $snapshotId !== '') {
    $path = blu_robot_snapshot_html_path($contId, $snapshotId);
    if (!$path) {
        http_response_code(404);
        echo 'Snapshot negăsit.';
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if ($contId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lipsește cont_id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'snapshots' => blu_list_robot_snapshots($contId, 80),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
