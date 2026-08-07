<?php
declare(strict_types=1);

/**
 * Client Ollama local — normalizare OEM + traducere GR→RO pentru piese GBG.
 * Necesită Ollama pe PC (default http://127.0.0.1:11434).
 */

function blu_ollama_enabled(): bool
{
    $v = strtolower(trim((string) blu_env('OLLAMA_ENABLED', '1')));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function blu_ollama_base_url(): string
{
    $url = trim((string) blu_env('OLLAMA_URL', 'http://127.0.0.1:11434'));
    return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:11434';
}

function blu_ollama_model(): string
{
    $m = trim((string) blu_env('OLLAMA_MODEL', 'qwen2.5:3b'));
    return $m !== '' ? $m : 'qwen2.5:3b';
}

/**
 * @return array{ok:bool,text?:string,error?:string,raw?:array}
 */
function blu_ollama_chat(string $system, string $user, float $temperature = 0.1, int $timeout = 45): array
{
    if (!blu_ollama_enabled()) {
        return ['ok' => false, 'error' => 'Ollama dezactivat (OLLAMA_ENABLED=0)'];
    }

    $payload = [
        'model' => blu_ollama_model(),
        'stream' => false,
        'options' => [
            'temperature' => $temperature,
            'num_predict' => 512,
        ],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
    ];

    $ch = curl_init(blu_ollama_base_url() . '/api/chat');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || $body === false) {
        return ['ok' => false, 'error' => 'Ollama offline: ' . ($err !== '' ? $err : 'conexiune esuata')];
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json) || $code >= 400) {
        return ['ok' => false, 'error' => 'Raspuns Ollama invalid (HTTP ' . $code . ')'];
    }
    $text = trim((string) (($json['message']['content'] ?? '') ?: ''));
    if ($text === '') {
        return ['ok' => false, 'error' => 'Ollama a returnat text gol', 'raw' => $json];
    }
    return ['ok' => true, 'text' => $text, 'raw' => $json];
}

/**
 * Extrage JSON din răspuns LLM (poate conține markdown).
 * @return array<string,mixed>|null
 */
function blu_ollama_parse_json_object(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
        $decoded = json_decode($m[0], true);
        return is_array($decoded) ? $decoded : null;
    }
    return null;
}

/**
 * Normalizează / optimizează lista de OEM pentru căutare Autodoc/TecDoc.
 * Rule-based întâi; Ollama doar dacă e nevoie de curățare ambiguă.
 *
 * @param list<string> $oemCodes
 * @return list<string>
 */
function blu_ollama_optimize_oem_codes(array $oemCodes, string $codArticol = '', string $brand = ''): array
{
    $base = [];
    foreach ($oemCodes as $c) {
        $c = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $c) ?? '');
        if ($c !== '') {
            $base[] = $c;
        }
    }
    $codArticol = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $codArticol) ?? '');

    // Folosim listă + lookup — cheile numerice PHP (ex. "60573516") ar deveni int în array asociativ.
    $out = [];
    $seen = [];
    $push = static function (string $code) use (&$out, &$seen): void {
        if ($code === '') {
            return;
        }
        $key = 'c:' . $code;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $out[] = $code;
        // Fără zerouri la început (doar dacă rămân cifre)
        $stripped = ltrim($code, '0');
        if ($stripped !== '' && $stripped !== $code) {
            $key2 = 'c:' . $stripped;
            if (!isset($seen[$key2])) {
                $seen[$key2] = true;
                $out[] = $stripped;
            }
        }
    };
    foreach ($base as $c) {
        $push((string) $c);
    }

    // Ollama: doar dacă avem text murdar / multiple token-uri ambigue
    $needsAi = false;
    foreach ($oemCodes as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }
        if (preg_match('/[^\w\s,;.\-\/]/u', $raw) || preg_match('/\s{2,}|[a-z]{4,}/u', $raw)) {
            $needsAi = true;
            break;
        }
    }

    if ($needsAi && blu_ollama_enabled()) {
        $sys = 'Esti expert piese auto OEM. Returnezi DOAR JSON valid, fara markdown.';
        $user = "Extrage si normalizeaza codurile OEM pentru cautare TecDoc/Autodoc.\n"
            . "Brand: {$brand}\n"
            . "Cod articol furnizor: {$codArticol}\n"
            . "Text OEM brut: " . implode(' | ', $oemCodes) . "\n"
            . "Raspunde exact: {\"oem_codes\":[\"...\"]}\n"
            . "Reguli: doar coduri alfanumerice; fara spatii; fara duplicate; pastreaza variante utile.";
        $res = blu_ollama_chat($sys, $user, 0.0, 30);
        if (!empty($res['ok'])) {
            $obj = blu_ollama_parse_json_object((string) $res['text']);
            if (is_array($obj) && !empty($obj['oem_codes']) && is_array($obj['oem_codes'])) {
                foreach ($obj['oem_codes'] as $c) {
                    $c = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $c) ?? '');
                    $push($c);
                }
            }
        }
    }

    return $out;
}

