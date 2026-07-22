<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';

function blu_website_cms_file(): string
{
  return blu_data_dir() . DIRECTORY_SEPARATOR . 'website_pages.json';
}

/** @return array<string, array<string, string>> */
function blu_website_cms_defaults(): array
{
  return [
    'despre' => [
      'hero_eyebrow' => 'Blue-Car · piese auto · România',
      'hero_title' => 'Experiență de magazin online, focus pe compatibilitate',
      'hero_lead' => 'Nu vindem doar piese — identificăm varianta corectă OEM, confirmăm stocul cu furnizorii și livrăm rapid, cu transparență totală asupra prețului.',
      'stat1_label' => 'Piese indexate',
      'stat2_label' => 'Categorii active',
      'stat3_value' => '24–72h',
      'stat3_label' => 'Livrare din stoc',
      'stat4_value' => '100%',
      'stat4_label' => 'Suport compatibilitate',
      'trust1_title' => 'Piese OEM verificate',
      'trust1_sub' => 'Coduri clare, compatibilitate confirmată',
      'trust2_title' => 'Livrare rapidă RO',
      'trust2_sub' => 'Expediere din stoc sau la comandă',
      'trust3_title' => 'Plată securizată',
      'trust3_sub' => 'Confirmare telefonică, fără surprize',
      'trust4_title' => 'Suport dedicat',
      'trust4_sub' => 'Consultanță compatibilitate gratuită',
      'story_p1' => 'Blue-Car s-a născut din nevoia de a aduce pe piața românească un magazin de piese auto <strong>clar, rapid și profesionist</strong> — fără liste haotice și fără prețuri ascunse.',
      'story_p2' => 'Catalogul nostru este alimentat din surse verificate, cu coduri OEM vizibile și filtre inteligente după categorie, marcă și compatibilitate vehicul.',
      'story_p3' => 'Fie că ești proprietar, service independent sau flotă, primești aceeași atenție: identificăm piesa, confirmăm termenul și livrăm cu documente complete.',
      'value1_title' => 'Căutare inteligentă',
      'value1_text' => 'Filtru după OEM, categorie sau vehicul — găsești piesa în câteva secunde.',
      'value2_title' => 'Preț transparent',
      'value2_text' => 'Lei clari, fără costuri surpriză. Oferta se confirmă telefonic înainte de expediere.',
      'value3_title' => 'Stoc actualizat',
      'value3_text' => 'Sincronizare cu furnizorii B2B — vezi ce e disponibil imediat sau la comandă.',
      'values_li1' => 'Piese noi, ambalaj original sau echivalent certificat',
      'values_li2' => 'Verificare compatibilitate înainte de expediere',
      'values_li3' => 'Factură fiscală pentru persoane juridice',
      'values_li4' => 'Retur 14 zile pentru produse neutilizate',
      'values_li5' => 'Suport post-vânzare dedicat',
      'values_p1' => '<strong>Garanția calității</strong> înseamnă că fiecare comandă trece prin verificare OEM. Dacă există dubii, te contactăm înainte de a trimite coletul — preferăm o confirmare în plus decât o piesă greșită.',
      'process1_title' => 'Cauți în catalog',
      'process1_text' => 'Folosește filtrele după categorie, cod OEM sau selectorul de vehicul de pe homepage.',
      'process2_title' => 'Adaugi în coș',
      'process2_text' => 'Verifici cantitatea, prețul și detaliile produsului — totul vizibil înainte de comandă.',
      'process3_title' => 'Finalizezi & confirmăm',
      'process3_text' => 'Trimiți comanda online; echipa Blue-Car te sună pentru confirmare plată și livrare.',
      'process4_title' => 'Primești coletul',
      'process4_text' => 'Livrare curier 24–72h din stoc. Urmărire AWB comunicată telefonic sau email.',
      'cta_title' => 'Ai nevoie de ajutor la identificarea piesei?',
      'cta_text' => 'Scrie-ne codul OEM sau trimite o poză — răspundem rapid cu varianta compatibilă.',
    ],
    'contact' => [
      'hero_eyebrow' => 'Suport Blue-Car · răspuns rapid',
      'hero_title' => 'Suntem aici pentru piesa potrivită',
      'hero_lead' => 'Trimite codul OEM, marca mașinii sau poza piesei — identificăm varianta corectă și îți confirmăm prețul și termenul de livrare.',
      'trust1_title' => 'Piese OEM verificate',
      'trust1_sub' => 'Coduri clare, compatibilitate confirmată',
      'trust2_title' => 'Livrare rapidă RO',
      'trust2_sub' => 'Expediere din stoc sau la comandă',
      'trust3_title' => 'Plată securizată',
      'trust3_sub' => 'Confirmare telefonică, fără surprize',
      'trust4_title' => 'Suport dedicat',
      'trust4_sub' => 'Consultanță compatibilitate gratuită',
      'faq1_q' => 'Cum identific piesa corectă?',
      'faq1_a' => 'Trimite codul OEM de pe eticheta veche, VIN-ul mașinii sau o poză clară. Echipa noastră verifică compatibilitatea în catalogul furnizorilor.',
      'faq2_q' => 'Cât durează livrarea?',
      'faq2_a' => 'Piesele din stoc pleacă în 24h. Comenzile speciale se confirmă telefonic cu termen estimativ de 2–5 zile lucrătoare.',
      'faq3_q' => 'Pot returna o piesă?',
      'faq3_a' => 'Produsele neutilizate, în ambalaj original, pot fi returnate în 14 zile. Piesele montate pe vehicul nu intră în retur.',
      'aside1_title' => 'Ofertă rapidă',
      'aside1_text' => 'Trimite codul OEM pe WhatsApp sau telefon — primești preț și disponibilitate în câteva minute.',
      'aside2_title' => 'Cum funcționează',
      'aside2_step1' => 'Cauți în catalog sau ne scrii',
      'aside2_step2' => 'Confirmăm compatibilitatea OEM',
      'aside2_step3' => 'Plasezi comanda · livrăm rapid',
      'aside3_title' => 'Explorează catalogul',
      'aside3_text' => 'piese indexate — filtrează după categorie sau cod.',
      'cta_eyebrow' => 'Blue-Car · piese auto online',
      'cta_title' => 'Gata să comanzi?',
      'cta_text' => 'Adaugă produse în coș sau solicită ofertă personalizată pentru flotă sau service.',
    ],
    'index' => [
      'home_section_title' => 'Produse recomandate',
      'home_section_link' => 'Catalog complet',
      'home_empty_text' => 'Catalogul se populează din panoul de administrare.',
    ],
  ];
}

