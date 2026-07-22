<?php
declare(strict_types=1);

/** @var string $shopPageTitle */
/** @var string $shopPageDescription */
/** @var string $shopBodyClass */
$shopPageTitle = $shopPageTitle ?? 'Blue-Car — Piese auto';
$shopPageDescription = $shopPageDescription ?? 'Magazin online de piese auto Blue-Car. OEM, livrare rapidă, consultanță dedicată.';
$shopBodyClass = $shopBodyClass ?? 'shop-page';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title><?= blu_shop_h($shopPageTitle) ?></title>
    <meta name="description" content="<?= blu_shop_h($shopPageDescription) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/fontawesome-min.css">
    <link rel="stylesheet" href="assets/shop/shop.css?v=55">
    <link rel="stylesheet" href="assets/shop/cms-builder.css?v=1">
</head>
<body class="<?= blu_shop_h($shopBodyClass) ?>">