/**
 * Dicționar rapid GR→RO pentru termeni GBG frecvenți (înainte de Ollama).
 * @return array{part_name:string,side?:string,quality?:string}|null
 */
function blu_gbg_greek_dictionary_match(string $greek): ?array
{
    $g = mb_strtoupper($greek, 'UTF-8');
    $map = [
        'ΓΡΥΛΛΟΣ ΠΑΡΑΘΥΡΟΥ' => 'Macara geam',
        'ΓΡΥΛΟΣ ΠΑΡΑΘΥΡΟΥ' => 'Macara geam',
        'ΜΗΧΑΝΙΣΜΟΣ ΠΑΡΑΘΥΡΟΥ' => 'Macara geam',
        'ΓΡΥΛΛΟΣ' => 'Macara geam',
        'ΔΙΑΚΟΠΤΗΣ ΠΑΡΑΘΥΡΟΥ' => 'Comutator macara geam',
        'ΔΙΑΚΟΠΤΗΣ' => 'Comutator',
        'ΦΑΝΑΡΙ' => 'Far',
        'ΦΑΝΟΣ' => 'Far',
        'ΠΡΟΒΟΛΕΑΣ ΟΜΙΧΛΗΣ' => 'Proiector ceata',
        'ΠΡΟΒΟΛΕΑΣ' => 'Proiector',
        'ΦΑΝΑΡΙ ΠΙΣΩ' => 'Stop',
        'ΦΩΣ STOP' => 'Stop',
        'ΚΑΘΡΕΦΤΗΣ' => 'Oglinda',
        'ΚΡΥΣΤΑΛΛΟ ΚΑΘΡΕΦΤΗ' => 'Sticla oglinda',
        'ΑΜΟΡΤΙΣΕΡ' => 'Amortizor',
        'ΦΙΛΤΡΟ ΚΑΜΠΙΝΑΣ' => 'Filtru habitaclu',
        'ΦΙΛΤΡΟ ΑΕΡΑ' => 'Filtru aer',
        'ΦΙΛΤΡΟ ΛΑΔΙΟΥ' => 'Filtru ulei',
        'ΦΙΛΤΡΟ ΚΑΥΣΙΜΟΥ' => 'Filtru combustibil',
        'ΔΙΣΚΟΠΛΑΚΑ' => 'Disc frana',
        'ΤΑΚΑΚΙΑ' => 'Placute frana',
        'ΣΙΑΓΩΝΕΣ' => 'Saboti frana',
        'ΔΑΓΚΑΝΑ' => 'Etrier frana',
        'ΨΥΓΕΙΟ ΝΕΡΟΥ' => 'Radiator',
        'ΨΥΓΕΙΟ A/C' => 'Radiator AC',
        'ΨΥΓΕΙΟ' => 'Radiator',
        'ΑΝΤΛΙΑ ΝΕΡΟΥ' => 'Pompa apa',
        'ΑΝΤΛΙΑ ΒΕΝΖΙΝΗΣ' => 'Pompa combustibil',
        'ΑΝΤΛΙΑ ΛΑΔΙΟΥ' => 'Pompa ulei',
        'ΣΥΜΠΛΕΚΤΗΣ' => 'Ambreiaj',
        'ΗΜΙΑΞΟΝΙΟ' => 'Planetara',
        'ΕΛΑΤΗΡΙΟ' => 'Arc suspensie',
        'ΒΡΑΧΙΟΝΑΣ' => 'Brat suspensie',
        'ΨΑΛΙΔΙ' => 'Bascula',
        'ΑΚΡΟΜΠΑΡΟ' => 'Cap bara directie',
        'ΜΠΑΛΑΚΙ' => 'Pivot',
        'ΡΟΥΛΕΜΑΝ' => 'Rulment',
        'ΙΜΑΝΤΑΣ ΧΡΟΝΙΣΜΟΥ' => 'Curea distributie',
        'ΙΜΑΝΤΑΣ' => 'Curea',
        'ΤΕΝΤΩΤΗΡΑΣ' => 'Intinzator',
        'ΘΕΡΜΟΣΤΑΤΗΣ' => 'Termostat',
        'ΜΠΟΥΖΙ' => 'Bujie',
        'ΜΠΙΕΛΑ' => 'Biela',
        'ΚΥΛΙΝΔΡΟΣ' => 'Cilindru',
        'ΠΟΡΤΑ' => 'Usa',
        'ΚΑΠΟ' => 'Capota motor',
        'ΠΟΡΤΜΠΑΓΚΑΖ' => 'Capota portbagaj',
        'ΠΡΟΦΥΛΑΚΤΗΡΑΣ' => 'Bara protectie',
        'ΦΤΕΡΟ' => 'Aripa',
        'ΠΑΝΕΛ' => 'Panou',
        'ΜΑΣΚΑ' => 'Grila radiator',
        'ΥΑΛΟΚΑΘΑΡΙΣΤΗΡΑΣ' => 'Stergator',
        'ΜΟΤΕΡ ΥΑΛΟΚΑΘΑΡΙΣΤΗΡΑ' => 'Motor stergator',
        'ΔΥΝΑΜΟ' => 'Alternator',
        'ΜΙΖΑ' => 'Electromotor',
        'ΣΕΝΣΟΡ' => 'Senzor',
        'ΛΑΜΔΑ' => 'Sonda lambda',
        'ΤΟΥΡΜΠΙΝΑ' => 'Turbocompresor',
        'ΣΥΜΠΙΕΣΤΗΣ' => 'Compessor AC',
        'ΧΕΙΡΟΛΑΒΗ' => 'Maner usa',
        'ΚΑΘΙΣΜΑ' => 'Scaun',
        'ΤΑΜΠΛΟ' => 'Plansa bord',
        'ΠΑΤΑΚΙ' => 'Covoras',
        'ΚΑΛΩΔΙΟ' => 'Cablu',
        'ΦΙΣΑ' => 'Mufa',
        'ΡΕΛΕ' => 'Releu',
    ];
    $part = null;
    foreach ($map as $gr => $ro) {
        if (mb_strpos($g, $gr, 0, 'UTF-8') !== false) {
            $part = $ro;
            break;
        }
    }
    if ($part === null) {
        return null;
    }
    $bits = [$part];
    if (mb_strpos($g, 'ΕΜΠΡΟΣ', 0, 'UTF-8') !== false) {
        $bits[] = 'fata';
    } elseif (mb_strpos($g, 'ΠΙΣΩ', 0, 'UTF-8') !== false) {
        $bits[] = 'spate';
    }
    if (mb_strpos($g, 'ΗΛΕΚΤΡΙΚ', 0, 'UTF-8') !== false) {
        $bits[] = 'electric';
    }
    // ΑΡ = stânga, ΔΕ = dreapta (convenție GBG)
    if (preg_match('/\bΑΡ\b/u', $greek) || mb_strpos($g, ' ΑΡ', 0, 'UTF-8') !== false || str_ends_with(trim($greek), 'ΑΡ')) {
        $bits[] = 'stanga';
    } elseif (preg_match('/\bΔΕ\b/u', $greek) || mb_strpos($g, ' ΔΕ', 0, 'UTF-8') !== false) {
        $bits[] = 'dreapta';
    }
    if (mb_strpos($g, 'Α ΠΟΙΟΤΗΤΑ', 0, 'UTF-8') !== false || mb_strpos($g, 'Α\' ΠΟΙΟΤΗΤΑ', 0, 'UTF-8') !== false) {
        $bits[] = 'calitate A';
    }
    return ['part_name' => implode(' ', $bits)];
}

