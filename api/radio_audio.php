<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$fail = static function (int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
};

$user = function_exists('vg_api_auth') ? vg_api_auth() : null;
if (!is_array($user) || $user === []) {
    $fail(401, 'Non authentifie.');
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$audioId = trim((string) ($_GET['audio_id'] ?? $_GET['id'] ?? ''));
if ($audioId === '' || !preg_match('/^[a-z0-9._-]+$/i', $audioId)) {
    $fail(400, 'Audio invalide.');
}

$messages = function_exists('vgx_list_communications') ? vgx_list_communications() : [];
$matchingMessages = [];
foreach ($messages as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }
    if ((string) ($candidate['audio_id'] ?? '') === $audioId) {
        $matchingMessages[] = $candidate;
    }
}

if ($matchingMessages === []) {
    $fail(404, 'Audio introuvable.');
}
$message = $matchingMessages[0];

$role = strtolower(trim((string) ($user['role'] ?? '')));
$isAllowed = function_exists('vg_is_backoffice_role') && vg_is_backoffice_role($role);
if (!$isAllowed && $role === 'agent') {
    $agentId = trim((string) ($user['agent_id'] ?? $_SESSION['agent_id'] ?? $user['id'] ?? ''));
    foreach ($matchingMessages as $candidateMessage) {
        $toRole = strtolower(trim((string) ($candidateMessage['to_role'] ?? '')));
        $fromRole = strtolower(trim((string) ($candidateMessage['from_role'] ?? '')));
        $toId = trim((string) ($candidateMessage['to_id'] ?? ''));
        $fromId = trim((string) ($candidateMessage['from_id'] ?? ''));
        $messageAgentId = trim((string) ($candidateMessage['agent_id'] ?? ''));
        $isAllowed = $agentId !== '' && (
            ($toRole === 'agent' && ($toId === '' || $toId === $agentId))
            || ($fromRole === 'agent' && $fromId === $agentId)
            || $messageAgentId === $agentId
        );
        if ($isAllowed) {
            $message = $candidateMessage;
            break;
        }
    }
}
if (!$isAllowed && $role === 'client') {
    $clientId = trim((string) ($user['client_id'] ?? $_SESSION['client_id'] ?? $user['id'] ?? ''));
    foreach ($matchingMessages as $candidateMessage) {
        $isAllowed = $clientId !== '' && (
            (string) ($candidateMessage['client_id'] ?? '') === $clientId
            || (strtolower((string) ($candidateMessage['to_role'] ?? '')) === 'client' && (string) ($candidateMessage['to_id'] ?? '') === $clientId)
            || (strtolower((string) ($candidateMessage['from_role'] ?? '')) === 'client' && (string) ($candidateMessage['from_id'] ?? '') === $clientId)
        );
        if ($isAllowed) {
            $message = $candidateMessage;
            break;
        }
    }
}

if (!$isAllowed) {
    $fail(403, 'Acces refuse.');
}

$storageRoot = function_exists('vg_storage_dir')
    ? rtrim(str_replace('\\', '/', vg_storage_dir()), '/')
    : rtrim(str_replace('\\', '/', dirname(__DIR__) . '/storage'), '/');
$storageReal = realpath($storageRoot);
$storageReal = $storageReal !== false ? rtrim(str_replace('\\', '/', $storageReal), '/') : $storageRoot;

$relativePath = trim((string) ($message['audio_relative_path'] ?? ''));
$candidatePath = '';
if ($relativePath !== '') {
    $candidatePath = $storageRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}
if ($candidatePath === '' && trim((string) ($message['audio_path'] ?? '')) !== '') {
    $candidatePath = str_replace('\\', '/', trim((string) $message['audio_path']));
}

$realPath = $candidatePath !== '' ? realpath($candidatePath) : false;
if ($realPath === false || !is_file($realPath)) {
    $fail(404, 'Fichier audio introuvable.');
}
$realPath = str_replace('\\', '/', $realPath);
if (!str_starts_with($realPath, $storageReal . '/')) {
    $fail(403, 'Chemin audio refuse.');
}

$mime = trim((string) ($message['audio_mime'] ?? 'audio/webm'));
if ($mime === '') {
    $mime = 'audio/webm';
}
$extensionMap = [
    'audio/webm' => 'webm',
    'video/webm' => 'webm',
    'audio/ogg' => 'ogg',
    'audio/opus' => 'opus',
    'audio/mp4' => 'm4a',
    'video/mp4' => 'm4a',
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
];
$extension = $extensionMap[strtolower($mime)] ?? 'webm';
$filename = $audioId . '.' . $extension;

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: inline; filename="' . $filename . '"');

readfile($realPath);
exit;
