<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';
require_once __DIR__ . '/pricing.php';

/** @return array<string, array> */
function blu_products_imported_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    $index = [];
    $rows = blu_read_json_file(blu_imported_cards_file(), []);
    if (!is_array($rows)) {
        return $index;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $oem = strtoupper(trim((string)($row['cod_oem'] ?? '')));
        if ($oem !== '') {
            $index[$oem] = $row;
        }
    }
    return $index;
}

function blu_product_parse_description(string $text): array
{
    if ($text === '') {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $blocks = [];
    $currentLabel = null;
    $currentValue = [];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            continue;
        }
        if (str_ends_with($trim, ':') && mb_strlen($trim, 'UTF-8') < 80) {
            if ($currentLabel !== null) {
                $blocks[] = [
                    'label' => $currentLabel,
                    'value' => trim(implode("\n", $currentValue)),
                ];
            }
            $currentLabel = rtrim($trim, ':');
            $currentValue = [];
            continue;
        }
        if ($currentLabel === null) {
            $blocks[] = ['label' => 'Titlu', 'value' => $trim];
            $currentLabel = '__title_done';
            continue;
        }
        if ($currentLabel === '__title_done') {
            $blocks[] = ['label' => 'Detaliu', 'value' => $trim];
            continue;
        }
        $currentValue[] = $trim;
    }
    if ($currentLabel !== null && $currentLabel !== '__title_done') {
        $blocks[] = [
            'label' => $currentLabel,
            'value' => trim(implode("\n", $currentValue)),
        ];
    }

    return $blocks;
}

function blu_product_extract_model_parts(string $model): array
{
    $model = trim($model);
    if ($model === '') {
        return ['model' => '', 'motorizare' => ''];
    }
    if (preg_match('/^(.+?)\s+(\d\.\d+\s*.+|\d\.\d+[A-Z0-9\s\(\),\-\/]+)$/u', $model, $m)) {
        return ['model' => trim($m[1]), 'motorizare' => trim($m[2])];
    }
    return ['model' => $model, 'motorizare' => ''];
}

function blu_product_display_name(array $product): string
{
    $name = trim((string)($product['nume'] ?? $product['name'] ?? $product['titlu'] ?? $product['title'] ?? ''));
    return $name !== '' ? $name : '-';
}

function blu_product_display_code(array $product): string
{
    $code = trim((string)($product['cod_oem'] ?? $product['cod'] ?? $product['cod_articol'] ?? ''));
    return $code !== '' ? $code : '-';
}

function blu_product_view_data(array $product, ?array $card = null): array
{
    if ($card === null) {
        $oemKey = strtoupper(trim((string)($product['cod_oem'] ?? '')));
        $card = blu_products_imported_index()[$oemKey] ?? null;
    }

    $title = blu_product_display_name($product);
    $descriere = trim((string)($product['descriere'] ?? ''));
    $supplierBrand = trim((string)($card['brand'] ?? ''));
    $carBrand = trim((string)($product['marca_masina'] ?? $card['marca_masina'] ?? ''));
    $modelRaw = trim((string)($card['model'] ?? ''));
    $modelParts = blu_product_extract_model_parts($modelRaw);
    $mainCategory = trim((string)($card['main_category'] ?? 'Piese auto'));
    $subCategory = trim((string)($product['categorie'] ?? $card['sub_category'] ?? $card['pieseauto_category'] ?? ''));
    $stoc = trim((string)($product['stoc'] ?? '1'));
    $pretRaw = trim((string)($product['pret'] ?? ''));
    $pretBazaInput = 0.0;
    if ($pretRaw !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $pretRaw) ?? ''))) {
        $pretBazaInput = (float)str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $pretRaw));
    }
    // Prețul stocat este considerat preț de BAZĂ (cost); finalul se calculează cu adaos + cheltuieli + TVA.
    $pricing = blu_compute_price($pretBazaInput);
    $pretBaza = $pricing['baza'];
    $pretFinal = $pricing['pret_final'];
    $img = trim((string)($product['imagine'] ?? $card['image'] ?? ''));
    $codArticol = trim((string)($card['cod_articol'] ?? ''));
    $codOem = blu_product_display_code($product);
    $status = ($card['status'] ?? '') === 'imported' || $descriere !== '' ? 'Active' : 'Draft';
    $featured = ($card['tecdoc_enriched'] ?? false) || str_contains($descriere, 'Producătorul');

    return [
        'id' => (int)($product['id'] ?? $product['randomn_id'] ?? 0),
        'title' => $title,
        'descriere' => $descriere,
        'description_blocks' => blu_product_parse_description($descriere),
        'image' => $img,
        'cod_oem' => $codOem,
        'cod_articol' => $codArticol,
        'coduri_oem' => trim((string)($card['coduri_oem'] ?? '')),
        'supplier_brand' => $supplierBrand,
        'car_brand' => $carBrand,
        'model' => $modelParts['model'] !== '' ? $modelParts['model'] : $modelRaw,
        'motorizare' => $modelParts['motorizare'] !== '' ? $modelParts['motorizare'] : 'Variază după vehicul',
        'main_category' => $mainCategory,
        'sub_category' => $subCategory,
        'product_category' => trim((string)($card['product_category'] ?? ($mainCategory . ' -> ' . $subCategory))),
        'pret_baza' => $pretBaza,
        'pret_final' => $pretFinal,
        'pret_baza_display' => number_format($pretBaza, 2, ',', ' ') . ' lei',
        'pret_final_display' => number_format($pretFinal, 2, ',', ' ') . ' lei',
        'pret_breakdown' => $pricing,
        'stoc' => $stoc !== '' ? $stoc : '1',
        'regula' => 'Produse',
        'status' => $status,
        'featured' => $featured,
        'car' => trim((string)($card['car'] ?? '')),
        'updated_at' => (string)($product['updated_at'] ?? ''),
    ];
}

