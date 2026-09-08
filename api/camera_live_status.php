<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$user = function_exists('vg_api_auth') ? vg_api_auth() : null;
if (!is_array($user) || $user === []) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Non authentifie.']);
    exit;
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$cameraId = trim((string) ($_GET['camera_id'] ?? ''));
if ($cameraId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'camera_id manquant.']);
    exit;
}

$camera = null;
foreach (function_exists('vgx_cameras') ? array_values(vgx_cameras()) : [] as $cameraRow) {
    if (is_array($cameraRow) && (string) ($cameraRow['id'] ?? '') === $cameraId) {
        $camera = $cameraRow;
        break;
    }
}

if (!is_array($camera)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Camera introuvable.']);
    exit;
}

$role = (string) ($user['role'] ?? 'client');
if ($role === 'client') {
    $client = function_exists('vg_portal_require_client') ? vg_portal_require_client($user) : null;
    $clientId = is_array($client) ? (string) ($client['id'] ?? '') : '';
    if ($clientId === '' || (string) ($camera['client_id'] ?? '') !== $clientId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Acces refuse a cette camera.']);
        exit;
    }
}

$bridge = function_exists('vg_camera_mediamtx_stream')
    ? vg_camera_mediamtx_stream($camera)
    : ['enabled' => false, 'available' => false, 'playable' => false, 'message' => 'Pont direct indisponible.'];
$preview = function_exists('vg_camera_browser_preview')
    ? vg_camera_browser_preview($camera)
    : ['supported' => false, 'mode' => 'none', 'url' => '', 'message' => ''];
$streamLabel = function_exists('vg_camera_redact_url')
    ? vg_camera_redact_url((string) ($camera['stream_url'] ?? ''))
    : (string) ($camera['stream_url'] ?? '');

echo json_encode([
    'status' => 'ok',
    'camera' => [
        'id' => (string) ($camera['id'] ?? ''),
        'name' => (string) ($camera['name'] ?? 'Camera VIGILANCE'),
        'location' => (string) ($camera['location'] ?? ''),
        'type' => (string) ($camera['type'] ?? ''),
        'status' => (string) ($camera['status'] ?? ''),
        'stream_label' => $streamLabel,
    ],
    'bridge' => [
        'enabled' => !empty($bridge['enabled']),
        'available' => !empty($bridge['available']),
        'playable' => !empty($bridge['playable']),
        'hls_url' => (string) ($bridge['hls_url'] ?? ''),
        'webrtc_available' => !empty($bridge['webrtc_available']),
        'webrtc_playable' => !empty($bridge['webrtc_playable']),
        'webrtc_url' => (string) ($bridge['webrtc_url'] ?? ''),
        'whep_url' => (string) ($bridge['whep_url'] ?? ''),
        'latency_mode' => (string) ($bridge['latency_mode'] ?? 'offline'),
        'message' => (string) ($bridge['message'] ?? ''),
    ],
    'preview' => [
        'supported' => !empty($preview['supported']),
        'mode' => (string) ($preview['mode'] ?? 'none'),
        'url' => (string) ($preview['url'] ?? ''),
        'message' => (string) ($preview['message'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
