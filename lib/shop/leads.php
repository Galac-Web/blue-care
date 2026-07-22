<?php
declare(strict_types=1);

function blu_shop_leads_file(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'leads.json';
}

function blu_shop_save_lead(array $lead): bool
{
    $file = blu_shop_leads_file();
    $rows = [];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = $raw !== false ? json_decode($raw, true) : [];
        if (is_array($decoded)) {
            $rows = array_values($decoded);
        }
    }
    $lead['id'] = count($rows) + 1;
    $lead['created_at'] = date('Y-m-d H:i:s');
    $rows[] = $lead;
    $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}
