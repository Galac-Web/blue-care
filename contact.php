<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';
require_once __DIR__ . '/lib/shop/leads.php';
require_once __DIR__ . '/lib/messages_store.php';

$company = blu_shop_company();
$bluCmsPage = 'contact';
$message = '';
$messageType = '';
$productRef = (int) ($_GET['produs'] ?? 0);
$product = $productRef > 0 ? blu_shop_find_product($productRef) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $body = trim((string) ($_POST['message'] ?? ''));
    if ($name === '' || $phone === '' || $body === '') {
        $message = 'Completează numele, telefonul și mesajul.';
        $messageType = 'error';
    } else {
        $text = "Contact: {$name}\nTel: {$phone}\nEmail: {$email}\n\n{$body}";
        if ($product) {
            $text .= "\n\nProdus: " . ($product['nume'] ?? '') . ' (ID ' . $productRef . ')';
        }
        $ok = blu_shop_save_lead([
            'source' => 'shop_contact',
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'p' => $phone,
            's' => 'contact',
            't' => $text,
        ]);
        if ($ok) {
            blu_message_create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'message_body' => $body,
                'subject' => $product ? 'Produs ID ' . $productRef : 'Contact magazin',
                'channel' => 'website',
                'direction' => 'inbound',
                'delivery_status' => 'received',
                'bot_status' => 'needs_human',
                'source_url' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ]);
            $message = 'Mesaj trimis. Te contactăm în cel mult 2 ore lucrătoare.';
            $messageType = 'success';
        } else {
            $message = 'Eroare la trimitere.';
            $messageType = 'error';
        }
    }
}

$shopPhone = trim((string) ($company['phone'] ?? $company['telefon'] ?? ''));
$shopEmail = trim((string) ($company['email'] ?? 'contact@blue-car.ro'));
$shopAddress = trim((string) ($company['address'] ?? $company['adresa'] ?? 'România'));
$shopPageTitle = 'Contact — Blue-Car';
$shopPageDescription = 'Contactează echipa Blue-Car pentru oferte, compatibilitate OEM și comenzi personalizate.';
$shopBodyClass = 'shop-page shop-page-contact shop-ui-v10';

