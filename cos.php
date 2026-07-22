<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';
require_once __DIR__ . '/lib/shop/cart.php';
require_once __DIR__ . '/lib/shop/leads.php';
require_once __DIR__ . '/lib/commerce_store.php';

$message = '';
$messageType = '';
$shopUser = blu_shop_current_user();
$checkoutDefaults = [
    'name' => trim((string) ($shopUser['name'] ?? '')),
    'email' => trim((string) ($shopUser['email'] ?? '')),
    'phone' => '',
    'address' => '',
    'note' => '',
];

if (isset($_GET['added'])) {
    $message = 'Produsul a fost adăugat în coș.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'update') {
        blu_cart_update($id, (int) ($_POST['qty'] ?? 1));
        header('Location: cos.php');
        exit;
    }
    if ($action === 'remove') {
        blu_cart_remove($id);
        header('Location: cos.php');
        exit;
    }
    if ($action === 'checkout') {
        $lines = blu_cart_lines();
        if (!$lines) {
            $message = 'Coșul este gol.';
            $messageType = 'error';
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? $checkoutDefaults['email']));
            $address = trim((string) ($_POST['address'] ?? ''));
            $note = trim((string) ($_POST['note'] ?? ''));
            $checkoutDefaults = compact('name', 'phone', 'email', 'address', 'note');
            if ($name === '' || $phone === '') {
                $message = 'Completează numele și telefonul.';
                $messageType = 'error';
            } else {
                $itemsText = [];
                foreach ($lines as $line) {
                    $p = $line['product'];
                    $itemsText[] = sprintf(
                        '%s x%d — %s (OEM %s)',
                        trim((string) ($p['nume'] ?? '')),
                        (int) $line['qty'],
                        blu_shop_price_label($p),
                        trim((string) ($p['cod_oem'] ?? '-'))
                    );
                }
                $customer = [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'note' => $note,
                    'payment_method' => 'ramburs',
                    'shop_user_id' => (int) ($shopUser['id'] ?? 0),
                ];
                $order = blu_order_create_from_checkout($customer, $lines, blu_cart_total());
                blu_shop_save_lead([
                    'source' => 'shop_checkout',
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'note' => $note,
                    'total' => blu_cart_format_money(blu_cart_total()),
                    'items' => $itemsText,
                    'order_id' => (string) ($order['id'] ?? ''),
                    'order_number' => (string) ($order['order_number'] ?? ''),
                    'p' => $phone,
                    's' => 'comanda',
                    't' => implode("\n", $itemsText) . "\nTotal: " . blu_cart_format_money(blu_cart_total()),
                ]);
                if ($order !== null) {
                    blu_cart_clear();
                    $orderNo = rawurlencode((string) ($order['order_number'] ?? ''));
                    header('Location: cos.php?success=1' . ($orderNo !== '' ? '&order=' . $orderNo : ''));
                    exit;
                }
                $message = 'Nu am putut înregistra comanda. Încearcă din nou sau contactează-ne telefonic.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['success'])) {
    $orderNo = trim((string) ($_GET['order'] ?? ''));
    $message = $orderNo !== ''
        ? ('Comanda ' . $orderNo . ' a fost înregistrată! Te contactăm în curând pentru confirmare și detalii plată.')
        : 'Comanda a fost trimisă! Te contactăm în curând pentru confirmare și detalii plată.';
    $messageType = 'success';
}

$lines = blu_cart_lines();
$total = blu_cart_total();
$itemCount = blu_cart_count();

