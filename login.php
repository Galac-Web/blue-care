<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';

blu_shop_redirect_if_logged_in();

$error = '';
$errorBlocked = false;
$notice = blu_shop_pull_flash('auth_notice');
$required = isset($_GET['required']) && (string) $_GET['required'] === '1';
$loggedOut = isset($_GET['logout']) && (string) $_GET['logout'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = blu_shop_attempt_login(
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );
    if ($result['ok']) {
        $redirect = trim((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
        if ($redirect !== '' && $redirect[0] === '/' && !str_contains($redirect, '//')) {
            header('Location: ' . $redirect);
            exit;
        }
        header('Location: index.php');
        exit;
    }
    $error = $result['error'] ?? 'Autentificare eșuată.';
    $errorBlocked = !empty($result['blocked']);
}

$redirect = trim((string) ($_GET['redirect'] ?? ''));
$shopPageTitle = 'Autentificare — Blue-Car';
$shopBodyClass = 'shop-auth-body shop-auth-v2';
require __DIR__ . '/shop/includes/head.php';
?>
<div class="auth-v2" aria-hidden="false">
    <div class="auth-v2__cosmos" aria-hidden="true">
        <div class="auth-v2__mesh"></div>
        <div class="auth-v2__beam"></div>
        <div class="auth-v2__ring auth-v2__ring--outer"></div>
        <div class="auth-v2__ring auth-v2__ring--inner"></div>
        <div class="auth-v2__grid"></div>
        <div class="auth-v2__watermark">SECURE</div>
    </div>

    <div class="auth-v2__shell">
        <header class="auth-v2__hero">
            <div class="auth-v2__emblem" aria-hidden="true">
                <span class="auth-v2__emblem-core"><i class="fa-solid fa-shield-halved"></i></span>
            </div>
            <p class="auth-v2__eyebrow"><span class="auth-v2__pulse"></span> Portal privat Blue-Car</p>
            <h1 class="auth-v2__brand">Blue<span>Car</span></h1>
            <p class="auth-v2__tagline">Acces exclusiv la catalog, prețuri și comenzi — doar conturi autorizate.</p>
        </header>

        <section class="auth-v2__portal" aria-labelledby="auth-v2-title">
            <div class="auth-v2__portal-glow" aria-hidden="true"></div>
            <div class="auth-v2__portal-inner">
                <div class="auth-v2__portal-head">
                    <span class="auth-v2__badge"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i> Verificare identitate</span>
                    <h2 id="auth-v2-title">Intră în magazin</h2>
                    <p>Introdu datele primite de la echipa Blue-Car.</p>
                </div>

                <?php if ($required && $error === ''): ?>
                    <div class="shop-alert shop-alert-info auth-v2__alert" role="status">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Trebuie să te autentifici pentru a accesa această pagină.
                    </div>
                <?php endif; ?>

                <?php if ($loggedOut && $error === ''): ?>
                    <div class="shop-alert shop-alert-success auth-v2__alert" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Te-ai deconectat cu succes.
                    </div>
                <?php endif; ?>

                <?php if ($notice !== null && $error === ''): ?>
                    <div class="shop-alert shop-alert-error auth-v2__alert" role="alert">
                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                        <?= blu_shop_h($notice) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="shop-alert shop-alert-error auth-v2__alert" role="alert">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <?= blu_shop_h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-v2__form shop-form" id="shop-login-form">
                    <?php if ($redirect !== ''): ?>
                        <input type="hidden" name="redirect" value="<?= blu_shop_h($redirect) ?>">
                    <?php endif; ?>

                    <div class="auth-v2__field shop-field">
                        <label for="login-email">Email</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-at" aria-hidden="true"></i>
                            <input type="email" id="login-email" name="email" required autocomplete="username" placeholder="nume@email.ro" value="<?= blu_shop_h((string) ($_POST['email'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="auth-v2__field shop-field">
                        <label for="login-password">Parolă</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Parola ta">
                        </div>
                    </div>

                    <button type="submit" class="auth-v2__submit shop-btn shop-btn-accent">
                        <span class="auth-v2__submit-shine" aria-hidden="true"></span>
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        Deschide portalul
                    </button>
                </form>

                <?php if (blu_shop_registration_enabled()): ?>
                    <p class="auth-v2__footnote">Nu ai cont? <a href="register.php<?= $redirect !== '' ? '?redirect=' . rawurlencode($redirect) : '' ?>">Creează cont</a></p>
                <?php else: ?>
                    <p class="auth-v2__footnote auth-v2__footnote--muted">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Conturile se creează doar de administrator. Contactează-ne pentru acces.
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php if ($errorBlocked): ?>
<div class="shop-auth-modal is-open" id="shop-blocked-modal" role="dialog" aria-modal="true" aria-labelledby="shop-blocked-title">
    <div class="shop-auth-modal__backdrop"></div>
    <div class="shop-auth-modal__box">
        <div class="shop-auth-modal__icon shop-auth-modal__icon--danger"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
        <h2 id="shop-blocked-title">Cont blocat</h2>
        <p>Accesul tău la magazin a fost restricționat de administrator. Nu poți vizualiza catalogul sau plasa comenzi.</p>
        <p class="shop-auth-modal__hint">Pentru deblocare, contactează echipa Blue-Car la telefon sau email.</p>
        <button type="button" class="shop-btn shop-btn-accent" id="shop-blocked-close">Am înțeles</button>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('shop-blocked-modal');
    var closeBtn = document.getElementById('shop-blocked-close');
    function closeModal() {
        if (modal) modal.classList.remove('is-open');
    }
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.querySelector('.shop-auth-modal__backdrop')?.addEventListener('click', closeModal);
    }
})();
</script>
<?php endif; ?>
</body></html>