require __DIR__ . '/shop/includes/head.php';
require __DIR__ . '/shop/includes/header.php';
?>
<main class="shop-main shop-main--woo">
    <div class="shop-container">
        <nav class="shop-breadcrumb reveal" aria-label="Breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Acasă</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span>Contact</span>
        </nav>

        <?php
        $wooHeroEyebrow = blu_cms_get('contact', 'hero_eyebrow');
        $wooHeroTitle = blu_cms_get('contact', 'hero_title');
        $wooHeroLead = blu_cms_get('contact', 'hero_lead');
        $wooHeroIcon = 'fa-solid fa-headset';
        require __DIR__ . '/shop/includes/woo-page-hero.php';
        ?>

        <?php require __DIR__ . '/shop/includes/woo-trust-strip.php'; ?>

        <?php blu_builder_render_zone('contact', 'after_hero'); ?>

        <div class="shop-woo-layout shop-woo-section reveal">
            <div class="shop-woo-panel">
                <div class="shop-woo-tabs" data-woo-tabs>
                    <div class="shop-woo-tablist shop-pill-tablist" role="tablist" aria-label="Secțiuni contact">
                        <button type="button" class="shop-woo-tab shop-pill-tab is-active" role="tab" id="contact-tab-form" aria-selected="true" aria-controls="contact-panel-form" data-tab="form">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Trimite mesaj
                        </button>
                        <button type="button" class="shop-woo-tab shop-pill-tab" role="tab" id="contact-tab-info" aria-selected="false" aria-controls="contact-panel-info" data-tab="info" tabindex="-1">
                            <i class="fa-solid fa-address-card" aria-hidden="true"></i> Date contact
                        </button>
                        <button type="button" class="shop-woo-tab shop-pill-tab" role="tab" id="contact-tab-faq" aria-selected="false" aria-controls="contact-panel-faq" data-tab="faq" tabindex="-1">
                            <i class="fa-solid fa-circle-question" aria-hidden="true"></i> Întrebări frecvente
                        </button>
                    </div>

                    <div class="shop-woo-tabpanel is-active" role="tabpanel" id="contact-panel-form" aria-labelledby="contact-tab-form" data-panel="form">
                        <?php if ($message !== ''): ?>
                            <div class="shop-alert shop-alert-<?= $messageType === 'success' ? 'success' : 'error' ?>"><?= blu_shop_h($message) ?></div>
                        <?php endif; ?>
                        <?php if ($product): ?>
                            <div class="shop-woo-ref-card">
                                <i class="fa-solid fa-box" aria-hidden="true"></i>
                                <div>
                                    <strong>Referință produs</strong>
                                    <p><?= blu_shop_h((string) ($product['nume'] ?? '')) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="shop-form shop-woo-form">
                            <div class="shop-woo-form-grid">
                                <div class="shop-field">
                                    <label><i class="fa-solid fa-user" aria-hidden="true"></i> Nume complet *</label>
                                    <input type="text" name="name" required placeholder="Ex: Ion Popescu">
                                </div>
                                <div class="shop-field">
                                    <label><i class="fa-solid fa-phone" aria-hidden="true"></i> Telefon *</label>
                                    <input type="tel" name="phone" required placeholder="07xx xxx xxx">
                                </div>
                            </div>
                            <div class="shop-field">
                                <label><i class="fa-solid fa-envelope" aria-hidden="true"></i> Email</label>
                                <input type="email" name="email" placeholder="optional@email.ro">
                            </div>
                            <div class="shop-field">
                                <label><i class="fa-solid fa-message" aria-hidden="true"></i> Mesajul tău *</label>
                                <textarea name="message" rows="5" required placeholder="Cod OEM, marcă/model mașină, ce piesă cauți..."><?php if ($product): ?>Bună, doresc informații despre: <?= blu_shop_h((string) ($product['nume'] ?? '')) ?><?php endif; ?></textarea>
                            </div>
                            <button type="submit" class="shop-btn shop-btn-accent shop-btn-glow"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Trimite cererea</button>
                        </form>
                    </div>

                    <div class="shop-woo-tabpanel" role="tabpanel" id="contact-panel-info" aria-labelledby="contact-tab-info" data-panel="info" hidden>
                        <ul class="shop-woo-info-list">
                            <?php if ($shopPhone !== ''): ?>
                            <li>
                                <span class="shop-woo-info-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                                <div>
                                    <strong>Telefon comenzi</strong>
                                    <a href="tel:<?= blu_shop_h(preg_replace('/\s+/', '', $shopPhone)) ?>"><?= blu_shop_h($shopPhone) ?></a>
                                    <em>Luni–Vineri 09:00–18:00</em>
                                </div>
                            </li>
                            <?php endif; ?>
                            <li>
                                <span class="shop-woo-info-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                                <div>
                                    <strong>Email</strong>
                                    <a href="mailto:<?= blu_shop_h($shopEmail) ?>"><?= blu_shop_h($shopEmail) ?></a>
                                    <em>Răspundem în aceeași zi lucrătoare</em>
                                </div>
                            </li>
                            <li>
                                <span class="shop-woo-info-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                                <div>
                                    <strong>Livrare</strong>
                                    <p><?= blu_shop_h($shopAddress) ?></p>
                                    <em>Curier național · 24–72h din stoc</em>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="shop-woo-tabpanel" role="tabpanel" id="contact-panel-faq" aria-labelledby="contact-tab-faq" data-panel="faq" hidden>
                        <div class="shop-woo-faq">
                            <?php for ($f = 1; $f <= 3; $f++): ?>
                            <details class="shop-woo-faq-item"<?= $f === 1 ? ' open' : '' ?>>
                                <summary><i class="fa-solid <?= $f === 1 ? 'fa-barcode' : ($f === 2 ? 'fa-clock' : 'fa-rotate-left') ?>" aria-hidden="true"></i> <?php blu_cms_tag('contact', 'faq' . $f . '_q', 'span', null); ?></summary>
                                <?php blu_cms_tag('contact', 'faq' . $f . '_a', 'p', null); ?>
                            </details>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="shop-woo-aside">
                <div class="shop-woo-aside-card shop-woo-aside-card--accent">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <?php blu_cms_tag('contact', 'aside1_title', 'h3', null); ?>
                    <?php blu_cms_tag('contact', 'aside1_text', 'p', null); ?>
                    <?php if ($shopPhone !== ''): ?>
                        <a class="shop-btn shop-btn-accent" href="tel:<?= blu_shop_h(preg_replace('/\s+/', '', $shopPhone)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Sună acum</a>
                    <?php endif; ?>
                </div>
                <div class="shop-woo-aside-card">
                    <h3><i class="fa-solid fa-route" aria-hidden="true"></i> <?php blu_cms_tag('contact', 'aside2_title', 'span', null); ?></h3>
                    <ol class="shop-woo-steps-mini">
                        <li><span>1</span> <?php blu_cms_tag('contact', 'aside2_step1', 'span', null); ?></li>
                        <li><span>2</span> <?php blu_cms_tag('contact', 'aside2_step2', 'span', null); ?></li>
                        <li><span>3</span> <?php blu_cms_tag('contact', 'aside2_step3', 'span', null); ?></li>
                    </ol>
                </div>
                <div class="shop-woo-aside-card">
                    <h3><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> <?php blu_cms_tag('contact', 'aside3_title', 'span', null); ?></h3>
                    <p>Peste <?= count(blu_load_products()) ?> <?php blu_cms_tag('contact', 'aside3_text', 'span', null); ?></p>
                    <a class="shop-btn shop-btn-ghost" href="catalog.php"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Vezi catalogul</a>
                </div>
            </aside>
        </div>

        <section class="shop-woo-cta-band shop-woo-section reveal" aria-label="Call to action">
            <div class="shop-woo-cta-band-inner">
                <div>
                    <p class="shop-woo-hero-eyebrow"><i class="fa-solid fa-car" aria-hidden="true"></i> <?php blu_cms_tag('contact', 'cta_eyebrow', 'span', null); ?></p>
                    <?php blu_cms_tag('contact', 'cta_title', 'h2', null); ?>
                    <?php blu_cms_tag('contact', 'cta_text', 'p', null); ?>
                </div>
                <div class="shop-woo-cta-actions">
                    <a class="shop-btn shop-btn-accent" href="catalog.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Catalog</a>
                    <a class="shop-btn shop-btn-ghost" href="cos.php"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Coșul meu</a>
                </div>
            </div>
        </section>

        <?php blu_builder_render_zone('contact', 'before_footer'); ?>
    </div>
</main>
<?php require __DIR__ . '/shop/includes/footer.php'; ?>
