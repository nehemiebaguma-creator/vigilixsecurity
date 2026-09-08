<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../auth/check.php';

header('Content-Type: application/json; charset=utf-8');

$user = function_exists('vg_current_user') ? vg_current_user() : [];
if ($user === []) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non authentifie.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$messageId = trim((string) ($_POST['message_id'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
if ($messageId === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Message introuvable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$messages = function_exists('vgx_list_communications') ? array_values(vgx_list_communications()) : [];
$messageRow = null;
foreach ($messages as $message) {
    if (is_array($message) && (string) ($message['id'] ?? '') === $messageId) {
        $messageRow = $message;
        break;
    }
}

if (!is_array($messageRow)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Message introuvable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userRole = strtolower(trim((string) ($user['role'] ?? '')));
$userId = '';
if ($userRole === 'client') {
    $userId = trim((string) ($user['client_id'] ?? ''));
} elseif ($userRole === 'agent') {
    $userId = trim((string) ($user['agent_id'] ?? ''));
} else {
    $userId = trim((string) ($user['id'] ?? 'control-center'));
}

$isAuthorized = false;
$toRole = strtolower(trim((string) ($messageRow['to_role'] ?? '')));
$toId = trim((string) ($messageRow['to_id'] ?? ''));
$clientId = trim((string) ($messageRow['client_id'] ?? ''));
$agentId = trim((string) ($messageRow['agent_id'] ?? ''));

if ($userRole === 'client') {
    $isAuthorized = $toRole === 'client' && ($toId === $userId || $clientId === $userId);
} elseif ($userRole === 'agent') {
    $isAuthorized = ($toRole === 'agent' && in_array($toId, ['', $userId], true)) || $agentId === $userId;
} elseif (function_exists('vg_is_backoffice_role') && vg_is_backoffice_role($userRole)) {
    $isAuthorized = $toRole === 'admin' || $toRole === $userRole;
}

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Vous ne pouvez pas accuser ce message.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!function_exists('vgx_ack_communication')) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Service ACK indisponible.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$acked = vgx_ack_communication($messageId, [
    'role' => $userRole,
    'id' => $userId,
    'name' => (string) ($user['name'] ?? 'Utilisateur VIGILANCE'),
], $note);

if (!is_array($acked)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Impossible de confirmer la lecture.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pendingForPortal = 0;
if (isset($GLOBALS['messages']) && is_array($GLOBALS['messages'])) {
    $messages = $GLOBALS['messages'];
} else {
    $messages = function_exists('vgx_list_communications') ? array_values(vgx_list_communications()) : [];
}

foreach ($messages as $message) {
    if (!is_array($message) || empty($message['requires_ack']) || strtolower((string) ($message['ack_status'] ?? 'pending')) === 'acknowledged') {
        continue;
    }

    $messageToRole = strtolower(trim((string) ($message['to_role'] ?? '')));
    $messageToId = trim((string) ($message['to_id'] ?? ''));
    $messageClientId = trim((string) ($message['client_id'] ?? ''));
    $messageAgentId = trim((string) ($message['agent_id'] ?? ''));

    if ($userRole === 'client' && $messageToRole === 'client' && ($messageToId === $userId || $messageClientId === $userId)) {
        $pendingForPortal++;
    } elseif ($userRole === 'agent' && (($messageToRole === 'agent' && in_array($messageToId, ['', $userId], true)) || $messageAgentId === $userId)) {
        $pendingForPortal++;
    } elseif (function_exists('vg_is_backoffice_role') && vg_is_backoffice_role($userRole) && ($messageToRole === 'admin' || $messageToRole === $userRole)) {
        $pendingForPortal++;
    }
}

echo json_encode([
    'ok' => true,
    'message' => $acked,
    'pending_count' => $pendingForPortal,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
