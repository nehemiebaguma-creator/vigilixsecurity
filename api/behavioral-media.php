<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/behavioral.php';

$user = function_exists('vg_api_auth') ? vg_api_auth() : null;
if (!is_array($user) || $user === []) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Non authentifie.';
    exit;
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$kind = strtolower(trim((string) ($_GET['kind'] ?? 'video')));
if ($sessionId === '' || !in_array($kind, ['video', 'audio'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parametres invalides.';
    exit;
}

$session = vg_behavioral_find_session($sessionId);
if (!is_array($session) || !vg_behavioral_can_view_session($session, $user)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acces refuse.';
    exit;
}

$media = vg_behavioral_read_media($session, $kind);
if (!is_array($media) || !is_string($media['bytes'] ?? null)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Media introuvable.';
    exit;
}

$mime = trim((string) ($media['mime'] ?? 'application/octet-stream'));
$filenameBase = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($session['session_code'] ?? $sessionId));
$filenameBase = trim((string) $filenameBase, '-');
$extensionMap = [
    'video/webm' => 'webm',
    'video/mp4' => 'mp4',
    'video/ogg' => 'ogv',
    'audio/webm' => 'webm',
    'audio/ogg' => 'ogg',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/mpeg' => 'mp3',
];
$extension = $extensionMap[strtolower($mime)] ?? ($kind === 'audio' ? 'audio' : 'video');

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
header('Content-Length: ' . strlen((string) $media['bytes']));
header('Content-Disposition: inline; filename="' . ($filenameBase !== '' ? $filenameBase : 'behavioral-media') . '-' . $kind . '.' . $extension . '"');

echo $media['bytes'];
exit;