function blu_render_product_description_blocks(array $blocks, bool $compact = false): void
{
    if ($blocks === []) {
        echo '<p class="text-muted mb-0">Fără descriere.</p>';
        return;
    }

    $fullText = '';
    foreach ($blocks as $block) {
        $value = trim((string)($block['value'] ?? ''));
        if ($value !== '') {
            $fullText .= ($fullText !== '' ? "\n" : '') . $value;
        }
    }
    $titleBlock = $blocks[0]['value'] ?? '';
    if (function_exists('blu_description_uses_card_template') && blu_description_uses_card_template($fullText)) {
        blu_render_card_template_preview(trim((string)$titleBlock), $fullText, $compact);
        return;
    }

    $limit = $compact ? 6 : 9999;
    echo '<div class="blu-desc-blocks' . ($compact ? ' blu-desc-blocks--compact' : '') . '">';
    foreach (array_slice($blocks, 0, $limit) as $block) {
        $label = trim((string)($block['label'] ?? ''));
        $value = trim((string)($block['value'] ?? ''));
        if ($label === '' || $value === '') {
            continue;
        }
        echo '<div class="blu-desc-block">';
        echo '<div class="blu-desc-block__label">' . e($label) . '</div>';
        echo '<div class="blu-desc-block__value">' . nl2br(e($value)) . '</div>';
        echo '</div>';
    }
    if ($compact && count($blocks) > $limit) {
        echo '<div class="small text-muted mt-2">+' . (count($blocks) - $limit) . ' câmpuri în descriere</div>';
    }
    echo '</div>';
}

