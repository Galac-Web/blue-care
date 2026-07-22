<?php

declare(strict_types=1);



require_once __DIR__ . '/shop/auth.php';



function blu_shop_users_for_admin(): array

{

    $users = blu_shop_load_users();

    usort($users, static function (array $a, array $b): int {

        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

    });

    return $users;

}



function blu_render_shop_users_panel(string $csrf): void

{

    $users = blu_shop_users_for_admin();

    $activeCount = 0;

    $blockedCount = 0;

    foreach ($users as $user) {

        if (blu_shop_user_is_blocked($user)) {

            $blockedCount++;

        } else {

            $activeCount++;

        }

    }

    $publicRegister = blu_shop_registration_enabled();

    ?>

    <div class="alert alert-info border-0 shadow-sm mb-3">

        <strong><i class="bi bi-shield-lock"></i> Magazin protejat</strong><br>

        <span class="small">Catalogul și coșul sunt publice<?= blu_shop_guest_browse_enabled() ? '' : ' (dezactivat — necesită login)' ?>.
        Comenzile din <code>cos.php</code> apar automat în <a href="?page=orders">Toate comenzile</a>.
        Conturile clienți se creează aici<?= $publicRegister ? ' sau prin înregistrare publică' : ' — înregistrarea publică este dezactivată' ?>.</span>

    </div>



    <div class="row g-3 mb-3">

        <div class="col-md-4">

            <div class="card"><div class="card-body">

                <div class="text-muted small">Total conturi site</div>

                <div class="h4 mb-0"><?= count($users) ?></div>

            </div></div>

        </div>

        <div class="col-md-4">

            <div class="card"><div class="card-body">

                <div class="text-muted small">Active</div>

                <div class="h4 mb-0 text-success"><?= $activeCount ?></div>

            </div></div>

        </div>

        <div class="col-md-4">

            <div class="card"><div class="card-body">

                <div class="text-muted small">Blocate</div>

                <div class="h4 mb-0 text-danger"><?= $blockedCount ?></div>

            </div></div>

        </div>

    </div>



    <div class="card mb-3">

        <div class="card-header card-no-border pb-0">

            <h4 class="mb-1">Creare cont client</h4>

            <p class="text-muted small mb-0">Generează login (email) și parolă pentru acces la magazin.</p>

        </div>

        <div class="card-body">

            <form method="post" class="row g-3 align-items-end" onsubmit="return confirm('Creezi acest cont de acces la magazin?');">

                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="action" value="shop_user_create">

                <div class="col-md-3">

                    <label class="form-label small fw-bold">Nume complet</label>

                    <input type="text" name="name" class="form-control form-control-sm" required minlength="2" placeholder="Ion Popescu">

                </div>

                <div class="col-md-3">

                    <label class="form-label small fw-bold">Email (login)</label>

                    <input type="email" name="email" class="form-control form-control-sm" required placeholder="client@email.ro">

                </div>

                <div class="col-md-2">

                    <label class="form-label small fw-bold">Parolă</label>

                    <input type="text" name="password" class="form-control form-control-sm" required minlength="6" placeholder="Min. 6 car." autocomplete="new-password">

                </div>

                <div class="col-md-2">

                    <label class="form-label small fw-bold">Status</label>

                    <select name="status" class="form-select form-select-sm">

                        <option value="active">Activ</option>

                        <option value="blocked">Blocat</option>

                    </select>

                </div>

                <div class="col-md-2">

                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-person-plus"></i> Creează</button>

                </div>

            </form>

        </div>

    </div>



    <div class="card">

        <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">

            <div>

                <h4 class="mb-1">Clienți înregistrați</h4>

                <p class="text-muted small mb-0">Blochează, resetează parola sau șterge.</p>

            </div>

            <a class="btn btn-sm btn-outline-secondary" href="../login.php" target="_blank" rel="noopener">Pagina login ↗</a>

        </div>

        <div class="card-body">

            <?php if (!$users): ?>

                <p class="text-muted mb-0">Niciun client încă. Creează primul cont cu formularul de mai sus.</p>

            <?php else: ?>

                <div class="table-responsive theme-scrollbar">

                    <table class="table table-hover align-middle">

                        <thead>

                        <tr>

                            <th>ID</th>

                            <th>Nume</th>

                            <th>Email</th>

                            <th>Status</th>

                            <th>Creat</th>

                            <th class="text-end">Acțiuni</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($users as $user):

                            $id = (int) ($user['id'] ?? 0);

                            $blocked = blu_shop_user_is_blocked($user);

                            ?>

                            <tr>

                                <td><?= $id ?></td>

                                <td><?= htmlspecialchars((string) ($user['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                                <td><code><?= htmlspecialchars((string) ($user['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>

                                <td>

                                    <?php if ($blocked): ?>

                                        <span class="badge bg-danger">Blocat</span>

                                    <?php else: ?>

                                        <span class="badge bg-success">Activ</span>

                                    <?php endif; ?>

                                </td>

                                <td class="small"><?= htmlspecialchars((string) ($user['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                                <td class="text-end">

                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">

                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resetModal<?= $id ?>">Reset parolă</button>

                                        <?php if ($blocked): ?>

                                            <form method="post" class="d-inline">

                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                                                <input type="hidden" name="action" value="shop_user_unblock">

                                                <input type="hidden" name="user_id" value="<?= $id ?>">

                                                <button type="submit" class="btn btn-sm btn-outline-success">Deblochează</button>

                                            </form>

                                        <?php else: ?>

                                            <form method="post" class="d-inline" onsubmit="return confirm('Blochezi accesul la magazin?');">

                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                                                <input type="hidden" name="action" value="shop_user_block">

                                                <input type="hidden" name="user_id" value="<?= $id ?>">

                                                <button type="submit" class="btn btn-sm btn-outline-warning">Blochează</button>

                                            </form>

                                        <?php endif; ?>

                                        <form method="post" class="d-inline" onsubmit="return confirm('Ștergi definitiv contul?');">

                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden" name="action" value="shop_user_delete">

                                            <input type="hidden" name="user_id" value="<?= $id ?>">

                                            <button type="submit" class="btn btn-sm btn-outline-danger">Șterge</button>

                                        </form>

                                    </div>

                                    <div class="modal fade" id="resetModal<?= $id ?>" tabindex="-1" aria-hidden="true">

                                        <div class="modal-dialog modal-dialog-centered">

                                            <div class="modal-content">

                                                <form method="post">

                                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                                                    <input type="hidden" name="action" value="shop_user_reset_password">

                                                    <input type="hidden" name="user_id" value="<?= $id ?>">

                                                    <div class="modal-header">

                                                        <h5 class="modal-title">Reset parolă</h5>

                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                                                    </div>

                                                    <div class="modal-body">

                                                        <p class="small text-muted"><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

                                                        <input type="text" name="password" class="form-control" required minlength="6" placeholder="Parolă nouă" autocomplete="new-password">

                                                    </div>

                                                    <div class="modal-footer">

                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anulează</button>

                                                        <button type="submit" class="btn btn-primary btn-sm">Salvează</button>

                                                    </div>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <?php

}


