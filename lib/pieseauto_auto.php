<?php
declare(strict_types=1);

require_once __DIR__ . '/robot_config.php';
require_once __DIR__ . '/robot_launcher.php';
require_once __DIR__ . '/scanned_products.php';
require_once __DIR__ . '/pieseauto_categories.php';
require_once __DIR__ . '/pricing.php';

function blu_pieseauto_auto_config_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'pieseauto_auto.json';
}

function blu_pieseauto_published_file(): string
{
    return blu_data_dir() . DIRECTORY_SEPARATOR . 'pieseauto_published.json';
}

/** @return array{enabled:bool,cont_id:string,default_price:float,min_title_len:int,stare_produs:string} */
function blu_pieseauto_auto_config(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'enabled' => true,
        'cont_id' => 'bluecar',
        'default_price' => 100.0,
        'min_title_len' => 15,
        'stare_produs' => 'Nou',
        'wait_until_done' => true,
        'max_wait_sec' => 420,
        'auto_open_browser' => true,
    ];

    $raw = blu_read_json_file(blu_pieseauto_auto_config_file(), []);
    if (!is_array($raw)) {
        $raw = [];
    }

    $cache = array_merge($defaults, $raw);
    $cache['enabled'] = !in_array(strtolower(trim((string)($raw['enabled'] ?? '1'))), ['0', 'false', 'no', 'off'], true);
    $cache['cont_id'] = preg_replace('/[^a-zA-Z0-9]/', '', (string)($raw['cont_id'] ?? $defaults['cont_id'])) ?: $defaults['cont_id'];
    $cache['default_price'] = max(1.0, (float)($raw['default_price'] ?? $defaults['default_price']));
    $cache['min_title_len'] = max(5, (int)($raw['min_title_len'] ?? $defaults['min_title_len']));
    $cache['stare_produs'] = trim((string)($raw['stare_produs'] ?? $defaults['stare_produs'])) ?: $defaults['stare_produs'];
    $cache['wait_until_done'] = !in_array(strtolower(trim((string)($raw['wait_until_done'] ?? '1'))), ['0', 'false', 'no', 'off'], true);
    $cache['max_wait_sec'] = max(60, min(900, (int)($raw['max_wait_sec'] ?? $defaults['max_wait_sec'])));
    $cache['auto_open_browser'] = !in_array(
        strtolower(trim((string)($raw['auto_open_browser'] ?? ($defaults['auto_open_browser'] ? '1' : '0')))),
        ['0', 'false', 'no', 'off'],
        true
    );

    return $cache;
}

function blu_pieseauto_auto_enabled(): bool
{
    return (bool)blu_pieseauto_auto_config()['enabled'];
}

function blu_pieseauto_auto_cont_id(): string
{
    return (string)blu_pieseauto_auto_config()['cont_id'];
}

/** @return array{email:string,pass:string,company:string}|null */
function blu_pieseauto_auto_account_credentials(): ?array
{
    $file = blu_data_dir() . DIRECTORY_SEPARATOR . 'pieseauto_accounts.json';
    $rows = blu_read_json_file($file, []);
    if (!is_array($rows)) {
        return null;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $email = trim((string)($row['email'] ?? ''));
        $pass = trim((string)($row['pas'] ?? $row['pass'] ?? ''));
        if ($email !== '' && $pass !== '') {
            return [
                'email' => $email,
                'pass' => $pass,
                'company' => trim((string)($row['company_name'] ?? '')),
            ];
        }
    }

    return null;
}

function blu_pieseauto_publish_key(array $card, array $context = []): string
{
    $pid = trim((string)($card['product_id'] ?? ''));
    if ($pid !== '') {
        return 'pid:' . $pid;
    }

    $cod = trim((string)($card['cod_articol'] ?? $context['cod_articol'] ?? ''));
    $oem = trim((string)($card['cod_oem'] ?? $context['coduri_oem'] ?? ''));
    return 'cod:' . strtoupper($cod . '|' . $oem);
}

/** @return array<string, array<string, mixed>> */
function blu_pieseauto_published_map(): array
{
    $rows = blu_read_json_file(blu_pieseauto_published_file(), []);
    if (!is_array($rows)) {
        return [];
    }

    $map = [];
    foreach ($rows as $key => $row) {
        if (is_string($key) && is_array($row)) {
            $map[$key] = $row;
        } elseif (is_array($row) && !empty($row['key'])) {
            $map[(string)$row['key']] = $row;
        }
    }

    return $map;
}

