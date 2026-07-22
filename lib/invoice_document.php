<?php
declare(strict_types=1);

require_once __DIR__ . '/commerce_store.php';
require_once __DIR__ . '/shop/bootstrap.php';

function blu_invoice_find(string $id): ?array
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }
    foreach (blu_invoices_list() as $row) {
        if ((string)($row['id'] ?? '') === $id || (string)($row['invoice_number'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

/** @return array<string,mixed> */
function blu_invoice_resolve_order(array $invoice): array
{
    $orderId = trim((string)($invoice['order_id'] ?? ''));
    if ($orderId !== '') {
        $order = blu_order_find($orderId);
        if (is_array($order)) {
            return $order;
        }
    }
    $orderNo = trim((string)($invoice['order_number'] ?? ''));
    if ($orderNo !== '') {
        foreach (blu_orders_list() as $order) {
            if ((string)($order['order_number'] ?? '') === $orderNo) {
                return $order;
            }
        }
    }
    return [];
}

/** @return array<string,mixed> */
function blu_invoice_build_context(array $invoice): array
{
    $order = blu_invoice_resolve_order($invoice);
    $company = blu_shop_company();
    $items = is_array($order['items'] ?? null) ? $order['items'] : [];
    $itemsText = trim((string)($order['items_text'] ?? ''));
    if ($items === [] && $itemsText !== '') {
        foreach (preg_split('/\r?\n/', $itemsText) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = ['name' => $line, 'qty' => 1, 'line_total' => 0];
            }
        }
    }

    $amount = (float)($invoice['amount'] ?? $order['total_amount'] ?? 0);
    $status = (string)($invoice['invoice_status'] ?? 'neachitata');
    $payment = (string)($invoice['payment_method'] ?? $order['payment_method'] ?? 'ramburs');

    return [
        'invoice' => $invoice,
        'order' => $order,
        'company' => [
            'name' => trim((string)($company['name'] ?? $company['nume'] ?? 'Blue-Car')),
            'phone' => trim((string)($company['phone'] ?? $company['telefon'] ?? '')),
            'email' => trim((string)($company['email'] ?? 'contact@blu-car.ro')),
            'address' => trim((string)($company['address'] ?? $company['adresa'] ?? '')),
            'cui' => trim((string)($company['cui'] ?? $company['tax_id'] ?? '')),
            'reg' => trim((string)($company['reg_com'] ?? $company['registration'] ?? '')),
        ],
        'client' => [
            'name' => trim((string)($invoice['client_name'] ?? $order['client_name'] ?? '')),
            'phone' => trim((string)($invoice['phone'] ?? $order['phone'] ?? '')),
            'email' => trim((string)($invoice['email'] ?? $order['email'] ?? '')),
            'address' => trim((string)($order['address'] ?? '')),
        ],
        'items' => $items,
        'amount' => $amount,
        'amount_label' => number_format($amount, 2, ',', '.') . ' RON',
        'status' => $status,
        'status_label' => blu_commerce_status_label('invoice', $status),
        'payment' => $payment,
        'payment_label' => match ($payment) {
            'ramburs' => 'Ramburs la livrare',
            'card_online' => 'Card online',
            'transfer' => 'Transfer bancar',
            'cash' => 'Numerar',
            default => $payment,
        },
        'invoice_number' => trim((string)($invoice['invoice_number'] ?? '')),
        'invoice_title' => trim((string)($invoice['invoice_title'] ?? 'Factură fiscală')),
        'order_number' => trim((string)($invoice['order_number'] ?? $order['order_number'] ?? '')),
        'created_at' => trim((string)($invoice['created_at'] ?? date('Y-m-d H:i:s'))),
        'due_date' => trim((string)($invoice['due_date'] ?? '')),
        'notes' => trim((string)($invoice['notes'] ?? $order['notes'] ?? '')),
    ];
}

function blu_invoice_format_phone_whatsapp(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '40')) {
        return $digits;
    }
    if (str_starts_with($digits, '0')) {
        return '4' . $digits;
    }
    return '40' . $digits;
}

function blu_invoice_public_view_url(array $invoice, ?string $baseUrl = null): string
{
    $base = rtrim((string)($baseUrl ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'blu-car.ro');
        $base = $scheme . '://' . $host;
    }
    $adminPath = '/admin/invoice-view.php?id=' . rawurlencode((string)($invoice['id'] ?? ''));
    return $base . $adminPath;
}

function blu_invoice_whatsapp_message(array $invoice, ?string $baseUrl = null): string
{
    $ctx = blu_invoice_build_context($invoice);
    $lines = [
        'Bună ziua, ' . ($ctx['client']['name'] !== '' ? $ctx['client']['name'] : 'client Blue-Car') . '!',
        '',
        'Vă transmitem factura ' . $ctx['invoice_number'] . ' — ' . $ctx['amount_label'] . '.',
        'Comandă: ' . ($ctx['order_number'] !== '' ? $ctx['order_number'] : '—'),
        'Status: ' . $ctx['status_label'],
        'Plată: ' . $ctx['payment_label'],
    ];
    if ($ctx['items'] !== []) {
        $lines[] = '';
        $lines[] = 'Produse:';
        foreach ($ctx['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string)($item['name'] ?? 'Produs'));
            $qty = max(1, (int)($item['qty'] ?? 1));
            $lineTotal = (float)($item['line_total'] ?? 0);
            $suffix = $lineTotal > 0 ? ' — ' . number_format($lineTotal, 2, ',', '.') . ' lei' : '';
            $lines[] = '- ' . $name . ' x' . $qty . $suffix;
        }
    }
    if ($ctx['due_date'] !== '') {
        $lines[] = '';
        $lines[] = 'Scadență: ' . $ctx['due_date'];
    }
    $lines[] = '';
    $lines[] = $ctx['company']['name'];
    if ($ctx['company']['phone'] !== '') {
        $lines[] = 'Tel: ' . $ctx['company']['phone'];
    }
  return implode("\n", $lines);
}

function blu_invoice_whatsapp_url(array $invoice, ?string $baseUrl = null): string
{
    $ctx = blu_invoice_build_context($invoice);
    $phone = blu_invoice_format_phone_whatsapp($ctx['client']['phone']);
    $text = blu_invoice_whatsapp_message($invoice, $baseUrl);
    if ($phone === '') {
        return 'https://wa.me/?text=' . rawurlencode($text);
    }
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
}

function blu_invoice_render_html(array $invoice, bool $printable = false): string
{
    $ctx = blu_invoice_build_context($invoice);
    $company = $ctx['company'];
    $client = $ctx['client'];
    $items = $ctx['items'];
    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $esc($ctx['invoice_title'] . ' ' . $ctx['invoice_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 0; background: #f8fafc; }
        .wrap { max-width: 820px; margin: 24px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .head { display: flex; justify-content: space-between; gap: 24px; padding: 28px 32px; border-bottom: 1px solid #e2e8f0; }
        .brand { font-size: 1.5rem; font-weight: 700; color: #308e87; }
        .meta { text-align: right; font-size: .92rem; color: #475569; }
        .meta strong { display: block; font-size: 1.15rem; color: #0f172a; }
        .section { padding: 24px 32px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .box h3 { margin: 0 0 8px; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .box p { margin: 0; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; }
        .total { text-align: right; font-size: 1.2rem; font-weight: 700; color: #308e87; margin-top: 16px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; background: #fff7ed; color: #c2410c; }
        .notes { margin-top: 16px; padding: 12px 14px; background: #f8fafc; border-radius: 8px; font-size: .92rem; color: #475569; }
        .toolbar { padding: 16px 32px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; }
        .btn { border: none; border-radius: 8px; padding: 10px 16px; font-weight: 600; cursor: pointer; }
        .btn-print { background: #308e87; color: #fff; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .wrap { margin: 0; border: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <div>
                <div class="brand"><?= $esc($company['name']) ?></div>
                <?php if ($company['address'] !== ''): ?><div><?= $esc($company['address']) ?></div><?php endif; ?>
                <?php if ($company['phone'] !== ''): ?><div>Tel: <?= $esc($company['phone']) ?></div><?php endif; ?>
                <?php if ($company['email'] !== ''): ?><div><?= $esc($company['email']) ?></div><?php endif; ?>
                <?php if ($company['cui'] !== ''): ?><div>CUI: <?= $esc($company['cui']) ?></div><?php endif; ?>
            </div>
            <div class="meta">
                <strong><?= $esc($ctx['invoice_title']) ?></strong>
                <div>Nr. <?= $esc($ctx['invoice_number']) ?></div>
                <div>Data: <?= $esc(substr($ctx['created_at'], 0, 10)) ?></div>
                <?php if ($ctx['due_date'] !== ''): ?><div>Scadență: <?= $esc($ctx['due_date']) ?></div><?php endif; ?>
                <?php if ($ctx['order_number'] !== ''): ?><div>Comandă: <?= $esc($ctx['order_number']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="section grid">
            <div class="box">
                <h3>Client</h3>
                <p><strong><?= $esc($client['name'] !== '' ? $client['name'] : '—') ?></strong></p>
                <?php if ($client['phone'] !== ''): ?><p>Tel: <?= $esc($client['phone']) ?></p><?php endif; ?>
                <?php if ($client['email'] !== ''): ?><p>Email: <?= $esc($client['email']) ?></p><?php endif; ?>
                <?php if ($client['address'] !== ''): ?><p>Adresă: <?= $esc($client['address']) ?></p><?php endif; ?>
            </div>
            <div class="box">
                <h3>Detalii plată</h3>
                <p><span class="badge"><?= $esc($ctx['status_label']) ?></span></p>
                <p style="margin-top:10px"><?= $esc($ctx['payment_label']) ?></p>
            </div>
        </div>
        <div class="section">
            <h3 style="margin:0 0 8px;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b">Produse</h3>
            <table>
                <thead>
                    <tr><th>Produs</th><th>Cant.</th><th>Total</th></tr>
                </thead>
                <tbody>
                <?php if ($items === []): ?>
                    <tr><td colspan="3">—</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php if (!is_array($item)) continue; ?>
                        <tr>
                            <td><?= $esc((string)($item['name'] ?? 'Produs')) ?></td>
                            <td><?= (int)max(1, (int)($item['qty'] ?? 1)) ?></td>
                            <td><?= $esc(number_format((float)($item['line_total'] ?? 0), 2, ',', '.') . ' lei') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="total">Total: <?= $esc($ctx['amount_label']) ?></div>
            <?php if ($ctx['notes'] !== ''): ?>
                <div class="notes"><strong>Note:</strong> <?= nl2br($esc($ctx['notes'])) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($printable): ?>
        <div class="toolbar">
            <button class="btn btn-print" type="button" onclick="window.print()">Printează / PDF</button>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    return (string) ob_get_clean();
}

/** @return array{ok:bool,message:string} */
function blu_invoice_send_email(array $invoice): array
{
    $ctx = blu_invoice_build_context($invoice);
    $to = trim($ctx['client']['email']);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Clientul nu are email valid.'];
    }

    $fromEmail = trim((string)blu_env('MAIL_FROM', 'noreply@blu-car.ro'));
    $fromName = trim((string)blu_env('MAIL_FROM_NAME', $ctx['company']['name']));
    $subject = 'Factura ' . $ctx['invoice_number'] . ' — ' . $ctx['company']['name'];
    $html = blu_invoice_render_html($invoice, false);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ($fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail),
        'Reply-To: ' . ($ctx['company']['email'] !== '' ? $ctx['company']['email'] : $fromEmail),
    ];

    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
    if (!$sent) {
        return ['ok' => false, 'message' => 'Nu am putut trimite emailul (verifică setările mail pe server).'];
    }

    blu_invoice_mark_sent($invoice, 'email');
    return ['ok' => true, 'message' => 'Factura a fost trimisă pe email la ' . $to . '.'];
}

function blu_invoice_mark_sent(array $invoice, string $channel): void
{
    $id = trim((string)($invoice['id'] ?? ''));
    if ($id === '') {
        return;
    }
    blu_invoice_save([
        'id' => $id,
        'last_sent_at' => date('Y-m-d H:i:s'),
        'last_sent_via' => $channel,
    ]);
}

/** @return array{ok:bool,row?:array,message?:string} */
function blu_invoice_create_from_order(string $orderId): array
{
    $order = blu_order_find($orderId);
    if ($order === null) {
        return ['ok' => false, 'message' => 'Comanda nu a fost găsită.'];
    }
    foreach (blu_invoices_list() as $inv) {
        if ((string)($inv['order_id'] ?? '') === (string)($order['id'] ?? '')) {
            return ['ok' => true, 'row' => $inv, 'message' => 'Există deja o factură pentru această comandă.'];
        }
    }
    $result = blu_invoice_save([
        'invoice_title' => 'Factură fiscală',
        'order_id' => (string)($order['id'] ?? ''),
        'order_number' => (string)($order['order_number'] ?? ''),
        'client_name' => (string)($order['client_name'] ?? ''),
        'phone' => (string)($order['phone'] ?? ''),
        'email' => (string)($order['email'] ?? ''),
        'amount' => (float)($order['total_amount'] ?? 0),
        'payment_method' => (string)($order['payment_method'] ?? 'ramburs'),
        'invoice_status' => 'neachitata',
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'notes' => (string)($order['notes'] ?? ''),
    ]);
    return $result;
}
