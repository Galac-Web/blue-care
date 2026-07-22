<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

/**
 * Workflow robot per furnizor (pași Selenium configurabili).
 * Stocare: data/robot_workflows/{cont_id}.json
 */

function blu_robot_workflows_dir(): string
{
    $dir = blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_workflows';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function blu_robot_workflow_file(string $contId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $contId) ?: 'default';
    return blu_robot_workflows_dir() . DIRECTORY_SEPARATOR . $safe . '.json';
}

/** @return array<string, mixed> */
function blu_default_gbg_workflow(): array
{
    return [
        'version' => 1,
        'robot_mode' => 'workflow',
        'site_name' => 'GBG e-shop',
        'login_url' => 'http://www.gbg-eshop.gr/demo/Authenticate3.aspx',
        'catalog_url' => 'http://www.gbg-eshop.gr/demo/gbg_wapp.aspx',
        'html_pages' => [
            [
                'key' => 'login',
                'name' => 'Pagina LOGIN (HTML)',
                'url' => '{{login_url}}',
                'what_scans' => 'Formular autentificare: câmp user (#textUsername), parolă (#textPassword), buton login.',
                'verify_selectors' => ['#textUsername', '#textPassword', '#ctl00_centerPlaceHolder_ButtonLogin'],
                'save_html' => true,
            ],
            [
                'key' => 'catalog',
                'name' => 'Pagina CATALOG marci (HTML)',
                'url' => '{{catalog_url}}',
                'what_scans' => 'Lista mărci (BrandClick), headere modele, linkuri submodele și piese GenericStyle_Link.',
                'verify_selectors' => ["a[onclick*='BrandClick']", "//div[contains(@id, 'butModelHeader')]"],
                'save_html' => true,
            ],
            [
                'key' => 'produs',
                'name' => 'Pagina PRODUS (HTML)',
                'url' => '(dinamic — fiecare link .GenericStyle_Link)',
                'what_scans' => 'Cod articol #ctl00_centerPlaceHolder_labelItemID, coduri OEM (Γνήσιοι Κωδικοί), apoi trimitere server TecDoc.',
                'verify_selectors' => ['#ctl00_centerPlaceHolder_labelItemID'],
                'save_html' => true,
            ],
        ],
        'banners' => [
            [
                'name' => 'Avertisment HTTP Chrome (Continue / Proceed)',
                'selectors' => [
                    "//button[contains(., 'Перейти')]",
                    "//button[contains(., 'Continue')]",
                    "//button[contains(., 'Proceed')]",
                    "//button[contains(., 'Advanced')]",
                    "//a[contains(., 'Proceed')]",
                    "//*[@id='proceed-button']",
                    "//*[@id='proceed-link']",
                    "//*[@id='primary-button']",
                ],
            ],
            [
                'name' => 'Popup mesaje GBG (Μήνυμα / DIVGreetingsMsg)',
                'selectors' => [
                    '#btnMsgClose',
                    'button#btnMsgClose',
                    '.GrMsgFooter button',
                    "//button[@id='btnMsgClose']",
                    "//button[contains(., 'OK')]",
                ],
            ],
        ],
        'steps' => [
            [
                'order' => 1,
                'type' => 'navigate',
                'label' => 'Deschid pagina de login GBG',
                'url' => '{{login_url}}',
                'html_purpose' => 'Pagina HTML login — robotul verifică formularul înainte de completare.',
                'save_html' => true,
                'what_scans' => 'Formular login, titlu pagină, prezență câmpuri user/parolă.',
                'verify_selectors' => ['#textUsername', '#textPassword'],
                'wait_after' => 6,
            ],
            [
                'order' => 2,
                'type' => 'analyze_html',
                'label' => 'Analizez HTML login (snapshot + verificare)',
                'page_key' => 'login',
                'save_html' => true,
                'wait_after' => 2,
            ],
            [
                'order' => 3,
                'type' => 'close_banners',
                'label' => 'Închid banere / avertismente HTTP',
                'use_global_banners' => true,
                'wait_after' => 2,
            ],
            [
                'order' => 4,
                'type' => 'wait',
                'label' => 'Aștept încărcarea completă a formularului',
                'seconds' => 3,
            ],
            [
                'order' => 5,
                'type' => 'fill',
                'label' => 'Completez utilizatorul',
                'selector' => '#textUsername',
                'selector_type' => 'css',
                'value' => '{{user}}',
                'wait_after' => 1.5,
            ],
            [
                'order' => 6,
                'type' => 'fill',
                'label' => 'Completez parola',
                'selector' => '#textPassword',
                'selector_type' => 'css',
                'value' => '{{pass}}',
                'wait_after' => 1.5,
            ],
            [
                'order' => 7,
                'type' => 'wait',
                'label' => 'Pauză scurtă înainte de Login',
                'seconds' => 2,
            ],
            [
                'order' => 8,
                'type' => 'click',
                'label' => 'Apăs butonul Login',
                'selector' => '#ctl00_centerPlaceHolder_ButtonLogin',
                'selector_type' => 'css',
                'wait_after' => 4,
            ],
            [
                'order' => 9,
                'type' => 'wait',
                'label' => 'Aștept răspunsul după login (browser lent)',
                'seconds' => 25,
            ],
            [
                'order' => 10,
                'type' => 'close_banners',
                'label' => 'Închid banere după login',
                'use_global_banners' => true,
                'wait_after' => 2,
            ],
            [
                'order' => 11,
                'type' => 'analyze_html',
                'label' => 'Deschid + analizez HTML catalog înainte de scanare',
                'url' => '{{catalog_url}}',
                'page_key' => 'catalog',
                'save_html' => true,
                'what_scans' => 'Număr linkuri marci, headere modele — confirmă că catalogul s-a încărcat.',
                'verify_selectors' => ["a[onclick*='BrandClick']"],
                'wait_after' => 8,
            ],
            [
                'order' => 12,
                'type' => 'builtin',
                'label' => 'Scanare catalog (marci → modele → piese → server)',
                'action' => 'gbg_catalog_scan',
                'html_purpose' => 'Parcurge toate mărcile/modelele; la fiecare piesă deschide HTML produs, extrage cod OEM, trimite la server.',
            ],
        ],
        'updated_at' => null,
    ];
}

