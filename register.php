<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';

if (!blu_shop_registration_enabled()) {
    blu_shop_set_flash('auth_notice', 'Înregistrarea publică este dezactivată. Contul îți este creat de administrator.');
    header('Location: login.php');
    exit;
}

blu_shop_redirect_if_logged_in();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password !== $confirm) {
        $error = 'Parolele nu coincid.';
    } else {
        $result = blu_shop_register(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            $password
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
        $error = $result['error'] ?? 'Înregistrare eșuată.';
    }
}

$redirect = trim((string) ($_GET['redirect'] ?? ''));
$shopPageTitle = 'Creare cont — Blue-Car';
$shopBodyClass = 'shop-auth-body shop-auth-v2';
require __DIR__ . '/shop/includes/head.php';
?>
<div class="auth-v2">
    <div class="auth-v2__cosmos" aria-hidden="true">
        <div class="auth-v2__mesh"></div>
        <div class="auth-v2__beam"></div>
        <div class="auth-v2__ring auth-v2__ring--outer"></div>
        <div class="auth-v2__ring auth-v2__ring--inner"></div>
        <div class="auth-v2__grid"></div>
        <div class="auth-v2__watermark">JOIN</div>
    </div>

    <div class="auth-v2__shell">
        <header class="auth-v2__hero">
            <div class="auth-v2__emblem" aria-hidden="true">
                <span class="auth-v2__emblem-core"><i class="fa-solid fa-user-plus"></i></span>
            </div>
            <p class="auth-v2__eyebrow"><span class="auth-v2__pulse"></span> Membru nou Blue-Car</p>
            <h1 class="auth-v2__brand">Blue<span>Car</span></h1>
            <p class="auth-v2__tagline">Creează cont și accesează imediat catalogul privat cu prețuri și comenzi.</p>
        </header>

        <section class="auth-v2__portal" aria-labelledby="auth-v2-register-title">
            <div class="auth-v2__portal-glow" aria-hidden="true"></div>
            <div class="auth-v2__portal-inner">
                <div class="auth-v2__portal-head">
                    <span class="auth-v2__badge"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Înregistrare</span>
                    <h2 id="auth-v2-register-title">Creează cont</h2>
                    <p>Completează datele tale pentru acces la magazin.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="shop-alert shop-alert-error auth-v2__alert" role="alert">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <?= blu_shop_h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-v2__form shop-form">
                    <?php if ($redirect !== ''): ?>
                        <input type="hidden" name="redirect" value="<?= blu_shop_h($redirect) ?>">
                    <?php endif; ?>

                    <div class="auth-v2__field shop-field">
                        <label for="register-name">Nume complet</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <input type="text" id="register-name" name="name" required autocomplete="name" minlength="2" placeholder="Ion Popescu">
                        </div>
                    </div>

                    <div class="auth-v2__field shop-field">
                        <label for="register-email">Email</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-at" aria-hidden="true"></i>
                            <input type="email" id="register-email" name="email" required autocomplete="email" placeholder="nume@email.ro">
                        </div>
                    </div>

                    <div class="auth-v2__field shop-field">
                        <label for="register-password">Parolă</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input type="password" id="register-password" name="password" required autocomplete="new-password" minlength="6" placeholder="Min. 6 caractere">
                        </div>
                    </div>

                    <div class="auth-v2__field shop-field">
                        <label for="register-password-confirm">Confirmă parola</label>
                        <div class="auth-v2__input-wrap">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <input type="password" id="register-password-confirm" name="password_confirm" required autocomplete="new-password" minlength="6" placeholder="Repetă parola">
                        </div>
                    </div>

                    <button type="submit" class="auth-v2__submit shop-btn shop-btn-accent">
                        <span class="auth-v2__submit-shine" aria-hidden="true"></span>
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Activează contul
                    </button>
                </form>

                <p class="auth-v2__footnote">Ai deja cont? <a href="login.php<?= $redirect !== '' ? '?redirect=' . rawurlencode($redirect) : '' ?>">Autentificare</a></p>
            </div>
        </section>
    </div>
</div>
</body></html>
