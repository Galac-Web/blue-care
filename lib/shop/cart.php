<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function blu_cart_items(): array
{
    $cart = $_SESSION['blu_cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function blu_cart_count(): int
{
    $n = 0;
    foreach (blu_cart_items() as $line) {
        $n += max(1, (int) ($line['qty'] ?? 1));
    }
    return $n;
}

function blu_cart_add(int $productId, int $qty = 1): bool
{
    $product = blu_shop_find_product($productId);
    if ($product === null) {
        return false;
    }
    $qty = max(1, min(99, $qty));
    $cart = blu_cart_items();
    $key = (string) $productId;
    if (isset($cart[$key])) {
        $cart[$key]['qty'] = min(99, (int) $cart[$key]['qty'] + $qty);
    } else {
        $cart[$key] = [
            'id' => $productId,
            'qty' => $qty,
            'added_at' => date('Y-m-d H:i:s'),
        ];
    }
    $_SESSION['blu_cart'] = $cart;
    return true;
}

function blu_cart_update(int $productId, int $qty): void
{
    $cart = blu_cart_items();
    $key = (string) $productId;
    if ($qty <= 0) {
        unset($cart[$key]);
    } elseif (isset($cart[$key])) {
        $cart[$key]['qty'] = max(1, min(99, $qty));
    }
    $_SESSION['blu_cart'] = $cart;
}

function blu_cart_remove(int $productId): void
{
    $cart = blu_cart_items();
    unset($cart[(string) $productId]);
    $_SESSION['blu_cart'] = $cart;
}

function blu_cart_clear(): void
{
    unset($_SESSION['blu_cart']);
}

/** @return list<array{product:array,qty:int,line_total:float}> */
function blu_cart_lines(): array
{
    $lines = [];
    foreach (blu_cart_items() as $line) {
        $id = (int) ($line['id'] ?? 0);
        $qty = max(1, (int) ($line['qty'] ?? 1));
        $product = blu_shop_find_product($id);
        if ($product === null) {
            continue;
        }
        $unit = blu_shop_price_value($product);
        $lines[] = [
            'product' => $product,
            'qty' => $qty,
            'line_total' => $unit * $qty,
        ];
    }
    return $lines;
}

function blu_cart_total(): float
{
    $sum = 0.0;
    foreach (blu_cart_lines() as $line) {
        $sum += (float) $line['line_total'];
    }
    return $sum;
}

function blu_cart_format_money(float $value): string
{
    return number_format($value, 2, ',', '.') . ' lei';
}
