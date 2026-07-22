<?php
declare(strict_types=1);

require_once __DIR__ . '/visuals.php';

/** @return list<array{id:string,name:string,slug:string}> */
function blu_gbg_car_brands_static_list(): array
{
    static $brands = null;
    if ($brands !== null) {
        return $brands;
    }

    $names = [
        'ALFA ROMEO', 'AUDI', 'AUTOBIANCHI', 'BMW', 'BYD', 'CHANGAN', 'CHERY', 'CHRYSLER', 'CITROËN', 'CUPRA',
        'DACIA', 'DAEWOO - CHEVROLET', 'DAIHATSU', 'DODGE', 'DR', 'DS', 'FIAT', 'FORD', 'GAC', 'GEELY',
        'HONDA', 'HYUNDAI', 'INFINITI', 'ISUZU', 'IVECO', 'JAECOO', 'JAGUAR', 'JEEP', 'KGM', 'KIA',
        'LADA', 'LANCIA', 'LAND ROVER', 'LEAPMOTOR', 'LEXUS', 'LOTUS', 'LYNK & CO', 'MAN', 'MASERATI', 'MAZDA',
        'MERCEDES', 'MG', 'MINI', 'MITSUBISHI', 'NISSAN', 'OMODA', 'OPEL', 'PEUGEOT', 'PIAGGIO', 'PORSCHE',
        'RENAULT', 'ROVER', 'SAAB', 'SEAT', 'SKODA', 'SMART', 'SSANGYONG', 'SUBARU', 'SUZUKI', 'TESLA',
        'TOYOTA', 'VOYAH', 'VOLVO', 'VW', 'XEV', 'XPENG', 'ZASTAVA', 'ZEEKR', 'UNIVERSAL',
    ];

    $brands = [];
    foreach ($names as $i => $name) {
        $slug = blu_gbg_brand_slug($name);
        $brands[] = [
            'id' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => $slug,
        ];
    }

    return $brands;
}

/** @return list<array{id:string,name:string,slug:string}> */
function blu_gbg_car_brands(): array
{
    require_once __DIR__ . '/gbg_structure.php';
    return blu_gbg_structure_brands();
}

function blu_gbg_brand_slug(string $name): string
{
    $slug = mb_strtolower(trim($name));
    $slug = str_replace(['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', 'ë', 'ö', 'ü'], ['a', 'a', 'i', 's', 't', 's', 't', 'e', 'o', 'u'], $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'brand';
}

function blu_gbg_brand_logo_url(string $slug, string $name = ''): string
{
    $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'shop' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'brands';
    $candidates = [$slug];
    if ($name !== '') {
        $candidates[] = blu_gbg_brand_slug($name);
    }
    $candidates = array_values(array_unique(array_filter($candidates)));

    foreach ($candidates as $candidate) {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
            $file = $base . DIRECTORY_SEPARATOR . $candidate . '.' . $ext;
            if (is_file($file)) {
                return blu_shop_asset('img/brands/' . $candidate . '.' . $ext);
            }
        }
    }
    return '';
}

function blu_gbg_brand_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        if ($part === '' || $part === '-') {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($out) >= 3) {
            break;
        }
    }
    return $out !== '' ? $out : '?';
}

/** @return list<array{id:string,label:string,icon:string}> */
function blu_gbg_main_categories_static_fallback(): array
{
    return [
        ['id' => 'caroserie', 'label' => 'Caroserie', 'icon' => 'fa-solid fa-car'],
        ['id' => 'motor', 'label' => 'Motor', 'icon' => 'fa-solid fa-gear'],
        ['id' => 'transmisie', 'label' => 'Transmisie', 'icon' => 'fa-solid fa-gears'],
        ['id' => 'frane', 'label' => 'Frânare', 'icon' => 'fa-solid fa-circle-stop'],
        ['id' => 'suspensie', 'label' => 'Suspensie', 'icon' => 'fa-solid fa-arrows-up-down'],
        ['id' => 'directie', 'label' => 'Direcție', 'icon' => 'fa-solid fa-dharmachakra'],
        ['id' => 'evacuare', 'label' => 'Evacuare', 'icon' => 'fa-solid fa-smog'],
        ['id' => 'racire', 'label' => 'Răcire', 'icon' => 'fa-solid fa-fan'],
        ['id' => 'electrica', 'label' => 'Electrică', 'icon' => 'fa-solid fa-bolt'],
        ['id' => 'filtre', 'label' => 'Filtre', 'icon' => 'fa-solid fa-filter'],
        ['id' => 'interior', 'label' => 'Interior', 'icon' => 'fa-solid fa-couch'],
        ['id' => 'faruri', 'label' => 'Faruri & lumini', 'icon' => 'fa-solid fa-lightbulb'],
    ];
}

/** @return list<array{id:string,label:string,img:string,query:string}> */
function blu_gbg_special_categories_static_fallback(): array
{
    return [
        ['id' => '300', 'label' => 'Sisteme răcire', 'img' => '300', 'query' => 'racire'],
        ['id' => '600', 'label' => 'Tuning', 'img' => '600', 'query' => 'tuning'],
        ['id' => '400', 'label' => 'Amortizoare', 'img' => '400', 'query' => 'amortizor'],
        ['id' => '700', 'label' => 'Rezervoare apă', 'img' => '700', 'query' => 'rezervor'],
        ['id' => '800', 'label' => 'Contacte', 'img' => '800', 'query' => 'contact'],
        ['id' => '200', 'label' => 'Lămpi', 'img' => '200', 'query' => 'lampa'],
        ['id' => '900', 'label' => 'Kit montaj', 'img' => '900', 'query' => 'kit'],
        ['id' => '100', 'label' => 'Lanțuri', 'img' => '100', 'query' => 'lant'],
        ['id' => '910', 'label' => 'Cutii filtru aer', 'img' => '910', 'query' => 'filtru aer'],
        ['id' => '920', 'label' => 'Oprire ușă', 'img' => '920', 'query' => 'usa'],
        ['id' => '930', 'label' => 'Ghidaje ușă culisantă', 'img' => '930', 'query' => 'ghidaj'],
    ];
}

/** @return list<array{id:string,label:string,icon:string}> */
function blu_gbg_main_categories(): array
{
    return blu_gbg_main_categories_static_fallback();
}

/** @return list<array{id:string,label:string,img:string,query:string}> */
function blu_gbg_special_categories(): array
{
    if (!function_exists('blu_gbg_structure_special_categories')) {
        require_once __DIR__ . '/gbg_structure.php';
    }
    return blu_gbg_structure_special_categories();
}

function blu_gbg_special_category_image_url(string $imageKey): string
{
    $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'shop' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR . 'special';
    foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
        $file = $base . DIRECTORY_SEPARATOR . $imageKey . '.' . $ext;
        if (is_file($file)) {
            return blu_shop_asset('img/categories/special/' . $imageKey . '.' . $ext);
        }
    }
    return blu_shop_asset('img/categories/cat-default.svg');
}

/** Listă fixă de mărci (ordinea GBG), fără sortare dinamică. */
function blu_gbg_static_brands(): array
{
    return blu_gbg_car_brands();
}
