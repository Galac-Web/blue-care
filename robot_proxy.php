<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/robot_config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning');
    http_response_code(204);
    exit;
}

$targetBase = blu_robot_furnizori_effective_url();

$path = $_GET['path'] ?? '/';
if (!is_string($path) || $path === '' || $path[0] !== '/' || preg_match('#^//#', $path)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'mesaj' => 'Robot proxy path invalid.']);
    exit;
}

$url = $targetBase . $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = file_get_contents('php://input') ?: '';
$headers = [
    'ngrok-skip-browser-warning: 69420',
];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (is_string($contentType) && $contentType !== '') {
    $headers[] = 'Content-Type: ' . $contentType;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_HTTPHEADER => $headers,
]);

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentTypeResponse = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8';
curl_close($ch);

header('Access-Control-Allow-Origin: *');
header('Content-Type: ' . $contentTypeResponse);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'mesaj' => 'Robot Furnizori indisponibil. Verifică robot1.py și ROBOT_FURNIZORI_URL din .env (port '
            . (parse_url($targetBase, PHP_URL_PORT) ?: '5000') . ').',
        'detalii' => $error,
        'target' => $targetBase,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($status > 0 ? $status : 200);
echo $response;
