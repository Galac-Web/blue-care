<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/website_cms.php';
require_once dirname(__DIR__) . '/lib/website_builder.php';

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

$page = trim((string) ($data['page'] ?? ''));
$fields = $data['fields'] ?? [];
$blocks = $data['blocks'] ?? null;
if (!is_array($fields)) {
  $fields = [];
}

$messages = [];

if ($fields !== []) {
  $result = blu_website_cms_save($page, $fields);
  if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => (string) ($result['message'] ?? 'Eroare.')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $messages[] = (string) ($result['message'] ?? 'Text salvat.');
}

if (is_array($blocks)) {
  $blockResult = blu_builder_save_blocks($page, $blocks);
  if (empty($blockResult['ok'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => (string) ($blockResult['message'] ?? 'Eroare blocuri.')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $messages[] = (string) ($blockResult['message'] ?? 'Blocuri salvate.');
}

if ($messages === []) {
  $messages[] = 'Nimic de salvat.';
}

echo json_encode([
  'success' => true,
  'message' => implode(' ', $messages),
], JSON_UNESCAPED_UNICODE);
