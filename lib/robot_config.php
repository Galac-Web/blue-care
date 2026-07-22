<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function blu_robot_furnizori_base_url(): string
{
    $url = trim((string)blu_env('ROBOT_FURNIZORI_URL', ''));
    if ($url === '') {
        $url = trim((string)blu_env('ROBOT_BASE_URL', 'http://127.0.0.1:5000'));
    }
    return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:5000';
}

/** URL public (ex. ngrok HTTPS) — accesibil din browser și de pe serverul de producție. */
function blu_robot_furnizori_tunnel_url(): string
{
    $url = trim((string)blu_env('ROBOT_FURNIZORI_TUNNEL_URL', ''));
    return $url !== '' ? rtrim($url, '/') : '';
}

/** URL folosit de robot_proxy.php și ping server-side. */
function blu_robot_furnizori_effective_url(): string
{
    $tunnel = blu_robot_furnizori_tunnel_url();
    return $tunnel !== '' ? $tunnel : blu_robot_furnizori_base_url();
}

function blu_robot_pieseauto_tunnel_url(): string
{
    $url = trim((string)blu_env('ROBOT_PIESEAUTO_TUNNEL_URL', ''));
    return $url !== '' ? rtrim($url, '/') : '';
}

function blu_robot_pieseauto_effective_url(): string
{
    $tunnel = blu_robot_pieseauto_tunnel_url();
    return $tunnel !== '' ? $tunnel : blu_robot_pieseauto_base_url();
}

/** @return array{proxy:string,direct_furnizori:string,direct_pieseauto:string,local_direct:string,configured_furnizori:string,is_localhost_config:bool,is_local_admin:bool,hint:string} */
function blu_robot_admin_js_config(): array
{
    $configured = blu_robot_furnizori_base_url();
    $host = strtolower((string)(parse_url($configured, PHP_URL_HOST) ?? ''));
    $isLocalConfig = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

    $directFurn = blu_robot_furnizori_tunnel_url();
    $directPa = blu_robot_pieseauto_tunnel_url();

    $adminHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $adminHost = preg_replace('/:\d+$/', '', $adminHost) ?? $adminHost;
    $isLocalAdmin = (bool)preg_match('/^(localhost|127\.0\.0\.1|blu-car\.test|blu-car\.local)$/', $adminHost);

    $localDirect = ($isLocalAdmin && $isLocalConfig) ? $configured : '';

    $hint = '';
    if ($isLocalConfig && $directFurn === '' && !$isLocalAdmin) {
        $hint = 'Robotul rulează pe PC-ul tău. Pentru admin pe blu-car.ro setează ROBOT_FURNIZORI_TUNNEL_URL (ngrok HTTPS → port '
            . (parse_url($configured, PHP_URL_PORT) ?: '5000')
            . ') în .env pe server, sau deschide admin local: http://blu-car.test/admin/?page=robot-monitor';
    } elseif ($isLocalAdmin && !$isLocalConfig) {
        $hint = 'ROBOT_FURNIZORI_URL din .env nu e localhost — verifică portul robotului.';
    }

    $proxyBase = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '';

    return [
        'proxy' => $proxyBase . 'robot_proxy.php',
        'direct_furnizori' => $directFurn,
        'direct_pieseauto' => $directPa,
        'local_direct' => $localDirect,
        'configured_furnizori' => $configured,
        'is_localhost_config' => $isLocalConfig,
        'is_local_admin' => $isLocalAdmin,
        'hint' => $hint,
    ];
}

function blu_robot_pieseauto_base_url(): string
{
    $url = trim((string)blu_env('ROBOT_PIESEAUTO_URL', 'http://127.0.0.1:5001'));
    return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:5001';
}

/** @return array<string, mixed> */
function blu_robot_profile(string $type): array
{
    $type = strtolower(trim($type));
    if ($type === 'pieseauto') {
        return [
            'type' => 'pieseauto',
            'label' => 'PieseAuto',
            'base_url' => blu_robot_pieseauto_base_url(),
            'script' => 'robot_pieseauto.py',
            'ping_path' => '/verificare_sesiune',
            'lock_file' => '.robot_pieseauto.lock',
            'log_file' => 'robot_pieseauto_service.log',
            'vbs' => 'start_pieseauto_hidden.vbs',
        ];
    }

    return [
        'type' => 'furnizori',
        'label' => 'Furnizori GBG',
        'base_url' => blu_robot_furnizori_base_url(),
        'script' => 'robot1.py',
        'ping_path' => '/status',
        'lock_file' => '.robot_furnizori.lock',
        'log_file' => 'robot_service.log',
        'vbs' => 'start_robot_hidden.vbs',
    ];
}
