<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$respond = static function (int $statusCode, array $payload) use ($jsonFlags): void {
    http_response_code($statusCode);
    echo json_encode($payload, $jsonFlags);
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    $respond(405, [
        'ok' => false,
        'error' => 'Methode non autorisee.',
    ]);
}

$user = function_exists('vg_current_user') ? vg_current_user() : [];
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$forwardedAddr = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
$isLocalRequest = $forwardedAddr === '' && in_array($remoteAddr, ['127.0.0.1', '::1'], true);
if (($user['role'] ?? '') !== 'admin' && !$isLocalRequest) {
    $respond(403, [
        'ok' => false,
        'error' => 'Acces reserve au centre OPS.',
    ]);
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$action = trim((string) ($_POST['action'] ?? 'analyze_due'));
$limit = max(1, min(8, (int) ($_POST['limit'] ?? 1)));
$cooldownSeconds = max(0, min(3600, (int) ($_POST['cooldown_seconds'] ?? $_POST['cooldown'] ?? 1)));
$failureCooldownSeconds = max(15, min(1800, (int) ($_POST['failure_cooldown_seconds'] ?? 15)));

$cameras = function_exists('vgx_cameras') ? array_values(vgx_cameras()) : [];
$cameraById = [];
foreach ($cameras as $camera) {
    if (!is_array($camera)) {
        continue;
    }

    $cameraId = trim((string) ($camera['id'] ?? ''));
    if ($cameraId !== '') {
        $cameraById[$cameraId] = $camera;
    }
}

$summaries = [];
foreach ($cameraById as $cameraId => $camera) {
    $summaries[$cameraId] = function_exists('vgx_camera_intelligence_summary')
        ? vgx_camera_intelligence_summary($camera)
        : [];
}

$overview = static function (array $summaryRows): array {
    $capable = 0;
    $alert = 0;
    $congested = 0;
    $fresh = 0;
    $critical = 0;
    $dispatch = 0;
    $stale = 0;
    $autoAlertOpen = 0;
    $persistent = 0;
    $riskTotal = 0;
    $confidenceTotal = 0;
    $scoredCount = 0;

    foreach ($summaryRows as $summary) {
        if (!is_array($summary)) {
            continue;
        }

        if (!empty($summary['intelligence_ready'])) {
            $capable++;
        }
        if (
            ($summary['smart_status'] ?? '') === 'alert'
            || !empty($summary['dispatch_recommended'])
            || ($summary['auto_alert_status'] ?? '') === 'open'
        ) {
            $alert++;
        }
        if (($summary['smart_status'] ?? '') === 'congested') {
            $congested++;
        }
        if (($summary['escalation_state'] ?? '') === 'critical') {
            $critical++;
        }
        if (!empty($summary['dispatch_recommended'])) {
            $dispatch++;
        }
        if (($summary['auto_alert_status'] ?? '') === 'open') {
            $autoAlertOpen++;
        }
        if ((int) ($summary['persistence_score'] ?? 0) >= 60) {
            $persistent++;
        }

        $ageSeconds = (int) ($summary['last_analysis_age_seconds'] ?? 0);
        if (!empty($summary['stream_online']) && ($summary['last_analysis_at'] ?? null) !== null && $ageSeconds <= 600) {
            $fresh++;
        } elseif (!empty($summary['stream_online'])) {
            $stale++;
        }

        if (!empty($summary['analysis_fresh']) || !empty($summary['intelligence_ready'])) {
            $riskTotal += (int) ($summary['risk_score'] ?? 0);
            $confidenceTotal += (int) ($summary['confidence_score'] ?? 0);
            $scoredCount++;
        }
    }

    return [
        'total' => count($summaryRows),
        'capable' => $capable,
        'fresh' => $fresh,
        'alert' => $alert,
        'congested' => $congested,
        'critical' => $critical,
        'dispatch' => $dispatch,
        'stale' => $stale,
        'auto_alert_open' => $autoAlertOpen,
        'persistent' => $persistent,
        'avg_confidence' => $scoredCount > 0 ? (int) round($confidenceTotal / $scoredCount) : 0,
        'avg_risk' => $scoredCount > 0 ? (int) round($riskTotal / $scoredCount) : 0,
    ];
};

$visionHealth = function_exists('vg_vision_service_health')
    ? vg_vision_service_health($action !== 'health', 2)
    : ['ok' => false, 'error' => 'Le module vision est indisponible.'];

if ($action === 'health') {
    $respond(200, [
        'ok' => true,
        'action' => $action,
        'service' => $visionHealth,
        'overview' => $overview($summaries),
        'summaries' => array_values($summaries),
    ]);
}

$updated = [];
$errors = [];

if ($action === 'analyze_camera') {
    $cameraId = trim((string) ($_POST['camera_id'] ?? ''));
    if ($cameraId === '' || !isset($cameraById[$cameraId])) {
        $respond(404, [
            'ok' => false,
            'error' => 'Camera introuvable.',
            'service' => $visionHealth,
            'overview' => $overview($summaries),
        ]);
    }

    $result = function_exists('vgx_camera_analyze_capture')
        ? vgx_camera_analyze_capture($cameraById[$cameraId], true, 45)
        : ['ok' => false, 'error' => 'Le moteur camera IA n est pas charge.'];

    if (!empty($result['ok']) && is_array($result['summary'] ?? null)) {
        $updated[] = $result['summary'];
        $summaries[$cameraId] = $result['summary'];
    } else {
        $errors[] = [
            'camera_id' => $cameraId,
            'error' => (string) ($result['error'] ?? 'Analyse impossible.'),
            'summary' => $result['summary'] ?? ($summaries[$cameraId] ?? []),
        ];
    }
} elseif ($action === 'analyze_due') {
    $results = function_exists('vgx_camera_auto_analyze_due')
        ? vgx_camera_auto_analyze_due($limit, $cooldownSeconds, $failureCooldownSeconds)
        : [];

    foreach ($results as $result) {
        $cameraId = trim((string) ($result['summary']['camera_id'] ?? ''));
        if (!empty($result['ok']) && is_array($result['summary'] ?? null) && $cameraId !== '') {
            $updated[] = $result['summary'];
            $summaries[$cameraId] = $result['summary'];
            continue;
        }

        if ($cameraId !== '') {
            $summaries[$cameraId] = $result['summary'] ?? ($summaries[$cameraId] ?? []);
        }

        $errors[] = [
            'camera_id' => $cameraId,
            'error' => (string) ($result['error'] ?? 'Analyse impossible.'),
            'summary' => $result['summary'] ?? [],
        ];
    }
} else {
    $respond(422, [
        'ok' => false,
        'error' => 'Action IA inconnue.',
        'service' => $visionHealth,
        'overview' => $overview($summaries),
    ]);
}

$respond(200, [
    'ok' => true,
    'action' => $action,
    'service' => function_exists('vg_vision_service_health') ? vg_vision_service_health(false, 2) : $visionHealth,
    'updated_count' => count($updated),
    'updated' => $updated,
    'errors' => $errors,
    'overview' => $overview($summaries),
]);