/** @return array<string, mixed> */
function blu_load_robot_workflow(string $contId): array
{
    $contId = trim($contId);
    if ($contId === '') {
        return blu_default_gbg_workflow();
    }
    $file = blu_robot_workflow_file($contId);
    $data = blu_read_json_file($file, null);
    if (!is_array($data) || empty($data['steps'])) {
        $def = blu_default_gbg_workflow();
        $def['cont_id'] = $contId;
        return $def;
    }
    $data['cont_id'] = $contId;
    return blu_normalize_robot_workflow($data);
}

/** @param array<string, mixed> $data */
function blu_normalize_robot_workflow(array $data): array
{
    $steps = [];
    foreach ($data['steps'] ?? [] as $i => $step) {
        if (!is_array($step)) {
            continue;
        }
        $step['order'] = (int)($step['order'] ?? ($i + 1));
        $step['type'] = trim((string)($step['type'] ?? 'wait'));
        $step['label'] = trim((string)($step['label'] ?? ('Pas ' . $step['order'])));
        $steps[] = $step;
    }
    usort($steps, static fn ($a, $b) => ($a['order'] <=> $b['order']));
    $data['steps'] = array_values($steps);

    $banners = [];
    foreach ($data['banners'] ?? [] as $b) {
        if (!is_array($b)) {
            continue;
        }
        $name = trim((string)($b['name'] ?? 'Banner'));
        $sels = [];
        foreach ($b['selectors'] ?? [] as $s) {
            $s = trim((string)$s);
            if ($s !== '') {
                $sels[] = $s;
            }
        }
        if ($sels) {
            $banners[] = ['name' => $name, 'selectors' => $sels];
        }
    }
    $data['banners'] = $banners;

    $htmlPages = [];
    foreach ($data['html_pages'] ?? [] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $sels = [];
        foreach ($p['verify_selectors'] ?? [] as $s) {
            $s = trim((string)$s);
            if ($s !== '') {
                $sels[] = $s;
            }
        }
        $htmlPages[] = [
            'key' => trim((string)($p['key'] ?? '')),
            'name' => trim((string)($p['name'] ?? 'Pagină HTML')),
            'url' => trim((string)($p['url'] ?? '')),
            'what_scans' => trim((string)($p['what_scans'] ?? '')),
            'verify_selectors' => $sels,
            'save_html' => !empty($p['save_html']),
        ];
    }
    $data['html_pages'] = $htmlPages;

    $data['version'] = (int)($data['version'] ?? 1);
    $data['robot_mode'] = (string)($data['robot_mode'] ?? 'workflow');

    return $data;
}

/** @param array<string, mixed> $workflow */
function blu_save_robot_workflow(string $contId, array $workflow): array
{
    $contId = trim($contId);
    if ($contId === '') {
        return ['success' => false, 'message' => 'Lipsește cont_id furnizor.'];
    }
    $workflow = blu_normalize_robot_workflow($workflow);
    $workflow['cont_id'] = $contId;
    $workflow['updated_at'] = date('Y-m-d H:i:s');

    if (!blu_write_json_file(blu_robot_workflow_file($contId), $workflow)) {
        return ['success' => false, 'message' => 'Nu s-a putut salva workflow-ul.'];
    }

    return ['success' => true, 'message' => 'Pașii robot salvați.', 'workflow' => $workflow];
}

/** Înlocuiește {{user}}, {{login_url}} etc. pentru preview (nu pentru secrete în log). */
function blu_workflow_substitute_preview(string $text, array $vars): string
{
    foreach ($vars as $k => $v) {
        $text = str_replace('{{' . $k . '}}', (string)$v, $text);
    }
    return $text;
}
