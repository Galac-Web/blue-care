<?php
declare(strict_types=1);

require_once __DIR__ . '/products_store.php';

function blu_robot_feed_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_feed.json';
}

function blu_robot_stats_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_stats.json';
}

function blu_robot_no_oem_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_no_oem.json';
}

/** Cheie unică pentru un produs fără OEM (cod articol + marcă + model). */
function blu_robot_no_oem_key(array $row): string
{
    return strtoupper(trim(
        (string)($row['cod_articol'] ?? '') . '|' .
        (string)($row['brand'] ?? '') . '|' .
        (string)($row['model'] ?? '')
    ));
}

/** Salvează (dedup) un produs fără coduri OEM într-o listă persistentă. */
function blu_robot_record_no_oem(array $entry): void
{
    $list = blu_read_json_file(blu_robot_no_oem_file(), []);
    if (!is_array($list)) {
        $list = [];
    }
    $byKey = [];
    foreach ($list as $row) {
        if (is_array($row)) {
            $byKey[blu_robot_no_oem_key($row)] = $row;
        }
    }

    $clean = [
        'cont_id' => (string)($entry['cont_id'] ?? ''),
        'brand' => (string)($entry['brand'] ?? ''),
        'model' => (string)($entry['model'] ?? ''),
        'cod_articol' => (string)($entry['cod_articol'] ?? ''),
        'coduri_oem' => (string)($entry['coduri_oem'] ?? ''),
        'reason' => (string)($entry['message'] ?? $entry['reason'] ?? 'Fara coduri OEM'),
        'time' => date('Y-m-d H:i:s'),
    ];
    $byKey[blu_robot_no_oem_key($clean)] = $clean;

    $out = array_slice(array_values($byKey), -2000);
    blu_write_json_file(blu_robot_no_oem_file(), $out);
}

/** @return list<array> Cele mai recente produse fără OEM (primele = cele mai noi). */
function blu_robot_get_no_oem(int $limit = 500): array
{
    $list = blu_read_json_file(blu_robot_no_oem_file(), []);
    if (!is_array($list)) {
        return [];
    }
    $list = array_reverse(array_values($list));
    return array_slice($list, 0, max(1, $limit));
}

function blu_robot_count_no_oem(): int
{
    $list = blu_read_json_file(blu_robot_no_oem_file(), []);
    return is_array($list) ? count($list) : 0;
}

function blu_robot_clear_no_oem(): void
{
    blu_write_json_file(blu_robot_no_oem_file(), []);
}

function blu_robot_provided_api_key(): string
{
    $provided = trim((string)($_GET['api_key'] ?? $_POST['api_key'] ?? ''));

    if ($provided === '' && !empty($_SERVER['HTTP_X_ROBOT_KEY'])) {
        $provided = trim((string)$_SERVER['HTTP_X_ROBOT_KEY']);
    }

    if ($provided === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string)$name) === 'x-robot-key') {
                    $provided = trim((string)$value);
                    break;
                }
            }
        }
    }

    return $provided;
}

function blu_robot_api_key_ok(): bool
{
    $secret = trim((string)blu_env('ROBOT_API_KEY', ''));
    $secret = preg_replace('/[\x00-\x1F\x7F]/u', '', $secret) ?? $secret;
    if ($secret === '') {
        return true;
    }
    $provided = blu_robot_provided_api_key();
    return $provided !== '' && hash_equals($secret, $provided);
}

/** @return array{total:int,found:int,empty:int,errors:int,last_at:string} */
function blu_robot_load_stats(): array
{
    $stats = blu_read_json_file(blu_robot_stats_file(), []);
    return array_merge([
        'total' => 0,
        'found' => 0,
        'empty' => 0,
        'errors' => 0,
        'no_oem' => 0,
        'last_at' => '',
    ], is_array($stats) ? $stats : []);
}

function blu_robot_save_stats(array $stats): void
{
    blu_write_json_file(blu_robot_stats_file(), $stats);
}

function blu_robot_append_feed(array $event): void
{
    $feed = blu_read_json_file(blu_robot_feed_file(), []);
    if (!is_array($feed)) {
        $feed = [];
    }
    $event['id'] = $event['id'] ?? uniqid('evt_', true);
    $event['time'] = $event['time'] ?? date('Y-m-d H:i:s');
    array_unshift($feed, $event);
    $feed = array_slice($feed, 0, 500);
    blu_write_json_file(blu_robot_feed_file(), $feed);
}

