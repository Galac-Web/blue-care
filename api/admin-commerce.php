<?php
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/lib/commerce_store.php';

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

$entity = strtolower(trim((string)($data['entity'] ?? '')));
$action = strtolower(trim((string)($data['action'] ?? 'list')));

function blu_commerce_json_ok($payload = null): void
{
    echo json_encode(['success' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
    exit;
}

function blu_commerce_json_err(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function blu_commerce_filter_rows(array $rows, string $q, array $fields): array
{
    $q = mb_strtolower(trim($q));
    if ($q === '') {
        return $rows;
    }
    return array_values(array_filter($rows, static function (array $row) use ($q, $fields): bool {
        foreach ($fields as $field) {
            if (str_contains(mb_strtolower((string)($row[$field] ?? '')), $q)) {
                return true;
            }
        }
        return false;
    }));
}

function blu_invoices_stats(): array
{
    $rows = blu_invoices_list();
    $stats = [
        'all' => count($rows),
        'achitata' => 0,
        'neachitata' => 0,
        'anulata' => 0,
        'storno' => 0,
        'total_amount' => 0.0,
    ];
    foreach ($rows as $row) {
        $status = (string)($row['invoice_status'] ?? '');
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
        $stats['total_amount'] += (float)($row['amount'] ?? 0);
    }
    $stats['total_amount'] = round($stats['total_amount'], 2);
    return $stats;
}

function blu_deliveries_stats(): array
{
    $rows = blu_deliveries_list();
    $stats = [
        'all' => count($rows),
        'pregatire' => 0,
        'awb_generat' => 0,
        'in_tranzit' => 0,
        'livrat' => 0,
        'retur' => 0,
        'anulat' => 0,
    ];
    foreach ($rows as $row) {
        $status = (string)($row['delivery_status'] ?? '');
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    return $stats;
}

function blu_orders_stats(): array
{
    $rows = blu_orders_list();
    $stats = [
        'all' => count($rows),
        'comandat' => 0,
        'procesare' => 0,
        'expediere' => 0,
        'livrat' => 0,
        'anulat' => 0,
        'total_amount' => 0.0,
    ];
    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? '');
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
        $stats['total_amount'] += (float)($row['total_amount'] ?? 0);
    }
    $stats['total_amount'] = round($stats['total_amount'], 2);
    return $stats;
}

match ($entity) {
    'orders' => match ($action) {
        'list' => blu_commerce_json_ok(blu_commerce_filter_rows(
            blu_orders_list(),
            (string)($data['q'] ?? ''),
            ['order_number', 'client_name', 'phone', 'email', 'address', 'items_text']
        )),
        'stats' => blu_commerce_json_ok(blu_orders_stats()),
        'save' => (function () use ($data): void {
            $result = blu_order_save($data);
            if (empty($result['ok'])) {
                blu_commerce_json_err((string)($result['message'] ?? 'Eroare salvare.'));
            }
            blu_commerce_json_ok($result['row'] ?? null);
        })(),
        'delete' => (function () use ($data): void {
            $id = trim((string)($data['id'] ?? ''));
            if ($id === '' || !blu_order_delete($id)) {
                blu_commerce_json_err('Comanda nu a putut fi ștearsă.');
            }
            blu_commerce_json_ok(['deleted' => $id]);
        })(),
        'import_leads' => (function (): void {
            require_once dirname(__DIR__) . '/lib/shop/leads.php';
            $leadsFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'leads.json';
            $leads = blu_read_json_file($leadsFile, []);
            if (!is_array($leads)) {
                $leads = [];
            }
            $existing = blu_orders_list();
            $existingPhones = [];
            foreach ($existing as $row) {
                $key = mb_strtolower(trim((string)($row['phone'] ?? '')) . '|' . trim((string)($row['created_at'] ?? '')));
                if ($key !== '|') {
                    $existingPhones[$key] = true;
                }
            }
            $imported = 0;
            foreach ($leads as $lead) {
                if (!is_array($lead)) {
                    continue;
                }
                $phone = trim((string)($lead['phone'] ?? $lead['p'] ?? ''));
                $created = trim((string)($lead['created_at'] ?? $lead['data'] ?? date('Y-m-d H:i:s')));
                $key = mb_strtolower($phone . '|' . $created);
                if (isset($existingPhones[$key])) {
                    continue;
                }
                $itemsText = $lead['items'] ?? $lead['t'] ?? '';
                if (is_array($itemsText)) {
                    $itemsText = implode("\n", $itemsText);
                }
                blu_order_save([
                    'client_name' => trim((string)($lead['name'] ?? $lead['nume'] ?? '')),
                    'phone' => $phone,
                    'email' => trim((string)($lead['email'] ?? '')),
                    'address' => trim((string)($lead['address'] ?? $lead['adresa'] ?? '')),
                    'notes' => trim((string)($lead['note'] ?? '')),
                    'items_text' => trim((string)$itemsText),
                    'total_amount' => (float)preg_replace('/[^\d.,]/', '', (string)($lead['total'] ?? '0')),
                    'source' => trim((string)($lead['source'] ?? 'lead_import')),
                    'status' => 'comandat',
                ]);
                $existingPhones[$key] = true;
                $imported++;
            }
            blu_commerce_json_ok(['imported' => $imported]);
        })(),
        default => blu_commerce_json_err('Acțiune necunoscută pentru comenzi.'),
    },
    'invoices' => match ($action) {
        'list' => blu_commerce_json_ok(blu_commerce_filter_rows(
            blu_invoices_list(),
            (string)($data['q'] ?? ''),
            ['invoice_number', 'order_number', 'client_name', 'phone', 'email']
        )),
        'stats' => blu_commerce_json_ok(blu_invoices_stats()),
        'save' => (function () use ($data): void {
            $result = blu_invoice_save($data);
            if (empty($result['ok'])) {
                blu_commerce_json_err((string)($result['message'] ?? 'Eroare salvare.'));
            }
            blu_commerce_json_ok($result['row'] ?? null);
        })(),
        'delete' => (function () use ($data): void {
            $id = trim((string)($data['id'] ?? ''));
            if ($id === '' || !blu_invoice_delete($id)) {
                blu_commerce_json_err('Factura nu a putut fi ștearsă.');
            }
            blu_commerce_json_ok(['deleted' => $id]);
        })(),
        'whatsapp' => (function () use ($data): void {
            require_once dirname(__DIR__) . '/lib/invoice_document.php';
            $id = trim((string)($data['id'] ?? ''));
            $invoice = blu_invoice_find($id);
            if ($invoice === null) {
                blu_commerce_json_err('Factura nu există.');
            }
            blu_invoice_mark_sent($invoice, 'whatsapp');
            blu_commerce_json_ok([
                'url' => blu_invoice_whatsapp_url($invoice),
                'message' => blu_invoice_whatsapp_message($invoice),
            ]);
        })(),
        'send_email' => (function () use ($data): void {
            require_once dirname(__DIR__) . '/lib/invoice_document.php';
            $id = trim((string)($data['id'] ?? ''));
            $invoice = blu_invoice_find($id);
            if ($invoice === null) {
                blu_commerce_json_err('Factura nu există.');
            }
            $result = blu_invoice_send_email($invoice);
            if (empty($result['ok'])) {
                blu_commerce_json_err((string)($result['message'] ?? 'Nu am putut trimite emailul.'));
            }
            blu_commerce_json_ok(['message' => (string)($result['message'] ?? 'Email trimis.')]);
        })(),
        'create_from_order' => (function () use ($data): void {
            require_once dirname(__DIR__) . '/lib/invoice_document.php';
            $orderId = trim((string)($data['order_id'] ?? ''));
            if ($orderId === '') {
                blu_commerce_json_err('Selectează o comandă.');
            }
            $result = blu_invoice_create_from_order($orderId);
            if (empty($result['ok'])) {
                blu_commerce_json_err((string)($result['message'] ?? 'Factura nu a putut fi generată.'));
            }
            blu_commerce_json_ok([
                'row' => $result['row'] ?? null,
                'message' => (string)($result['message'] ?? 'Factură generată.'),
            ]);
        })(),
        default => blu_commerce_json_err('Acțiune necunoscută pentru facturi.'),
    },
    'deliveries' => match ($action) {
        'list' => blu_commerce_json_ok(blu_commerce_filter_rows(
            blu_deliveries_list(),
            (string)($data['q'] ?? ''),
            ['awb', 'order_number', 'client_name', 'phone', 'email', 'address', 'courier']
        )),
        'stats' => blu_commerce_json_ok(blu_deliveries_stats()),
        'save' => (function () use ($data): void {
            $result = blu_delivery_save($data);
            if (empty($result['ok'])) {
                blu_commerce_json_err((string)($result['message'] ?? 'Eroare salvare.'));
            }
            blu_commerce_json_ok($result['row'] ?? null);
        })(),
        'delete' => (function () use ($data): void {
            $id = trim((string)($data['id'] ?? ''));
            if ($id === '' || !blu_delivery_delete($id)) {
                blu_commerce_json_err('Livrarea nu a putut fi ștearsă.');
            }
            blu_commerce_json_ok(['deleted' => $id]);
        })(),
        default => blu_commerce_json_err('Acțiune necunoscută pentru livrări.'),
    },
    default => blu_commerce_json_err('Entitate necunoscută.'),
};
