<?php
declare(strict_types=1);

if (!function_exists('blu_env')) {
    function blu_load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            if ($key === '') {
                continue;
            }
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
            }
            if (getenv($key) === false) {
                @putenv($key . '=' . $val);
            }
        }
    }

    function blu_env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return $default;
    }

    $root = dirname(__DIR__);
    blu_load_env_file($root . DIRECTORY_SEPARATOR . '.env');
}
