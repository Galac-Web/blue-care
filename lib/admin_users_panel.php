<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/admin_section_nav.php';

/**
 * @param list<array<string,mixed>> $users
 * @param array<string,mixed>|null $currentAdmin
 */
function blu_render_admin_users_panel(string $csrf, array $users, ?array $currentAdmin): void
{
    if (!blu_admin_can_manage_users($currentAdmin)) {
        ?>
        <div class="alert alert-warning">
            Nu ai permisiunea de a gestiona utilizatorii admin. Contactează administratorul principal.
        </div>
        <?php
        return;
    }

    $modules = blu_admin_permission_modules();
    $presets = blu_admin_role_presets();
    $editId = (int) ($_GET['edit_user'] ?? 0);
    $editUser = null;
    foreach ($users as $u) {
        if ((int) ($u['id'] ?? 0) === $editId) {
            $editUser = blu_admin_normalize_user($u);
            break;
        }
    }
    ?>
    <?php blu_render_admin_section_nav('sistem', 'users'); ?>

    <div class="au-panel">
        <div class="au-intro card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">Echipă &amp; acces de lucru</h5>
                        <p class="text-muted small mb-0">Creează conturi pentru colegi și delegă clar ce secțiuni pot folosi.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis border"><?= count($users) ?> utilizatori</span>
                        <span class="badge bg-success-subtle text-success-emphasis border"><?= count(array_filter($users, static fn($u) => ($u['status'] ?? 'active') === 'active')) ?> activi</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card h-100 shadow-sm">
                    <div class="card-header card-no-border pb-0">
                        <h4 class="mb-1"><?= $editUser ? 'Editează utilizator #' . (int) $editUser['id'] : 'Utilizator nou' ?></h4>
                        <?php if ($editUser): ?>
                            <a href="?page=users" class="small">Anulează editarea</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" class="theme-form" id="auUserForm">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="<?= $editUser ? 'update_user' : 'save_user' ?>">
                            <?php if ($editUser): ?>
                                <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nume *</label>
                                <input class="form-control" name="name" required maxlength="120"
                                       value="<?= htmlspecialchars((string) ($editUser['name'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input class="form-control" type="email" name="email" required maxlength="190"
                                       value="<?= htmlspecialchars((string) ($editUser['email'] ?? ''), ENT_QUOTES) ?>"
                                       <?= $editUser && (int) ($editUser['id'] ?? 0) === 1 ? 'readonly' : '' ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Parolă<?= $editUser ? ' (lasă gol pentru a păstra)' : ' *' ?></label>
                                <input class="form-control" type="password" name="password"
                                       <?= $editUser ? '' : 'required' ?>
                                       placeholder="<?= $editUser ? '••••••••' : 'min. 6 caractere' ?>"
                                       autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profil rapid (rol)</label>
                                <select class="form-select" name="role" id="auRolePreset">
                                    <?php foreach ($presets as $key => $preset): ?>
                                        <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
                                            <?= ($editUser['role'] ?? 'operator_comenzi') === $key ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($preset['label'], ENT_QUOTES) ?> — <?= htmlspecialchars($preset['desc'], ENT_QUOTES) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Selectează un profil, apoi ajustează manual modulele dacă e nevoie.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status cont</label>
                                <select class="form-select" name="status" <?= $editUser && (int) ($editUser['id'] ?? 0) === 1 ? 'disabled' : '' ?>>
                                    <option value="active" <?= ($editUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activ — poate intra în admin</option>
                                    <option value="disabled" <?= ($editUser['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Dezactivat — acces blocat</option>
                                </select>
                                <?php if ($editUser && (int) ($editUser['id'] ?? 0) === 1): ?>
                                    <input type="hidden" name="status" value="active">
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label mb-0">Acces delegat (module)</label>
                                    <button type="button" class="btn btn-link btn-sm p-0" id="auSelectAll">Toate</button>
                                </div>
                                <div class="au-perm-grid">
                                    <?php
                                    $selected = $editUser['permissions'] ?? blu_admin_preset_permissions('operator_comenzi');
                                    foreach ($modules as $key => $mod):
                                        $checked = in_array($key, $selected, true);
                                        $isPrimary = $editUser && (int) ($editUser['id'] ?? 0) === 1;
                                    ?>
                                        <label class="au-perm-item">
                                            <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
                                                   class="au-perm-check" <?= $checked ? 'checked' : '' ?> <?= $isPrimary ? 'checked disabled' : '' ?>>
                                            <span class="au-perm-item__body">
                                                <strong><?= htmlspecialchars($mod['label'], ENT_QUOTES) ?></strong>
                                                <small><?= htmlspecialchars($mod['desc'], ENT_QUOTES) ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($editUser && (int) ($editUser['id'] ?? 0) === 1): ?>
                                    <?php foreach (blu_admin_all_permission_keys() as $pk): ?>
                                        <input type="hidden" name="permissions[]" value="<?= htmlspecialchars($pk, ENT_QUOTES) ?>">
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <button class="btn btn-primary w-100" type="submit">
                                <?= $editUser ? 'Salvează modificările' : 'Adaugă utilizator' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card shadow-sm">
                    <div class="card-header card-no-border pb-0">
                        <h4 class="mb-1">Utilizatori admin</h4>
                        <p class="text-muted small mb-0">Conturi staff — nu clienții magazinului online.</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive theme-scrollbar">
                            <table class="table table-hover align-middle mb-0 au-users-table">
                                <thead>
                                <tr>
                                    <th>Utilizator</th>
                                    <th>Profil</th>
                                    <th>Acces delegat</th>
                                    <th>Status</th>
                                    <th class="text-end">Acțiuni</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users as $user):
                                    $user = blu_admin_normalize_user($user);
                                    $uid = (int) ($user['id'] ?? 0);
                                    $isSelf = (int) ($currentAdmin['id'] ?? 0) === $uid;
                                    $isPrimary = $uid === 1;
                                    ?>
                                    <tr class="<?= ($user['status'] ?? 'active') === 'disabled' ? 'table-secondary' : '' ?>">
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES) ?></div>
                                            <?php if ($isSelf): ?><span class="badge bg-info-subtle text-info-emphasis">Tu</span><?php endif; ?>
                                            <?php if ($isPrimary): ?><span class="badge bg-warning-subtle text-warning-emphasis">Principal</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(blu_admin_role_label((string) ($user['role'] ?? '')), ENT_QUOTES) ?></span>
                                        </td>
                                        <td>
                                            <div class="au-access-badges">
                                                <?php foreach ($user['permissions'] as $permKey):
                                                    if (!isset($modules[$permKey])) continue;
                                                    $mod = $modules[$permKey];
                                                    ?>
                                                    <span class="badge bg-<?= htmlspecialchars($mod['color'], ENT_QUOTES) ?>-subtle text-<?= htmlspecialchars($mod['color'], ENT_QUOTES) ?>-emphasis border" title="<?= htmlspecialchars($mod['desc'], ENT_QUOTES) ?>">
                                                        <?= htmlspecialchars($mod['label'], ENT_QUOTES) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                                <span class="badge bg-success">Activ</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Dezactivat</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                                <a class="btn btn-sm btn-outline-primary" href="?page=users&amp;edit_user=<?= $uid ?>">Editează</a>
                                                <?php if (!$isPrimary && !$isSelf): ?>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Ștergi acest utilizator?');">
                                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Șterge</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-3 shadow-sm">
                    <div class="card-header card-no-border pb-0"><h5 class="mb-0">Legendă profile</h5></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($presets as $key => $preset):
                                if ($key === 'custom') continue;
                                ?>
                                <div class="col-md-6">
                                    <div class="au-preset-card">
                                        <strong><?= htmlspecialchars($preset['label'], ENT_QUOTES) ?></strong>
                                        <p class="small text-muted mb-1"><?= htmlspecialchars($preset['desc'], ENT_QUOTES) ?></p>
                                        <div class="small"><?= htmlspecialchars(blu_admin_permissions_summary($preset['permissions']), ENT_QUOTES) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .au-perm-grid { display: grid; gap: .5rem; max-height: 280px; overflow-y: auto; padding-right: .25rem; }
        .au-perm-item { display: flex; gap: .65rem; align-items: flex-start; border: 1px solid #e2e8f0; border-radius: 10px; padding: .65rem .75rem; cursor: pointer; margin: 0; background: #fff; }
        .au-perm-item:has(input:checked) { border-color: #308e87; background: rgba(48,142,135,.06); }
        .au-perm-item input { margin-top: .2rem; flex-shrink: 0; }
        .au-perm-item__body { display: flex; flex-direction: column; gap: .1rem; }
        .au-perm-item__body small { color: #64748b; line-height: 1.3; }
        .au-access-badges { display: flex; flex-wrap: wrap; gap: .25rem; max-width: 320px; }
        .au-users-table td { vertical-align: middle; }
        .au-preset-card { border: 1px dashed #cbd5e1; border-radius: 10px; padding: .75rem; height: 100%; background: #f8fafc; }
    </style>

    <script>
    (function () {
        'use strict';
        const presets = <?= json_encode(array_map(static fn($p) => $p['permissions'], $presets), JSON_UNESCAPED_UNICODE) ?>;
        const roleSelect = document.getElementById('auRolePreset');
        const checks = document.querySelectorAll('.au-perm-check:not(:disabled)');
        const selectAllBtn = document.getElementById('auSelectAll');

        function applyPreset(role) {
            const perms = presets[role] || [];
            checks.forEach((cb) => {
                if (role === 'admin') {
                    cb.checked = true;
                } else if (role === 'custom') {
                    return;
                } else {
                    cb.checked = perms.includes(cb.value);
                }
            });
        }

        roleSelect?.addEventListener('change', () => applyPreset(roleSelect.value));

        selectAllBtn?.addEventListener('click', () => {
            checks.forEach((cb) => { cb.checked = true; });
            if (roleSelect) roleSelect.value = 'custom';
        });

        document.getElementById('auUserForm')?.addEventListener('submit', (e) => {
            const any = Array.from(document.querySelectorAll('.au-perm-check:checked, input[name="permissions[]"]')).length > 0;
            if (!any) {
                e.preventDefault();
                alert('Selectează cel puțin un modul de acces.');
            }
        });
    })();
    </script>
    <?php
}