/** @return array<string, array<string, string>> */
function blu_website_cms_pages_registry(): array
{
  return [
    'despre' => ['label' => 'Despre noi', 'file' => 'despre.php', 'icon' => 'fa-circle-info'],
    'contact' => ['label' => 'Contact', 'file' => 'contact.php', 'icon' => 'fa-envelope'],
    'index' => ['label' => 'Magazin (Acasă)', 'file' => 'index.php', 'icon' => 'fa-store'],
  ];
}

/** @return array<string, array<string, string>> */
function blu_website_cms_load(): array
{
  $defaults = blu_website_cms_defaults();
  $saved = blu_read_json_file(blu_website_cms_file(), []);
  if (!is_array($saved)) {
    $saved = [];
  }
  $merged = $defaults;
  foreach ($saved as $page => $fields) {
    if (!is_array($fields)) {
      continue;
    }
    if (!isset($merged[$page])) {
      $merged[$page] = [];
    }
    foreach ($fields as $key => $value) {
      $merged[$page][(string) $key] = (string) $value;
    }
  }
  return $merged;
}

function blu_cms_get(string $page, string $key, ?string $default = null): string
{
  static $cache = null;
  if ($cache === null) {
    $cache = blu_website_cms_load();
  }
  if (isset($cache[$page][$key])) {
    return (string) $cache[$page][$key];
  }
  if ($default !== null) {
    return $default;
  }
  return blu_website_cms_defaults()[$page][$key] ?? '';
}

function blu_cms_edit_mode(): bool
{
  if (empty($_GET['blu_cms_edit']) || (string) $_GET['blu_cms_edit'] !== '1') {
    return false;
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
  }
  return !empty($_SESSION['admin']);
}

function blu_cms_page_slug(): string
{
  return trim((string) ($GLOBALS['bluCmsPage'] ?? ''));
}

/**
 * @param array<string, string> $attrs
 */
function blu_cms_tag(string $page, string $key, string $tag, ?string $default = null, array $attrs = [], bool $html = false): void
{
  $value = blu_cms_get($page, $key, $default);
  $cmsKey = htmlspecialchars($page . '.' . $key, ENT_QUOTES, 'UTF-8');
  $attrStr = '';
  foreach ($attrs as $name => $val) {
    $attrStr .= ' ' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
      . '="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"';
  }
  if (blu_cms_edit_mode()) {
    echo '<' . $tag . $attrStr
      . ' data-cms="' . $cmsKey . '" data-cms-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '"'
      . ' data-cms-html="' . ($html ? '1' : '0') . '" contenteditable="true" spellcheck="true">';
    echo $html ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    echo '</' . $tag . '>';
    return;
  }
  echo '<' . $tag . $attrStr . '>';
  echo $html ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  echo '</' . $tag . '>';
}

/** @param array<string, string> $fields */
function blu_website_cms_save(string $page, array $fields): array
{
  $registry = blu_website_cms_pages_registry();
  if (!isset($registry[$page])) {
    return ['ok' => false, 'message' => 'Pagină necunoscută.'];
  }
  $defaults = blu_website_cms_defaults();
  $allowed = array_keys($defaults[$page] ?? []);
  if ($allowed === []) {
    return ['ok' => false, 'message' => 'Pagina nu are câmpuri editabile.'];
  }

  $all = blu_read_json_file(blu_website_cms_file(), []);
  if (!is_array($all)) {
    $all = [];
  }
  if (!isset($all[$page]) || !is_array($all[$page])) {
    $all[$page] = [];
  }

  foreach ($fields as $key => $value) {
    $key = (string) $key;
    if (!in_array($key, $allowed, true)) {
      continue;
    }
    $all[$page][$key] = trim((string) $value);
  }

  $dir = blu_data_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $ok = file_put_contents(
    blu_website_cms_file(),
    json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  );
  if ($ok === false) {
    return ['ok' => false, 'message' => 'Nu am putut salva conținutul.'];
  }
  return ['ok' => true, 'message' => 'Conținut salvat.'];
}