function blu_pieseauto_is_published(string $key): bool
{
    return isset(blu_pieseauto_published_map()[$key]);
}

function blu_pieseauto_mark_published(string $key, array $meta): void
{
    $map = blu_pieseauto_published_map();
    $map[$key] = array_merge($meta, [
        'key' => $key,
        'published_at' => date('Y-m-d H:i:s'),
    ]);

    $list = array_values($map);
    if (count($list) > 5000) {
        $list = array_slice($list, -5000);
    }

    blu_write_json_file(blu_pieseauto_published_file(), $list);
}

/**
 * @return array{ok:bool,status:string,message:string,http_code?:int,queued?:bool,queue_size?:int,skipped?:bool}
 */
function blu_pieseauto_robot_request(string $path, ?array $payload = null, string $method = 'GET', int $timeout = 30): array
{
    $base = rtrim(blu_robot_pieseauto_effective_url(), '/');
    $url = $base . $path;

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 'error', 'message' => 'curl_init eșuat'];
    }

    $headers = ['Content-Type: application/json', 'ngrok-skip-browser-warning: 69420'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ];

    $method = strtoupper($method);
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => 'error', 'message' => $err !== '' ? $err : 'Fără răspuns de la robot', 'http_code' => $code];
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => 'error', 'message' => 'Răspuns invalid de la robot', 'http_code' => $code];
    }

    $status = strtolower(trim((string)($data['status'] ?? '')));
    $ok = $code >= 200 && $code < 300 && ($status === 'succes' || $status === 'activ' || $status === 'lansat' || $status === 'online' || $status === 'ok');

    return [
        'ok' => $ok,
        'status' => $status !== '' ? $status : ($ok ? 'succes' : 'error'),
        'message' => (string)($data['mesaj'] ?? $data['message'] ?? ''),
        'http_code' => $code,
        'queued' => !empty($data['queued']),
        'queue_size' => isset($data['queue_size']) ? (int)$data['queue_size'] : null,
        'browser_active' => !empty($data['browser_active']),
        'busy' => !empty($data['busy']),
        'raw' => $data,
    ];
}

