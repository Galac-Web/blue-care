<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/robot_config.php';

function blu_robot_auto_start_enabled(): bool
{
    $v = strtolower(trim((string)blu_env('ROBOT_AUTO_START', '1')));
    return !in_array($v, ['0', 'false', 'no', 'off'], true);
}

function blu_robot_is_local_target(?string $url = null): bool
{
    $url = $url ?? blu_robot_furnizori_base_url();
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
}

function blu_robot_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'robot';
}

function blu_robot_lock_file(string $type = 'furnizori'): string
{
    $profile = blu_robot_profile($type);
    return blu_robot_dir() . DIRECTORY_SEPARATOR . $profile['lock_file'];
}

function blu_robot_log_file(string $type = 'furnizori'): string
{
    $profile = blu_robot_profile($type);
    return blu_robot_dir() . DIRECTORY_SEPARATOR . $profile['log_file'];
}

function blu_robot_ping(string $type = 'furnizori'): bool
{
    $profile = blu_robot_profile($type);
    $baseUrl = rtrim((string)$profile['base_url'], '/');
    if ($type === 'furnizori') {
        $baseUrl = blu_robot_furnizori_effective_url();
    } elseif ($type === 'pieseauto') {
        $baseUrl = blu_robot_pieseauto_effective_url();
    }
    $pingPath = (string)$profile['ping_path'];

    $ch = curl_init($baseUrl . $pingPath);
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['ngrok-skip-browser-warning: 69420'],
    ]);

    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 500;
}

function blu_robot_launch_lock_recent(string $type, int $seconds = 45): bool
{
    $lock = blu_robot_lock_file($type);
    if (!is_file($lock)) {
        return false;
    }
    return (time() - (int)filemtime($lock)) < $seconds;
}

function blu_robot_touch_launch_lock(string $type): void
{
    @touch(blu_robot_lock_file($type), time());
}

function blu_robot_python_bin(): string
{
    $candidates = [
        trim((string)blu_env('ROBOT_PYTHON', '')),
        'C:\\laragon\\bin\\python\\python-3.13\\python.exe',
        'python',
    ];
    foreach ($candidates as $bin) {
        if ($bin === '') {
            continue;
        }
        if ($bin === 'python' || is_file($bin)) {
            return $bin;
        }
    }

    return 'python';
}

function blu_robot_clear_launch_lock(string $type): void
{
    @unlink(blu_robot_lock_file($type));
}

function blu_robot_spawn_process(string $type = 'furnizori'): bool
{
    $profile = blu_robot_profile($type);
    $robotDir = blu_robot_dir();
    $script = $robotDir . DIRECTORY_SEPARATOR . $profile['script'];
    if (!is_file($script)) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $ps1 = $robotDir . DIRECTORY_SEPARATOR . ($type === 'pieseauto' ? 'ensure_pieseauto.ps1' : 'ensure_furnizori.ps1');
        if (is_file($ps1)) {
            $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
                . escapeshellarg($ps1);
            if (function_exists('proc_open')) {
                $descriptors = [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']];
                $process = @proc_open($cmd, $descriptors, $pipes);
                if (is_resource($process)) {
                    $exit = proc_close($process);
                    return $exit === 0 || blu_robot_ping($type);
                }
            }
            exec($cmd, $out, $exit);
            return $exit === 0 || blu_robot_ping($type);
        }
    }

    $vbs = $robotDir . DIRECTORY_SEPARATOR . $profile['vbs'];
    if (PHP_OS_FAMILY === 'Windows' && is_file($vbs)) {
        $cmd = 'wscript.exe //B "' . str_replace('"', '""', $vbs) . '"';
    } else {
        $log = blu_robot_log_file($type);
        $cmd = 'cd ' . escapeshellarg($robotDir)
            . ' && nohup ' . escapeshellarg(blu_robot_python_bin())
            . ' ' . escapeshellarg($profile['script'])
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
    }

    if (function_exists('proc_open')) {
        $descriptors = PHP_OS_FAMILY === 'Windows'
            ? [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']]
            : [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            @proc_close($process);
            sleep(4);
            return blu_robot_ping($type);
        }
    }

    if (function_exists('popen')) {
        $h = @popen($cmd, 'r');
        if (is_resource($h)) {
            @pclose($h);
            sleep(4);
            return blu_robot_ping($type);
        }
    }

    @exec($cmd);
    sleep(4);
    return blu_robot_ping($type);
}

/**
 * @return array{success:bool,message:string,online?:bool,already_running?:bool,starting?:bool,spawned?:bool,robot?:string}
 */
function blu_robot_ensure_running(string $type = 'furnizori', bool $force = false): array
{
    $profile = blu_robot_profile($type);
    $baseUrl = (string)$profile['base_url'];

    if (!blu_robot_auto_start_enabled() && !$force) {
        return [
            'success' => false,
            'message' => 'Pornire automată dezactivată (ROBOT_AUTO_START=0).',
            'online' => blu_robot_ping($type),
            'robot' => $type,
        ];
    }

    $host = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?? ''));
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        return [
            'success' => false,
            'message' => 'Pornire automată doar pentru URL local (127.0.0.1).',
            'online' => blu_robot_ping($type),
            'robot' => $type,
        ];
    }

    if (blu_robot_ping($type)) {
        return [
            'success' => true,
            'message' => $profile['label'] . ' rulează deja.',
            'online' => true,
            'already_running' => true,
            'robot' => $type,
        ];
    }

    if (blu_robot_launch_lock_recent($type)) {
        if (blu_robot_ping($type)) {
            return [
                'success' => true,
                'message' => $profile['label'] . ' rulează deja.',
                'online' => true,
                'already_running' => true,
                'robot' => $type,
            ];
        }
        $lock = blu_robot_lock_file($type);
        $lockAge = is_file($lock) ? (time() - (int)filemtime($lock)) : 999;
        if ($lockAge < 12) {
            return [
                'success' => true,
                'message' => $profile['label'] . ' se pornește...',
                'online' => false,
                'starting' => true,
                'robot' => $type,
            ];
        }
        blu_robot_clear_launch_lock($type);
    }

    blu_robot_touch_launch_lock($type);
    $spawned = blu_robot_spawn_process($type);
    if (!$spawned) {
        blu_robot_clear_launch_lock($type);
        return [
            'success' => false,
            'message' => 'Nu am putut porni ' . $profile['label'] . '. Rulează robot\\start_' . ($type === 'pieseauto' ? 'pieseauto_visible.bat' : 'tot.bat') . '.',
            'online' => false,
            'robot' => $type,
        ];
    }

    return [
        'success' => true,
        'message' => $profile['label'] . ' pornit.',
        'online' => true,
        'already_running' => false,
        'robot' => $type,
    ];
}

function blu_robot_ensure_all_running(bool $force = false): array
{
    return [
        'furnizori' => blu_robot_ensure_running('furnizori', $force),
        'pieseauto' => blu_robot_ensure_running('pieseauto', $force),
    ];
}
