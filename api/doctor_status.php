<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/doctor.php';

header('Content-Type: application/json; charset=UTF-8');

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$respond = static function (int $statusCode, array $payload) use ($jsonFlags): void {
    http_response_code($statusCode);
    echo json_encode($payload, $jsonFlags);
    exit;
};

$currentUser = function_exists('vg_current_user') ? vg_current_user() : [];
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$forwardedAddr = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
$isLocalRequest = $forwardedAddr === '' && in_array($remoteAddr, ['127.0.0.1', '::1'], true);
$isAdminSession = is_array($currentUser) && (($currentUser['role'] ?? '') === 'admin');
if (!$isLocalRequest && !$isAdminSession) {
    $respond(403, [
        'ok' => false,
        'error' => 'Acces reserve au poste local ou a une session admin.',
    ]);
}

if (!in_array(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'POST'], true)) {
    $respond(405, [
        'ok' => false,
        'error' => 'Methode non autorisee.',
    ]);
}

$store = vg_load_store();
$cameras = is_array($store['cameras'] ?? null) ? array_values($store['cameras']) : [];
$cameraId = trim((string) ($_REQUEST['camera_id'] ?? ''));
$selectedCamera = vg_doctor_find_camera_by_id($cameras, $cameraId);
$services = vg_doctor_service_snapshot(true);

$payload = [
    'ok' => true,
    'timestamp' => date('c'),
    'services' => $services,
    'camera' => null,
    'diagnosis' => null,
    'playbooks' => [],
];

if ($cameraId !== '') {
    if ($selectedCamera === null) {
        $respond(404, [
            'ok' => false,
            'error' => 'Camera introuvable.',
            'services' => $services,
            'timestamp' => date('c'),
        ]);
    }

    $diagnosis = vg_doctor_camera_diagnosis($selectedCamera, $services);
    $payload['camera'] = vg_doctor_camera_public_payload($selectedCamera);
    $payload['diagnosis'] = $diagnosis;
    $payload['playbooks'] = vg_doctor_camera_playbooks($selectedCamera, $diagnosis);
}

$respond(200, $payload);