/** @return array<string, mixed>|null */
function blu_pieseauto_find_imported_card(string $productId): ?array
{
    $productId = trim($productId);
    if ($productId === '') {
        return null;
    }

    $file = blu_imported_cards_file();
    $rows = blu_read_json_file($file, []);
    if (!is_array($rows)) {
        return null;
    }

    for ($i = count($rows) - 1; $i >= 0; $i--) {
        $row = $rows[$i];
        if (!is_array($row)) {
            continue;
        }
        $pid = (string)($row['product_id'] ?? $row['id'] ?? '');
        if ($pid === $productId) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function blu_pieseauto_build_publish_payload(array $displayCard, array $context = []): ?array
{
    $cfg = blu_pieseauto_auto_config();
    $contId = blu_pieseauto_auto_cont_id();

    $full = null;
    $pid = trim((string)($displayCard['product_id'] ?? ''));
    if ($pid !== '') {
        $full = blu_pieseauto_find_imported_card($pid);
    }

    $merged = is_array($full) ? array_merge($full, $displayCard) : $displayCard;
    $items = blu_pieseauto_scanned_items('', 500);
    $item = null;
    foreach ($items as $row) {
        if ($pid !== '' && ($row['id'] ?? '') === $pid) {
            $item = $row;
            break;
        }
    }

    if ($item === null) {
        $title = trim((string)($merged['title'] ?? ''));
        $description = trim((string)($merged['descriere'] ?? $merged['description'] ?? $title));
        $imageUrl = trim((string)($merged['image'] ?? ''));
        $images = $imageUrl !== '' ? [$imageUrl] : [];
        $paMeta = blu_pieseauto_classify_product($merged);
        $category = blu_pieseauto_robot_category_name(array_merge($merged, [
            'main_category' => $paMeta['main_category'] ?? '',
            'sub_category' => $paMeta['sub_category'] ?? '',
            'pieseauto_category' => $paMeta['sub_category'] ?? '',
        ]));
        $price = (float)($cfg['default_price']);
        foreach (['price_ron_final', 'pret', 'price'] as $pk) {
            if (isset($merged[$pk]) && (float)$merged[$pk] > 0) {
                $price = (float)$merged[$pk];
                break;
            }
        }
        $item = [
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
            'price' => $price,
            'pieseauto_category' => $category,
            'image_url' => $images[0] ?? '',
            'images' => $images,
        ];
    }

    $title = trim((string)($item['title'] ?? ''));
    $description = trim((string)($item['description'] ?? $title));

    // Garantie Model cartelă + RO înainte de postare PieseAuto
    require_once __DIR__ . '/tecdoc_product_enrich.php';
    if ($description === '' || !blu_description_uses_card_template($description)) {
        $ctx = [
            'brand' => (string)($context['brand'] ?? $merged['marca_masina'] ?? $merged['car_brand'] ?? $item['car_brand'] ?? ''),
            'model' => (string)($context['model'] ?? $merged['model'] ?? $item['car_model'] ?? ''),
            'cod_articol' => (string)($displayCard['cod_articol'] ?? $context['cod_articol'] ?? $item['cod_articol'] ?? ''),
            'coduri_oem' => (string)($displayCard['cod_oem'] ?? $context['coduri_oem'] ?? $item['cod_oem'] ?? $item['coduri_oem'] ?? ''),
        ];
        $fixed = blu_apply_card_template_to_card(array_merge($merged, [
            'title' => $title,
            'title_original' => (string)($merged['title_original'] ?? $title),
            'description' => $description,
            'cod_articol' => $ctx['cod_articol'],
            'coduri_oem' => $ctx['coduri_oem'],
            'marca_masina' => $ctx['brand'],
            'model' => $ctx['model'],
        ]), $ctx);
        $title = trim((string)($fixed['title'] ?? $title));
        $description = trim((string)($fixed['description'] ?? $description));
    }

    if ($title === '' || mb_strlen($title, 'UTF-8') < (int)$cfg['min_title_len']) {
        return null;
    }

    $images = [];
    if (!empty($item['images']) && is_array($item['images'])) {
        foreach ($item['images'] as $img) {
            if (is_string($img) && $img !== '') {
                $images[] = $img;
            } elseif (is_array($img) && !empty($img['url'])) {
                $images[] = (string)$img['url'];
            }
        }
    }
    if ($images === [] && !empty($item['image_url'])) {
        $images[] = (string)$item['image_url'];
    }
    $images = array_values(array_unique(array_filter($images)));
    if ($images === []) {
        return null;
    }

    $paMeta = blu_pieseauto_classify_product(array_merge($merged, $item));
    $categorie = blu_pieseauto_robot_category_name(array_merge($merged, $item, [
        'main_category' => $paMeta['main_category'] ?? '',
        'sub_category' => $paMeta['sub_category'] ?? '',
        'pieseauto_category' => $paMeta['sub_category'] ?? '',
    ]));
    $categoriePrincipala = trim((string)($paMeta['main_category'] ?? ''));

    return [
        'cont_id' => $contId,
        'titlu' => $title,
        'descriere' => $description !== '' ? $description : $title,
        'pret' => max(1, (float)($item['price'] ?? $cfg['default_price'])),
        'stare_produs' => (string)$cfg['stare_produs'],
        'categorie_nume' => $categorie,
        'categorie_principala' => $categoriePrincipala !== '' ? $categoriePrincipala : 'Caroserie',
        'imagine_url' => $images[0],
        'imagini_multiple' => $images,
        'product_id' => $pid,
        'cod_articol' => (string)($displayCard['cod_articol'] ?? $context['cod_articol'] ?? ''),
        'cod_oem' => (string)($displayCard['cod_oem'] ?? $context['coduri_oem'] ?? ''),
    ];
}

/**
 * Pregătește robotul PieseAuto: pornește serviciul, loghează contul, așteaptă browser activ.
 *
 * @return array{success:bool,message:string,online?:bool,browser_active?:bool,cont_id?:string}
 */
function blu_pieseauto_auto_prepare(int $waitBrowserSec = 12, ?bool $openBrowser = null): array
{
    if (!blu_pieseauto_auto_enabled()) {
        return ['success' => false, 'message' => 'Publicare automată PieseAuto dezactivată.'];
    }

    $cfg = blu_pieseauto_auto_config();
    if ($openBrowser === null) {
        $openBrowser = (bool)$cfg['auto_open_browser'];
    }

    $contId = blu_pieseauto_auto_cont_id();
    $creds = blu_pieseauto_auto_account_credentials();
    if ($creds === null) {
        return ['success' => false, 'message' => 'Lipsește cont PieseAuto în pieseauto_accounts.json.'];
    }

    $waitBrowserSec = max(0, min(30, $waitBrowserSec));
    $start = blu_robot_ensure_running('pieseauto', false);
    $deadline = time() + 12;
    while (time() < $deadline && !blu_robot_ping('pieseauto')) {
        usleep(400000);
    }

    if (!blu_robot_ping('pieseauto')) {
        return [
            'success' => false,
            'message' => 'Robotul PieseAuto nu răspunde (' . blu_robot_pieseauto_effective_url() . '). Pornește robot_pieseauto.py.',
            'online' => false,
            'cont_id' => $contId,
            'start' => $start,
        ];
    }

    $state = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($contId), null, 'GET', 8);
    $browserActive = !empty($state['browser_active']);
    $login = null;

    if ($openBrowser && !$browserActive) {
        $login = blu_pieseauto_robot_request('/comanda', [
            'cont_id' => $contId,
            'user' => $creds['email'],
            'pass' => $creds['pass'],
        ], 'POST', 12);
    }

    if (!$openBrowser || $waitBrowserSec <= 0) {
        return [
            'success' => true,
            'message' => $browserActive
                ? 'Browser PieseAuto deja activ.'
                : 'Serviciu online. Deschide browserul manual din panoul PieseAuto (Stația 2).',
            'online' => true,
            'browser_active' => $browserActive,
            'cont_id' => $contId,
            'login' => $login,
            'async' => !$browserActive,
        ];
    }

    $browserDeadline = time() + $waitBrowserSec;
    while (time() < $browserDeadline) {
        $state = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($contId), null, 'GET', 6);
        if (!empty($state['browser_active'])) {
            $browserActive = true;
            break;
        }
        usleep(500000);
    }

    return [
        'success' => $browserActive,
        'message' => $browserActive
            ? 'Robot PieseAuto pregătit (' . $contId . ').'
            : 'Browser inactiv — lansează manual din Stația 2.',
        'online' => true,
        'browser_active' => $browserActive,
        'cont_id' => $contId,
        'login' => $login,
        'async' => !$browserActive,
    ];
}

