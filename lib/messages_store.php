<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

function blu_messages_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'messages.json';
}

/** @return list<array<string,mixed>> */
function blu_messages_load(): array
{
    $rows = blu_read_json_file(blu_messages_file(), []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

/** @param list<array<string,mixed>> $rows */
function blu_messages_save(array $rows): bool
{
    return blu_write_json_file(blu_messages_file(), array_values($rows));
}

function blu_message_new_random_id(array $rows): int
{
    $used = [];
    foreach ($rows as $row) {
        $used[(int)($row['randomn_id'] ?? 0)] = true;
    }
    for ($i = 0; $i < 20; $i++) {
        $candidate = random_int(500000, 999999);
        if (!isset($used[$candidate])) {
            return $candidate;
        }
    }
    return (int) (time() . random_int(10, 99));
}

function blu_message_channel_label(string $channel): string
{
    return match ($channel) {
        'whatsapp' => 'WhatsApp',
        'olx' => 'OLX',
        'pieseauto' => 'PieseAuto.ro',
        'dezro' => 'dez.ro',
        'facebook' => 'Facebook',
        'website' => 'Website',
        'email' => 'Email',
        default => 'Manual',
    };
}

/** @param array<string,mixed> $data */
function blu_message_create(array $data): array
{
    $rows = blu_messages_load();
    $now = date('Y-m-d H:i:s');
    $randomId = blu_message_new_random_id($rows);
    $direction = in_array(($data['direction'] ?? 'outbound'), ['inbound', 'outbound'], true)
        ? (string) $data['direction']
        : 'outbound';
    $channel = trim((string) ($data['channel'] ?? 'manual')) ?: 'manual';
    $conversationId = (int) ($data['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        $conversationId = $randomId;
    }

    $row = [
        'id' => 'msg-' . $randomId,
        'randomn_id' => $randomId,
        'conversation_id' => $conversationId,
        'name' => trim((string) ($data['name'] ?? '')),
        'email' => trim((string) ($data['email'] ?? '')),
        'phone' => trim((string) ($data['phone'] ?? '')),
        'subject' => trim((string) ($data['subject'] ?? '')),
        'message_body' => trim((string) ($data['message_body'] ?? '')),
        'direction' => $direction,
        'channel' => $channel,
        'message_status' => trim((string) ($data['message_status'] ?? ($direction === 'inbound' ? 'new' : 'queued'))),
        'delivery_status' => trim((string) ($data['delivery_status'] ?? ($direction === 'inbound' ? 'received' : 'queued'))),
        'bot_status' => trim((string) ($data['bot_status'] ?? ($direction === 'inbound' ? 'none' : 'pending'))),
        'external_conversation_id' => trim((string) ($data['external_conversation_id'] ?? '')),
        'external_message_id' => trim((string) ($data['external_message_id'] ?? '')),
        'source_url' => trim((string) ($data['source_url'] ?? '')),
        'assigned_bot' => trim((string) ($data['assigned_bot'] ?? '')),
        'is_read' => !empty($data['is_read']) ? 1 : 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if ($row['name'] === '' || $row['message_body'] === '') {
        return ['ok' => false, 'message' => 'Numele și mesajul sunt obligatorii.'];
    }

    $rows[] = $row;
    blu_messages_save($rows);

    return ['ok' => true, 'row' => $row, 'randomn_id' => $randomId, 'conversation_id' => $conversationId];
}

function blu_message_mark_read(int $randomId): bool
{
    $rows = blu_messages_load();
    $found = false;
    foreach ($rows as $i => $row) {
        if ((int) ($row['randomn_id'] ?? 0) !== $randomId) {
            continue;
        }
        $found = true;
        $rows[$i]['is_read'] = 1;
        $rows[$i]['message_status'] = 'read';
        $rows[$i]['updated_at'] = date('Y-m-d H:i:s');
        break;
    }
    if (!$found) {
        return false;
    }
    blu_messages_save($rows);
    return true;
}

function blu_message_mark_conversation_read(int $conversationId): void
{
    $rows = blu_messages_load();
    $now = date('Y-m-d H:i:s');
    foreach ($rows as $i => $row) {
        if ((int) ($row['conversation_id'] ?? 0) !== $conversationId) {
            continue;
        }
        $rows[$i]['is_read'] = 1;
        $rows[$i]['message_status'] = 'read';
        $rows[$i]['updated_at'] = $now;
    }
    blu_messages_save($rows);
}

function blu_message_delete(int $randomId): bool
{
    $rows = blu_messages_load();
    $kept = array_values(array_filter($rows, static fn(array $r): bool => (int) ($r['randomn_id'] ?? 0) !== $randomId));
    if (count($kept) === count($rows)) {
        return false;
    }
    blu_messages_save($kept);
    return true;
}

/** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
function blu_messages_paginate(array $rows, int $page, int $perPage): array
{
    $total = count($rows);
    $perPage = max(1, min(100, $perPage));
    $page = max(1, $page);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    return [
        'items' => array_slice($rows, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
function blu_messages_filter(string $q, array $rows): array
{
    $q = mb_strtolower(trim($q));
    if ($q === '') {
        return $rows;
    }
    return array_values(array_filter($rows, static function (array $row) use ($q): bool {
        foreach (['name', 'email', 'phone', 'subject', 'message_body', 'channel'] as $field) {
            if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $q)) {
                return true;
            }
        }
        return false;
    }));
}

/** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
function blu_messages_list(int $page = 1, int $perPage = 10, string $q = ''): array
{
    $rows = blu_messages_load();
    usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    $filtered = blu_messages_filter($q, $rows);
    return blu_messages_paginate($filtered, $page, $perPage);
}

/** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
function blu_messages_conversations(int $page = 1, int $perPage = 10, string $q = ''): array
{
    $rows = blu_messages_load();
    $filtered = blu_messages_filter($q, $rows);
    $latestByConv = [];
    foreach ($filtered as $row) {
        $convId = (int) ($row['conversation_id'] ?? $row['randomn_id'] ?? 0);
        if ($convId <= 0) {
            continue;
        }
        $cur = $latestByConv[$convId] ?? null;
        if ($cur === null || strcmp((string) ($row['created_at'] ?? ''), (string) ($cur['created_at'] ?? '')) > 0) {
            $latestByConv[$convId] = $row;
        }
    }
    $conversations = array_values($latestByConv);
    usort($conversations, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return blu_messages_paginate($conversations, $page, $perPage);
}

/** @return list<array<string,mixed>> */
function blu_messages_by_conversation(int $conversationId): array
{
    $rows = array_values(array_filter(
        blu_messages_load(),
        static fn(array $r): bool => (int) ($r['conversation_id'] ?? 0) === $conversationId
    ));
    usort($rows, static fn(array $a, array $b): int => ((int) ($a['randomn_id'] ?? 0)) <=> ((int) ($b['randomn_id'] ?? 0)));
    return $rows;
}

function blu_messages_unread_count(): int
{
    $count = 0;
    foreach (blu_messages_conversations(1, 500, '')['items'] as $row) {
        if (empty($row['is_read']) && ($row['direction'] ?? '') === 'inbound') {
            $count++;
        }
    }
    return $count;
}

function blu_messages_import_leads(): int
{
    require_once __DIR__ . '/shop/leads.php';
    $leads = blu_read_json_file(blu_shop_leads_file(), []);
    if (!is_array($leads)) {
        return 0;
    }
    $rows = blu_messages_load();
    $keys = [];
    foreach ($rows as $row) {
        $keys[mb_strtolower(trim((string) ($row['phone'] ?? '')) . '|' . trim((string) ($row['created_at'] ?? '')))] = true;
    }
    $imported = 0;
    foreach ($leads as $lead) {
        if (!is_array($lead)) {
            continue;
        }
        $phone = trim((string) ($lead['phone'] ?? $lead['p'] ?? ''));
        $created = trim((string) ($lead['created_at'] ?? $lead['data'] ?? date('Y-m-d H:i:s')));
        $key = mb_strtolower($phone . '|' . $created);
        if (isset($keys[$key])) {
            continue;
        }
        $text = $lead['t'] ?? $lead['items'] ?? '';
        if (is_array($text)) {
            $text = implode("\n", $text);
        }
        $result = blu_message_create([
            'name' => trim((string) ($lead['name'] ?? $lead['nume'] ?? 'Client')),
            'phone' => $phone,
            'email' => trim((string) ($lead['email'] ?? '')),
            'message_body' => trim((string) $text) ?: 'Cerere din magazin',
            'subject' => trim((string) ($lead['source'] ?? 'lead')),
            'channel' => str_contains((string) ($lead['source'] ?? ''), 'contact') ? 'website' : 'manual',
            'direction' => 'inbound',
            'delivery_status' => 'received',
            'bot_status' => 'needs_human',
            'is_read' => 0,
        ]);
        if (!empty($result['ok'])) {
            $keys[$key] = true;
            $imported++;
        }
    }
    return $imported;
}
