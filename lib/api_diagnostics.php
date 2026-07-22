<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/robot_config.php';

/**
 * Diagnostic chei / token-uri API pentru admin.
 * Aici se aduna toate erorile posibile (RapidAPI, cheie robot, roboti locali, MySQL)
 * intr-un singur loc, ca utilizatorul sa vada clar problema si sa o poata corecta.
 */

function blu_diag_env_file(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
}

/**
 * Cheile din .env care pot fi editate direct din panoul de diagnostic.
 * @return array<string, array{label:string, type:string, hint:string}>
 */
function blu_diag_editable_keys(): array
{
    return [
        'RAPIDAPI_AUTOPARTS_KEY' => [
            'label' => 'Cheie RapidAPI (TecDoc / Auto Parts Catalog)',
            'type' => 'secret',
            'hint' => 'Cheia x-rapidapi-key din contul RapidAPI, abonat la „auto-parts-catalog”.',
        ],
        'SCRAPE_DO_TOKEN' => [
            'label' => 'Token scrape.do (Autodoc24)',
            'type' => 'secret',
            'hint' => 'Cheia/Token-ul pentru http://api.scrape.do/ (folosit în lib/autodoc_scraper.php).',
        ],
        'ROBOT_API_KEY' => [
            'label' => 'Cheie Robot (X-Robot-Key)',
            'type' => 'secret',
            'hint' => 'Aceeasi valoare trebuie pusa in robot_config.json (robot_api_key) pe robot.',
        ],
        'ROBOT_FURNIZORI_URL' => [
            'label' => 'URL Robot Furnizori GBG (scanare)',
            'type' => 'url',
            'hint' => 'Ex: http://127.0.0.1:5000',
        ],
        'ROBOT_PIESEAUTO_URL' => [
            'label' => 'URL Robot PieseAuto (postare)',
            'type' => 'url',
            'hint' => 'Ex: http://127.0.0.1:5003',
        ],
        'DB_HOST' => ['label' => 'MySQL host', 'type' => 'plain', 'hint' => 'Ex: localhost'],
        'DB_NAME' => ['label' => 'MySQL bază de date', 'type' => 'plain', 'hint' => 'Ex: blu_car_db (dedicat Blue-Car, NU agent_db)'],
        'DB_USER' => ['label' => 'MySQL utilizator', 'type' => 'plain', 'hint' => 'Ex: root'],
        'DB_PASS' => ['label' => 'MySQL parolă', 'type' => 'secret', 'hint' => 'Parola utilizatorului MySQL.'],
    ];
}

/** Mascare cheie pentru afisare sigura (pastreaza primele/ultimele caractere). */
function blu_diag_mask(string $value): string
{
    $value = trim($value);
    $len = strlen($value);
    if ($len === 0) {
        return '(gol)';
    }
    if ($len <= 6) {
        return str_repeat('•', $len);
    }
    return substr($value, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($value, -4);
}

/** @return array<string,string> valorile curente efective (din .env / mediu) */
function blu_diag_current_values(): array
{
    $out = [];
    foreach (blu_diag_editable_keys() as $key => $_meta) {
        $out[$key] = (string)blu_env($key, '');
    }
    return $out;
}

/**
 * Salveaza in .env doar cheile permise, pastrand restul liniilor/comentariilor.
 * @param array<string,string> $input
 * @return array{ok:bool, message:string, updated:array<int,string>}
 */
function blu_diag_save_env(array $input): array
{
    $allowed = blu_diag_editable_keys();
    $file = blu_diag_env_file();

    $changes = [];
    foreach ($allowed as $key => $_meta) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $val = trim((string)$input[$key]);
        $val = preg_replace('/[\r\n]+/', '', $val) ?? $val;
        $changes[$key] = $val;
    }

    if (!$changes) {
        return ['ok' => false, 'message' => 'Nicio cheie de salvat.', 'updated' => []];
    }

    $lines = is_file($file) ? @file($file, FILE_IGNORE_NEW_LINES) : [];
    if (!is_array($lines)) {
        $lines = [];
    }

    $seen = [];
    foreach ($lines as $i => $line) {
        $trim = ltrim($line);
        if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        if (array_key_exists($key, $changes)) {
            $lines[$i] = $key . '=' . $changes[$key];
            $seen[$key] = true;
        }
    }

    foreach ($changes as $key => $val) {
        if (empty($seen[$key])) {
            $lines[] = $key . '=' . $val;
        }
    }

    $content = implode("\n", $lines) . "\n";
    $ok = @file_put_contents($file, $content) !== false;

    if ($ok) {
        // Reflecta imediat in procesul curent ca re-testarea sa foloseasca noile valori.
        foreach ($changes as $key => $val) {
            $_ENV[$key] = $val;
            @putenv($key . '=' . $val);
        }
    }

    return [
        'ok' => $ok,
        'message' => $ok
            ? 'Chei salvate în .env. Roboții Python trebuie reporniți ca să preia valorile noi.'
            : 'Nu am putut scrie în .env (verifică permisiunile fișierului).',
        'updated' => array_keys($changes),
    ];
}

