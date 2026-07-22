<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/messages_store.php';

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

$action = strtolower(trim((string) ($data['action'] ?? $data['type_product'] ?? 'conversations')));
$page = max(1, (int) ($data['page'] ?? 1));
$perPage = max(1, min(100, (int) ($data['per_page'] ?? 10)));
$q = trim((string) ($data['q'] ?? ''));

try {
    $payload = match ($action) {
        'list' => blu_messages_list($page, $perPage, $q),
        'conversations' => blu_messages_conversations($page, $perPage, $q),
        'conversation' => blu_messages_by_conversation((int) ($data['conversation_id'] ?? 0)),
        'add' => (function () use ($data): array {
            $result = blu_message_create($data);
            if (empty($result['ok'])) {
                throw new RuntimeException((string) ($result['message'] ?? 'Eroare salvare.'));
            }
            return [
                'randomn_id' => (int) ($result['randomn_id'] ?? 0),
                'conversation_id' => (int) ($result['conversation_id'] ?? 0),
                'row' => $result['row'] ?? null,
            ];
        })(),
        'markread' => (function () use ($data): ?array {
            $randomId = (int) ($data['randomn_id'] ?? $data['id'] ?? 0);
            $convId = (int) ($data['conversation_id'] ?? 0);
            if ($convId > 0) {
                blu_message_mark_conversation_read($convId);
            } elseif ($randomId > 0) {
                if (!blu_message_mark_read($randomId)) {
                    throw new RuntimeException('Mesajul nu a fost găsit.');
                }
            } else {
                throw new RuntimeException('Lipsește identificatorul mesajului.');
            }
            return null;
        })(),
        'delete' => (function () use ($data): ?array {
            $randomId = (int) ($data['randomn_id'] ?? $data['id'] ?? 0);
            if ($randomId <= 0 || !blu_message_delete($randomId)) {
                throw new RuntimeException('Mesajul nu a putut fi șters.');
            }
            return null;
        })(),
        'import_leads' => ['imported' => blu_messages_import_leads()],
        'stats' => ['unread' => blu_messages_unread_count()],
        default => throw new RuntimeException('Acțiune necunoscută: ' . $action),
    };

    echo json_encode(['success' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
