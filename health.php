<?php

declare(strict_types=1);

require_once __DIR__ . '/config/runtime.php';
require_once __DIR__ . '/config/database.runtime.php';
require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/mysql-data.php';
require_once __DIR__ . '/includes/camera.php';
require_once __DIR__ . '/includes/drone.php';
require_once __DIR__ . '/includes/vision.php';
require_once __DIR__ . '/includes/radio.php';
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/includes/redis.php';
}

header('Content-Type: application/json; charset=utf-8');

$storage = vg_storage_path();
$media = vg_media_path();
$dbStatus = [
    'connected' => false,
    'database' => function_exists('vg_db_config') ? (string) ((vg_db_config()['name'] ?? '')) : '',
    'host' => function_exists('vg_db_config') ? ((string) ((vg_db_config()['host'] ?? 'localhost')) . ':' . (string) ((vg_db_config()['port'] ?? '3306'))) : 'localhost:3306',
    'message' => 'Diagnostic rapide: connexion profonde non lancee pour eviter un blocage de presentation.',
    'details' => '',
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mysqli' => extension_loaded('mysqli'),
    'driver' => function_exists('vg_db_driver') ? vg_db_driver() : null,
];
$store = function_exists('vgx_store') ? vgx_store() : [];
$cameras = isset($store['cameras']) && is_array($store['cameras']) ? array_values($store['cameras']) : [];
$equipment = isset($store['equipment']) && is_array($store['equipment']) ? array_values($store['equipment']) : [];
$dbReady = false;
$mediamtxStatus = function_exists('vg_mediamtx_paths_status')
    ? vg_mediamtx_paths_status(false, false)
    : ['available' => false, 'api_base' => '', 'hls_base' => '', 'paths' => []];
$visionStatus = function_exists('vg_vision_service_health')
    ? vg_vision_service_health(false, 1)
    : ['ok' => false, 'ready' => false, 'service' => 'vigilix-vision', 'error' => 'Service vision indisponible.'];
$cameraWorkerStatus = function_exists('vg_camera_ai_worker_status')
    ? vg_camera_ai_worker_status()
    : ['running' => false];
$redisStatus = function_exists('vgx_redis_health')
    ? vgx_redis_health(false, 0.12)
    : ['listening' => false, 'host' => '127.0.0.1', 'port' => 6379, 'auto_started' => false];
$pttStatus = function_exists('vg_ptt_service_health')
    ? vg_ptt_service_health(false, 0.25)
    : ['listening' => false, 'host' => '127.0.0.1', 'port' => 8765, 'error' => 'Service radio indisponible.', 'auto_started' => false];
$pttListening = !empty($pttStatus['listening']);
$cameraReachable = 0;
$cameraIssues = 0;
foreach ($cameras as $camera) {
    if (!is_array($camera)) {
        continue;
    }

    $cameraUrl = trim((string) ($camera['stream_url'] ?? $camera['rtsp_url'] ?? $camera['hls_url'] ?? ''));
    if ($cameraUrl !== '') {
        $cameraReachable++;
    } elseif (strtolower((string) ($camera['status'] ?? '')) === 'active') {
        $cameraIssues++;
    }
}
$health = [
    'project' => 'VIGILANCE Security',
    'status' => $dbReady ? (($cameraIssues > 0 || !$pttListening) ? 'degraded' : 'ok') : 'partial',
    'timestamp' => date('c'),
    'app_url' => vg_public_base_url(),
    'php' => PHP_VERSION,
    'checks' => [
        'storage_exists' => is_dir($storage),
        'storage_writable' => is_dir($storage) ? is_writable($storage) : false,
        'media_exists' => is_dir($media),
        'media_writable' => is_dir($media) ? is_writable($media) : false,
        'json_extension' => extension_loaded('json'),
        'session_extension' => extension_loaded('session'),
        'pdo_extension' => extension_loaded('pdo'),
        'openssl_extension' => extension_loaded('openssl'),
    ],
    'database' => $dbStatus,
    'data_source' => function_exists('vgx_data_source_name') ? vgx_data_source_name() : 'local-json',
    'cloud' => [
        'db_ready' => $dbReady,
        'schema_ready' => $dbReady,
    ],
    'cameras' => [
        'count' => count($cameras),
        'reachable' => $cameraReachable,
        'issues' => $cameraIssues,
    ],
    'equipment' => [
        'count' => count($equipment),
    ],
    'runtime' => [
        'mediamtx' => [
            'available' => !empty($mediamtxStatus['available']),
            'api_base' => (string) ($mediamtxStatus['api_base'] ?? ''),
            'hls_base' => (string) ($mediamtxStatus['hls_base'] ?? ''),
            'path_count' => is_array($mediamtxStatus['paths'] ?? null) ? count($mediamtxStatus['paths']) : 0,
        ],
        'vision' => [
            'ok' => !empty($visionStatus['ok']),
            'ready' => !empty($visionStatus['ready']),
            'service' => (string) ($visionStatus['service'] ?? 'vigilix-vision'),
            'threat_model_ready' => !empty($visionStatus['threat_model_ready']),
            'threat_detection_mode' => (string) ($visionStatus['threat_detection_mode'] ?? 'unavailable'),
            'active_threat_model' => $visionStatus['active_threat_model'] ?? null,
            'error' => (string) ($visionStatus['error'] ?? ''),
        ],
        'camera_ai_worker' => [
            'running' => !empty($cameraWorkerStatus['running']),
            'last_tick_at' => (string) ($cameraWorkerStatus['last_tick_at'] ?? ''),
            'last_activity_at' => (string) ($cameraWorkerStatus['last_activity_at'] ?? ''),
            'last_ok_count' => (int) ($cameraWorkerStatus['last_ok_count'] ?? 0),
            'last_error_count' => (int) ($cameraWorkerStatus['last_error_count'] ?? 0),
        ],
        'redis' => [
            'listening' => !empty($redisStatus['listening']),
            'host' => (string) ($redisStatus['host'] ?? '127.0.0.1'),
            'port' => (int) ($redisStatus['port'] ?? 6379),
            'auto_started' => !empty($redisStatus['auto_started']),
        ],
        'ptt_radio' => [
            'listening' => $pttListening,
            'host' => (string) ($pttStatus['host'] ?? '127.0.0.1'),
            'port' => (int) ($pttStatus['port'] ?? 8765),
            'auto_started' => !empty($pttStatus['auto_started']),
            'error' => (string) ($pttStatus['error'] ?? ''),
        ],
    ],
    'notes' => [
        'Local platform is operational when PHP pages open without fatal errors.',
        'Production cloud requires MySQL as source of truth, HTTPS, backups, logging and externalized secrets.',
        'AI vision services for motion/face/plate detection must be deployed separately from the PHP UI.',
    ],
];

http_response_code(200);
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
