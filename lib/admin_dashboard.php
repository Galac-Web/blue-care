<?php
declare(strict_types=1);

require_once __DIR__ . '/commerce_store.php';
require_once __DIR__ . '/messages_store.php';
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/robot_feed.php';
require_once __DIR__ . '/catalog_import.php';
require_once __DIR__ . '/shop/auth.php';
require_once __DIR__ . '/gbg_suppliers.php';

function blu_dash_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function blu_dash_parse_price(mixed $raw): float
{
    $s = preg_replace('/[^\d.,]/', '', (string) $raw) ?? '';
    if ($s === '') {
        return 0.0;
    }
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    return max(0.0, (float) $s);
}

function blu_dash_format_money(float $value): string
{
    return number_format($value, 2, ',', '.') . ' lei';
}

/** @return array<string, mixed> */
function blu_admin_dashboard_collect(array $products, array $leads, array $adminUsers): array
{
    $orders = function_exists('blu_orders_list') ? blu_orders_list() : [];
    $invoices = function_exists('blu_invoices_list') ? blu_invoices_list() : [];
    $deliveries = function_exists('blu_deliveries_list') ? blu_deliveries_list() : [];
    $messages = blu_messages_load();
    $shopUsers = blu_shop_load_users();
    $suppliers = blu_read_json_file(blu_gbg_suppliers_json_file(), []);
    $robotStats = blu_read_json_file(blu_robot_stats_file(), []);
    $robotFeed = blu_read_json_file(blu_robot_feed_file(), []);
    $importStatus = blu_count_import_cards_by_status();
    $pricing = blu_pricing_settings();
    $rapidApi = function_exists('blu_rapidapi_key') && blu_rapidapi_key() !== '';

    $stockValue = 0.0;
    $inStock = 0;
    $outStock = 0;
    $byCategory = [];
    $byBrand = [];
    $lastProductUpdate = '';

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $price = blu_dash_parse_price($product['pret'] ?? $product['price'] ?? 0);
        $qty = max(0, (int) ($product['stoc'] ?? $product['stock'] ?? 1));
        $stockValue += $price * ($qty > 0 ? $qty : 1);
        if ($qty > 0) {
            $inStock++;
        } else {
            $outStock++;
        }

        $cat = trim((string) ($product['categorie'] ?? $product['category'] ?? 'Necategorizat'));
        if ($cat === '') {
            $cat = 'Necategorizat';
        }
        $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;

        $brand = trim((string) ($product['marca_masina'] ?? $product['brand'] ?? 'Altele'));
        if ($brand === '') {
            $brand = 'Altele';
        }
        $byBrand[$brand] = ($byBrand[$brand] ?? 0) + 1;

        $upd = (string) ($product['updated_at'] ?? '');
        if ($upd !== '' && ($lastProductUpdate === '' || strcmp($upd, $lastProductUpdate) > 0)) {
            $lastProductUpdate = $upd;
        }
    }

    arsort($byCategory);
    arsort($byBrand);

    $ordersRevenue = 0.0;
    $ordersByStatus = [];
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        $ordersRevenue += (float) ($order['total_amount'] ?? 0);
        $st = trim((string) ($order['status'] ?? 'comandat')) ?: 'comandat';
        $ordersByStatus[$st] = ($ordersByStatus[$st] ?? 0) + 1;
    }

    $unreadMessages = 0;
    $inboundMessages = 0;
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        if (($msg['direction'] ?? '') === 'inbound') {
            $inboundMessages++;
        }
        $read = (int) ($msg['is_read'] ?? 0);
        $status = (string) ($msg['message_status'] ?? '');
        if ($read === 0 || $status === 'new') {
            $unreadMessages++;
        }
    }

    $shopActive = 0;
    $shopBlocked = 0;
    foreach ($shopUsers as $user) {
        if (!is_array($user)) {
            continue;
        }
        if (blu_shop_user_is_blocked($user)) {
            $shopBlocked++;
        } else {
            $shopActive++;
        }
    }

    $activityDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $key = date('Y-m-d', strtotime('-' . $i . ' days'));
        $activityDays[$key] = 0;
    }
    $bumpDay = static function (string $ts) use (&$activityDays): void {
        if ($ts === '') {
            return;
        }
        $day = substr($ts, 0, 10);
        if (isset($activityDays[$day])) {
            $activityDays[$day]++;
        }
    };
    foreach ($leads as $lead) {
        if (is_array($lead)) {
            $bumpDay((string) ($lead['time'] ?? $lead['created_at'] ?? ''));
        }
    }
    foreach ($messages as $msg) {
        if (is_array($msg)) {
            $bumpDay((string) ($msg['created_at'] ?? ''));
        }
    }
    foreach ($orders as $order) {
        if (is_array($order)) {
            $bumpDay((string) ($order['created_at'] ?? ''));
        }
    }
    if (is_array($robotFeed)) {
        foreach (array_slice(array_reverse($robotFeed), 0, 30) as $evt) {
            if (is_array($evt)) {
                $bumpDay((string) ($evt['time'] ?? ''));
            }
        }
    }

    $recentOrders = array_slice($orders, 0, 6);
    $recentLeads = array_slice(array_reverse($leads), 0, 6);
    $recentMessages = array_slice($messages, 0, 6);
    usort($recentMessages, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    $recentMessages = array_slice($recentMessages, 0, 6);

    $recentRobot = [];
    if (is_array($robotFeed)) {
        $recentRobot = array_slice(array_reverse($robotFeed), 0, 8);
    }

    return [
        'products_count' => count($products),
        'stock_value' => $stockValue,
        'in_stock' => $inStock,
        'out_stock' => $outStock,
        'orders_count' => count($orders),
        'orders_revenue' => $ordersRevenue,
        'orders_by_status' => $ordersByStatus,
        'invoices_count' => count($invoices),
        'deliveries_count' => count($deliveries),
        'leads_count' => count($leads),
        'messages_count' => count($messages),
        'unread_messages' => $unreadMessages,
        'inbound_messages' => $inboundMessages,
        'admin_users_count' => count($adminUsers),
        'shop_users_count' => count($shopUsers),
        'shop_active' => $shopActive,
        'shop_blocked' => $shopBlocked,
        'suppliers_count' => is_array($suppliers) ? count($suppliers) : 0,
        'import_status' => $importStatus,
        'import_pending' => (int) ($importStatus['pending'] ?? 0),
        'import_total' => array_sum(array_map('intval', $importStatus)),
        'robot_stats' => is_array($robotStats) ? $robotStats : [],
        'robot_no_oem' => blu_robot_count_no_oem(),
        'pricing' => $pricing,
        'rapidapi_ok' => $rapidApi,
        'by_category' => array_slice($byCategory, 0, 8, true),
        'by_brand' => array_slice($byBrand, 0, 6, true),
        'activity_days' => $activityDays,
        'last_product_update' => $lastProductUpdate,
        'recent_orders' => $recentOrders,
        'recent_leads' => $recentLeads,
        'recent_messages' => $recentMessages,
        'recent_robot' => $recentRobot,
    ];
}

