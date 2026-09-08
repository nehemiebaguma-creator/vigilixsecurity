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
if (($user['role'] ?? '') !== 'admin') {
    $respond(403, [
        'ok' => false,
        'error' => 'Acces reserve au centre OPS.',
    ]);
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$cameraId = trim((string) ($_POST['camera_id'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($cameraId === '') {
    $respond(422, [
        'ok' => false,
        'error' => 'Selectionnez une camera avant la capture.',
    ]);
}

$camera = null;
foreach ((function_exists('vgx_cameras') ? vgx_cameras() : []) as $cameraRow) {
    if (!is_array($cameraRow)) {
        continue;
    }

    if ((string) ($cameraRow['id'] ?? '') === $cameraId) {
        $camera = $cameraRow;
        break;
    }
}

if (!is_array($camera)) {
    $respond(404, [
        'ok' => false,
        'error' => 'Camera introuvable dans le registre OPS.',
    ]);
}

$captureSource = function_exists('vg_camera_operator_capture_source')
    ? vg_camera_operator_capture_source($camera)
    : ['supported' => false, 'message' => 'Capture indisponible.'];

if (empty($captureSource['supported'])) {
    $respond(422, [
        'ok' => false,
        'error' => (string) ($captureSource['message'] ?? 'Cette camera ne permet pas encore une capture operateur exploitable.'),
    ]);
}

$captureFetch = function_exists('vg_camera_fetch_capture')
    ? vg_camera_fetch_capture((string) ($captureSource['url'] ?? ''), 4)
    : ['ok' => false, 'error' => 'Le module de capture camera est indisponible.'];

if (
    (empty($captureFetch['ok']) || !is_string($captureFetch['bytes'] ?? null))
    && trim((string) ($captureSource['fallback_url'] ?? '')) !== ''
) {
    $captureFetch = function_exists('vg_camera_fetch_capture')
        ? vg_camera_fetch_capture((string) ($captureSource['fallback_url'] ?? ''), 4)
        : $captureFetch;
}

if (empty($captureFetch['ok']) || !is_string($captureFetch['bytes'] ?? null)) {
    $respond(502, [
        'ok' => false,
        'error' => (string) ($captureFetch['error'] ?? 'Impossible de recuperer une image depuis la camera.')
            . ' Verifiez le flux photo HTTP de la camera et son acces depuis le serveur VIGILANCE.',
    ]);
}

$mimeType = (string) ($captureFetch['mime'] ?? 'image/jpeg');
$extensionMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
$extension = $extensionMap[strtolower($mimeType)] ?? 'jpg';

$captureDirectory = rtrim((string) vg_storage_dir(), '/\\') . '/operator-captures';
if (!is_dir($captureDirectory) && !@mkdir($captureDirectory, 0777, true) && !is_dir($captureDirectory)) {
    $respond(500, [
        'ok' => false,
        'error' => 'Impossible de preparer le stockage des captures operateur.',
    ]);
}

$fileName = 'capture-' . date('Ymd-His') . '-' . substr(md5($cameraId . microtime(true)), 0, 8) . '.' . $extension;
$absolutePath = $captureDirectory . '/' . $fileName;
$relativePath = 'storage/operator-captures/' . $fileName;

if (@file_put_contents($absolutePath, $captureFetch['bytes']) === false) {
    $respond(500, [
        'ok' => false,
        'error' => 'Impossible de sauvegarder la capture operateur.',
    ]);
}

$analysis = function_exists('vgx_drone_api_upload')
    ? vgx_drone_api_upload(
        '/vision/identify-face',
        'image',
        $absolutePath,
        [],
        'Le service de reconnaissance faciale est indisponible.',
        120
    )
    : ['ok' => false, 'error' => 'Le connecteur vision n est pas charge.'];

if (empty($analysis['ok']) || !is_array($analysis['data'] ?? null)) {
    $respond(502, [
        'ok' => false,
        'error' => (string) ($analysis['error'] ?? 'La reconnaissance faciale a echoue.'),
        'capture_url' => function_exists('vg_url') ? vg_url($relativePath) : ('/' . ltrim($relativePath, '/')),
    ]);
}

$analysisData = $analysis['data'];
$facesDetected = (int) ($analysisData['faces_detected'] ?? 0);
$matches = array_values(array_filter((array) ($analysisData['matches'] ?? []), static function ($match): bool {
    return is_array($match);
}));
$matchCount = count($matches);

$riskPriority = ['normal' => 0, 'suspect' => 1, 'dangereux' => 2, 'critique' => 3];
$topRiskLevel = 'normal';
foreach ($matches as $match) {
    $matchRisk = strtolower((string) ($match['risk_level'] ?? 'normal'));
    if (($riskPriority[$matchRisk] ?? 0) > ($riskPriority[$topRiskLevel] ?? 0)) {
        $topRiskLevel = $matchRisk;
    }
}

$status = 'clear';
if ($matchCount > 0) {
    $status = in_array($topRiskLevel, ['dangereux', 'critique'], true) ? 'critical-match' : 'match';
} elseif ($facesDetected > 0) {
    $status = 'checked';
} else {
    $status = 'no-face';
}

$clientName = '';
$clientId = (string) ($camera['client_id'] ?? '');
if ($clientId !== '') {
    foreach ((function_exists('vgx_clients') ? vgx_clients() : []) as $client) {
        if (!is_array($client)) {
            continue;
        }
        if ((string) ($client['id'] ?? '') === $clientId) {
            $clientName = (string) ($client['name'] ?? $client['company_name'] ?? '');
            break;
        }
    }
}

$event = function_exists('vgx_save_face_recognition_event')
    ? vgx_save_face_recognition_event([
        'camera_id' => (string) ($camera['id'] ?? ''),
        'camera_name' => (string) ($camera['name'] ?? $camera['label'] ?? 'Camera VIGILANCE'),
        'client_id' => $clientId,
        'client_name' => $clientName,
        'operator_name' => (string) ($user['name'] ?? 'Operateur VIGILANCE'),
        'capture_url' => function_exists('vg_url') ? vg_url($relativePath) : ('/' . ltrim($relativePath, '/')),
        'capture_mode' => (string) ($captureSource['mode'] ?? 'operator_capture'),
        'status' => $status,
        'faces_detected' => $facesDetected,
        'match_count' => $matchCount,
        'matches' => $matches,
        'notes' => $notes,
        'captured_at' => date('c'),
    ])
    : [];

$nextAction = 'Continuer la surveillance camera.';
if ($matchCount > 0 && in_array($topRiskLevel, ['dangereux', 'critique'], true)) {
    $nextAction = 'Escalader au centre OPS, ouvrir le dossier et preparer un dispatch terrain.';
} elseif ($matchCount > 0) {
    $nextAction = 'Verifier le dossier, confirmer l identite et consigner la remontee.';
} elseif ($facesDetected > 0) {
    $nextAction = 'Aucune correspondance. Garder la veille et relancer une capture si besoin.';
} else {
    $nextAction = 'Reprendre une capture plus nette ou utiliser la torche/focus.';
}

$respond(200, [
    'ok' => true,
    'camera' => [
        'id' => (string) ($camera['id'] ?? ''),
        'name' => (string) ($camera['name'] ?? $camera['label'] ?? 'Camera VIGILANCE'),
        'location' => (string) ($camera['location'] ?? $camera['zone'] ?? 'Position non renseignee'),
        'client_id' => $clientId,
        'client_name' => $clientName,
    ],
    'capture' => [
        'mode' => (string) ($captureSource['mode'] ?? 'operator_capture'),
        'label' => (string) ($captureSource['label'] ?? 'Capture operateur'),
        'preview_url' => (string) ($captureSource['preview_url'] ?? ''),
        'stored_url' => function_exists('vg_url') ? vg_url($relativePath) : ('/' . ltrim($relativePath, '/')),
        'mime' => $mimeType,
    ],
    'analysis' => $analysisData,
    'event' => $event,
    'workflow' => [
        'status' => $status,
        'top_risk_level' => $topRiskLevel,
        'next_action' => $nextAction,
    ],
]);
