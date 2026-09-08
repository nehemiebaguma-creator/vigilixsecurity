<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = function_exists('vg_api_auth') ? vg_api_auth() : null;
if ($user === null) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Non authentifie.';
    exit;
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$cameraId = trim((string) ($_GET['camera_id'] ?? ''));
if ($cameraId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'camera_id manquant.';
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
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Camera introuvable.';
    exit;
}

$captureSource = function_exists('vg_camera_operator_capture_source')
    ? vg_camera_operator_capture_source($camera)
    : ['supported' => false, 'message' => 'Capture indisponible.'];
$preview = function_exists('vg_camera_browser_preview')
    ? vg_camera_browser_preview($camera)
    : ['supported' => false, 'mode' => 'none', 'url' => '', 'message' => ''];
$explicitSnapshotUrl = function_exists('vg_camera_normalize_url')
    ? vg_camera_normalize_url((string) ($camera['snapshot_url'] ?? ''))
    : trim((string) ($camera['snapshot_url'] ?? ''));
if ($explicitSnapshotUrl !== '' && function_exists('vg_camera_apply_basic_auth')) {
    $explicitSnapshotUrl = vg_camera_apply_basic_auth($explicitSnapshotUrl, $camera);
}
$hikvisionSnapshotUrl = function_exists('vg_camera_hikvision_snapshot_url')
    ? vg_camera_hikvision_snapshot_url($camera)
    : '';
$previewUrl = trim((string) ($preview['url'] ?? ''));
$captureUrl = trim((string) ($captureSource['url'] ?? ''));
$captureFallbackUrl = trim((string) ($captureSource['fallback_url'] ?? ''));

$snapshotCandidates = [];
$addSnapshotCandidate = static function (string $url, string $label) use (&$snapshotCandidates): void {
    $url = trim($url);
    if ($url === '' || preg_match('~^https?://~i', $url) !== 1) {
        return;
    }

    foreach ($snapshotCandidates as $candidate) {
        if (($candidate['url'] ?? '') === $url) {
            return;
        }
    }

    $snapshotCandidates[] = [
        'url' => $url,
        'label' => $label,
    ];
};

$looksLikeLocalPreviewProxy = $previewUrl !== ''
    && (
        str_contains($previewUrl, '/api/camera_snapshot.php')
        || str_contains($previewUrl, '\\api\\camera_snapshot.php')
    );

if (!empty($preview['supported']) && (string) ($preview['mode'] ?? '') === 'image') {
    $addSnapshotCandidate($explicitSnapshotUrl, 'snapshot-explicite');
    $addSnapshotCandidate($hikvisionSnapshotUrl, 'snapshot-hikvision');
    if (!$looksLikeLocalPreviewProxy) {
        $addSnapshotCandidate($previewUrl, 'preview-image');
    }
}

$captureMode = (string) ($captureSource['mode'] ?? '');
if (in_array($captureMode, ['ip-webcam-photo-focus', 'hikvision-snapshot', 'http-image-snapshot'], true)) {
    $addSnapshotCandidate($captureUrl, 'capture-primaire');
}
$addSnapshotCandidate($captureFallbackUrl, 'capture-secours');

if ($snapshotCandidates === []) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo (string) ($captureSource['message'] ?? $preview['message'] ?? 'Cette camera ne fournit pas de snapshot exploitable.');
    exit;
}

$captureFetch = ['ok' => false, 'error' => 'Aucun snapshot distant n a pu etre recupere.'];
foreach ($snapshotCandidates as $candidate) {
    $captureFetch = function_exists('vg_camera_fetch_capture')
        ? vg_camera_fetch_capture((string) ($candidate['url'] ?? ''), 4)
        : ['ok' => false, 'error' => 'Le module de capture camera est indisponible.'];

    if (!empty($captureFetch['ok']) && is_string($captureFetch['bytes'] ?? null)) {
        break;
    }
}

if (empty($captureFetch['ok']) || !is_string($captureFetch['bytes'] ?? null)) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo (string) ($captureFetch['error'] ?? 'Impossible de recuperer une image camera.');
    exit;
}

$mime = trim((string) ($captureFetch['mime'] ?? 'image/jpeg'));
if ($mime === '' || strpos($mime, 'image/') !== 0) {
    $mime = 'image/jpeg';
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: ' . $mime);
echo $captureFetch['bytes'];
exit;
