<?php
declare(strict_types=1);

define('BLU_ADMIN_CONTEXT', true);
session_start();

if (!($_SESSION['admin'] ?? null)) {
    header('Location: ?page=login');
    exit;
}

require_once dirname(__DIR__) . '/lib/invoice_document.php';

$id = trim((string)($_GET['id'] ?? ''));
$invoice = blu_invoice_find($id);
if ($invoice === null) {
    http_response_code(404);
    echo 'Factura nu a fost găsită.';
    exit;
}

echo blu_invoice_render_html($invoice, true);
