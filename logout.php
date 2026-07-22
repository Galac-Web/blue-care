<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/shop/bootstrap.php';

blu_shop_logout_with_reason('');
header('Location: login.php?logout=1');
exit;
