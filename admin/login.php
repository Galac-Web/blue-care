<?php
declare(strict_types=1);

define('BLU_ADMIN_CONTEXT', true);

$query = ['page' => 'login'];
if (isset($_GET['redirect'])) {
    $redirect = trim((string) $_GET['redirect']);
    if ($redirect !== '' && !str_contains($redirect, '/admin') && !preg_match('#login\.php#i', $redirect)) {
        $query['redirect'] = $redirect;
    }
}

header('Location: index.php?' . http_build_query($query), true, 302);
exit;