/** @return array{ok:bool, http:int, body:string, error:string} */
function blu_diag_http_get(string $url, int $timeout = 4): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'body' => '', 'error' => 'cURL indisponibil în PHP.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout + 2,
        CURLOPT_HTTPHEADER => ['ngrok-skip-browser-warning: 69420'],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [
        'ok' => $body !== false && $http >= 200 && $http < 500,
        'http' => $http,
        'body' => is_string($body) ? $body : '',
        'error' => (string)$err,
    ];
}

/**
 * Ruleaza toate verificarile si intoarce o lista de carduri de stare.
 * @return array{checks:array<int,array<string,mixed>>, errors:array<int,array<string,mixed>>, generated_at:string}
 */
function blu_diag_run_all(): array
{
    $checks = [];

    // 1) RapidAPI (TecDoc)
    require_once __DIR__ . '/catalog_import.php';
    $rapidKey = function_exists('blu_rapidapi_key') ? blu_rapidapi_key() : trim((string)blu_env('RAPIDAPI_AUTOPARTS_KEY', ''));
    if ($rapidKey === '') {
        $checks[] = [
            'id' => 'rapidapi',
            'label' => 'RapidAPI — TecDoc / Auto Parts Catalog',
            'ok' => false,
            'level' => 'err',
            'message' => 'Lipsește cheia RAPIDAPI_AUTOPARTS_KEY.',
            'detail' => '',
            'hint' => 'Completează cheia mai jos (RAPIDAPI_AUTOPARTS_KEY) și salvează.',
            'fix_key' => 'RAPIDAPI_AUTOPARTS_KEY',
        ];
    } else {
        $diag = function_exists('blu_rapidapi_diagnostic') ? blu_rapidapi_diagnostic() : ['ok' => true];
        $ok = !empty($diag['ok']);
        $msg = $ok ? 'Cheie validă — răspuns OK de la RapidAPI.' : (string)($diag['error'] ?? 'Eroare necunoscută.');
        $checks[] = [
            'id' => 'rapidapi',
            'label' => 'RapidAPI — TecDoc / Auto Parts Catalog',
            'ok' => $ok,
            'level' => $ok ? 'ok' : 'err',
            'message' => $msg,
            'detail' => json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '',
            'hint' => $ok ? '' : 'Verifică cheia RapidAPI și abonamentul la „auto-parts-catalog”. O poți corecta mai jos.',
            'fix_key' => 'RAPIDAPI_AUTOPARTS_KEY',
            'masked' => blu_diag_mask($rapidKey),
        ];
    }

    // 2) Token scrape.do
    $scrapeToken = trim((string)blu_env('SCRAPE_DO_TOKEN', ''));
    $checks[] = [
        'id' => 'scrape_do',
        'label' => 'scrape.do (Autodoc24)',
        'ok' => $scrapeToken !== '',
        'level' => $scrapeToken !== '' ? 'ok' : 'warn',
        'message' => $scrapeToken !== ''
            ? 'Token setat. Autodoc24 poate fi căutat prin scrape.do.'
            : 'SCRAPE_DO_TOKEN gol — căutările Autodoc24 pot eșua.',
        'detail' => $scrapeToken !== '' ? ('Valoare: ' . blu_diag_mask($scrapeToken)) : '',
        'hint' => $scrapeToken !== '' ? '' : 'Completează SCRAPE_DO_TOKEN în .env și salvează din Diagnostic API.',
        'fix_key' => 'SCRAPE_DO_TOKEN',
        'masked' => $scrapeToken !== '' ? blu_diag_mask($scrapeToken) : '(gol)',
    ];

    // 3) Cheie Robot (X-Robot-Key) pe site
    $robotKey = trim((string)blu_env('ROBOT_API_KEY', ''));
    $checks[] = [
        'id' => 'robot_key',
        'label' => 'Cheie Robot (site) — X-Robot-Key',
        'ok' => $robotKey !== '',
        'level' => $robotKey !== '' ? 'ok' : 'warn',
        'message' => $robotKey !== ''
            ? 'Cheie setată. Robotul Python trebuie să trimită exact aceeași valoare.'
            : 'ROBOT_API_KEY gol — orice robot e acceptat (nesecurizat).',
        'detail' => $robotKey !== '' ? ('Valoare site: ' . blu_diag_mask($robotKey)) : '',
        'hint' => 'Dacă robotul dă „API key invalida” (403), pune aceeași valoare în robot_config.json (robot_api_key) pe robot.',
        'fix_key' => 'ROBOT_API_KEY',
        'masked' => $robotKey !== '' ? blu_diag_mask($robotKey) : '(gol)',
    ];

    // 3) Robot Furnizori GBG (Flask local)
    $furl = blu_robot_furnizori_base_url();
    $fres = blu_diag_http_get($furl . '/status');
    $checks[] = [
        'id' => 'robot_furnizori',
        'label' => 'Robot Furnizori GBG (scanare)',
        'ok' => $fres['ok'],
        'level' => $fres['ok'] ? 'ok' : 'err',
        'message' => $fres['ok'] ? ('ONLINE (' . $furl . ')') : ('OFFLINE / inaccesibil (' . $furl . ')'),
        'detail' => $fres['ok'] ? '' : ('HTTP ' . $fres['http'] . ($fres['error'] ? ' — ' . $fres['error'] : '')),
        'hint' => $fres['ok'] ? '' : 'Pornește robot1.py (sau rulează robot\\install_autostart.bat). Verifică ROBOT_FURNIZORI_URL.',
        'fix_key' => 'ROBOT_FURNIZORI_URL',
    ];

    // 4) Robot PieseAuto (Flask local)
    $purl = blu_robot_pieseauto_base_url();
    $pres = blu_diag_http_get($purl . '/verificare_sesiune');
    $checks[] = [
        'id' => 'robot_pieseauto',
        'label' => 'Robot PieseAuto (postare)',
        'ok' => $pres['ok'],
        'level' => $pres['ok'] ? 'ok' : 'err',
        'message' => $pres['ok'] ? ('ONLINE (' . $purl . ')') : ('OFFLINE / inaccesibil (' . $purl . ')'),
        'detail' => $pres['ok'] ? '' : ('HTTP ' . $pres['http'] . ($pres['error'] ? ' — ' . $pres['error'] : '')),
        'hint' => $pres['ok'] ? '' : 'Pornește robot_pieseauto.py. Verifică ROBOT_PIESEAUTO_URL.',
        'fix_key' => 'ROBOT_PIESEAUTO_URL',
    ];

    // 5) MySQL
    $db = blu_db_settings_from_env();
    if ($db === null) {
        $checks[] = [
            'id' => 'mysql',
            'label' => 'MySQL (produse importate)',
            'ok' => false,
            'level' => 'warn',
            'message' => 'DB_NAME / DB_USER lipsesc în .env.',
            'detail' => '',
            'hint' => 'Completează DB_HOST, DB_NAME, DB_USER, DB_PASS mai jos.',
            'fix_key' => 'DB_NAME',
        ];
    } else {
        $dbOk = false;
        $dbErr = '';
        try {
            $dsn = 'mysql:host=' . ($db['db_host'] ?: 'localhost') . ';dbname=' . $db['db_name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [
                PDO::ATTR_TIMEOUT => 4,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');
            $dbOk = true;
        } catch (Throwable $e) {
            $dbErr = $e->getMessage();
        }
        $checks[] = [
            'id' => 'mysql',
            'label' => 'MySQL (produse importate)',
            'ok' => $dbOk,
            'level' => $dbOk ? 'ok' : 'err',
            'message' => $dbOk
                ? ('Conexiune OK → ' . $db['db_name'] . '@' . $db['db_host'])
                : 'Conexiune eșuată.',
            'detail' => $dbOk ? '' : $dbErr,
            'hint' => $dbOk ? '' : 'Verifică DB_HOST / DB_NAME / DB_USER / DB_PASS mai jos și că MySQL rulează.',
            'fix_key' => 'DB_PASS',
        ];
    }

    // Ultimele erori reale din jurnalul robotului (RapidAPI / import)
    $errors = [];
    if (is_file(__DIR__ . '/robot_feed.php')) {
        require_once __DIR__ . '/robot_feed.php';
        if (function_exists('blu_robot_get_feed')) {
            foreach (blu_robot_get_feed(120) as $ev) {
                $status = strtolower((string)($ev['status'] ?? ''));
                $evErrors = $ev['errors'] ?? [];
                if ($status === 'error' || (is_array($evErrors) && $evErrors)) {
                    $msg = (string)($ev['message'] ?? '');
                    if (is_array($evErrors) && $evErrors) {
                        $msg = trim($msg . ' ' . implode(' | ', array_map('strval', $evErrors)));
                    }
                    $errors[] = [
                        't' => (string)($ev['at'] ?? $ev['time'] ?? $ev['t'] ?? ''),
                        'msg' => $msg !== '' ? $msg : 'Eroare nespecificată.',
                    ];
                }
                if (count($errors) >= 40) {
                    break;
                }
            }
        }
    }

    return [
        'checks' => $checks,
        'errors' => $errors,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}
