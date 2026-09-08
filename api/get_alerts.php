<?php

require __DIR__ . '/../auth/check.php';
require __DIR__ . '/../includes/bootstrap.php';

if (function_exists('vg_require_role')) {
    vg_require_role('admin');
}

$alerts = function_exists('vgx_alerts') ? array_values(vgx_alerts()) : [];
$interventions = function_exists('vgx_interventions') ? array_values(vgx_interventions()) : [];
$agents = function_exists('vgx_agents') ? array_values(vgx_agents()) : [];
$treatedAlerts = function_exists('vgx_treated_alerts') ? array_values(vgx_treated_alerts()) : [];
$calls = function_exists('vgx_list_call_requests') ? array_values(vgx_list_call_requests()) : [];
$communications = function_exists('vgx_list_communications') ? array_values(vgx_list_communications()) : [];
$isTerminalIntervention = static function (array $intervention): bool {
    $status = (string) ($intervention['status'] ?? '');

    return function_exists('vgx_is_terminal_intervention_status')
        ? vgx_is_terminal_intervention_status($status)
        : in_array(strtolower(trim($status)), ['terminee', 'terminée', 'mission terminee', 'mission terminée', 'mission cloturee', 'mission clôturée', 'situation maitrisee', 'situation maîtrisée', 'resolved', 'closed', 'done'], true);
};

usort($alerts, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

usort($interventions, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? $b['departure_time'] ?? ''), (string) ($a['created_at'] ?? $a['departure_time'] ?? ''));
});

usort($treatedAlerts, static function (array $a, array $b): int {
    return strcmp((string) ($b['closed_at'] ?? $b['updated_at'] ?? $b['created_at'] ?? ''), (string) ($a['closed_at'] ?? $a['updated_at'] ?? $a['created_at'] ?? ''));
});

$metrics = [
    'alerts' => 0,
    'all_alerts' => count($alerts),
    'stale_alerts' => 0,
    'mission_alerts' => 0,
    'agents' => count($agents),
    'call_requests' => count($calls),
    'communications' => 0,
    'urgent_communications' => 0,
    'pending_ack_communications' => 0,
    'available_agents' => count(array_filter($agents, static function (array $agent): bool {
        $status = strtolower((string) ($agent['status'] ?? ''));
        return in_array($status, ['disponible', 'available', 'ready', 'actif'], true);
    })),
];
$isNewAlertStatus = static function (string $status): bool {
    $normalized = strtolower(trim($status));
    return in_array($normalized, ['nouvelle alerte', 'nouvelle', 'new', 'ouverte', 'open'], true);
};
$isPendingCall = static function (array $call): bool {
    $status = strtolower(trim((string) ($call['status'] ?? 'pending')));
    return in_array($status, ['nouveau', 'new', 'en attente', 'pending', 'urgent', 'sos', 'critique', 'critical'], true);
};

