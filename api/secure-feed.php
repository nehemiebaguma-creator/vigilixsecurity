<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = function_exists('vg_current_user') ? vg_current_user() : null;
if (!is_array($user)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non authentifie.']);
    exit;
}
if (function_exists('vg_session_release')) {
    vg_session_release();
}

$role = strtolower((string) ($user['role'] ?? ''));
$payload = [
    'ok' => true,
    'role' => $role,
    'messages' => [],
    'alerts' => [],
    'calls' => [],
    'interventions' => [],
    'metrics' => [],
];

if ($role === 'admin') {
    $payload['messages'] = array_slice(vgx_list_communications(), 0, 20);
    $payload['alerts'] = array_slice(vgx_alerts(), 0, 10);
    $payload['calls'] = array_slice(vgx_list_call_requests(), 0, 10);
    $payload['interventions'] = array_slice(vgx_store_bootstrap()['interventions'] ?? [], 0, 10);
} elseif ($role === 'client') {
    $client = function_exists('vg_portal_current_client') ? vg_portal_current_client($user) : vgx_find_client_by_email((string) ($user['email'] ?? ''));
    if ($client !== null) {
        $payload['messages'] = array_slice(vgx_list_communications('client', (string) ($client['id'] ?? '')), 0, 20);
        $payload['alerts'] = array_slice(vgx_client_alerts((string) ($client['id'] ?? '')), 0, 10);
        $payload['calls'] = array_values(array_filter(vgx_list_call_requests(), static function (array $call) use ($client): bool {
            return (string) ($call['client_id'] ?? '') === (string) ($client['id'] ?? '');
        }));
    }
} elseif ($role === 'agent') {
    $agentId = trim((string) ($user['agent_id'] ?? ''));
    $agent = $agentId !== '' && function_exists('vgx_find_agent_by_id')
        ? vgx_find_agent_by_id($agentId)
        : null;
    if ($agent === null) {
        $agent = vgx_find_agent_by_email((string) ($user['email'] ?? ''));
    }
    if ($agent !== null) {
        $currentAgentId = (string) ($agent['id'] ?? '');
        $payload['messages'] = array_slice(vgx_list_communications('agent', $currentAgentId), 0, 20);
        $payload['interventions'] = array_slice(vgx_agent_interventions($currentAgentId), 0, 10);
        $payload['calls'] = array_values(array_filter(vgx_list_call_requests(), static function (array $call) use ($currentAgentId): bool {
            $requesterId = (string) ($call['requester_id'] ?? '');
            $targetRole = strtolower((string) ($call['target_role'] ?? ''));
            $targetId = (string) ($call['target_id'] ?? '');
            $contextId = (string) ($call['context_id'] ?? '');

            return $requesterId === $currentAgentId
                || ($targetRole === 'agent' && $targetId === $currentAgentId)
                || $contextId === $currentAgentId;
        }));
    }
}

$payload['messages'] = array_values(array_map(static function (array $message): array {
    $fromRole = strtolower((string) ($message['from_role'] ?? ''));
    $toRole = strtolower((string) ($message['to_role'] ?? ''));
    $urgency = strtolower((string) ($message['urgency'] ?? 'normal'));
    $requiresAck = !empty($message['requires_ack']);
    $ackStatus = strtolower(trim((string) ($message['ack_status'] ?? ($requiresAck ? 'pending' : 'not_required'))));

    $message['urgency'] = $urgency === 'critical' ? 'critique' : ($urgency === 'sos' ? 'critique' : $urgency);
    $message['sender_label'] = match ($fromRole) {
        'client' => (string) ($message['client_name'] ?? 'Abonne VIGILANCE'),
        'agent' => (string) ($message['agent_name'] ?? 'Agent VIGILANCE'),
        default => 'Centre OPS',
    };
    $message['recipient_role_label'] = match ($toRole) {
        'client' => 'Client',
        'agent' => 'Agent',
        default => 'Centre',
    };
    $message['requires_ack'] = $requiresAck;
    $message['ack_status'] = $ackStatus;
    $message['ack_label'] = $requiresAck
        ? ($ackStatus === 'acknowledged' ? 'Accuse confirme' : 'Accuse attendu')
        : '';

    return $message;
}, $payload['messages']));

$pendingAckCount = count(array_filter($payload['messages'], static function (array $message): bool {
    return !empty($message['requires_ack']) && strtolower((string) ($message['ack_status'] ?? 'pending')) !== 'acknowledged';
}));

$payload['metrics'] = [
    'messages' => count($payload['messages']),
    'alerts' => count($payload['alerts']),
    'calls' => count($payload['calls']),
    'interventions' => count($payload['interventions']),
    'pending_ack_communications' => $pendingAckCount,
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
