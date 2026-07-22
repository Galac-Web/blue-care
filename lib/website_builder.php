<?php
declare(strict_types=1);

require_once __DIR__ . '/website_cms.php';

function blu_builder_file(): string
{
  return blu_data_dir() . DIRECTORY_SEPARATOR . 'website_builder.json';
}

/** @return array<string, array<string, mixed>> */
function blu_builder_block_types(): array
{
  return [
    'heading' => [
      'label' => 'Titlu',
      'icon' => 'fa-heading',
      'desc' => 'Titlu secțiune H2 sau H3',
      'defaults' => ['level' => 'h2', 'text' => 'Titlu nou'],
      'fields' => [
        ['key' => 'level', 'label' => 'Nivel', 'type' => 'select', 'options' => ['h2' => 'Mare (H2)', 'h3' => 'Mediu (H3)']],
        ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
      ],
    ],
    'text' => [
      'label' => 'Paragraf',
      'icon' => 'fa-align-left',
      'desc' => 'Text liber, suportă bold și link',
      'defaults' => ['html' => '<p>Scrie aici conținutul blocului. Poți folosi <strong>bold</strong> și linkuri.</p>'],
      'fields' => [
        ['key' => 'html', 'label' => 'Conținut', 'type' => 'html'],
      ],
    ],
    'message' => [
      'label' => 'Mesaj / Alertă',
      'icon' => 'fa-message',
      'desc' => 'Casetă informativă colorată',
      'defaults' => ['variant' => 'info', 'title' => 'Informare', 'text' => 'Mesaj pentru vizitatori.'],
      'fields' => [
        ['key' => 'variant', 'label' => 'Stil', 'type' => 'select', 'options' => ['info' => 'Info (albastru)', 'success' => 'Succes (verde)', 'warning' => 'Atenție (galben)', 'danger' => 'Important (roșu)']],
        ['key' => 'title', 'label' => 'Titlu', 'type' => 'text'],
        ['key' => 'text', 'label' => 'Mesaj', 'type' => 'textarea'],
      ],
    ],
    'image' => [
      'label' => 'Imagine',
      'icon' => 'fa-image',
      'desc' => 'Fotografie sau banner',
      'defaults' => ['url' => '', 'alt' => 'Imagine', 'caption' => '', 'link' => ''],
      'fields' => [
        ['key' => 'url', 'label' => 'URL imagine', 'type' => 'text'],
        ['key' => 'alt', 'label' => 'Text alternativ', 'type' => 'text'],
        ['key' => 'caption', 'label' => 'Legendă (opțional)', 'type' => 'text'],
        ['key' => 'link', 'label' => 'Link la click (opțional)', 'type' => 'text'],
      ],
    ],
    'button' => [
      'label' => 'Buton',
      'icon' => 'fa-hand-pointer',
      'desc' => 'Buton call-to-action',
      'defaults' => ['label' => 'Află mai multe', 'url' => 'catalog.php', 'style' => 'accent'],
      'fields' => [
        ['key' => 'label', 'label' => 'Text buton', 'type' => 'text'],
        ['key' => 'url', 'label' => 'Link', 'type' => 'text'],
        ['key' => 'style', 'label' => 'Stil', 'type' => 'select', 'options' => ['accent' => 'Accent (verde)', 'ghost' => 'Contur', 'glow' => 'Evidențiat']],
      ],
    ],
    'columns' => [
      'label' => '2 coloane',
      'icon' => 'fa-columns',
      'desc' => 'Text în două coloane',
      'defaults' => [
        'left' => '<p><strong>Coloana stânga</strong><br>Conținut text.</p>',
        'right' => '<p><strong>Coloana dreapta</strong><br>Conținut text.</p>',
      ],
      'fields' => [
        ['key' => 'left', 'label' => 'Coloana stânga', 'type' => 'html'],
        ['key' => 'right', 'label' => 'Coloana dreapta', 'type' => 'html'],
      ],
    ],
    'spacer' => [
      'label' => 'Spațiu',
      'icon' => 'fa-arrows-up-down',
      'desc' => 'Spațiu vertical între secțiuni',
      'defaults' => ['size' => 'md'],
      'fields' => [
        ['key' => 'size', 'label' => 'Înălțime', 'type' => 'select', 'options' => ['sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare']],
      ],
    ],
    'divider' => [
      'label' => 'Separator',
      'icon' => 'fa-minus',
      'desc' => 'Linie de separare',
      'defaults' => [],
      'fields' => [],
    ],
  ];
}

/** @return array<string, list<string>> */
function blu_builder_page_zones(): array
{
  return [
    'despre' => ['after_hero', 'before_footer'],
    'contact' => ['after_hero', 'before_footer'],
    'index' => ['after_hero', 'before_footer'],
  ];
}

