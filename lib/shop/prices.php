<?php
declare(strict_types=1);

function blu_shop_price_value(array $product): float
{
    $raw = trim((string) ($product['pret'] ?? $product['price'] ?? $product['price_ron_display'] ?? '0'));
    $normalized = preg_replace('/[^0-9.,]/', '', $raw);
    $normalized = str_replace(',', '.', (string) $normalized);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function blu_shop_price_label(array $product): string
{
    $value = blu_shop_price_value($product);
    if ($value <= 0) {
        return 'La cerere';
    }
    return number_format($value, 2, ',', '.') . ' lei';
}