foreach ($alerts as $index => $alert) {
    $client = function_exists('vgx_find_client_by_id')
        ? vgx_find_client_by_id((string) ($alert['client_id'] ?? ''))
        : null;
    $dispatchContext = function_exists('vgx_alert_dispatch_context')
        ? vgx_alert_dispatch_context($alert)
        : [];
    $alerts[$index]['client_name'] = $client ? (string) ($client['full_name'] ?? $client['name'] ?? 'Client') : 'Client';
    $alerts[$index]['client_phone'] = $client ? (string) ($client['phone'] ?? '') : '';
    $alerts[$index]['client_address'] = $client ? trim(implode(' - ', array_filter([
        (string) ($client['commune'] ?? ''),
        (string) ($client['quartier'] ?? ''),
        (string) ($client['avenue'] ?? ''),
        (string) ($client['address'] ?? ''),
    ]))) : '';
    $alerts[$index]['client_commune'] = $client ? (string) ($client['commune'] ?? '') : '';
    $alerts[$index]['client_subscription'] = $client ? (string) ($client['subscription'] ?? '') : '';
    $alerts[$index]['client_latitude'] = (string) (($alert['latitude'] ?? '') !== '' ? ($alert['latitude'] ?? '') : ($client['latitude'] ?? ''));
    $alerts[$index]['client_longitude'] = (string) (($alert['longitude'] ?? '') !== '' ? ($alert['longitude'] ?? '') : ($client['longitude'] ?? ''));
    $alerts[$index]['dispatch_radio_short'] = (string) (($alert['dispatch_radio_short'] ?? '') !== '' ? ($alert['dispatch_radio_short'] ?? '') : ($dispatchContext['dispatch_radio_short'] ?? ''));
    $alerts[$index]['dispatch_briefing'] = (string) (($alert['dispatch_briefing'] ?? '') !== '' ? ($alert['dispatch_briefing'] ?? '') : ($dispatchContext['dispatch_briefing'] ?? ''));
    $alerts[$index]['route_url'] = (string) ($dispatchContext['route_url'] ?? '');
    $alerts[$index]['route_summary'] = (string) ($dispatchContext['route_summary'] ?? '');
    $alerts[$index]['linked_cameras'] = array_values((array) ($dispatchContext['linked_cameras'] ?? []));
    $alerts[$index]['linked_camera_count'] = (int) ($dispatchContext['linked_camera_count'] ?? 0);
    $alerts[$index]['linked_camera_summary'] = (string) ($dispatchContext['linked_camera_summary'] ?? '');
    $alerts[$index]['camera_intel_summary'] = (array) ($dispatchContext['camera_intel_summary'] ?? []);
    $alerts[$index]['recommended_actions'] = array_values((array) ($dispatchContext['recommended_actions'] ?? []));
    $leadCamera = $alerts[$index]['linked_cameras'][0] ?? [];
    $videoPath = trim((string) (($alerts[$index]['incident_video_relative_path'] ?? '') ?: ($alerts[$index]['video_path'] ?? '')));
    $videoUrl = trim((string) ($alerts[$index]['video_url'] ?? ''));
    $leadVideoPath = trim((string) (($leadCamera['incident_video_relative_path'] ?? '') ?: ($leadCamera['video_path'] ?? '')));
    $leadVideoUrl = trim((string) ($leadCamera['video_url'] ?? ''));
    if ($videoPath === '' && $leadVideoPath !== '') {
        $videoPath = $leadVideoPath;
    }
    if ($videoUrl === '' && $leadVideoUrl !== '') {
        $videoUrl = $leadVideoUrl;
    }
    if ($videoUrl === '' && $videoPath !== '' && function_exists('vg_url')) {
        $videoUrl = vg_url($videoPath);
    }
    $clipBeforeSeconds = (int) ($alerts[$index]['incident_video_seconds_before'] ?? $leadCamera['incident_video_seconds_before'] ?? 10);
    $clipAfterSeconds = (int) ($alerts[$index]['incident_video_seconds_after'] ?? $leadCamera['incident_video_seconds_after'] ?? 20);
    $clipPending = !empty($alerts[$index]['incident_video_pending']) || !empty($leadCamera['incident_video_pending']);
    $dangerText = strtolower(trim(implode(' ', [
        (string) ($alerts[$index]['message'] ?? ''),
        (string) ($alerts[$index]['detection_summary'] ?? ''),
        (string) ($alerts[$index]['type'] ?? ''),
        (string) ($alerts[$index]['type_alerte'] ?? ''),
        (string) ($alerts[$index]['detection_labels_text'] ?? ''),
    ])));
    $dangerByText = $dangerText !== '' && preg_match('/arme|couteau|knife|gun|pistol|weapon|danger|objet dangereux/i', $dangerText) === 1;
    $alerts[$index]['incident_video_relative_path'] = $videoPath;
    $alerts[$index]['video_path'] = $videoPath;
    $alerts[$index]['video_url'] = $videoUrl;
    $alerts[$index]['incident_video_pending'] = $clipPending ? 1 : 0;
    $alerts[$index]['incident_video_seconds_before'] = max(0, $clipBeforeSeconds);
    $alerts[$index]['incident_video_seconds_after'] = max(0, $clipAfterSeconds);
    $alerts[$index]['requires_siren'] = !empty($alert['requires_siren']) || !empty($alert['sound_alert']) || $dangerByText ? 1 : 0;
    $alerts[$index]['sound_alert'] = !empty($alert['sound_alert']) || !empty($alert['requires_siren']) || $dangerByText ? 1 : 0;
    $alerts[$index]['danger_detected'] = !empty($alert['danger_detected']) || !empty($alert['weapon_detected']) || $dangerByText ? 1 : 0;
    $alerts[$index]['weapon_detected'] = !empty($alert['weapon_detected']) || !empty($alert['danger_detected']) || $dangerByText ? 1 : 0;
    $alerts[$index]['priority'] = (string) (($alert['priority'] ?? '') !== '' ? $alert['priority'] : ($alert['level_name'] ?? ''));
    $alerts[$index]['level'] = (string) (($alert['level'] ?? '') !== '' ? $alert['level'] : ($alert['level_name'] ?? ''));
    $alerts[$index]['type'] = (string) (($alert['type'] ?? '') !== '' ? $alert['type'] : ($alert['type_alerte'] ?? ''));
}