/**
 * Așteaptă ca robotul PieseAuto să termine publicarea curentă (și coada).
 *
 * @return array{ok:bool,message:string,waited?:int}
 */
function blu_pieseauto_wait_until_idle(string $contId, int $maxWaitSec = 420): array
{
    $contId = preg_replace('/[^a-zA-Z0-9]/', '', $contId) ?: blu_pieseauto_auto_cont_id();
    $started = time();
    $deadline = $started + max(30, $maxWaitSec);
    $seenBusy = false;

    while (time() < $deadline) {
        $state = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($contId), null, 'GET', 12);
        if (!$state['ok']) {
            usleep(900000);
            continue;
        }

        $raw = is_array($state['raw'] ?? null) ? $state['raw'] : [];
        $busy = !empty($state['busy']);
        $queue = (int)($raw['queue_size'] ?? $state['queue_size'] ?? 0);

        if ($busy || $queue > 0) {
            $seenBusy = true;
            usleep(1000000);
            continue;
        }

        if (!$seenBusy) {
            usleep(600000);
            if (time() - $started >= 10) {
                return [
                    'ok' => false,
                    'message' => 'Robot PieseAuto nu a pornit publicarea.',
                    'waited' => time() - $started,
                ];
            }
            continue;
        }

        $last = $raw['last_publish'] ?? null;
        if (is_array($last)) {
            return [
                'ok' => !empty($last['ok']),
                'message' => (string)($last['message'] ?? ''),
                'waited' => time() - $started,
            ];
        }

        $msg = (string)($state['message'] ?? '');
        if ($msg === '') {
            $st = blu_pieseauto_robot_request('/get_status?cont_id=' . rawurlencode($contId), null, 'GET', 8);
            $msg = (string)($st['message'] ?? '');
        }

        if (str_contains($msg, '✅') || stripos($msg, 'publicat cu succes') !== false) {
            return ['ok' => true, 'message' => $msg, 'waited' => time() - $started];
        }
        if (str_contains($msg, '❌') || stripos($msg, 'Eroare') !== false) {
            return ['ok' => false, 'message' => $msg, 'waited' => time() - $started];
        }

        return ['ok' => true, 'message' => $msg !== '' ? $msg : 'Publicare PieseAuto finalizată.', 'waited' => time() - $started];
    }

    return [
        'ok' => false,
        'message' => 'Timeout așteptare PieseAuto (' . $maxWaitSec . 's).',
        'waited' => time() - $started,
    ];
}

