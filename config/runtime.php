<?php

declare(strict_types=1);

// Load .env file once into $_ENV / putenv if not already loaded by the web server.
// Skipped in Docker/production where env vars come from the OS environment.
(static function (): void {
    $dotenv = dirname(__DIR__) . '/.env';
    if (!is_file($dotenv)) {
        return;
    }
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        // Strip surrounding quotes
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        // Only set if not already defined by OS / Apache SetEnv
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
})();

if (!function_exists('vg_env')) {
    function vg_env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('vg_public_base_url')) {
    function vg_public_base_url(): string
    {
        $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
        $host = $forwardedHost !== '' ? $forwardedHost : trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $forwardedProtoRaw = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $forwardedProto = strtolower(trim(explode(',', $forwardedProtoRaw)[0] ?? ''));
        $scheme = ($forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';

        $root = '/vigilance';
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        if ($script !== '') {
            $segments = array_values(array_filter(explode('/', trim($script, '/')), 'strlen'));
            foreach ($segments as $index => $segment) {
                if (in_array(strtolower($segment), ['vigilance', 'vigilix', 'viglix'], true)) {
                    $root = '/' . implode('/', array_slice($segments, 0, $index + 1));
                    break;
                }
            }
        }

        if ($host !== '') {
            return $scheme . '://' . $host . rtrim($root, '/');
        }

        $configured = (string) vg_env('APP_URL', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return 'http://localhost' . rtrim($root, '/');
    }
}

if (!function_exists('vg_storage_path')) {
    function vg_storage_path(string $suffix = ''): string
    {
        $base = dirname(__DIR__) . '/storage';

        if (!is_dir($base)) {
            @mkdir($base, 0777, true);
        }

        if ($suffix === '') {
            return $base;
        }

        return $base . '/' . ltrim($suffix, '/');
    }
}

if (!function_exists('vg_media_path')) {
    function vg_media_path(string $suffix = ''): string
    {
        $base = (string) vg_env('MEDIA_PATH', vg_storage_path('uploads'));

        if (!is_dir($base)) {
            @mkdir($base, 0777, true);
        }

        if ($suffix === '') {
            return $base;
        }

        return rtrim($base, '/\\') . '/' . ltrim($suffix, '/');
    }
}