/** @param array<string, mixed> $dash */
function blu_render_admin_dashboard(array $dash, string $assetBase, ?array $admin = null): void
{
    $adminName = trim((string) ($admin['name'] ?? $admin['email'] ?? 'Administrator'));
    $hour = (int) date('G');
    $greeting = $hour < 12 ? 'Bună dimineața' : ($hour < 18 ? 'Bună ziua' : 'Bună seara');

    $kpis = [
        ['Produse active', (string) $dash['products_count'], 'fa-boxes-stacked', 'blue', '?page=products', blu_dash_format_money((float) $dash['stock_value']) . ' stoc'],
        ['Comenzi', (string) $dash['orders_count'], 'fa-cart-shopping', 'green', '?page=orders', blu_dash_format_money((float) $dash['orders_revenue']) . ' total'],
        ['Cereri clienți', (string) $dash['leads_count'], 'fa-inbox', 'violet', '?page=leads', 'Contact magazin'],
        ['Mesagerie', (string) $dash['messages_count'], 'fa-comments', 'cyan', '?page=messages', (int) $dash['unread_messages'] . ' necitite'],
        ['Clienți magazin', (string) $dash['shop_users_count'], 'fa-users', 'teal', '?page=shop-users', (int) $dash['shop_active'] . ' activi'],
        ['Furnizori', (string) $dash['suppliers_count'], 'fa-truck-field', 'amber', '?page=furnizori', 'Conturi scanare'],
        ['Cartele import', (string) $dash['import_total'], 'fa-file-import', 'rose', '?page=imported', (int) $dash['import_pending'] . ' în așteptare'],
        ['Utilizatori admin', (string) $dash['admin_users_count'], 'fa-user-shield', 'slate', '?page=users', 'Acces panou'],
    ];

    $mini = [
        ['Facturi', (string) $dash['invoices_count'], '?page=facturi', 'fa-file-invoice'],
        ['Livrări AWB', (string) $dash['deliveries_count'], '?page=livrare', 'fa-truck-fast'],
        ['În stoc', (string) $dash['in_stock'], '?page=products', 'fa-circle-check'],
        ['Fără stoc', (string) $dash['out_stock'], '?page=products', 'fa-circle-xmark'],
        ['Robot scanate', (string) ((int) ($dash['robot_stats']['found'] ?? 0)), '?page=robot-monitor', 'fa-robot'],
        ['Fără OEM', (string) $dash['robot_no_oem'], '?page=robot-monitor', 'fa-triangle-exclamation'],
        ['Adaos', number_format((float) ($dash['pricing']['adaos_pct'] ?? 30), 1, ',', '.') . '%', '?page=furnizori&tab=adaos', 'fa-percent'],
        ['RapidAPI', !empty($dash['rapidapi_ok']) ? 'Activ' : 'Lipsă', '?page=tools', 'fa-plug'],
    ];

    $chartCategories = [
        'labels' => array_keys($dash['by_category']),
        'values' => array_values($dash['by_category']),
    ];
    $chartActivity = [
        'labels' => array_map(static fn(string $d): string => date('d.m', strtotime($d)), array_keys($dash['activity_days'])),
        'values' => array_values($dash['activity_days']),
    ];
    $chartBrands = [
        'labels' => array_keys($dash['by_brand']),
        'values' => array_values($dash['by_brand']),
    ];

    $robotLast = (string) ($dash['robot_stats']['last_at'] ?? '—');
    $lastProd = (string) ($dash['last_product_update'] ?: '—');

    ?>
    <div class="blu-dash">
        <section class="blu-dash-welcome card border-0">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="blu-dash-welcome__eyebrow mb-1"><i class="fa-solid fa-gauge-high me-1"></i> Panou operațional</p>
                    <h3 class="blu-dash-welcome__title mb-1"><?= blu_dash_h($greeting) ?>, <?= blu_dash_h($adminName) ?></h3>
                    <p class="text-muted mb-0 small">Vizualizare în timp real — catalog, comenzi, mesaje, robot și import.</p>
                </div>
                <div class="blu-dash-welcome__meta text-md-end">
                    <span class="blu-dash-pill blu-dash-pill--ok"><i class="fa-solid fa-circle"></i> Magazin online</span>
                    <span class="blu-dash-pill <?= !empty($dash['rapidapi_ok']) ? 'blu-dash-pill--ok' : 'blu-dash-pill--warn' ?>">
                        <i class="fa-solid fa-circle"></i> RapidAPI <?= !empty($dash['rapidapi_ok']) ? 'OK' : 'neconfigurat' ?>
                    </span>
                    <div class="text-muted small mt-2"><?= blu_dash_h(date('d.m.Y H:i')) ?></div>
                </div>
            </div>
        </section>

        <div class="blu-kpi-grid blu-kpi-grid--8">
            <?php foreach ($kpis as $kpi): ?>
                <a href="<?= blu_dash_h($kpi[4]) ?>" class="blu-kpi blu-kpi--<?= blu_dash_h($kpi[3]) ?> text-decoration-none">
                    <div class="blu-kpi__top">
                        <div>
                            <div class="blu-kpi__value"><?= blu_dash_h($kpi[1]) ?></div>
                            <p class="blu-kpi__label"><?= blu_dash_h($kpi[0]) ?></p>
                            <p class="blu-kpi__sub"><?= blu_dash_h($kpi[5]) ?></p>
                        </div>
                        <div class="blu-kpi__icon"><i class="fa-solid <?= blu_dash_h($kpi[2]) ?>"></i></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="blu-dash-mini-grid">
            <?php foreach ($mini as $item): ?>
                <a href="<?= blu_dash_h($item[2]) ?>" class="blu-dash-mini">
                    <i class="fa-solid <?= blu_dash_h($item[3]) ?>"></i>
                    <span class="blu-dash-mini__val"><?= blu_dash_h($item[1]) ?></span>
                    <span class="blu-dash-mini__lbl"><?= blu_dash_h($item[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border pb-0">
                        <h5 class="mb-0"><i class="fa-solid fa-chart-column me-2 text-primary"></i>Produse pe categorie</h5>
                    </div>
                    <div class="card-body">
                        <div class="blu-dash-chart-wrap">
                            <canvas id="bluDashChartCategories" aria-label="Produse pe categorie"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border pb-0">
                        <h5 class="mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Activitate 7 zile</h5>
                    </div>
                    <div class="card-body">
                        <div class="blu-dash-chart-wrap">
                            <canvas id="bluDashChartActivity" aria-label="Activitate"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border pb-0">
                        <h5 class="mb-0"><i class="fa-solid fa-car me-2 text-primary"></i>Top mărci</h5>
                    </div>
                    <div class="card-body">
                        <div class="blu-dash-chart-wrap">
                            <canvas id="bluDashChartBrands" aria-label="Mărci"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa-solid fa-cart-shopping me-2"></i>Ultimele comenzi</h5>
                        <a href="?page=orders" class="small">Vezi toate</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 blu-dash-table">
                                <thead><tr><th>Nr.</th><th>Client</th><th>Total</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if ($dash['recent_orders'] === []): ?>
                                    <tr><td colspan="4" class="text-muted p-3">Nu există comenzi încă.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($dash['recent_orders'] as $order): ?>
                                    <tr>
                                        <td><a href="?page=orders"><?= blu_dash_h((string) ($order['order_number'] ?? $order['id'] ?? '—')) ?></a></td>
                                        <td><?= blu_dash_h((string) ($order['client_name'] ?? '—')) ?></td>
                                        <td><?= blu_dash_h(blu_dash_format_money((float) ($order['total_amount'] ?? 0))) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= blu_dash_h((string) ($order['status'] ?? '—')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa-solid fa-inbox me-2"></i>Ultimele cereri</h5>
                        <a href="?page=leads" class="small">Vezi toate</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 blu-dash-table">
                                <thead><tr><th>Telefon</th><th>Sursă</th><th>Ora</th></tr></thead>
                                <tbody>
                                <?php if ($dash['recent_leads'] === []): ?>
                                    <tr><td colspan="3" class="text-muted p-3">Nu sunt cereri încă.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($dash['recent_leads'] as $lead): ?>
                                    <tr>
                                        <td><?= blu_dash_h((string) ($lead['p'] ?? $lead['phone'] ?? '—')) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= blu_dash_h((string) ($lead['s'] ?? $lead['source'] ?? '—')) ?></span></td>
                                        <td class="text-muted small"><?= blu_dash_h((string) ($lead['time'] ?? $lead['created_at'] ?? '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa-solid fa-comments me-2"></i>Mesagerie recentă</h5>
                        <a href="?page=messages" class="small">Deschide</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 blu-dash-table">
                                <thead><tr><th>Contact</th><th>Canal</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if ($dash['recent_messages'] === []): ?>
                                    <tr><td colspan="3" class="text-muted p-3">Nu sunt mesaje.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($dash['recent_messages'] as $msg): ?>
                                    <tr>
                                        <td><?= blu_dash_h((string) ($msg['name'] ?? $msg['phone'] ?? '—')) ?></td>
                                        <td><?= blu_dash_h(blu_message_channel_label((string) ($msg['channel'] ?? 'manual'))) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= blu_dash_h((string) ($msg['message_status'] ?? '—')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card blu-dash-card h-100">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa-solid fa-robot me-2"></i>Jurnal robot (recent)</h5>
                        <a href="?page=robot-monitor" class="small">Monitor complet</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 blu-dash-table">
                                <thead><tr><th>Ora</th><th>Marcă / model</th><th>OEM</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if ($dash['recent_robot'] === []): ?>
                                    <tr><td colspan="4" class="text-muted p-3">Robotul nu are evenimente recente.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($dash['recent_robot'] as $evt): ?>
                                    <tr>
                                        <td class="text-muted small"><?= blu_dash_h((string) ($evt['time'] ?? '—')) ?></td>
                                        <td><?= blu_dash_h(trim((string) ($evt['brand'] ?? '') . ' ' . (string) ($evt['model'] ?? ''))) ?></td>
                                        <td><code><?= blu_dash_h((string) ($evt['cod_oem'] ?? $evt['coduri_oem'] ?? '—')) ?></code></td>
                                        <td><span class="badge bg-light text-dark"><?= blu_dash_h((string) ($evt['status'] ?? '—')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card blu-dash-card mb-3">
                    <div class="card-header card-no-border"><h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Acțiuni rapide</h5></div>
                    <div class="card-body blu-dash-actions">
                        <a href="?page=products" class="blu-dash-action"><i class="fa-solid fa-boxes-stacked"></i> Lista produse</a>
                        <a href="?page=imported" class="blu-dash-action"><i class="fa-solid fa-file-import"></i> Import scanate</a>
                        <a href="?page=furnizori&tab=robot" class="blu-dash-action"><i class="fa-solid fa-gears"></i> Logică robot</a>
                        <a href="?page=messages" class="blu-dash-action"><i class="fa-solid fa-paper-plane"></i> Mesagerie</a>
                        <a href="?page=orders" class="blu-dash-action"><i class="fa-solid fa-receipt"></i> Comenzi noi</a>
                        <a href="../" target="_blank" rel="noopener" class="blu-dash-action"><i class="fa-solid fa-store"></i> Vezi magazin</a>
                    </div>
                </div>
                <div class="card blu-dash-card blu-dash-health">
                    <div class="card-header card-no-border"><h5 class="mb-0"><i class="fa-solid fa-heart-pulse me-2"></i>Stare sistem</h5></div>
                    <div class="card-body">
                        <ul class="blu-dash-health__list mb-0">
                            <li><span>Ultima actualizare produs</span><strong><?= blu_dash_h($lastProd) ?></strong></li>
                            <li><span>Ultima rulare robot</span><strong><?= blu_dash_h($robotLast) ?></strong></li>
                            <li><span>TVA configurat</span><strong><?= blu_dash_h(number_format((float) ($dash['pricing']['tva_pct'] ?? 19), 1, ',', '.')) ?>%</strong></li>
                            <li><span>Cartele pending</span><strong><?= (int) $dash['import_pending'] ?></strong></li>
                            <li><span>Mesaje inbound</span><strong><?= (int) $dash['inbound_messages'] ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= blu_dash_h(rtrim($assetBase, '/') . '/js/chart/chartjs/chart.min.js') ?>"></script>
    <script>
    (function () {
        if (typeof Chart === 'undefined') return;
        const cats = <?= json_encode($chartCategories, JSON_UNESCAPED_UNICODE) ?>;
        const activity = <?= json_encode($chartActivity, JSON_UNESCAPED_UNICODE) ?>;
        const brands = <?= json_encode($chartBrands, JSON_UNESCAPED_UNICODE) ?>;
        const palette = ['#2563eb', '#0891b2', '#7c3aed', '#059669', '#d97706', '#f43f5e', '#64748b', '#0ea5e9'];
        const baseOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

        const elCat = document.getElementById('bluDashChartCategories');
        if (elCat && cats.labels.length) {
            new Chart(elCat, {
                type: 'bar',
                data: {
                    labels: cats.labels,
                    datasets: [{ data: cats.values, backgroundColor: palette, borderRadius: 8, maxBarThickness: 36 }]
                },
                options: Object.assign({}, baseOpts, { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } })
            });
        } else if (elCat) {
            elCat.parentElement.innerHTML = '<p class="text-muted small mb-0">Nu există date de categorii.</p>';
        }

        const elAct = document.getElementById('bluDashChartActivity');
        if (elAct) {
            new Chart(elAct, {
                type: 'line',
                data: {
                    labels: activity.labels,
                    datasets: [{
                        data: activity.values,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb'
                    }]
                },
                options: Object.assign({}, baseOpts, { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } })
            });
        }

        const elBrand = document.getElementById('bluDashChartBrands');
        if (elBrand && brands.labels.length) {
            new Chart(elBrand, {
                type: 'doughnut',
                data: {
                    labels: brands.labels,
                    datasets: [{ data: brands.values, backgroundColor: palette, borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
            });
        } else if (elBrand) {
            elBrand.parentElement.innerHTML = '<p class="text-muted small mb-0">Nu există date pe mărci.</p>';
        }
    })();
    </script>
    <?php
}