/**
 * Publică automat un produs importat pe PieseAuto.ro.
 *
 * @return array{ok:bool,status:string,message:string,skipped?:bool,queued?:bool,queue_size?:int|null,publish_key?:string}
 */
function blu_pieseauto_auto_publish(array $displayCard, array $context = []): array
{
    $cfg = blu_pieseauto_auto_config();

    if (!$cfg['enabled']) {
        return ['ok' => false, 'status' => 'disabled', 'message' => 'Auto PieseAuto dezactivat', 'skipped' => true];
    }

    if (!blu_product_ready_for_pieseauto($displayCard)) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'message' => 'Fără preț GBG — produs salvat fără stoc, nu se publică',
            'skipped' => true,
        ];
    }

    $publishKey = blu_pieseauto_publish_key($displayCard, $context);
    if (blu_pieseauto_is_published($publishKey)) {
        return ['ok' => true, 'status' => 'skipped', 'message' => 'Deja publicat pe PieseAuto', 'skipped' => true, 'publish_key' => $publishKey];
    }

    $payload = blu_pieseauto_build_publish_payload($displayCard, $context);
    if ($payload === null) {
        return ['ok' => false, 'status' => 'skipped', 'message' => 'Produs neeligibil (titlu scurt sau fără imagini)', 'skipped' => true, 'publish_key' => $publishKey];
    }

    $paContId = blu_pieseauto_auto_cont_id();

    if (!blu_robot_ping('pieseauto')) {
        blu_robot_ensure_running('pieseauto', false);
        usleep(1500000);
        if (!blu_robot_ping('pieseauto')) {
            return ['ok' => false, 'status' => 'offline', 'message' => 'Robot PieseAuto offline', 'publish_key' => $publishKey];
        }
    }

    $browser = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($paContId), null, 'GET', 8);
    $browserActive = !empty($browser['browser_active']);
    if (!$browserActive) {
        blu_pieseauto_auto_prepare(45, true);
        $browser = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($paContId), null, 'GET', 8);
        $browserActive = !empty($browser['browser_active']);
    }
    if (!$browserActive) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'message' => 'Browser PieseAuto inactiv — apasă «Lansează browser robot» în Stația 2 (cont: ' . $paContId . ') sau repornește robot_pieseauto.py, apoi reia scanarea.',
            'skipped' => true,
            'publish_key' => $publishKey,
        ];
    }

    $apiPayload = $payload;
    unset($apiPayload['product_id'], $apiPayload['cod_articol'], $apiPayload['cod_oem']);

    $resp = blu_pieseauto_robot_request('/adauga_piesa_noua', $apiPayload, 'POST', 25);
    if (!$resp['ok']) {
        $msg = $resp['message'] !== '' ? $resp['message'] : 'Eroare publicare PieseAuto';
        return ['ok' => false, 'status' => 'error', 'message' => $msg, 'publish_key' => $publishKey];
    }

    $waited = 0;
    if (!empty($cfg['wait_until_done'])) {
        $wait = blu_pieseauto_wait_until_idle($paContId, (int)$cfg['max_wait_sec']);
        $waited = (int)($wait['waited'] ?? 0);
        if (!$wait['ok']) {
            return [
                'ok' => false,
                'status' => 'publish_failed',
                'message' => (string)($wait['message'] ?? 'Publicare PieseAuto eșuată'),
                'waited_sec' => $waited,
                'publish_key' => $publishKey,
            ];
        }
        $finalMsg = (string)($wait['message'] ?? 'Publicat pe PieseAuto');
    } else {
        $finalMsg = $resp['message'] !== '' ? $resp['message'] : 'Trimis către PieseAuto';
    }

    blu_pieseauto_mark_published($publishKey, [
        'title' => $payload['titlu'],
        'product_id' => $payload['product_id'] ?? '',
        'cod_articol' => $payload['cod_articol'] ?? '',
        'cod_oem' => $payload['cod_oem'] ?? '',
        'cont_id' => $paContId,
        'waited_sec' => $waited,
    ]);

    return [
        'ok' => true,
        'status' => 'published',
        'message' => $finalMsg . ($waited > 0 ? ' (' . $waited . 's)' : ''),
        'waited_sec' => $waited,
        'publish_key' => $publishKey,
    ];
}
