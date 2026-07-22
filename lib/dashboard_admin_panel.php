<?php
declare(strict_types=1);

require_once __DIR__ . '/commerce_store.php';
require_once __DIR__ . '/robot_feed.php';
require_once __DIR__ . '/messages_store.php';
require_once __DIR__ . '/shop/auth.php';
require_once __DIR__ . '/shop_users_panel.php';

/**
 * @param list<array<string,mixed>> $products
 * @param list<array<string,mixed>> $leads
 * @param array<string,mixed>|null $admin
 */
function blu_render_dashboard_admin_panel(array $products, array $leads, ?array $admin = null): void
{
    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $orders = blu_orders_list();
    $invoices = blu_invoices_list();
    $deliveries = blu_deliveries_list();
    $shopUsers = blu_shop_users_for_admin();
    $messagesTotal = blu_messages_list(1, 1, '')['total'] ?? 0;
    $unreadMessages = blu_messages_unread_count();
    $robotFeed = blu_robot_get_feed(8);
    $suppliersFile = blu_data_dir() . DIRECTORY_SEPARATOR . 'gbg_suppliers.json';
    $suppliersCount = 0;
    if (is_file($suppliersFile)) {
        $supRows = json_decode((string) file_get_contents($suppliersFile), true);
        $suppliersCount = is_array($supRows) ? count($supRows) : 0;
    }

    $ordersTotal = 0.0;
    $orderStatus = ['comandat' => 0, 'procesare' => 0, 'expediere' => 0, 'livrat' => 0, 'anulat' => 0];
    foreach ($orders as $order) {
        $ordersTotal += (float) ($order['total_amount'] ?? 0);
        $st = (string) ($order['status'] ?? 'comandat');
        if (isset($orderStatus[$st])) {
            $orderStatus[$st]++;
        }
    }

    $invoicedTotal = 0.0;
    $unpaidInvoices = 0;
    foreach ($invoices as $inv) {
        $invoicedTotal += (float) ($inv['amount'] ?? 0);
        if ((string) ($inv['invoice_status'] ?? '') === 'neachitata') {
            $unpaidInvoices++;
        }
    }

    $adminName = trim((string) ($admin['name'] ?? 'Admin'));
    $hour = (int) date('G');
    $greeting = $hour < 12 ? 'Bună dimineața' : ($hour < 18 ? 'Bună ziua' : 'Bună seara');
    $today = date('d.m.Y');

    $kpis = [
        ['Produse active', count($products), 'fa-boxes-stacked', 'blue', '?page=products', ''],
        ['Comenzi', count($orders), 'fa-cart-shopping', 'green', '?page=orders', number_format($ordersTotal, 0, ',', '.') . ' RON'],
        ['Facturi', count($invoices), 'fa-file-invoice', 'violet', '?page=facturi', number_format($invoicedTotal, 0, ',', '.') . ' RON'],
        ['Cereri clienți', count($leads), 'fa-inbox', 'amber', '?page=leads', ''],
        ['Livrări AWB', count($deliveries), 'fa-truck-fast', 'teal', '?page=livrare', ''],
        ['Clienți magazin', count($shopUsers), 'fa-users', 'rose', '?page=shop-users', ''],
        ['Mesagerie', $messagesTotal, 'fa-comments', 'cyan', '?page=messages', $unreadMessages > 0 ? $unreadMessages . ' necitite' : ''],
        ['Furnizori', $suppliersCount, 'fa-truck-field', 'slate', '?page=furnizori', ''],
    ];

    $quickActions = [
        ['?page=products', 'fa-boxes-stacked', 'Produse', 'Catalog'],
        ['?page=orders', 'fa-cart-shopping', 'Comenzi', 'Toate comenzile'],
        ['?page=facturi', 'fa-file-invoice', 'Facturi', 'Emitere & trimitere'],
        ['?page=pieseauto', 'fa-robot', 'PieseAuto', 'Robot scanare'],
        ['?page=messages', 'fa-comments', 'Mesagerie', 'WhatsApp & email'],
        ['?page=website', 'fa-globe', 'Site', 'Constructor pagini'],
        ['../', 'fa-store', 'Magazin', 'Deschide site', true],
        ['?page=imported', 'fa-file-import', 'Import', 'Produse scanate'],
    ];

    $recentOrders = array_slice($orders, 0, 8);
    $recentLeads = array_slice(array_reverse($leads), 0, 6);
    ?>
    <div class="blu-dash-welcome reveal">
        <div class="blu-dash-welcome__text">
            <p class="blu-dash-welcome__eyebrow"><i class="fa-solid fa-gauge-high"></i> Dashboard · <?= $esc($today) ?></p>
            <h3><?= $esc($greeting) ?>, <?= $esc($adminName) ?>!</h3>
            <p>Rezumat complet al magazinului Blue-Car — comenzi, facturi, robot și cereri clienți.</p>
        </div>
        <div class="blu-dash-welcome__actions">
            <a class="btn btn-primary" href="?page=orders"><i class="fa-solid fa-cart-shopping me-1"></i> Comenzi</a>
            <a class="btn btn-outline-primary" href="../" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Magazin</a>
        </div>
    </div>

    <div class="blu-kpi-grid blu-kpi-grid--8">
        <?php foreach ($kpis as $kpi): ?>
            <a href="<?= $esc($kpi[4]) ?>" class="blu-kpi blu-kpi--<?= $esc($kpi[3]) ?> text-decoration-none">
                <div class="blu-kpi__top">
                    <div>
                        <div class="blu-kpi__value"><?= $esc((string) $kpi[1]) ?></div>
                        <p class="blu-kpi__label"><?= $esc($kpi[0]) ?></p>
                        <?php if (($kpi[5] ?? '') !== ''): ?>
                            <p class="blu-kpi__sub"><?= $esc((string) $kpi[5]) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="blu-kpi__icon"><i class="fa-solid <?= $esc($kpi[2]) ?>"></i></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8 col-lg-7">
            <div class="card blu-dash-card h-100">
                <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <span class="blu-card-icon"><i class="fa-solid fa-cart-shopping"></i></span>
                        <div>
                            <h4 class="mb-0">Comenzi recente</h4>
                            <p class="text-muted small mb-0">Ultimele comenzi din magazin</p>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="?page=orders">Vezi toate</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nr.</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($recentOrders === []): ?>
                                <tr><td colspan="5" class="text-muted">Nicio comandă încă.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                    $st = (string) ($order['status'] ?? 'comandat');
                                    $badge = match ($st) {
                                        'livrat' => 'bg-success',
                                        'anulat' => 'bg-danger',
                                        'expediere' => 'bg-info',
                                        'procesare' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                    ?>
                                    <tr>
                                        <td><strong><?= $esc((string) ($order['order_number'] ?? $order['id'] ?? '—')) ?></strong></td>
                                        <td>
                                            <?= $esc((string) ($order['client_name'] ?? '—')) ?>
                                            <?php if (!empty($order['phone'])): ?>
                                                <span class="d-block small text-muted"><?= $esc((string) $order['phone']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $badge ?>"><?= $esc($st) ?></span></td>
                                        <td><?= $esc(number_format((float) ($order['total_amount'] ?? 0), 2, ',', '.') . ' RON') ?></td>
                                        <td class="small text-muted"><?= $esc(substr((string) ($order['created_at'] ?? ''), 0, 16)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card blu-dash-card h-100">
                <div class="card-header card-no-border pb-0">
                    <div class="d-flex align-items-center gap-3">
                        <span class="blu-card-icon blu-card-icon--violet"><i class="fa-solid fa-bolt"></i></span>
                        <div><h4 class="mb-0">Acțiuni rapide</h4><p class="text-muted small mb-0">Acces direct la module</p></div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="blu-dash-actions">
                        <?php foreach ($quickActions as $act): ?>
                            <?php
                            $ext = !empty($act[4]) ? ' target="_blank" rel="noopener"' : '';
                            ?>
                            <a class="blu-dash-action" href="<?= $esc($act[0]) ?>"<?= $ext ?>>
                                <span class="blu-dash-action__icon"><i class="fa-solid <?= $esc($act[1]) ?>"></i></span>
                                <span class="blu-dash-action__text">
                                    <strong><?= $esc($act[2]) ?></strong>
                                    <em><?= $esc($act[3]) ?></em>
                                </span>
                                <i class="fa-solid fa-chevron-right blu-dash-action__arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card blu-dash-card h-100">
                <div class="card-header card-no-border pb-0 d-flex align-items-center gap-3">
                    <span class="blu-card-icon"><i class="fa-solid fa-chart-pie"></i></span>
                    <div><h4 class="mb-0">Status comenzi</h4></div>
                </div>
                <div class="card-body">
                    <?php foreach ($orderStatus as $label => $cnt): ?>
                        <?php if ($cnt === 0 && $label === 'anulat') continue; ?>
                        <div class="blu-dash-bar-row">
                            <span><?= $esc(ucfirst($label)) ?></span>
                            <div class="blu-dash-bar"><div class="blu-dash-bar__fill" style="width:<?= count($orders) > 0 ? min(100, round($cnt / max(1, count($orders)) * 100)) : 0 ?>%"></div></div>
                            <strong><?= (int) $cnt ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Valoare totală comenzi</span>
                        <strong><?= $esc(number_format($ordersTotal, 2, ',', '.') . ' RON') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small mt-2">
                        <span class="text-muted">Facturi neachitate</span>
                        <strong class="text-warning"><?= (int) $unpaidInvoices ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card blu-dash-card h-100">
                <div class="card-header card-no-border pb-0 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="blu-card-icon blu-card-icon--green"><i class="fa-solid fa-inbox"></i></span>
                        <div><h4 class="mb-0">Cereri recente</h4></div>
                    </div>
                    <a class="btn btn-sm btn-link" href="?page=leads">Toate</a>
                </div>
                <div class="card-body p-0">
                    <ul class="blu-dash-feed">
                        <?php if ($recentLeads === []): ?>
                            <li class="blu-dash-feed__empty">Nicio cerere recentă.</li>
                        <?php else: ?>
                            <?php foreach ($recentLeads as $lead): ?>
                                <li>
                                    <strong><?= $esc((string) ($lead['p'] ?? $lead['phone'] ?? '—')) ?></strong>
                                    <span><?= $esc(mb_strimwidth((string) ($lead['t'] ?? $lead['text'] ?? ''), 0, 60, '…')) ?></span>
                                    <em><?= $esc((string) ($lead['time'] ?? $lead['created_at'] ?? '')) ?></em>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card blu-dash-card h-100">
                <div class="card-header card-no-border pb-0 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="blu-card-icon blu-card-icon--amber"><i class="fa-solid fa-satellite-dish"></i></span>
                        <div><h4 class="mb-0">Robot & alerte</h4></div>
                    </div>
                    <a class="btn btn-sm btn-link" href="?page=robot-monitor">Monitor</a>
                </div>
                <div class="card-body p-0">
                    <ul class="blu-dash-feed">
                        <?php if ($robotFeed === []): ?>
                            <li class="blu-dash-feed__empty">Nicio activitate recentă.</li>
                        <?php else: ?>
                            <?php foreach ($robotFeed as $item): ?>
                                <li>
                                    <strong><?= $esc((string) ($item['type'] ?? $item['level'] ?? 'Robot')) ?></strong>
                                    <span><?= $esc(mb_strimwidth((string) ($item['message'] ?? $item['text'] ?? '—'), 0, 72, '…')) ?></span>
                                    <em><?= $esc((string) ($item['time'] ?? '')) ?></em>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}
