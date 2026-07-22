<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';
require_once __DIR__ . '/shop/prices.php';

function blu_orders_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'orders.json';
}

function blu_invoices_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'invoices.json';
}

function blu_deliveries_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'deliveries.json';
}

/** @return list<array<string,mixed>> */
function blu_commerce_load(string $file): array
{
    $rows = blu_read_json_file($file, []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

/** @param list<array<string,mixed>> $rows */
function blu_commerce_save(string $file, array $rows): bool
{
    return blu_write_json_file($file, array_values($rows));
}

function blu_commerce_new_id(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . substr(uniqid('', true), -6);
}

function blu_commerce_next_number(string $prefix, array $rows, string $field): string
{
    $max = 0;
    foreach ($rows as $row) {
        $num = (string)($row[$field] ?? '');
        if (preg_match('/(\d+)$/', $num, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return sprintf('%s-%04d', $prefix, $max + 1);
}

/** @return list<array<string,mixed>> */
function blu_orders_list(): array
{
    $rows = blu_commerce_load(blu_orders_file());
    usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $rows;
}

function blu_order_find(string $id): ?array
{
    foreach (blu_orders_list() as $row) {
        if ((string)($row['id'] ?? '') === $id || (string)($row['order_number'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

/** @param array<string,mixed> $data */
function blu_order_save(array $data): array
{
    $rows = blu_commerce_load(blu_orders_file());
    $id = trim((string)($data['id'] ?? ''));
    $now = date('Y-m-d H:i:s');

    if ($id === '') {
        $id = blu_commerce_new_id('BC');
        $orderNumber = trim((string)($data['order_number'] ?? ''));
        if ($orderNumber === '') {
            $orderNumber = blu_commerce_next_number('BC', $rows, 'order_number');
        }
        $row = [
            'id' => $id,
            'order_number' => $orderNumber,
            'client_name' => trim((string)($data['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'status' => trim((string)($data['status'] ?? 'comandat')) ?: 'comandat',
            'payment_method' => trim((string)($data['payment_method'] ?? 'ramburs')) ?: 'ramburs',
            'total_amount' => round((float)($data['total_amount'] ?? 0), 2),
            'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
            'items_text' => trim((string)($data['items_text'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'source' => trim((string)($data['source'] ?? 'manual')) ?: 'manual',
            'shop_user_id' => (int)($data['shop_user_id'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = $row;
        blu_commerce_save(blu_orders_file(), $rows);
        return ['ok' => true, 'row' => $row];
    }

    $found = false;
    foreach ($rows as $i => $row) {
        if ((string)($row['id'] ?? '') !== $id) {
            continue;
        }
        $found = true;
        $rows[$i] = array_merge($row, [
            'order_number' => trim((string)($data['order_number'] ?? $row['order_number'] ?? '')),
            'client_name' => trim((string)($data['client_name'] ?? $row['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? $row['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? $row['email'] ?? '')),
            'address' => trim((string)($data['address'] ?? $row['address'] ?? '')),
            'status' => trim((string)($data['status'] ?? $row['status'] ?? 'comandat')) ?: 'comandat',
            'payment_method' => trim((string)($data['payment_method'] ?? $row['payment_method'] ?? 'ramburs')),
            'total_amount' => round((float)($data['total_amount'] ?? $row['total_amount'] ?? 0), 2),
            'items' => is_array($data['items'] ?? null) ? $data['items'] : ($row['items'] ?? []),
            'items_text' => trim((string)($data['items_text'] ?? $row['items_text'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? $row['notes'] ?? '')),
            'shop_user_id' => (int)($data['shop_user_id'] ?? $row['shop_user_id'] ?? 0),
            'updated_at' => $now,
        ]);
        blu_commerce_save(blu_orders_file(), $rows);
        return ['ok' => true, 'row' => $rows[$i]];
    }

    return ['ok' => false, 'message' => 'Comanda nu a fost găsită.'];
}

function blu_order_delete(string $id): bool
{
    $rows = blu_commerce_load(blu_orders_file());
    $kept = array_values(array_filter($rows, static fn(array $r): bool => (string)($r['id'] ?? '') !== $id));
    if (count($kept) === count($rows)) {
        return false;
    }
    blu_commerce_save(blu_orders_file(), $kept);
    return true;
}

/** @param list<array{product:array,qty:int,line_total:float}> $lines */
function blu_order_create_from_checkout(array $customer, array $lines, float $total): ?array
{
    if ($lines === []) {
        return null;
    }
    $items = [];
    $textLines = [];
    foreach ($lines as $line) {
        $p = $line['product'];
        $items[] = [
            'product_id' => (int)($p['id'] ?? $p['randomn_id'] ?? 0),
            'name' => trim((string)($p['nume'] ?? '')),
            'oem' => trim((string)($p['cod_oem'] ?? '')),
            'qty' => (int)($line['qty'] ?? 1),
            'unit_price' => blu_shop_price_value($p),
            'line_total' => (float)($line['line_total'] ?? 0),
        ];
        $textLines[] = sprintf(
            '%s x%d — %s (OEM %s)',
            trim((string)($p['nume'] ?? '')),
            (int)($line['qty'] ?? 1),
            blu_shop_price_label($p),
            trim((string)($p['cod_oem'] ?? '-'))
        );
    }

    $result = blu_order_save([
        'client_name' => trim((string)($customer['name'] ?? '')),
        'phone' => trim((string)($customer['phone'] ?? '')),
        'email' => trim((string)($customer['email'] ?? '')),
        'address' => trim((string)($customer['address'] ?? '')),
        'notes' => trim((string)($customer['note'] ?? '')),
        'payment_method' => trim((string)($customer['payment_method'] ?? 'ramburs')) ?: 'ramburs',
        'total_amount' => $total,
        'items' => $items,
        'items_text' => implode("\n", $textLines),
        'source' => 'shop_checkout',
        'status' => 'comandat',
        'shop_user_id' => (int)($customer['shop_user_id'] ?? 0),
    ]);

    return $result['ok'] ? ($result['row'] ?? null) : null;
}

/** @return list<array<string,mixed>> */
function blu_invoices_list(): array
{
    $rows = blu_commerce_load(blu_invoices_file());
    usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $rows;
}

/** @param array<string,mixed> $data */
function blu_invoice_save(array $data): array
{
    $rows = blu_commerce_load(blu_invoices_file());
    $id = trim((string)($data['id'] ?? ''));
    $now = date('Y-m-d H:i:s');

    if ($id === '') {
        $id = blu_commerce_new_id('INV');
        $invoiceNumber = trim((string)($data['invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = blu_commerce_next_number('FC', $rows, 'invoice_number');
        }
        $row = [
            'id' => $id,
            'invoice_number' => $invoiceNumber,
            'invoice_title' => trim((string)($data['invoice_title'] ?? 'Factură fiscală')),
            'order_id' => trim((string)($data['order_id'] ?? '')),
            'order_number' => trim((string)($data['order_number'] ?? '')),
            'client_name' => trim((string)($data['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'invoice_status' => trim((string)($data['invoice_status'] ?? 'neachitata')) ?: 'neachitata',
            'payment_method' => trim((string)($data['payment_method'] ?? 'ramburs')) ?: 'ramburs',
            'amount' => round((float)($data['amount'] ?? 0), 2),
            'due_date' => trim((string)($data['due_date'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = $row;
        blu_commerce_save(blu_invoices_file(), $rows);
        return ['ok' => true, 'row' => $row];
    }

    foreach ($rows as $i => $row) {
        if ((string)($row['id'] ?? '') !== $id) {
            continue;
        }
        $merged = [
            'invoice_number' => trim((string)($data['invoice_number'] ?? $row['invoice_number'] ?? '')),
            'invoice_title' => trim((string)($data['invoice_title'] ?? $row['invoice_title'] ?? '')),
            'order_id' => trim((string)($data['order_id'] ?? $row['order_id'] ?? '')),
            'order_number' => trim((string)($data['order_number'] ?? $row['order_number'] ?? '')),
            'client_name' => trim((string)($data['client_name'] ?? $row['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? $row['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? $row['email'] ?? '')),
            'invoice_status' => trim((string)($data['invoice_status'] ?? $row['invoice_status'] ?? 'neachitata')),
            'payment_method' => trim((string)($data['payment_method'] ?? $row['payment_method'] ?? '')),
            'amount' => round((float)($data['amount'] ?? $row['amount'] ?? 0), 2),
            'due_date' => trim((string)($data['due_date'] ?? $row['due_date'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? $row['notes'] ?? '')),
            'updated_at' => $now,
        ];
        if (array_key_exists('last_sent_at', $data)) {
            $merged['last_sent_at'] = trim((string)$data['last_sent_at']);
        }
        if (array_key_exists('last_sent_via', $data)) {
            $merged['last_sent_via'] = trim((string)$data['last_sent_via']);
        }
        $rows[$i] = array_merge($row, $merged);
        blu_commerce_save(blu_invoices_file(), $rows);
        return ['ok' => true, 'row' => $rows[$i]];
    }

    return ['ok' => false, 'message' => 'Factura nu a fost găsită.'];
}

function blu_invoice_delete(string $id): bool
{
    $rows = blu_commerce_load(blu_invoices_file());
    $kept = array_values(array_filter($rows, static fn(array $r): bool => (string)($r['id'] ?? '') !== $id));
    if (count($kept) === count($rows)) {
        return false;
    }
    blu_commerce_save(blu_invoices_file(), $kept);
    return true;
}

/** @return list<array<string,mixed>> */
function blu_deliveries_list(): array
{
    $rows = blu_commerce_load(blu_deliveries_file());
    usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $rows;
}

/** @param array<string,mixed> $data */
function blu_delivery_save(array $data): array
{
    $rows = blu_commerce_load(blu_deliveries_file());
    $id = trim((string)($data['id'] ?? ''));
    $now = date('Y-m-d H:i:s');

    if ($id === '') {
        $id = blu_commerce_new_id('AWB');
        $row = [
            'id' => $id,
            'delivery_title' => trim((string)($data['delivery_title'] ?? 'Livrare curier')),
            'awb' => trim((string)($data['awb'] ?? '')),
            'order_id' => trim((string)($data['order_id'] ?? '')),
            'order_number' => trim((string)($data['order_number'] ?? '')),
            'client_name' => trim((string)($data['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'courier' => trim((string)($data['courier'] ?? 'Fan Courier')) ?: 'Fan Courier',
            'delivery_status' => trim((string)($data['delivery_status'] ?? 'pregatire')) ?: 'pregatire',
            'total_amount' => round((float)($data['total_amount'] ?? 0), 2),
            'delivery_date' => trim((string)($data['delivery_date'] ?? '')),
            'delivery_time' => trim((string)($data['delivery_time'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows[] = $row;
        blu_commerce_save(blu_deliveries_file(), $rows);
        return ['ok' => true, 'row' => $row];
    }

    foreach ($rows as $i => $row) {
        if ((string)($row['id'] ?? '') !== $id) {
            continue;
        }
        $rows[$i] = array_merge($row, [
            'delivery_title' => trim((string)($data['delivery_title'] ?? $row['delivery_title'] ?? '')),
            'awb' => trim((string)($data['awb'] ?? $row['awb'] ?? '')),
            'order_id' => trim((string)($data['order_id'] ?? $row['order_id'] ?? '')),
            'order_number' => trim((string)($data['order_number'] ?? $row['order_number'] ?? '')),
            'client_name' => trim((string)($data['client_name'] ?? $row['client_name'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? $row['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? $row['email'] ?? '')),
            'address' => trim((string)($data['address'] ?? $row['address'] ?? '')),
            'courier' => trim((string)($data['courier'] ?? $row['courier'] ?? 'Fan Courier')),
            'delivery_status' => trim((string)($data['delivery_status'] ?? $row['delivery_status'] ?? 'pregatire')),
            'total_amount' => round((float)($data['total_amount'] ?? $row['total_amount'] ?? 0), 2),
            'delivery_date' => trim((string)($data['delivery_date'] ?? $row['delivery_date'] ?? '')),
            'delivery_time' => trim((string)($data['delivery_time'] ?? $row['delivery_time'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? $row['notes'] ?? '')),
            'updated_at' => $now,
        ]);
        blu_commerce_save(blu_deliveries_file(), $rows);
        return ['ok' => true, 'row' => $rows[$i]];
    }

    return ['ok' => false, 'message' => 'Livrarea nu a fost găsită.'];
}

function blu_delivery_delete(string $id): bool
{
    $rows = blu_commerce_load(blu_deliveries_file());
    $kept = array_values(array_filter($rows, static fn(array $r): bool => (string)($r['id'] ?? '') !== $id));
    if (count($kept) === count($rows)) {
        return false;
    }
    blu_commerce_save(blu_deliveries_file(), $kept);
    return true;
}

function blu_commerce_status_label(string $type, string $status): string
{
    $maps = [
        'order' => [
            'comandat' => 'Comandat',
            'procesare' => 'În procesare',
            'expediere' => 'Expediere',
            'livrat' => 'Livrat',
            'anulat' => 'Anulat',
        ],
        'invoice' => [
            'neachitata' => 'Neachitată',
            'achitata' => 'Achitată',
            'anulata' => 'Anulată',
            'storno' => 'Storno',
        ],
        'delivery' => [
            'pregatire' => 'Pregătire colet',
            'awb_generat' => 'AWB generat',
            'in_tranzit' => 'În trânzit',
            'livrat' => 'Livrat',
            'retur' => 'Retur',
            'anulat' => 'Anulat',
        ],
    ];
    return $maps[$type][$status] ?? $status;
}
