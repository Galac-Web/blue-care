<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

/**
 * Setări „Adaos comercial": adaos %, TVA % și lista de cheltuieli.
 * Formula aplicată unui preț de bază (cost achiziție):
 *   1. cheltuieli = sumă(fix) + bază * sumă(procent)%
 *   2. cost_total = bază + cheltuieli
 *   3. adaos     = cost_total * adaos%
 *   4. fără_tva  = cost_total + adaos
 *   5. tva       = fără_tva * tva%
 *   6. preț_final = fără_tva + tva
 */

function blu_pricing_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'pricing_settings.json';
}

/** @return array{adaos_pct:float, tva_pct:float, cheltuieli:array<int,array{nume:string,tip:string,valoare:float}>} */
function blu_pricing_defaults(): array
{
    return [
        'adaos_pct' => 30.0,
        'tva_pct' => 19.0,
        'eur_ron_rate' => 4.975,
        'cheltuieli' => [],
    ];
}

/** @return array{adaos_pct:float, tva_pct:float, cheltuieli:array<int,array{nume:string,tip:string,valoare:float}>, updated_at?:string} */
function blu_pricing_settings(): array
{
    $defaults = blu_pricing_defaults();
    $data = blu_read_json_file(blu_pricing_file(), []);
    if (!is_array($data)) {
        return $defaults;
    }

    $out = $defaults;
    if (isset($data['adaos_pct']) && is_numeric($data['adaos_pct'])) {
        $out['adaos_pct'] = max(0.0, (float)$data['adaos_pct']);
    }
    if (isset($data['tva_pct']) && is_numeric($data['tva_pct'])) {
        $out['tva_pct'] = max(0.0, (float)$data['tva_pct']);
    }
    if (isset($data['eur_ron_rate']) && is_numeric($data['eur_ron_rate'])) {
        $out['eur_ron_rate'] = max(0.01, (float)$data['eur_ron_rate']);
    }
    if (isset($data['cheltuieli']) && is_array($data['cheltuieli'])) {
        foreach ($data['cheltuieli'] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nume = trim((string)($c['nume'] ?? ''));
            $tip = ($c['tip'] ?? 'fix') === 'procent' ? 'procent' : 'fix';
            $val = is_numeric($c['valoare'] ?? null) ? (float)$c['valoare'] : 0.0;
            if ($nume === '' && $val == 0.0) {
                continue;
            }
            $out['cheltuieli'][] = ['nume' => $nume !== '' ? $nume : 'Cheltuială', 'tip' => $tip, 'valoare' => $val];
        }
    }
    if (isset($data['updated_at'])) {
        $out['updated_at'] = (string)$data['updated_at'];
    }
    return $out;
}

/**
 * Salvează setările din input POST.
 * @param array<string,mixed> $input
 * @return array{ok:bool, message:string}
 */