/** @return list<array<string, mixed>> */
function blu_builder_load_blocks(string $page): array
{
  $all = blu_read_json_file(blu_builder_file(), []);
  if (!is_array($all) || !isset($all[$page]) || !is_array($all[$page])) {
    return [];
  }
  $blocks = [];
  foreach ($all[$page] as $block) {
    if (!is_array($block)) {
      continue;
    }
    $normalized = blu_builder_normalize_block($block);
    if ($normalized !== null) {
      $blocks[] = $normalized;
    }
  }
  return $blocks;
}

/** @param array<string, mixed> $block */
function blu_builder_normalize_block(array $block): ?array
{
  $types = blu_builder_block_types();
  $type = trim((string) ($block['type'] ?? ''));
  if (!isset($types[$type])) {
    return null;
  }
  $id = trim((string) ($block['id'] ?? ''));
  if ($id === '') {
    $id = blu_builder_new_id();
  }
  $zone = trim((string) ($block['zone'] ?? 'after_hero'));
  $propsIn = is_array($block['props'] ?? null) ? $block['props'] : [];
  $props = $types[$type]['defaults'];
  foreach ($propsIn as $k => $v) {
    $props[(string) $k] = is_string($v) ? $v : (string) json_encode($v);
  }
  return [
    'id' => $id,
    'type' => $type,
    'zone' => $zone,
    'props' => $props,
  ];
}

function blu_builder_new_id(): string
{
  return 'blk_' . date('Ymd') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
}

/** @param list<array<string, mixed>> $blocks */
function blu_builder_save_blocks(string $page, array $blocks): array
{
  $registry = blu_website_cms_pages_registry();
  if (!isset($registry[$page])) {
    return ['ok' => false, 'message' => 'Pagină necunoscută.'];
  }
  $zones = blu_builder_page_zones()[$page] ?? ['after_hero'];
  $normalized = [];
  foreach ($blocks as $block) {
    if (!is_array($block)) {
      continue;
    }
    $row = blu_builder_normalize_block($block);
    if ($row === null) {
      continue;
    }
    if (!in_array($row['zone'], $zones, true)) {
      $row['zone'] = $zones[0];
    }
    $normalized[] = $row;
  }
  $all = blu_read_json_file(blu_builder_file(), []);
  if (!is_array($all)) {
    $all = [];
  }
  $all[$page] = $normalized;
  $dir = blu_data_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $ok = file_put_contents(
    blu_builder_file(),
    json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  );
  if ($ok === false) {
    return ['ok' => false, 'message' => 'Nu am putut salva blocurile.'];
  }
  return ['ok' => true, 'message' => 'Blocuri salvate.', 'blocks' => $normalized];
}

function blu_builder_sanitize_html(string $html): string
{
  return strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><span><h2><h3><h4>');
}