foreach ($calls as $index => $call) {
    $client = function_exists('vgx_find_client_by_id')
        ? vgx_find_client_by_id((string) ($call['client_id'] ?? ''))
        : null;
    $calls[$index]['client_name'] = (string) ($call['client_name'] ?? ($client['full_name'] ?? $client['name'] ?? 'Abonne VIGILANCE'));
    $calls[$index]['phone'] = (string) ($call['phone'] ?? ($client['phone'] ?? ''));
    $calls[$index]['channel'] = (string) ($call['channel'] ?? 'Portail');
    $calls[$index]['requester_role'] = (string) ($call['requester_role'] ?? 'client');
    $calls[$index]['status'] = (string) ($call['status'] ?? 'pending');
}

usort($calls, static function (array $a, array $b) use ($isPendingCall): int {
    $priorityDiff = ((int) $isPendingCall($b)) <=> ((int) $isPendingCall($a));
    if ($priorityDiff !== 0) {
        return $priorityDiff;
    }

    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$pendingCalls = array_values(array_filter($calls, $isPendingCall));

$alertByClientId = [];
foreach ($alerts as $alert) {
    $clientId = (string) ($alert['client_id'] ?? '');
    if ($clientId !== '' && !isset($alertByClientId[$clientId])) {
        $alertByClientId[$clientId] = $alert;
    }
}

foreach ($interventions as $index => $intervention) {
    $client = function_exists('vgx_find_client_by_id')
        ? vgx_find_client_by_id((string) ($intervention['client_id'] ?? ''))
        : null;
    $linkedClientId = (string) ($intervention['client_id'] ?? '');
    $linkedAlert = $linkedClientId !== '' ? ($alertByClientId[$linkedClientId] ?? null) : null;
    $dispatchContext = is_array($linkedAlert) && function_exists('vgx_alert_dispatch_context')
        ? (array) vgx_alert_dispatch_context($linkedAlert)
        : [];
    $cameraIntel = (array) ($dispatchContext['camera_intel_summary'] ?? []);
    $linkedCameras = array_values((array) ($dispatchContext['linked_cameras'] ?? []));
    $leadCamera = $linkedCameras[0] ?? null;
    $agentIds = function_exists('vgx_intervention_agent_ids')
        ? vgx_intervention_agent_ids($intervention)
        : array_values(array_filter([(string) ($intervention['agent_id'] ?? '')]));
    $agentNames = [];
    $agentSummary = [];

    foreach ($agentIds as $agentId) {
        $agentRow = function_exists('vgx_find_agent_by_id') ? vgx_find_agent_by_id((string) $agentId) : null;
        if (!is_array($agentRow)) {
            continue;
        }

        $agentName = (string) ($agentRow['name'] ?? 'Agent VIGILANCE');
        $agentNames[] = $agentName;
        $agentSummary[] = [
            'id' => (string) ($agentRow['id'] ?? ''),
            'name' => $agentName,
            'zone' => (string) ($agentRow['zone'] ?? 'Kinshasa'),
            'role' => (string) ($agentRow['role_label'] ?? $agentRow['role'] ?? 'Agent terrain'),
            'status' => (string) ($agentRow['status'] ?? 'Disponible'),
        ];
    }

    $missionObjective = trim((string) ($dispatchContext['objective'] ?? ($linkedAlert['message'] ?? ($intervention['comment'] ?? 'Mission terrain'))));
    $routeSummary = trim((string) ($dispatchContext['route_summary'] ?? ($intervention['route'] ?? '')));
    $leadCameraName = trim((string) (($leadCamera['name'] ?? $leadCamera['camera_name'] ?? '')));
    $leadZone = trim((string) ($cameraIntel['lead_watch_zone_name'] ?? ($leadCamera['watch_zone_name'] ?? $leadCamera['zone'] ?? '')));
    $leadRuleSummary = trim((string) ($cameraIntel['lead_rule_summary'] ?? ''));
    $leadAlertSummary = trim((string) ($cameraIntel['lead_alert_summary'] ?? ''));
    $leadOpsSummary = trim((string) ($cameraIntel['lead_ops_summary'] ?? ''));
    $leadExpectedSummary = trim((string) ($cameraIntel['lead_expected_summary'] ?? ''));
    $handlingSuggestion = trim(implode(' ', array_filter([
        $leadOpsSummary !== '' ? 'Centre OPS: ' . $leadOpsSummary : '',
        $agentNames !== [] ? 'Agents engages: ' . implode(', ', $agentNames) . '.' : '',
        $routeSummary !== '' ? 'Route retenue: ' . $routeSummary . '.' : '',
    ])));
    $reportSuggestion = trim(implode(' ', array_filter([
        'Mission #' . (string) ($intervention['id'] ?? '--') . '.',
        $missionObjective !== '' ? 'Objet: ' . $missionObjective . '.' : '',
        $leadAlertSummary !== '' ? 'Declencheur: ' . $leadAlertSummary . '.' : '',
        $leadExpectedSummary !== '' ? 'Sortie attendue: ' . $leadExpectedSummary . '.' : '',
    ])));
    $followUpSuggestion = trim(implode(' ', array_filter([
        $leadExpectedSummary !== '' ? $leadExpectedSummary . '.' : '',
        $leadCameraName !== '' ? 'Conserver les preuves de ' . $leadCameraName . ($leadZone !== '' ? ' sur ' . $leadZone : '') . '.' : '',
        'Rappeler le client si un controle ou une maintenance complementaire reste necessaire.',
    ])));

    $interventions[$index]['client_name'] = (string) ($client['full_name'] ?? $client['name'] ?? 'Abonne VIGILANCE');
    $interventions[$index]['client_address'] = $client ? trim(implode(' - ', array_filter([
        (string) ($client['commune'] ?? ''),
        (string) ($client['quartier'] ?? ''),
        (string) ($client['avenue'] ?? ''),
        (string) ($client['address'] ?? ''),
    ]))) : '';
    $interventions[$index]['client_phone'] = (string) ($client['phone'] ?? '');
    $interventions[$index]['agent_ids'] = $agentIds;
    $interventions[$index]['agent_names'] = $agentNames;
    $interventions[$index]['agents'] = $agentSummary;
    $interventions[$index]['mission_objective'] = $missionObjective;
    $interventions[$index]['route_url'] = (string) ($dispatchContext['route_url'] ?? '');
    $interventions[$index]['route_summary'] = $routeSummary;
    $interventions[$index]['route_steps'] = array_values((array) ($dispatchContext['route_steps'] ?? []));
    $interventions[$index]['dispatch_radio_short'] = (string) ($dispatchContext['dispatch_radio_short'] ?? '');
    $interventions[$index]['dispatch_briefing'] = (string) ($dispatchContext['dispatch_briefing'] ?? '');
    $interventions[$index]['recommended_actions'] = array_values((array) ($dispatchContext['recommended_actions'] ?? []));
    $interventions[$index]['linked_camera_summary'] = (string) ($dispatchContext['linked_camera_summary'] ?? '');
    $interventions[$index]['camera_intel_summary'] = $cameraIntel;
    $interventions[$index]['lead_camera_name'] = $leadCameraName;
    $interventions[$index]['lead_zone'] = $leadZone;
    $interventions[$index]['lead_rule_summary'] = $leadRuleSummary;
    $interventions[$index]['lead_alert_summary'] = $leadAlertSummary;
    $interventions[$index]['lead_ops_summary'] = $leadOpsSummary;
    $interventions[$index]['lead_expected_summary'] = $leadExpectedSummary;
    $interventions[$index]['cause_suggestion'] = $missionObjective !== '' ? $missionObjective : 'Cause terrain a confirmer';
    $interventions[$index]['handling_suggestion'] = $handlingSuggestion !== '' ? $handlingSuggestion : 'Le centre et l agent doivent consigner ici la reponse reelle appliquee.';
    $interventions[$index]['report_suggestion'] = $reportSuggestion !== '' ? $reportSuggestion : 'Rapport final a completer.';
    $interventions[$index]['follow_up_suggestion'] = $followUpSuggestion !== '' ? $followUpSuggestion : 'Conserver les preuves utiles et confirmer les suites client.';
    $interventions[$index]['is_terminal'] = $isTerminalIntervention($intervention);
}

$openInterventions = array_values(array_filter($interventions, static function (array $intervention): bool {
    return empty($intervention['is_terminal']);
}));

$opsAlertGroups = function_exists('vgx_alert_ops_split')
    ? vgx_alert_ops_split($alerts, $interventions)
    : [
        'all' => $alerts,
        'live' => $alerts,
        'mission' => [],
        'stale' => [],
    ];
$liveAlerts = array_values((array) ($opsAlertGroups['live'] ?? []));
$missionAlerts = array_values((array) ($opsAlertGroups['mission'] ?? []));
$staleAlerts = array_values((array) ($opsAlertGroups['stale'] ?? []));

$metrics['alerts'] = count($liveAlerts);
$metrics['stale_alerts'] = count($staleAlerts);
$metrics['mission_alerts'] = count($missionAlerts);
$metrics['critical_alerts'] = count(array_filter($liveAlerts, static function (array $alert): bool {
    $level = strtolower((string) ($alert['priority'] ?? $alert['level'] ?? $alert['level_name'] ?? ''));

    return in_array($level, ['critique', 'critical', 'haute', 'high', 'urgent'], true);
}));
$metrics['new_alerts'] = count(array_filter($liveAlerts, static function (array $alert) use ($isNewAlertStatus): bool {
    return $isNewAlertStatus((string) ($alert['status'] ?? ''));
}));
$metrics['call_requests'] = count($pendingCalls);
$metrics['pending_calls'] = count($pendingCalls);

$availableAgents = array_values(array_filter($agents, static function (array $agent): bool {
    $status = strtolower((string) ($agent['status'] ?? ''));

    return in_array($status, ['disponible', 'available', 'ready', 'actif'], true);
}));

$availableAgentPayload = array_map(static function (array $agent): array {
    return [
        'id' => (string) ($agent['id'] ?? ''),
        'name' => (string) ($agent['name'] ?? 'Agent VIGILANCE'),
        'zone' => (string) ($agent['zone'] ?? 'Kinshasa'),
        'role' => (string) ($agent['role_label'] ?? $agent['role'] ?? 'Agent terrain'),
        'status' => (string) ($agent['status'] ?? 'Disponible'),
        'phone' => (string) ($agent['phone'] ?? ''),
    ];
}, $availableAgents);
$allAgentPayload = array_map(static function (array $agent): array {
    return [
        'id' => (string) ($agent['id'] ?? ''),
        'name' => (string) ($agent['name'] ?? 'Agent VIGILANCE'),
        'zone' => (string) ($agent['zone'] ?? 'Kinshasa'),
        'role' => (string) ($agent['role_label'] ?? $agent['role'] ?? 'Agent terrain'),
        'status' => (string) ($agent['status'] ?? 'Disponible'),
        'phone' => (string) ($agent['phone'] ?? ''),
    ];
}, $agents);

$communications = array_values(array_filter($communications, static function (array $message): bool {
    $fromRole = strtolower((string) ($message['from_role'] ?? ''));
    $toRole = strtolower((string) ($message['to_role'] ?? ''));
    $contextType = strtolower((string) ($message['context_type'] ?? ''));

    return $toRole === 'admin'
        || $fromRole === 'admin'
        || $contextType === 'radio';
}));

usort($communications, static function (array $a, array $b): int {
    $weight = ['normal' => 0, 'urgent' => 2, 'critique' => 3, 'critical' => 3, 'sos' => 3];
    $leftAck = !empty($a['requires_ack']) && strtolower((string) ($a['ack_status'] ?? 'pending')) !== 'acknowledged' ? 1 : 0;
    $rightAck = !empty($b['requires_ack']) && strtolower((string) ($b['ack_status'] ?? 'pending')) !== 'acknowledged' ? 1 : 0;
    if ($leftAck !== $rightAck) {
        return $rightAck <=> $leftAck;
    }

    $left = $weight[strtolower((string) ($a['urgency'] ?? 'normal'))] ?? 0;
    $right = $weight[strtolower((string) ($b['urgency'] ?? 'normal'))] ?? 0;
    if ($left !== $right) {
        return $right <=> $left;
    }

    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

foreach ($communications as $index => $message) {
    $fromRole = strtolower((string) ($message['from_role'] ?? ''));
    $toRole = strtolower((string) ($message['to_role'] ?? ''));
    $clientName = (string) ($message['client_name'] ?? '');
    $agentName = (string) ($message['agent_name'] ?? '');
    $subject = trim((string) ($message['subject'] ?? 'Message VIGILANCE'));
    $urgency = strtolower((string) ($message['urgency'] ?? 'normal'));
    $messageType = strtolower(trim((string) ($message['communication_type'] ?? $message['message_type'] ?? 'message')));
    $channel = trim((string) ($message['channel'] ?? $message['radio_channel'] ?? ''));
    $scope = strtolower(trim((string) ($message['radio_scope'] ?? '')));
    $requiresAck = !empty($message['requires_ack']);
    $ackStatus = strtolower(trim((string) ($message['ack_status'] ?? ($requiresAck ? 'pending' : 'not_required'))));

    if ($clientName === '' && !empty($message['client_id']) && function_exists('vgx_find_client_by_id')) {
        $client = vgx_find_client_by_id((string) $message['client_id']);
        $clientName = (string) ($client['full_name'] ?? $client['name'] ?? '');
    }

    if ($agentName === '' && !empty($message['agent_id']) && function_exists('vgx_find_agent_by_id')) {
        $agent = vgx_find_agent_by_id((string) $message['agent_id']);
        $agentName = (string) ($agent['name'] ?? '');
    }

    $communications[$index]['subject'] = $subject;
    $communications[$index]['urgency'] = $urgency === 'critical' ? 'critique' : ($urgency === 'sos' ? 'critique' : $urgency);
    $communications[$index]['sender_label'] = match ($fromRole) {
        'client' => $clientName !== '' ? $clientName : 'Abonne VIGILANCE',
        'agent' => $agentName !== '' ? $agentName : 'Agent VIGILANCE',
        default => 'Centre OPS',
    };
    $communications[$index]['sender_role_label'] = match ($fromRole) {
        'client' => 'Client',
        'agent' => 'Agent',
        default => 'Centre',
    };
    $communications[$index]['recipient_role_label'] = match ($toRole) {
        'client' => 'Client',
        'agent' => 'Agent',
        default => 'Centre',
    };
    $communications[$index]['client_name'] = $clientName !== '' ? $clientName : 'Abonne VIGILANCE';
    $communications[$index]['agent_name'] = $agentName !== '' ? $agentName : 'Agent VIGILANCE';
    $communications[$index]['message_type'] = $messageType;
    $communications[$index]['channel'] = $channel;
    $communications[$index]['radio_scope'] = $scope;
    $communications[$index]['requires_ack'] = $requiresAck;
    $communications[$index]['ack_status'] = $ackStatus;
    $communications[$index]['dispatch_id'] = (string) ($message['dispatch_id'] ?? $message['batch_id'] ?? '');
    $communications[$index]['channel_label'] = $channel !== '' ? strtoupper($channel) : '';
    $communications[$index]['ack_label'] = $requiresAck
        ? ($ackStatus === 'acknowledged' ? 'Accuse confirme' : 'Accuse attendu')
        : 'Libre';
}

$metrics['communications'] = count($communications);
$metrics['urgent_communications'] = count(array_filter($communications, static function (array $message): bool {
    return in_array(strtolower((string) ($message['urgency'] ?? 'normal')), ['urgent', 'critique', 'critical', 'sos'], true);
}));
$metrics['pending_ack_communications'] = count(array_filter($communications, static function (array $message): bool {
    return !empty($message['requires_ack']) && strtolower((string) ($message['ack_status'] ?? 'pending')) !== 'acknowledged';
}));

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'ok' => true,
    'metrics' => $metrics,
    'alerts' => $liveAlerts,
    'all_alerts' => array_values((array) ($opsAlertGroups['all'] ?? [])),
    'stale_alerts' => $staleAlerts,
    'mission_alerts' => $missionAlerts,
    'calls' => array_slice($pendingCalls !== [] ? $pendingCalls : $calls, 0, 8),
    'communications' => array_slice($communications, 0, 8),
    'interventions' => $interventions,
    'open_interventions' => array_slice($openInterventions, 0, 6),
    'treated_alerts' => array_slice($treatedAlerts, 0, 6),
    'available_agents' => $availableAgentPayload,
    'all_agents' => $allAgentPayload,
));
