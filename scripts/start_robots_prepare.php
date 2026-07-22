<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/pieseauto_auto.php';

function line(string $msg): void
{
    echo $msg . PHP_EOL;
}

line('--- Status roboți ---');

$furn = blu_robot_ping('furnizori');
$pa = blu_robot_ping('pieseauto');
line('GBG furnizor (5000): ' . ($furn ? 'ONLINE' : 'OFFLINE'));
line('PieseAuto (5007):     ' . ($pa ? 'ONLINE' : 'OFFLINE'));

if (!$furn) {
    line('ATENTIE: Pornește manual robot\\start_robot_hidden.vbs');
}
if (!$pa) {
    line('ATENTIE: Pornește manual robot\\start_pieseauto_hidden.vbs');
    exit(1);
}

$contId = blu_pieseauto_auto_cont_id();
$state = blu_pieseauto_robot_request('/este_ocupat?cont_id=' . rawurlencode($contId), null, 'GET', 8);
$browserActive = !empty($state['browser_active']);

line('');
if ($browserActive) {
    line('PieseAuto browser: ACTIV — gata de publicare.');
} else {
    line('PieseAuto browser: INACTIV');
    line('Deschide manual din admin → PieseAuto → «Lansează browser robot» (o singură fereastră).');
    line('Scanarea GBG poate rula fără browser PieseAuto până atunci.');
}

line('');
line('Servicii robot pregătite (fără browsere suplimentare).');
exit(0);
