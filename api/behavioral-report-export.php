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

$caps = vg_behavioral_capabilities($user);
if (empty($caps['export'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Export reserve a l administrateur.';
    exit;
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'session_id manquant.';
    exit;
}

$payload = vg_behavioral_export_payload($sessionId, $user);
if (!is_array($payload)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Dossier introuvable ou non exportable.';
    exit;
}

$session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
$authorizationRow = [
    'id' => uniqid('bha_', true),
    'session_id' => $sessionId,
    'authorization_type' => 'admin_export',
    'status' => 'granted',
    'subject_label' => (string) ($session['subject_label'] ?? ''),
    'granted_by_role' => strtolower(trim((string) ($user['role'] ?? 'admin'))),
    'granted_by_id' => vg_behavioral_actor_id($user),
    'granted_to_role' => strtolower(trim((string) ($user['role'] ?? 'admin'))),
    'granted_to_id' => vg_behavioral_actor_id($user),
    'details' => ['exported_at' => vg_behavioral_now()],
    'created_at' => vg_behavioral_now(),
];
vg_behavioral_store_append_rows('behavioral_authorizations', [$authorizationRow]);
vg_behavioral_db_sync_row('behavioral_authorizations', $authorizationRow);

$filenameBase = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($session['session_code'] ?? $sessionId));
$filenameBase = trim((string) $filenameBase, '-');
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . ($filenameBase !== '' ? $filenameBase : 'behavioral-report') . '.json"');

echo is_string($json) ? $json : '{}';
exit;