/** @return list<array> */
function blu_robot_get_feed(int $limit = 80): array
{
    $feed = blu_read_json_file(blu_robot_feed_file(), []);
    if (!is_array($feed)) {
        return [];
    }
    return array_slice($feed, 0, max(1, $limit));
}

/**
 * Primeste o piesa de la robot si o incarca (Autodoc24 daca exista OEM, altfel TecDoc).
 *
 * @param array{brand?:string,model?:string,cod_articol?:string,coduri_oem?:string,cont_id?:string} $input
 */
function blu_robot_process_part(array $input): array
{
    $brand = trim((string)($input['brand'] ?? $input['marca'] ?? ''));
    $model = trim((string)($input['model'] ?? $input['submodel'] ?? ''));
    $codArticol = trim((string)($input['cod_articol'] ?? $input['cod'] ?? ''));
    $coduriOem = trim((string)($input['coduri_oem'] ?? $input['oem'] ?? $input['cod_oem'] ?? ''));
    $contId = trim((string)($input['cont_id'] ?? $input['cont'] ?? 'robot'));
    $descriereGr = trim((string)($input['descriere_gr'] ?? $input['description_gr'] ?? $input['titlu_gr'] ?? ''));
    $imagineUrl = trim((string)(
        $input['imagine_url'] ?? $input['gbg_image'] ?? $input['image_url'] ?? $input['image'] ?? ''
    ));
    if ($imagineUrl !== '' && !preg_match('#^https?://#i', $imagineUrl)) {
        $imagineUrl = '';
    }
    $pretEur = blu_entry_pret_eur($input);

    // Optimizează OEM (variante + Ollama dacă e nevoie) înainte de căutare.
    require_once __DIR__ . '/ollama_client.php';
    if (function_exists('blu_extract_oem_codes_optimized')) {
        $opt = blu_extract_oem_codes_optimized($coduriOem, $codArticol, $brand);
        if ($opt !== []) {
            $coduriOem = implode(', ', $opt);
        }
    }

    $missingOem = blu_entry_missing_oem($codArticol, $coduriOem);
    $codes = blu_extract_search_codes($codArticol, $coduriOem);
    if ($codes === []) {
        $event = [
            'status' => 'no_oem',
            'cont_id' => $contId,
            'brand' => $brand,
            'model' => $model,
            'cod_articol' => $codArticol,
            'coduri_oem' => $coduriOem,
            'missing_oem' => true,
            'message' => 'OEM nu e — fara cod de cautat',
        ];
        blu_robot_append_feed($event);
        blu_robot_record_no_oem($event);
        $stats = blu_robot_load_stats();
        $stats['no_oem'] = (int)($stats['no_oem'] ?? 0) + 1;
        $stats['last_at'] = date('Y-m-d H:i:s');
        blu_robot_save_stats($stats);
        return ['ok' => false, 'status' => 'no_oem', 'error' => $event['message'], 'event' => $event];
    }

    $entry = [
        'brand' => $brand,
        'model' => $model,
        'cod_articol' => $codArticol,
        'coduri_oem' => $coduriOem,
        'codes' => $codes,
        'missing_oem' => $missingOem,
        'pret_eur' => $pretEur,
        'descriere_gr' => $descriereGr,
        'imagine_url' => $imagineUrl,
        'gbg_image' => $imagineUrl,
    ];

    blu_robot_append_feed([
        'status' => 'processing',
        'cont_id' => $contId,
        'brand' => $brand,
        'model' => $model,
        'cod_articol' => $codArticol,
        'coduri_oem' => $coduriOem,
        'codes' => $codes,
        'missing_oem' => $missingOem,
        'image' => $imagineUrl,
        'message' => $missingOem
            ? 'Cautare TecDoc dupa cod articol (OEM lipsa)...'
            : 'Cautare Autodoc24/TecDoc (OEM) + Ollama daca e nevoie...',
    ]);

    $batch = blu_process_catalog_batch([$entry], 0, 1, true);
    $cards = is_array($batch['cards'] ?? null) ? $batch['cards'] : [];
    $card = $cards[0] ?? null;
    $found = ($batch['found'] ?? 0) > 0;
    $importedCount = count(array_filter($cards, static fn($c) => is_array($c) && (($c['status'] ?? '') === 'imported')));

    $stats = blu_robot_load_stats();
    $stats['total'] = (int)$stats['total'] + 1;
    $stats['last_at'] = date('Y-m-d H:i:s');
    if ($found) {
        $stats['found'] = (int)$stats['found'] + 1;
    } elseif (!empty($batch['errors'])) {
        $stats['errors'] = (int)$stats['errors'] + 1;
    } elseif ($missingOem) {
        $stats['no_oem'] = (int)($stats['no_oem'] ?? 0) + 1;
    } else {
        $stats['empty'] = (int)$stats['empty'] + 1;
    }
    blu_robot_save_stats($stats);

    $status = $found ? 'imported' : (!empty($batch['errors']) ? 'error' : ($missingOem ? 'no_oem' : 'empty'));
    $event = [
        'status' => $status,
        'cont_id' => $contId,
        'brand' => $brand,
        'model' => $model,
        'cod_articol' => $codArticol,
        'coduri_oem' => $coduriOem,
        'missing_oem' => $missingOem,
        'title' => is_array($card) ? ($card['title'] ?? '') : '',
        'cod_oem' => is_array($card) ? ($card['cod_oem'] ?? '') : '',
        'image' => is_array($card) ? ($card['image'] ?? '') : '',
        'saved_db' => (int)($batch['saved_db'] ?? 0),
        'saved_admin' => (int)($batch['saved_admin'] ?? 0),
        'articles' => $importedCount > 0 ? $importedCount : (is_array($card) ? 1 : 0),
        'errors' => $batch['errors'] ?? [],
        'message' => $found
            ? ('Produs(e) incarcate: ' . $importedCount . ' — ' . (string)($card['title'] ?? ''))
            : (string)($batch['errors'][0] ?? ($missingOem ? 'Niciun articol TecDoc' : 'Niciun rezultat Autodoc24/TecDoc pentru OEM')),
    ];
    if ($pretEur > 0 && is_array($card) && (float)($card['price_ron_final'] ?? 0) > 0) {
        $event['message'] .= sprintf(
            ' | Pret: %.2f EUR → %.2f RON (final)',
            $pretEur,
            (float)$card['price_ron_final']
        );
        $event['pret_eur'] = $pretEur;
        $event['pret_ron_final'] = (float)$card['price_ron_final'];
    }
    $pieseauto = null;
    if ($found && is_array($card)) {
        require_once __DIR__ . '/pieseauto_auto.php';
        if (blu_product_ready_for_pieseauto($card)) {
            $pieseauto = blu_pieseauto_auto_publish($card, [
                'brand' => $brand,
                'model' => $model,
                'cod_articol' => $codArticol,
                'coduri_oem' => $coduriOem,
                'cont_id' => $contId,
            ]);
        } else {
            $pieseauto = [
                'ok' => false,
                'status' => 'skipped',
                'message' => 'Fără preț GBG — salvat fără stoc, nu se publică pe PieseAuto',
                'skipped' => true,
            ];
            $event['message'] .= ' | Fără preț GBG: stoc «Nu este stoc», PieseAuto omis';
        }
        if (is_array($pieseauto)) {
            $event['pieseauto'] = $pieseauto;
            $paMsg = (string)($pieseauto['message'] ?? '');
            if ($paMsg !== '' && empty($pieseauto['skipped'])) {
                $event['message'] .= ' | PieseAuto: ' . $paMsg;
            } elseif (!empty($pieseauto['skipped']) && ($pieseauto['status'] ?? '') === 'skipped' && str_contains($paMsg, 'Deja')) {
                $event['message'] .= ' | PieseAuto: deja publicat';
            }
            if (!empty($pieseauto['waited_sec'])) {
                $event['pieseauto_wait_sec'] = (int)$pieseauto['waited_sec'];
            }
        }
    }

    blu_robot_append_feed($event);
    if ($status === 'no_oem') {
        blu_robot_record_no_oem($event);
    }

    return [
        'ok' => $found,
        'status' => $status,
        'found' => $found,
        'card' => $card,
        'pieseauto' => $pieseauto,
        'batch' => [
            'saved_db' => $batch['saved_db'] ?? 0,
            'saved_admin' => $batch['saved_admin'] ?? 0,
        ],
        'stats' => $stats,
        'event' => $event,
    ];
}
