<?php
declare(strict_types=1);

/**
 * Instalare bază de date Blue-Car: blu_car_db + user blu_car
 * Acces: http://blu-car.test/prd/install_blu_car_db.php (o singură dată)
 */
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: ../admin/?page=login');
    exit;
}

require_once __DIR__ . '/db_products.php';
require_once dirname(__DIR__) . '/lib/env.php';

$rootUser = trim((string)blu_env('DB_INSTALL_ROOT_USER', 'root'));
$rootPass = (string)blu_env('DB_INSTALL_ROOT_PASS', blu_env('DB_PASS', ''));

$dbName = 'blu_car_db';
$dbUser = 'blu_car';
$dbPass = 'BluCar2026!';
$dbHost = 'localhost';

$messages = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdoRoot = new PDO(
            "mysql:host={$dbHost};charset=utf8mb4",
            $rootUser,
            $rootPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        foreach (["'{$dbUser}'@'localhost'", "'{$dbUser}'@'127.0.0.1'"] as $hostPart) {
            try {
                $pdoRoot->exec("CREATE USER IF NOT EXISTS {$hostPart} IDENTIFIED BY " . $pdoRoot->quote($dbPass));
            } catch (PDOException $e) {
                $pdoRoot->exec("ALTER USER {$hostPart} IDENTIFIED BY " . $pdoRoot->quote($dbPass));
            }
            $pdoRoot->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO {$hostPart}");
        }
        $pdoRoot->exec('FLUSH PRIVILEGES');
        $messages[] = "Baza «{$dbName}» și utilizatorul «{$dbUser}» au fost create/actualizate.";

        $pdo = dbProductsConnect([
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
        ]);
        if (!$pdo) {
            throw new RuntimeException('Conexiunea cu noul utilizator a eșuat. Verifică parola.');
        }
        dbProductsCreateTable($pdo);
        $messages[] = 'Tabelul `products` a fost creat în blu_car_db.';

        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile) && is_writable($envFile)) {
            $env = file_get_contents($envFile);
            $replacements = [
                '/^DB_HOST=.*/m' => 'DB_HOST=' . $dbHost,
                '/^DB_NAME=.*/m' => 'DB_NAME=' . $dbName,
                '/^DB_USER=.*/m' => 'DB_USER=' . $dbUser,
                '/^DB_PASS=.*/m' => 'DB_PASS=' . $dbPass,
            ];
            foreach ($replacements as $pattern => $line) {
                if (preg_match($pattern, $env)) {
                    $env = preg_replace($pattern, $line, $env) ?? $env;
                } else {
                    $env .= "\n" . $line;
                }
            }
            file_put_contents($envFile, $env);
            $messages[] = 'Fișierul .env a fost actualizat cu noile credențiale.';
        } else {
            $messages[] = 'Actualizează manual .env: DB_NAME=blu_car_db, DB_USER=blu_car, DB_PASS=BluCar2026!';
        }

        $ok = true;
    } catch (Throwable $e) {
        $messages[] = 'Eroare: ' . $e->getMessage();
        $messages[] = 'Alternativ: rulează install_blu_car_db.sql din phpMyAdmin (user root).';
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Instalare BD Blue-Car</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container" style="max-width:640px">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-3">Bază de date dedicată Blue-Car</h4>
            <p class="text-muted small">Separată de <code>agent_db</code> (aibotpiese). Nu atinge datele celuilalt proiect.</p>
            <table class="table table-sm">
                <tr><th>Bază</th><td><code>blu_car_db</code></td></tr>
                <tr><th>Utilizator</th><td><code>blu_car</code></td></tr>
                <tr><th>Parolă</th><td><code>BluCar2026!</code></td></tr>
                <tr><th>Host</th><td><code>localhost</code></td></tr>
            </table>
            <?php foreach ($messages as $m): ?>
                <div class="alert alert-<?= $ok ? 'success' : 'warning' ?> py-2"><?= h($m) ?></div>
            <?php endforeach; ?>
            <?php if (!$ok): ?>
            <form method="post">
                <p class="small">Folosește contul MySQL <strong>root</strong> (Laragon) pentru a crea baza și userul. Parola root: din .env actual sau goală.</p>
                <button type="submit" class="btn btn-primary">Creează blu_car_db acum</button>
            </form>
            <?php else: ?>
            <a href="../admin/?page=api-diagnostics" class="btn btn-success">Verifică în Diagnostic API</a>
            <a href="produse.php" class="btn btn-outline-primary ms-2">Produse BD</a>
            <?php endif; ?>
            <a href="../admin/?page=dashboard" class="btn btn-link mt-2">← Admin</a>
        </div>
    </div>
</div>
</body>
</html>