function blu_pricing_save(array $input): array
{
    $adaos = is_numeric($input['adaos_pct'] ?? null) ? max(0.0, (float)$input['adaos_pct']) : 0.0;
    $tva = is_numeric($input['tva_pct'] ?? null) ? max(0.0, (float)$input['tva_pct']) : 0.0;
    $eurRon = is_numeric(str_replace(',', '.', (string)($input['eur_ron_rate'] ?? '')))
        ? max(0.01, (float)str_replace(',', '.', (string)$input['eur_ron_rate']))
        : blu_pricing_defaults()['eur_ron_rate'];

    $cheltuieli = [];
    $nume = $input['chelt_nume'] ?? [];
    $tip = $input['chelt_tip'] ?? [];
    $val = $input['chelt_val'] ?? [];
    if (is_array($nume)) {
        $count = count($nume);
        for ($i = 0; $i < $count; $i++) {
            $n = trim((string)($nume[$i] ?? ''));
            $t = (($tip[$i] ?? 'fix') === 'procent') ? 'procent' : 'fix';
            $vRaw = $val[$i] ?? '';
            $v = is_numeric(str_replace(',', '.', (string)$vRaw)) ? (float)str_replace(',', '.', (string)$vRaw) : 0.0;
            if ($n === '' && $v == 0.0) {
                continue;
            }
            $cheltuieli[] = ['nume' => $n !== '' ? $n : 'Cheltuială', 'tip' => $t, 'valoare' => $v];
        }
    }

    $payload = [
        'adaos_pct' => $adaos,
        'tva_pct' => $tva,
        'eur_ron_rate' => $eurRon,
        'cheltuieli' => $cheltuieli,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $ok = blu_write_json_file(blu_pricing_file(), $payload);
    return [
        'ok' => $ok,
        'message' => $ok ? 'Setările de adaos comercial au fost salvate.' : 'Nu am putut salva setările de preț.',
    ];
}

/**
 * Calculează prețul final pornind de la un preț de bază (cost achiziție).
 * @return array{baza:float, cheltuieli_total:float, cost_total:float, adaos_val:float, pret_fara_tva:float, tva_val:float, pret_final:float, detalii_cheltuieli:array<int,array{nume:string,valoare:float}>}
 */
function blu_compute_price(float $baza, ?array $settings = null): array
{
    $settings = $settings ?? blu_pricing_settings();
    $baza = max(0.0, $baza);

    $cheltTotal = 0.0;
    $detalii = [];
    foreach ($settings['cheltuieli'] as $c) {
        $val = (float)($c['valoare'] ?? 0);
        $suma = ($c['tip'] ?? 'fix') === 'procent' ? ($baza * $val / 100.0) : $val;
        $cheltTotal += $suma;
        $detalii[] = ['nume' => (string)($c['nume'] ?? 'Cheltuială'), 'valoare' => round($suma, 2)];
    }

    $costTotal = $baza + $cheltTotal;
    $adaosVal = $costTotal * ((float)$settings['adaos_pct']) / 100.0;
    $pretFaraTva = $costTotal + $adaosVal;
    $tvaVal = $pretFaraTva * ((float)$settings['tva_pct']) / 100.0;
    $pretFinal = $pretFaraTva + $tvaVal;
    // Prețul din BD: rotunjire în sus la leu întreg (8,20 → 9; 8,00 rămâne 8).
    $pretFinal = blu_price_round_up($pretFinal);

    return [
        'baza' => round($baza, 2),
        'cheltuieli_total' => round($cheltTotal, 2),
        'cost_total' => round($costTotal, 2),
        'adaos_val' => round($adaosVal, 2),
        'pret_fara_tva' => round($pretFaraTva, 2),
        'tva_val' => round($tvaVal, 2),
        'pret_final' => $pretFinal,
        'detalii_cheltuieli' => $detalii,
    ];
}

/** Rotunjire comercială în sus la leu întreg (ex: 8.20 → 9, 8.00 → 8). */
function blu_price_round_up(float $price): float
{
    $price = max(0.0, $price);
    if ($price <= 0) {
        return 0.0;
    }
    // Evită erori de float (ex. 8.999999) înainte de ceil.
    $cents = round($price, 2);
    return (float) ceil($cents - 1e-9);
}

function blu_lei(float $v): string
{
    return number_format($v, 2, ',', ' ') . ' lei';
}

function blu_pricing_eur_ron_rate(?array $settings = null): float
{
    $settings = $settings ?? blu_pricing_settings();
    $rate = (float)($settings['eur_ron_rate'] ?? blu_pricing_defaults()['eur_ron_rate']);
    return max(0.01, $rate);
}

/** Preț de bază RON din cost furnizor GBG (EUR, fără TVA). */
function blu_eur_to_ron_baza(float $eur, ?array $settings = null): float
{
    $eur = max(0.0, $eur);
    if ($eur <= 0) {
        return 0.0;
    }
    return round($eur * blu_pricing_eur_ron_rate($settings), 2);
}

/**
 * Aplică preț GBG (EUR) + adaos comercial pe card produs.
 *
 * @param array<string,mixed> $card
 * @return array<string,mixed>
 */
function blu_apply_gbg_eur_price(array $card, float $eur): array
{
    if ($eur <= 0) {
        return $card;
    }

    $bazaRon = blu_eur_to_ron_baza($eur);
    if ($bazaRon <= 0) {
        return $card;
    }

    $pricing = blu_compute_price($bazaRon);
    $final = (float)$pricing['pret_final'];

    $card['price_eur'] = round($eur, 2);
    $card['price_ron_base'] = $bazaRon;
    $card['price_ron_final'] = $final;
    $card['price_ron_display'] = blu_lei($final);
    // Salvare BD: lei întregi (fără zecimale) după rotunjire în sus.
    $card['pret'] = number_format($final, 0, '.', '');

    if (!isset($card['admin_card']) || !is_array($card['admin_card'])) {
        $card['admin_card'] = [];
    }
    $card['admin_card']['pret'] = number_format($final, 0, '.', '');

    return $card;
}

/** @param array<string,mixed> $entry */
function blu_entry_pret_eur(array $entry): float
{
    foreach (['pret_eur', 'price_eur', 'pret_gbg_eur'] as $key) {
        if (isset($entry[$key]) && is_numeric(str_replace(',', '.', (string)$entry[$key]))) {
            return max(0.0, (float)str_replace(',', '.', (string)$entry[$key]));
        }
    }
    return 0.0;
}

/** Robot GBG a trimis explicit câmp de preț (chiar dacă e 0). */
function blu_entry_has_gbg_price_field(array $entry): bool
{
    foreach (['pret_eur', 'price_eur', 'pret_gbg_eur'] as $key) {
        if (array_key_exists($key, $entry)) {
            return true;
        }
    }
    return false;
}

/** @param array<string,mixed> $card */
function blu_mark_product_no_gbg_stock(array $card): array
{
    $card['gbg_price_missing'] = true;
    $card['stock'] = '0';
    $card['price_ron_final'] = 0.0;
    $card['price_ron_display'] = '';
    $card['pret'] = '';

    if (!isset($card['admin_card']) || !is_array($card['admin_card'])) {
        $card['admin_card'] = [];
    }
    $card['admin_card']['stoc'] = 'Nu este stoc';
    $card['admin_card']['pret'] = '';

    return $card;
}

/**
 * Preț GBG din scan robot: EUR → RON + adaos, sau marchează fără stoc.
 *
 * @param array<string,mixed> $card
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
function blu_apply_gbg_supplier_pricing(array $card, array $entry): array
{
    if (!blu_entry_has_gbg_price_field($entry)) {
        return $card;
    }

    $pretEur = blu_entry_pret_eur($entry);
    if ($pretEur > 0) {
        $card = blu_apply_gbg_eur_price($card, $pretEur);
        $card['gbg_price_missing'] = false;
        if (!isset($card['admin_card']) || !is_array($card['admin_card'])) {
            $card['admin_card'] = [];
        }
        $card['stock'] = '1';
        $card['admin_card']['stoc'] = '1';
        return $card;
    }

    return blu_mark_product_no_gbg_stock($card);
}

/** @param array<string,mixed> $card */
function blu_product_ready_for_pieseauto(array $card): bool
{
    if (!empty($card['gbg_price_missing'])) {
        return false;
    }
    foreach (['price_ron_final', 'pret', 'price'] as $key) {
        if (isset($card[$key]) && (float)$card[$key] > 0) {
            return true;
        }
    }
    $admin = $card['admin_card'] ?? null;
    if (is_array($admin) && isset($admin['pret']) && (float)$admin['pret'] > 0) {
        return true;
    }
    return false;
}