$shopPageTitle = 'Coș de cumpărături — Blue-Car';
$shopPageDescription = 'Verifică produsele din coș, actualizează cantitățile și finalizează comanda Blue-Car.';
$shopBodyClass = 'shop-page shop-page-cart shop-ui-v10';

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--woo">
    <div class="shop-container">
        <nav class="shop-breadcrumb reveal" aria-label="Breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Acasă</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span>Coș</span>
        </nav>

        <?php
        $wooHeroEyebrow = 'Checkout simplu · confirmare telefonică';
        $wooHeroTitle = 'Coșul tău Blue-Car';
        $wooHeroLead = $lines
            ? 'Ai ' . $itemCount . ' ' . ($itemCount === 1 ? 'produs' : 'produse') . ' în coș. Verifică detaliile și trimite comanda — te sunăm pentru confirmare.'
            : 'Coșul este gol. Explorează catalogul și adaugă piese compatibile cu vehiculul tău.';
        $wooHeroIcon = 'fa-solid fa-cart-shopping';
        require __DIR__ . '/shop/includes/woo-page-hero.php';
        ?>

        <nav class="shop-woo-checkout-steps shop-pill-tablist shop-woo-section reveal" aria-label="Pași comandă">
            <span class="shop-woo-step shop-pill-tab is-active"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Coș</span>
            <span class="shop-woo-step-line" aria-hidden="true"></span>
            <span class="shop-woo-step shop-pill-tab<?= $lines ? ' is-active' : '' ?>"><i class="fa-solid fa-user" aria-hidden="true"></i> Date livrare</span>
            <span class="shop-woo-step-line" aria-hidden="true"></span>
            <span class="shop-woo-step shop-pill-tab"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Confirmare</span>
        </nav>

        <?php require __DIR__ . '/shop/includes/woo-trust-strip.php'; ?>

        <?php if ($message !== ''): ?>
            <div class="shop-alert shop-alert-<?= $messageType === 'success' ? 'success' : 'error' ?> shop-woo-section reveal"><?= blu_shop_h($message) ?></div>
        <?php endif; ?>

        <?php if ($lines): ?>
        <div class="shop-woo-layout shop-woo-layout--cart shop-woo-section reveal">
            <div class="shop-woo-panel">
                <h2 class="shop-woo-panel-title"><i class="fa-solid fa-list" aria-hidden="true"></i> Produse în coș</h2>
                <ul class="shop-woo-cart-list">
                <?php foreach ($lines as $line):
                    $p = $line['product'];
                    $pid = blu_shop_product_id($p);
                    $img = trim((string) ($p['imagine'] ?? ''));
                ?>
                    <li class="shop-woo-cart-item">
                        <a href="<?= blu_shop_h(blu_shop_product_url($p)) ?>" class="shop-woo-cart-thumb">
                            <?php if ($img !== ''): ?>
                                <img src="<?= blu_shop_h($img) ?>" alt="">
                            <?php else: ?>
                                <span><i class="fa-solid fa-image" aria-hidden="true"></i></span>
                            <?php endif; ?>
                        </a>
                        <div class="shop-woo-cart-body">
                            <h3><a href="<?= blu_shop_h(blu_shop_product_url($p)) ?>"><?= blu_shop_h((string) ($p['nume'] ?? '')) ?></a></h3>
                            <?php if (!empty($p['cod_oem'])): ?>
                                <p class="shop-card-oem"><i class="fa-solid fa-barcode" aria-hidden="true"></i> OEM: <?= blu_shop_h((string) $p['cod_oem']) ?></p>
                            <?php endif; ?>
                            <p class="shop-woo-cart-unit"><?= blu_shop_h(blu_shop_price_label($p)) ?> / buc.</p>
                        </div>
                        <div class="shop-woo-cart-actions">
                            <form method="post" class="shop-woo-qty-form">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int) $pid ?>">
                                <label class="sr-only" for="qty-<?= (int) $pid ?>">Cantitate</label>
                                <input type="number" id="qty-<?= (int) $pid ?>" name="qty" value="<?= (int) $line['qty'] ?>" min="1" max="99">
                                <button type="submit" class="shop-btn shop-btn-sm-ghost">Actualizează</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $pid ?>">
                                <button type="submit" class="shop-woo-remove" aria-label="Șterge"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                            </form>
                        </div>
                        <div class="shop-woo-cart-line-total"><?= blu_shop_h(blu_cart_format_money((float) $line['line_total'])) ?></div>
                    </li>
                <?php endforeach; ?>
                </ul>
                <a class="shop-btn shop-btn-ghost" href="catalog.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Continuă cumpărăturile</a>
            </div>

            <aside class="shop-woo-aside">
                <div class="shop-woo-aside-card shop-woo-order-summary">
                    <h3><i class="fa-solid fa-receipt" aria-hidden="true"></i> Sumar comandă</h3>
                    <dl class="shop-woo-summary-rows">
                        <div><dt>Subtotal (<?= (int) $itemCount ?> produse)</dt><dd><?= blu_shop_h(blu_cart_format_money($total)) ?></dd></div>
                        <div><dt>Livrare</dt><dd>Calculată la confirmare</dd></div>
                        <div class="shop-woo-summary-total"><dt>Total estimativ</dt><dd><?= blu_shop_h(blu_cart_format_money($total)) ?></dd></div>
                    </dl>
                    <form method="post" class="shop-form shop-woo-form">
                        <input type="hidden" name="action" value="checkout">
                        <h4><i class="fa-solid fa-truck" aria-hidden="true"></i> Date livrare</h4>
                        <div class="shop-field">
                            <label>Nume complet *</label>
                            <input type="text" name="name" required value="<?= blu_shop_h($checkoutDefaults['name']) ?>">
                        </div>
                        <div class="shop-field">
                            <label>Telefon *</label>
                            <input type="tel" name="phone" required value="<?= blu_shop_h($checkoutDefaults['phone']) ?>">
                        </div>
                        <div class="shop-field">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= blu_shop_h($checkoutDefaults['email']) ?>">
                        </div>
                        <div class="shop-field">
                            <label>Adresă livrare</label>
                            <textarea name="address" rows="2" placeholder="Stradă, nr., localitate, județ"><?= blu_shop_h($checkoutDefaults['address']) ?></textarea>
                        </div>
                        <div class="shop-field">
                            <label>Observații comandă</label>
                            <textarea name="note" rows="2" placeholder="VIN, termen urgent, factură firmă..."><?= blu_shop_h($checkoutDefaults['note']) ?></textarea>
                        </div>
                        <button type="submit" class="shop-btn shop-btn-accent shop-btn-glow" style="width:100%">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Trimite comanda
                        </button>
                        <p class="shop-woo-pay-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Plata se confirmă telefonic. Nu procesăm plăți online în această versiune.</p>
                    </form>
                </div>
            </aside>
        </div>
        <?php else: ?>
        <div class="shop-empty shop-woo-empty shop-woo-section reveal">
            <span class="shop-woo-empty-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
            <h2>Coșul tău este gol</h2>
            <p>Descoperă catalogul Blue-Car — filtrează după categorie, cod OEM sau marcă auto.</p>
            <a class="shop-btn shop-btn-accent shop-btn-glow" href="catalog.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Vezi catalogul</a>
        </div>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
