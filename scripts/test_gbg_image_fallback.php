<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/catalog_import.php';

$withGbg = blu_apply_gbg_image_fallback(
    ['title' => 'Test', 'image' => ''],
    ['imagine_url' => 'https://gbg.example/p.jpg']
);
$keepTecdoc = blu_apply_gbg_image_fallback(
    ['title' => 'Test', 'image' => 'https://tecdoc.example/a.jpg'],
    ['imagine_url' => 'https://gbg.example/p.jpg']
);

echo 'gbg_fallback=' . (($withGbg['image'] ?? '') === 'https://gbg.example/p.jpg' ? 'ok' : 'fail') . "\n";
echo 'source=' . ($withGbg['image_source'] ?? '') . "\n";
echo 'keep_tecdoc=' . (($keepTecdoc['image'] ?? '') === 'https://tecdoc.example/a.jpg' ? 'ok' : 'fail') . "\n";
