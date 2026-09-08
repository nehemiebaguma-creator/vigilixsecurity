<?php

// Charger l'env si nécessaire
if (!function_exists('vg_load_env')) {
    require_once dirname(__DIR__) . '/includes/env.php';
}

return array(
    'host' => vg_env('DB_HOST', '127.0.0.1'),
    'port' => (int) vg_env('DB_PORT', '3306'),
    'database' => vg_env('DB_NAME', 'vigilix'),
    'username' => vg_env('DB_USER', 'root'),
    'password' => vg_env('DB_PASS', ''),
    'charset' => 'utf8mb4',
);