function blu_render_product_card(array $view, string $csrf, bool $selected = false): void
{
    $pid = (int)($view['id'] ?? 0);
    $categoryLabel = $view['sub_category'] !== '' ? $view['sub_category'] : $view['main_category'];
    $statusClass = ($view['status'] ?? '') === 'Active' ? 'is-active' : 'is-draft';
    ?>
    <article class="blu-pcard<?= $selected ? ' blu-pcard--selected' : '' ?>" id="product-<?= e((string)$pid) ?>">
        <div class="blu-pcard__media">
            <?php if (!empty($view['featured'])): ?>
                <span class="blu-pcard__featured">TecDoc</span>
            <?php endif; ?>
            <?php if ($view['image'] !== ''): ?>
                <img src="<?= e($view['image']) ?>" alt="<?= e($view['title']) ?>" class="blu-pcard__img" loading="lazy">
            <?php else: ?>
                <div class="blu-pcard__img blu-pcard__img--empty"><i class="fa-solid fa-image" aria-hidden="true"></i> Fără imagine</div>
            <?php endif; ?>
        </div>

        <div class="blu-pcard__head">
            <h3 class="blu-pcard__title" title="<?= e($view['title']) ?>"><?= e($view['title']) ?></h3>
            <?php if ($categoryLabel !== ''): ?>
                <p class="blu-pcard__category"><?= e($categoryLabel) ?></p>
            <?php endif; ?>
            <?php if ($view['cod_oem'] !== '' && $view['cod_oem'] !== '-'): ?>
                <p class="blu-pcard__oem">OEM: <?= e($view['cod_oem']) ?></p>
            <?php endif; ?>
        </div>

        <div class="blu-pcard__prices">
            <div class="blu-pcard__price">
                <span class="blu-pcard__price-label">Preț bază</span>
                <strong class="blu-pcard__price-value"><?= e($view['pret_baza_display']) ?></strong>
            </div>
            <div class="blu-pcard__price blu-pcard__price--final">
                <span class="blu-pcard__price-label">Preț final</span>
                <strong class="blu-pcard__price-value"><?= e($view['pret_final_display']) ?></strong>
            </div>
        </div>

        <dl class="blu-pcard__specs">
            <?php
            $specs = [
                ['Producător', $view['supplier_brand'] !== '' ? $view['supplier_brand'] : '—'],
                ['Marcă auto', $view['car_brand'] !== '' ? $view['car_brand'] : '—'],
                ['Model', $view['model'] !== '' ? $view['model'] : '—'],
                ['Motorizare', $view['motorizare'] !== '' ? $view['motorizare'] : '—'],
                ['Categorie', $view['main_category'] !== '' ? $view['main_category'] : '—'],
                ['Subcategorie', $view['sub_category'] !== '' ? $view['sub_category'] : '—'],
                ['Stoc', $view['stoc'] !== '' ? $view['stoc'] : '1'],
            ];
            foreach ($specs as [$label, $value]):
            ?>
                <div class="blu-pcard__spec">
                    <dt><?= e($label) ?></dt>
                    <dd title="<?= e((string) $value) ?>"><?= e((string) $value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <div class="blu-pcard__meta">
            <span class="blu-pcard__status <?= e($statusClass) ?>"><?= e($view['status']) ?></span>
            <span class="blu-pcard__regula"><?= e($view['regula']) ?></span>
        </div>

        <?php if ($view['descriere'] !== ''): ?>
            <details class="blu-pcard__desc">
                <summary>Descriere TecDoc</summary>
                <div class="blu-pcard__desc-body">
                    <?php blu_render_product_description_blocks($view['description_blocks'], true); ?>
                </div>
            </details>
        <?php endif; ?>

        <div class="blu-pcard__footer">
            <a class="blu-pcard__action" href="?page=products&edit=<?= e((string)$pid) ?>#product-detail" title="Reaplică adaos"><i class="fa-solid fa-percent" aria-hidden="true"></i> Adaos</a>
            <a class="blu-pcard__action" href="?page=products&edit=<?= e((string)$pid) ?>#product-detail"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
            <form method="post" class="blu-pcard__action-form" onsubmit="return confirm('Ștergi produsul?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" value="<?= e((string)$pid) ?>">
                <button type="submit" class="blu-pcard__action blu-pcard__action--danger"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
            </form>
        </div>
    </article>
    <?php
}

function blu_render_products_grid(array $products, string $csrf, ?int $selectedId = null): void
{
    if ($products === []) {
        echo '<div class="text-center py-5 text-muted"><p class="mb-2">Niciun produs. Importă sau sincronizează din TecDoc.</p></div>';
        return;
    }
    echo '<div class="row g-3 blu-products-grid">';
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $view = blu_product_view_data($product);
        $pid = (int)($view['id'] ?? 0);
        echo '<div class="col-xxl-4 col-xl-6 col-lg-6">';
        blu_render_product_card($view, $csrf, $selectedId !== null && $selectedId === $pid);
        echo '</div>';
    }
    echo '</div>';
}

