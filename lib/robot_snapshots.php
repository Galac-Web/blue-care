<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

function blu_robot_snapshots_dir(string $contId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $contId) ?: 'default';
    $dir = blu_data_dir() . DIRECTORY_SEPARATOR . 'robot_snapshots' . DIRECTORY_SEPARATOR . $safe;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** @return list<array<string, mixed>> */
function blu_list_robot_snapshots(string $contId, int $limit = 50): array
{
    $dir = blu_robot_snapshots_dir($contId);
    $items = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.meta.json') ?: [] as $metaFile) {
        $data = blu_read_json_file($metaFile, null);
        if (!is_array($data)) {
            continue;
        }
        $base = basename($metaFile, '.meta.json');
        $htmlFile = $dir . DIRECTORY_SEPARATOR . $base . '.html';
        $data['id'] = $base;
        $data['has_html'] = is_file($htmlFile);
        $data['html_size'] = $data['has_html'] ? (int)filesize($htmlFile) : 0;
        $items[] = $data;
    }
    usort($items, static fn ($a, $b) => strcmp((string)($b['saved_at'] ?? ''), (string)($a['saved_at'] ?? '')));
    return array_slice($items, 0, max(1, $limit));
}

function blu_robot_snapshot_html_path(string $contId, string $snapshotId): ?string
{
    $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '', $snapshotId);
    if ($safe === '') {
        return null;
    }
    $path = blu_robot_snapshots_dir($contId) . DIRECTORY_SEPARATOR . $safe . '.html';
    return is_file($path) ? $path : null;
}

/** @return list<array<string, string>> */
function blu_default_gbg_scan_logic_blocks(): array
{
    return [
        [
            'title' => 'Faza A — Pregătire (workflow configurat)',
            'html' => '<ol>
                <li><strong>Login</strong> — deschide HTML login, verifică <code>#textUsername</code>, salvează snapshot.</li>
                <li><strong>Banere</strong> — închide HTTP Chrome / popup conform listei.</li>
                <li><strong>Autentificare</strong> — user, parolă, click login, pauză.</li>
            </ol>',
        ],
        [
            'title' => 'Faza B — Catalog GBG (builtin gbg_catalog_scan)',
            'html' => '<ol>
                <li>Deschide <strong>gbg_wapp.aspx</strong> — pagina cu marci (<code>a[onclick*=BrandClick]</code>).</li>
                <li>Pentru fiecare <strong>marcă</strong>: click → așteaptă → deschide headere modele (<code>butModelHeader</code>).</li>
                <li>Pentru fiecare <strong>model</strong>: click → listează submodele (<code>.model-panel.show-content a.linkInBlack</code>).</li>
                <li>Pentru fiecare <strong>submodel</strong>: click → colectează linkuri piese (<code>.GenericStyle_Link</code>).</li>
            </ol>',
        ],
        [
            'title' => 'Faza C — Pagină produs (HTML de analizat)',
            'html' => '<ul>
                <li><strong>Cod articol</strong> — <code>#ctl00_centerPlaceHolder_labelItemID</code></li>
                <li><strong>Coduri OEM</strong> — XPath lângă «Γνήσιοι Κωδικοί»</li>
                <li>Dacă lipsește OEM → produs în tab «Fără OEM»</li>
                <li>Trimite la server → Autodoc/TecDoc → import în BD</li>
            </ul>',
        ],
        [
            'title' => 'Faza D — Anti-duplicare & interval',
            'html' => '<p>Respectă <em>scan_from</em> / <em>scan_to</em> și fișierul <code>scanate_{cont_id}.json</code>. La fiecare produs procesat, URL + cod se marchează scanat.</p>',
        ],
    ];
}