/**
 * Traduce descrierea greacă GBG în română + titlu scurt piesă.
 * 1) dicționar local  2) Ollama ca rezervă
 *
 * @return array{ok:bool,part_name?:string,title?:string,description?:string,error?:string,via?:string}
 */
function blu_ollama_translate_gbg_product(
    string $greekDescription,
    string $brand,
    string $model,
    string $oem,
    string $codArticol = ''
): array {
    $greekDescription = trim($greekDescription);
    if ($greekDescription === '') {
        return ['ok' => false, 'error' => 'Descriere greacă goală'];
    }

    $dict = blu_gbg_greek_dictionary_match($greekDescription);
    if ($dict !== null) {
        $part = $dict['part_name'];
        $title = trim($part . ', ' . $brand . ', ' . $model . ', OEM ' . $oem);
        $desc = $part . ' pentru ' . trim($brand . ' ' . $model)
            . '. Cod OEM ' . $oem . '.'
            . ($codArticol !== '' ? ' Cod furnizor ' . $codArticol . '.' : '')
            . ' Produs nou.';
        return [
            'ok' => true,
            'part_name' => $part,
            'title' => $title,
            'description' => $desc,
            'via' => 'dictionary',
        ];
    }

    if (!blu_ollama_enabled()) {
        return ['ok' => false, 'error' => 'Ollama dezactivat si fara match in dictionar'];
    }

    $sys = 'Esti translator tehnic auto greaca→romana. Returnezi DOAR JSON valid, fara markdown.';
    $user = "Traduce textul grecesc al piesei auto in romana.\n"
        . "Marca: {$brand}\nModel: {$model}\nOEM: {$oem}\nCod furnizor: {$codArticol}\n"
        . "Text grecesc: {$greekDescription}\n\n"
        . "JSON obligatoriu:\n"
        . "{\"part_name\":\"...\",\"title\":\"...\",\"description\":\"...\"}\n"
        . "Exemple part_name: \"Mecanism geam electric fata stanga\", \"Far stanga\", \"Amortizor fata\".\n"
        . "Reguli stricte:\n"
        . "- part_name = tipul piesei in romana (NU pune OEM/cod numeric in part_name)\n"
        . "- title = part_name + marca + model + OEM {$oem}\n"
        . "- description = 2-3 propozitii in romana, mentioneaza OEM {$oem}\n"
        . "- fara greaca, fara engleza inutila";

    $res = blu_ollama_chat($sys, $user, 0.2, 60);
    if (empty($res['ok'])) {
        return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Ollama fail')];
    }
    $obj = blu_ollama_parse_json_object((string) $res['text']);
    if (!is_array($obj)) {
        return ['ok' => false, 'error' => 'JSON invalid de la Ollama'];
    }
    $part = trim((string) ($obj['part_name'] ?? ''));
    $title = trim((string) ($obj['title'] ?? ''));
    $desc = trim((string) ($obj['description'] ?? ''));
    if ($part === '' && $title === '') {
        return ['ok' => false, 'error' => 'Traducere goala'];
    }
    // Respinge dacă part_name e doar un cod numeric
    if ($part !== '' && preg_match('/^\d+$/', $part)) {
        $part = 'Piesa auto';
    }
    if ($title === '') {
        $title = trim($part . ', ' . $brand . ', ' . $model . ', OEM ' . $oem);
    }
    if ($desc === '') {
        $desc = $title;
    }
    return [
        'ok' => true,
        'part_name' => $part !== '' ? $part : 'Piesa auto',
        'title' => $title,
        'description' => $desc,
        'via' => 'ollama',
    ];
}

/**
 * Ping rapid Ollama.
 * @return array{ok:bool,models?:list<string>,error?:string}
 */
function blu_ollama_ping(): array
{
    if (!blu_ollama_enabled()) {
        return ['ok' => false, 'error' => 'dezactivat'];
    }
    $ch = curl_init(blu_ollama_base_url() . '/api/tags');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno || $body === false) {
        return ['ok' => false, 'error' => 'Ollama nu raspunde pe ' . blu_ollama_base_url()];
    }
    $json = json_decode((string) $body, true);
    $models = [];
    foreach (($json['models'] ?? []) as $m) {
        if (is_array($m) && !empty($m['name'])) {
            $models[] = (string) $m['name'];
        }
    }
    return ['ok' => true, 'models' => $models];
}