function blu_render_product_detail_panel(?array $editProduct, string $csrf): void
{
    if ($editProduct === null) {
        return;
    }
    $view = blu_product_view_data($editProduct);
    ?>
    <div class="card blu-detail-panel mb-3" id="product-detail">
        <div class="card-header card-no-border pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">Detaliu produs #<?= e((string)$view['id']) ?></h4>
                <p class="text-muted mb-0 small"><?= e($view['title']) ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-light text-dark">OEM: <?= e($view['cod_oem']) ?></span>
                <?php if ($view['cod_articol'] !== ''): ?>
                    <span class="badge bg-light text-dark">Art: <?= e($view['cod_articol']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="blu-detail-media">
                        <?php if ($view['image'] !== ''): ?>
                            <img src="<?= e($view['image']) ?>" alt="" class="img-fluid rounded">
                        <?php else: ?>
                            <div class="blu-pcard__img blu-pcard__img--empty rounded">Fără imagine</div>
                        <?php endif; ?>
                    </div>
                    <div class="blu-detail-meta mt-3">
                        <div><strong>Brand piesă:</strong> <?= e($view['supplier_brand'] ?: '—') ?></div>
                        <div><strong>Marca auto:</strong> <?= e($view['car_brand'] ?: '—') ?></div>
                        <div><strong>Model:</strong> <?= e($view['model'] ?: '—') ?></div>
                        <div><strong>Motorizare:</strong> <?= e($view['motorizare']) ?></div>
                        <div><strong>Categorie:</strong> <?= e($view['product_category']) ?></div>
                    </div>
                    <?php $bd = $view['pret_breakdown']; ?>
                    <div class="blu-detail-meta mt-3 p-2" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                        <div class="fw-bold mb-1">Calcul preț (Adaos comercial)</div>
                        <div class="d-flex justify-content-between"><span>Preț bază</span><span><?= e(blu_lei($bd['baza'])) ?></span></div>
                        <?php foreach ($bd['detalii_cheltuieli'] as $c): ?>
                            <div class="d-flex justify-content-between text-muted"><span>+ <?= e($c['nume']) ?></span><span><?= e(blu_lei($c['valoare'])) ?></span></div>
                        <?php endforeach; ?>
                        <div class="d-flex justify-content-between"><span>+ Adaos comercial</span><span><?= e(blu_lei($bd['adaos_val'])) ?></span></div>
                        <div class="d-flex justify-content-between"><span>+ TVA</span><span><?= e(blu_lei($bd['tva_val'])) ?></span></div>
                        <div class="d-flex justify-content-between fw-bold text-success" style="border-top:1px dashed #cbd5e1;margin-top:.35rem;padding-top:.35rem;"><span>Preț final</span><span><?= e(blu_lei($bd['pret_final'])) ?></span></div>
                        <div class="small text-muted mt-1"><a href="?page=furnizori&tab=adaos">Modifică adaos / TVA / cheltuieli</a></div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <h5 class="mb-3">Cartelă produs (model strict)</h5>
                    <?php if (blu_description_uses_card_template($view['descriere'])): ?>
                        <?php blu_render_card_template_preview($view['title'], $view['descriere']); ?>
                    <?php else: ?>
                        <?php blu_render_product_description_blocks($view['description_blocks'], false); ?>
                    <?php endif; ?>
                </div>
            </div>
            <hr>
            <form method="post" class="theme-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" value="<?= e((string)$view['id']) ?>">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">Imagine produs</label>
                        <div class="d-flex align-items-start gap-3 flex-wrap">
                            <div style="width:96px;height:96px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;">
                                <?php if ($view['image'] !== ''): ?>
                                    <img src="<?= e($view['image']) ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain;">
                                <?php else: ?>
                                    <span class="text-muted small">fără</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1" style="min-width:240px;">
                                <input class="form-control mb-2" name="imagine" value="<?= e($editProduct['imagine'] ?? '') ?>" placeholder="URL imagine (ex: https://...)">
                                <input class="form-control" type="file" name="imagine_file" accept="image/*">
                                <div class="form-text">Lasă URL gol și încarcă un fișier, sau pune un link direct. Folosit când TecDoc nu are imagine.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nume anunț</label>
                        <input class="form-control" name="nume" value="<?= e($editProduct['nume'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Cod OEM / piesă</label>
                        <input class="form-control" name="cod_oem" value="<?= e($editProduct['cod_oem'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Subcategorie PieseAuto</label>
                        <input class="form-control" name="categorie" value="<?= e($editProduct['categorie'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Preț bază / cost (lei)</label>
                        <input class="form-control" name="pret" value="<?= e($editProduct['pret'] ?? '') ?>" placeholder="ex: 80">
                        <div class="form-text">Prețul final se calculează automat (adaos + cheltuieli + TVA).</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Stoc</label>
                        <input class="form-control" name="stoc" value="<?= e($editProduct['stoc'] ?? '1') ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Descriere (editabilă)</label>
                        <textarea class="form-control code-area" name="descriere" rows="12"><?= e($editProduct['descriere'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" type="submit">Salvează modificările</button>
                    <a class="btn btn-outline-secondary" href="?page=products">Închide detaliu</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
