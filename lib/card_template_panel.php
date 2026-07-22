<?php
declare(strict_types=1);

require_once __DIR__ . '/tecdoc_product_enrich.php';

function blu_render_card_template_panel(string $csrf): void
{
    $tpl = blu_card_template_settings();
    $boilerplate = implode("\n", $tpl['boilerplate']);

    $exTitle = 'Capota Motor MITSUBISHI OUTLANDER AN FAB 2013 - 2020 Cod OE 5900A540 / 5900A739 ' . $tpl['title_suffix'];
    $exLines = array_merge([
        'Cod OE: 5900A540 / 5900A739',
        'cod INT: 550100070',
        '',
        'COMPATIBIL CU:',
        'MITSUBISHI OUTLANDER 2013-2016 (GGW/GFW/ZL/ZK)',
        'MITSUBISHI OUTLANDER 2016-2020 (GGW/GFW/ZL/ZK)',
        '',
        $tpl['status_label'],
        '',
    ], $tpl['boilerplate']);
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Model cartelă produs</h5></div>
                <div class="card-body">
                    <p class="text-muted">
                        <strong>Format strict 1:1</strong> — toate produsele scanate, traduse și importate (GBG → Autodoc/TecDoc → magazin/PieseAuto)
                        folosesc exact acest model: titlu, Cod OE, cod INT, compatibilitate, etichetă stare și blocul standard al firmei.
                        Editezi aici doar elementele fixe; datele tehnice vin automat din catalog.
                    </p>
                    <form method="post" class="theme-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="save_card_template">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Etichetă stare</label>
                                <input class="form-control" name="status_label" value="<?= e($tpl['status_label']) ?>" placeholder="PRODUS NOU">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sufix titlu</label>
                                <input class="form-control" name="title_suffix" value="<?= e($tpl['title_suffix']) ?>" placeholder="NOUA">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bloc standard (o linie = un rând afișat)</label>
                            <textarea class="form-control" name="boilerplate" rows="11" style="font-family:monospace;font-size:.85rem;"><?= e($boilerplate) ?></textarea>
                            <div class="form-text">Program, garanție, retur, livrare etc. Apare identic pe fiecare produs.</div>
                        </div>

                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvează modelul</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars((function_exists('blu_admin_web_base') ? blu_admin_web_base() : '') . 'api/refresh_all_products.php', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-rotate"></i> Reîmprospătează TecDoc (regenerează cartelele)
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Previzualizare</h5></div>
                <div class="card-body">
                    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;background:#fff;">
                        <div class="fw-bold mb-2" style="line-height:1.3;"><?= e($exTitle) ?></div>
                        <div style="white-space:pre-line;font-size:.9rem;color:#334155;"><?= e(implode("\n", $exLines)) ?></div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Exemplu cu date demonstrative. Pe produsele reale datele vin din TecDoc.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