function blu_builder_esc(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** @param array<string, mixed> $block */
function blu_builder_render_block(array $block, bool $editMode = false): void
{
  $type = (string) ($block['type'] ?? '');
  $id = (string) ($block['id'] ?? '');
  $props = is_array($block['props'] ?? null) ? $block['props'] : [];
  $propsJson = htmlspecialchars(json_encode($props, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

  $wrapOpen = $editMode
    ? '<div class="blu-block" data-block-id="' . blu_builder_esc($id) . '" data-block-type="' . blu_builder_esc($type) . '" data-block-props=\'' . $propsJson . '\'>'
    : '';
  $wrapClose = $editMode ? '</div>' : '';

  echo $wrapOpen;

  switch ($type) {
    case 'heading':
      $level = in_array(($props['level'] ?? 'h2'), ['h2', 'h3'], true) ? (string) $props['level'] : 'h2';
      $text = blu_builder_esc((string) ($props['text'] ?? ''));
      echo '<div class="blu-block-heading shop-woo-section reveal"><' . $level . ' class="blu-block-heading__text">' . $text . '</' . $level . '></div>';
      break;

    case 'text':
      echo '<div class="blu-block-text shop-woo-prose shop-woo-section reveal">' . blu_builder_sanitize_html((string) ($props['html'] ?? '')) . '</div>';
      break;

    case 'message':
      $variant = (string) ($props['variant'] ?? 'info');
      if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
        $variant = 'info';
      }
      echo '<div class="blu-block-message shop-woo-section reveal">';
      echo '<div class="blu-block-alert blu-block-alert--' . blu_builder_esc($variant) . '">';
      echo '<strong class="blu-block-alert__title">' . blu_builder_esc((string) ($props['title'] ?? '')) . '</strong>';
      echo '<p class="blu-block-alert__text">' . nl2br(blu_builder_esc((string) ($props['text'] ?? ''))) . '</p>';
      echo '</div></div>';
      break;

    case 'image':
      $url = trim((string) ($props['url'] ?? ''));
      $alt = blu_builder_esc((string) ($props['alt'] ?? ''));
      $caption = trim((string) ($props['caption'] ?? ''));
      $link = trim((string) ($props['link'] ?? ''));
      if ($url !== '') {
        echo '<figure class="blu-block-image shop-woo-section reveal">';
        $img = '<img src="' . blu_builder_esc($url) . '" alt="' . $alt . '" loading="lazy">';
        if ($link !== '') {
          echo '<a href="' . blu_builder_esc($link) . '">' . $img . '</a>';
        } else {
          echo $img;
        }
        if ($caption !== '') {
          echo '<figcaption>' . blu_builder_esc($caption) . '</figcaption>';
        }
        echo '</figure>';
      } elseif ($editMode) {
        echo '<div class="blu-block-image blu-block-image--placeholder shop-woo-section reveal"><i class="fa-solid fa-image"></i> Adaugă URL imagine în panoul din dreapta</div>';
      }
      break;

    case 'button':
      $label = blu_builder_esc((string) ($props['label'] ?? 'Click'));
      $url = blu_builder_esc((string) ($props['url'] ?? '#'));
      $style = (string) ($props['style'] ?? 'accent');
      $btnClass = match ($style) {
        'ghost' => 'shop-btn-ghost',
        'glow' => 'shop-btn-glow',
        default => 'shop-btn-accent',
      };
      echo '<div class="blu-block-button shop-woo-section reveal"><a class="shop-btn ' . $btnClass . '" href="' . $url . '">' . $label . '</a></div>';
      break;

    case 'columns':
      echo '<div class="blu-block-columns shop-woo-section reveal"><div class="blu-block-columns__grid">';
      echo '<div class="blu-block-columns__col shop-woo-prose">' . blu_builder_sanitize_html((string) ($props['left'] ?? '')) . '</div>';
      echo '<div class="blu-block-columns__col shop-woo-prose">' . blu_builder_sanitize_html((string) ($props['right'] ?? '')) . '</div>';
      echo '</div></div>';
      break;

    case 'spacer':
      $size = in_array(($props['size'] ?? 'md'), ['sm', 'md', 'lg'], true) ? (string) $props['size'] : 'md';
      echo '<div class="blu-block-spacer blu-block-spacer--' . blu_builder_esc($size) . '" aria-hidden="true"></div>';
      break;

    case 'divider':
      echo '<hr class="blu-block-divider shop-woo-section reveal">';
      break;
  }

  if ($editMode) {
    echo '<div class="blu-block-controls" aria-hidden="true">';
    echo '<button type="button" class="blu-block-ctrl" data-block-act="up" title="Mută sus"><i class="fa-solid fa-arrow-up"></i></button>';
    echo '<button type="button" class="blu-block-ctrl" data-block-act="down" title="Mută jos"><i class="fa-solid fa-arrow-down"></i></button>';
    echo '<button type="button" class="blu-block-ctrl" data-block-act="edit" title="Editează"><i class="fa-solid fa-pen"></i></button>';
    echo '<button type="button" class="blu-block-ctrl blu-block-ctrl--danger" data-block-act="delete" title="Șterge"><i class="fa-solid fa-trash"></i></button>';
    echo '</div>';
  }

  echo $wrapClose;
}

function blu_builder_render_zone(string $page, string $zone): void
{
  $editMode = blu_cms_edit_mode();
  $blocks = array_values(array_filter(
    blu_builder_load_blocks($page),
    static fn(array $b): bool => (string) ($b['zone'] ?? '') === $zone
  ));
  $zoneLabel = match ($zone) {
    'after_hero' => 'După hero',
    'before_footer' => 'Înainte de footer',
    default => $zone,
  };
  echo '<div class="blu-builder-zone" data-builder-zone="' . blu_builder_esc($zone) . '" data-builder-page="' . blu_builder_esc($page) . '" data-zone-label="' . blu_builder_esc($zoneLabel) . '">';
  if ($editMode && $blocks === []) {
    echo '<div class="blu-builder-zone-empty"><i class="fa-solid fa-plus"></i> Zonă constructor: ' . blu_builder_esc($zoneLabel) . ' — adaugă blocuri din panoul din dreapta</div>';
  }
  foreach ($blocks as $block) {
    blu_builder_render_block($block, $editMode);
  }
  if ($editMode) {
    echo '<button type="button" class="blu-builder-zone-add" data-zone-add="' . blu_builder_esc($zone) . '"><i class="fa-solid fa-plus"></i> Adaugă bloc aici</button>';
  }
  echo '</div>';
}

function blu_builder_export_js_config(string $page): array
{
  return [
    'page' => $page,
    'blocks' => blu_builder_load_blocks($page),
    'types' => blu_builder_block_types(),
    'zones' => blu_builder_page_zones()[$page] ?? ['after_hero'],
    'zoneLabels' => [
      'after_hero' => 'După hero',
      'before_footer' => 'Înainte de footer',
    ],
  ];
}
