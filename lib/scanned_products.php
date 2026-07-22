<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_import.php';
require_once __DIR__ . '/products_store.php';
require_once __DIR__ . '/pieseauto_categories.php';

/**
 * Produse scanate + importate, pregătite pentru panoul PieseAuto.
 *
 * @return list<array{id:string,title:string,description:string,price:float,category_name:string,category_full:string,car_brand:string,image_url:string,images:list<string>,updated_at:string}>
 */
function blu_pieseauto_scanned_items(string $q = '', int $limit = 200): array
{
    $items = [];
    $cards = blu_load_imported_cards_for_admin();

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $status = (string)($card['status'] ?? 'imported');
        if ($status !== '' && !in_array($status, ['imported', 'pending'], true)) {
            continue;
        }

        $title = trim((string)($card['title'] ?? $card['ad_title'] ?? $card['nume'] ?? ''));
        if ($title === '') {
            continue;
        }

        $description = trim((string)($card['descriere'] ?? $card['description'] ?? $card['ad_description'] ?? ''));
        $brand = trim((string)($card['car_brand'] ?? $card['brand'] ?? $card['marca_masina'] ?? ''));
        $paMeta = blu_pieseauto_classify_product($card);
        $mainCategory = trim((string)($paMeta['main_category'] ?? ''));
        $subCategory = trim((string)($paMeta['sub_category'] ?? ''));
        $productCategory = trim((string)($paMeta['product_category'] ?? ''));
        $categoryForPieseauto = blu_pieseauto_robot_category_name(array_merge($card, [
            'main_category' => $mainCategory,
            'sub_category' => $subCategory,
            'pieseauto_category' => $subCategory,
            'product_category' => $productCategory,
        ]));
        $car = trim((string)($card['car'] ?? ''));

        $imageUrl = trim((string)($card['image'] ?? $card['imagine'] ?? ''));
        $images = [];
        if ($imageUrl !== '') {
            $images[] = $imageUrl;
        }

        $rawImages = $card['images_json'] ?? $card['main_image'] ?? null;
        if (is_string($rawImages) && $rawImages !== '') {
            $decoded = json_decode($rawImages, true);
            if (is_array($decoded)) {
                foreach ($decoded as $img) {
                    if (is_string($img) && $img !== '') {
                        $images[] = $img;
                    } elseif (is_array($img) && !empty($img['url'])) {
                        $images[] = (string)$img['url'];
                    }
                }
            } elseif (filter_var($rawImages, FILTER_VALIDATE_URL)) {
                $images[] = $rawImages;
            }
        }

        $images = array_values(array_unique(array_filter($images)));
        $price = 0.0;
        foreach (['price_ron_final', 'pret', 'price'] as $pk) {
            if (isset($card[$pk]) && (float)$card[$pk] > 0) {
                $price = (float)$card[$pk];
                break;
            }
        }

        $items[] = [
            'id' => (string)($card['product_id'] ?? $card['id'] ?? md5($title . '|' . ($card['cod_oem'] ?? ''))),
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
            'price' => $price > 0 ? $price : (!empty($card['gbg_price_missing']) ? 0 : 100),
            'category_name' => $categoryForPieseauto,
            'category_full' => $productCategory !== '' ? $productCategory : $categoryForPieseauto,
            'main_category' => $mainCategory,
            'sub_category' => $subCategory,
            'pieseauto_category' => $categoryForPieseauto,
            'pieseauto_fallback' => !empty($paMeta['fallback']),
            'car_brand' => $brand,
            'car_model' => trim((string)($card['model'] ?? '')),
            'cod_oem' => trim((string)($card['cod_oem'] ?? '')),
            'cod_articol' => trim((string)($card['cod_articol'] ?? '')),
            'coduri_oem' => trim((string)($card['coduri_oem'] ?? '')),
            'image_url' => $images[0] ?? '',
            'images' => $images,
            'updated_at' => (string)($card['updated_at'] ?? $card['created_at'] ?? ''),
        ];
    }

    if ($q !== '') {
        $needle = mb_strtolower($q, 'UTF-8');
        $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
            $haystack = mb_strtolower(
                ($item['title'] ?? '') . ' ' .
                ($item['car_brand'] ?? '') . ' ' .
                ($item['category_name'] ?? '') . ' ' .
                ($item['description'] ?? ''),
                'UTF-8'
            );
            return mb_strpos($haystack, $needle) !== false;
        }));
    }

    usort($items, static function (array $a, array $b): int {
        $ta = (string)($a['updated_at'] ?? '');
        $tb = (string)($b['updated_at'] ?? '');
        if ($ta !== '' || $tb !== '') {
            return strcmp($tb, $ta);
        }
        return strcmp((string)($b['title'] ?? ''), (string)($a['title'] ?? ''));
    });

    return array_slice($items, 0, max(1, min(500, $limit)));
}
