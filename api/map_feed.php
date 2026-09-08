<?php

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/behavioral.php';

if (function_exists('vg_require_role')) {
    vg_require_role('admin');
}

if (function_exists('vgx_camera_auto_analyze_due')) {
    vgx_camera_auto_analyze_due(1, 1, 15);
}

if (!function_exists('vg_map_alert_level')) {
    function vg_map_alert_level(array $alert): string
    {
        return (string) ($alert['priority'] ?? $alert['level'] ?? $alert['level_name'] ?? 'Normale');
    }
}

if (!function_exists('vg_map_alert_type')) {
    function vg_map_alert_type(array $alert): string
    {
        return (string) ($alert['type'] ?? $alert['type_alerte'] ?? 'Alerte');
    }
}

if (!function_exists('vg_map_box_polygon')) {
    function vg_map_box_polygon(float $lat, float $lng, float $latOffset, float $lngOffset): array
    {
        return [
            ['lat' => $lat + $latOffset, 'lng' => $lng - $lngOffset],
            ['lat' => $lat + $latOffset, 'lng' => $lng + $lngOffset],
            ['lat' => $lat - $latOffset, 'lng' => $lng + $lngOffset],
            ['lat' => $lat - $latOffset, 'lng' => $lng - $lngOffset],
        ];
    }
}

if (!function_exists('vg_map_traffic_color')) {
    function vg_map_traffic_color(string $status): string
    {
        $s = strtolower(trim($status));
        if (in_array($s, VGX_LEVEL_CRITICAL, true)) {
            return VGX_TRAFFIC_COLORS['critique'];
        }
        if (in_array($s, VGX_LEVEL_HIGH, true)) {
            return VGX_TRAFFIC_COLORS['eleve'];
        }
        if (in_array($s, VGX_LEVEL_MEDIUM, true)) {
            return VGX_TRAFFIC_COLORS['modere'];
        }
        return VGX_TRAFFIC_COLORS['normal'];
    }
}

if (!function_exists('vg_map_traffic_radius')) {
    function vg_map_traffic_radius(string $status): int
    {
        $s = strtolower(trim($status));
        if (in_array($s, VGX_LEVEL_CRITICAL, true)) {
            return VGX_TRAFFIC_RADII['critique'];
        }
        if (in_array($s, VGX_LEVEL_HIGH, true)) {
            return VGX_TRAFFIC_RADII['eleve'];
        }
        if (in_array($s, VGX_LEVEL_MEDIUM, true)) {
            return VGX_TRAFFIC_RADII['modere'];
        }
        return VGX_TRAFFIC_RADII['normal'];
    }
}

if (!function_exists('vg_map_slug')) {
    function vg_map_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['’', '\'', '_'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-');
    }
}

if (!function_exists('vg_map_point_in_polygon')) {
    function vg_map_point_in_polygon(float $lat, float $lng, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $count = count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i, $i++) {
            $xi = (float) ($polygon[$i]['lat'] ?? 0.0);
            $yi = (float) ($polygon[$i]['lng'] ?? 0.0);
            $xj = (float) ($polygon[$j]['lat'] ?? 0.0);
            $yj = (float) ($polygon[$j]['lng'] ?? 0.0);
            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < (($xj - $xi) * ($lng - $yi) / (($yj - $yi) ?: 1.0E-9)) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

if (!function_exists('vg_map_normalize_commune_name')) {
    function vg_map_normalize_commune_name(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slug = vg_map_slug($value);
        $aliases = [
            'bandalungwa' => 'Bandalungwa',
            'barumbu' => 'Barumbu',
            'bumbu' => 'Bumbu',
            'gombe' => 'Gombe',
            'kalamu' => 'Kalamu',
            'kasa-vubu' => 'Kasa-Vubu',
            'kasavubu' => 'Kasa-Vubu',
            'kimbanseke' => 'Kimbanseke',
            'kinshasa' => 'Kinshasa',
            'kintambo' => 'Kintambo',
            'kisenso' => 'Kisenso',
            'lemba' => 'Lemba',
            'limete' => 'Limete',
            'lingwala' => 'Lingwala',
            'makala' => 'Makala',
            'maluku' => 'Maluku',
            'masina' => 'Masina',
            'matete' => 'Matete',
            'mont-ngafula' => 'Mont Ngafula',
            'mont-ngafula-mbudi' => 'Mont Ngafula',
            'mt-ngafula' => 'Mont Ngafula',
            'ndjili' => 'Ndjili',
            'n-djili' => 'Ndjili',
            'nsele' => 'Nsele',
            'n-sele' => 'Nsele',
            'ngaba' => 'Ngaba',
            'ngaliema' => 'Ngaliema',
            'ngiri-ngiri' => 'Ngiri-Ngiri',
            'ngiringiri' => 'Ngiri-Ngiri',
            'selembao' => 'Selembao',
        ];

        return $aliases[$slug] ?? $value;
    }
}

if (!function_exists('vgx_camera_smart_status')) {
    function vgx_camera_smart_status(array $camera, ?array $lastAnalysis): string
    {
        if ($lastAnalysis === null) {
            return VGX_CAM_STATUS_IDLE;
        }
        $analyzedAt   = strtotime((string) ($lastAnalysis['analyzed_at'] ?? '')) ?: 0;
        $ageMinutes   = $analyzedAt > 0 ? (time() - $analyzedAt) / 60 : PHP_INT_MAX;
        $unauthorized = (int) ($lastAnalysis['unauthorized_count'] ?? 0);
        $vehicles     = (int) ($lastAnalysis['vehicle_count'] ?? 0);

        if ($ageMinutes > VGX_CAM_RECENT_MINUTES) {
            return VGX_CAM_STATUS_IDLE;
        }
        if ($unauthorized > 0) {
            return VGX_CAM_STATUS_ALERT;
        }
        if ($vehicles >= VGX_CAM_CONGESTION_THRESHOLD) {
            return VGX_CAM_STATUS_CONGESTED;
        }
        return VGX_CAM_STATUS_ACTIVE;
    }
}

$clients       = function_exists('vgx_clients') ? array_values(vgx_clients()) : [];
$alerts = function_exists('vgx_alerts') ? array_values(vgx_alerts()) : [];
$agents = function_exists('vgx_agents') ? array_values(vgx_agents()) : [];
$cameras = function_exists('vgx_cameras') ? array_values(vgx_cameras()) : [];
$interventions = function_exists('vgx_interventions') ? array_values(vgx_interventions()) : [];
$store = function_exists('vgx_store') ? vgx_store() : [];
$trafficReports = isset($store['traffic_reports']) && is_array($store['traffic_reports']) ? array_values($store['traffic_reports']) : [];
$trafficIncidents = function_exists('vgx_traffic_incidents_active') ? vgx_traffic_incidents_active() : (isset($store['traffic_incidents']) && is_array($store['traffic_incidents'])
    ? array_values(array_filter($store['traffic_incidents'], static fn(array $i): bool => !in_array((string)($i['status'] ?? ''), ['resolu', 'resolved'], true)))
    : []);

$controlHq = function_exists('vg_app')
    ? (array) vg_app('control_hq', ['latitude' => -4.3250, 'longitude' => 15.3222])
    : ['latitude' => -4.3250, 'longitude' => 15.3222];

$mapTimestampValue = static function (array $row, array $fields = ['updated_at', 'last_event_at', 'acked_at', 'created_at']): int {
    foreach ($fields as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    return 0;
};

$mapChannelLabel = static function (string $channel): string {
    $normalized = strtolower(trim($channel));

    return match ($normalized) {
        'alpha' => 'Canal Alpha',
        'bravo' => 'Canal Bravo',
        'charlie' => 'Canal Charlie',
        'delta' => 'Canal Delta',
        'ops' => 'Canal OPS',
        default => $normalized !== '' ? 'Canal ' . strtoupper($normalized) : 'Canal OPS',
    };
};

$mapScopeLabel = static function (string $scope): string {
    $normalized = strtolower(trim($scope));

    return match ($normalized) {
        'private' => 'Prive',
        'team' => 'Equipe',
        'group' => 'Groupe',
        'broadcast' => 'Broadcast',
        'public' => 'General',
        default => $normalized !== '' ? ucfirst($normalized) : 'Equipe',
    };
};

$mapAckLabel = static function (bool $requiresAck, string $ackStatus): string {
    if (!$requiresAck) {
        return 'Sans ACK';
    }

    return strtolower(trim($ackStatus)) === 'acknowledged' ? 'ACK confirme' : 'ACK attendu';
};

$mapPriorityWeight = static function (string $priority): int {
    return match (strtolower(trim($priority))) {
        'critique', 'critical' => 5,
        'urgent', 'haute', 'high' => 4,
        'surveillance', 'moyenne', 'moderee', 'modere', 'medium' => 3,
        'faible', 'low' => 2,
        default => 1,
    };
};

$mapExcerpt = static function (string $value, int $limit = 170): string {
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $limit
            ? rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…'
            : $value;
    }

    return strlen($value) > $limit ? rtrim(substr($value, 0, $limit - 1)) . '…' : $value;
};

$mapCasefileIsActive = static function (array $casefile): bool {
    if (function_exists('vg_casefile_is_active_status')) {
        return vg_casefile_is_active_status((string) ($casefile['status'] ?? ''));
    }

    return !in_array(strtolower(trim((string) ($casefile['status'] ?? ''))), [
        'closed',
        'resolved',
        'done',
        'archive',
        'archived',
    ], true);
};

$clientsDirectoryById = [];
foreach ($clients as $clientRow) {
    if (!is_array($clientRow)) {
        continue;
    }

    $clientId = trim((string) ($clientRow['id'] ?? ''));
    if ($clientId !== '') {
        $clientsDirectoryById[$clientId] = $clientRow;
    }
}

$agentsDirectoryById = [];
foreach ($agents as $agentRow) {
    if (!is_array($agentRow)) {
        continue;
    }

    $agentId = trim((string) ($agentRow['id'] ?? ''));
    if ($agentId !== '') {
        $agentsDirectoryById[$agentId] = $agentRow;
    }
}

$activeInterventions = [];
$interventionAgentIds = [];
$interventionByClientId = [];
$interventionByAgentId = [];
foreach ($interventions as $intervention) {
    if (!is_array($intervention)) {
        continue;
    }

    if (function_exists('vgx_is_terminal_intervention_status')
        && vgx_is_terminal_intervention_status((string) ($intervention['status'] ?? ''))
    ) {
        continue;
    }

    $activeInterventions[] = $intervention;
    $interventionId = trim((string) ($intervention['id'] ?? ''));
    $clientId = trim((string) ($intervention['client_id'] ?? ''));
    $agentIds = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        array_merge(
            (array) ($intervention['agent_ids'] ?? []),
            [($intervention['agent_id'] ?? '')]
        )
    ), static fn(string $value): bool => $value !== '')));

    if ($interventionId !== '') {
        $interventionAgentIds[$interventionId] = $agentIds;
    }

    if ($clientId !== '') {
        $existing = $interventionByClientId[$clientId] ?? null;
        if (!is_array($existing) || $mapTimestampValue($intervention) >= $mapTimestampValue($existing)) {
            $interventionByClientId[$clientId] = $intervention;
        }
    }

    foreach ($agentIds as $agentId) {
        $existing = $interventionByAgentId[$agentId] ?? null;
        if (!is_array($existing) || $mapTimestampValue($intervention) >= $mapTimestampValue($existing)) {
            $interventionByAgentId[$agentId] = $intervention;
        }
    }
}

$casefiles = function_exists('vg_casefiles') ? array_values(vg_casefiles()) : [];
$casefilesById = [];
$incidentCasefiles = [];
$casefilePrimaryClientIds = [];
$casefileByClientId = [];
foreach ($casefiles as $casefile) {
    if (!is_array($casefile)) {
        continue;
    }

    $casefileId = trim((string) ($casefile['id'] ?? ''));
    if ($casefileId !== '') {
        $casefilesById[$casefileId] = $casefile;
    }

    if ((string) ($casefile['category'] ?? '') !== 'operations_incident') {
        continue;
    }

    $incidentCasefiles[] = $casefile;
    foreach ((array) ($casefile['related_entities'] ?? []) as $entity) {
        if (!is_array($entity) || (string) ($entity['type'] ?? '') !== 'client') {
            continue;
        }

        $clientId = trim((string) ($entity['id'] ?? ''));
        if ($clientId === '') {
            continue;
        }

        if ($casefileId !== '' && !isset($casefilePrimaryClientIds[$casefileId])) {
            $casefilePrimaryClientIds[$casefileId] = $clientId;
        }

        $existing = $casefileByClientId[$clientId] ?? null;
        $existingActive = is_array($existing) ? $mapCasefileIsActive($existing) : false;
        $candidateActive = $mapCasefileIsActive($casefile);
        if (
            !is_array($existing)
            || ($candidateActive && !$existingActive)
            || ($candidateActive === $existingActive && $mapTimestampValue($casefile) >= $mapTimestampValue($existing))
        ) {
            $casefileByClientId[$clientId] = $casefile;
        }
    }
}

$allCommunications = function_exists('vgx_list_communications') ? array_values(vgx_list_communications()) : [];
$radioCommunications = [];
$latestCommunicationByClientId = [];
$latestCommunicationByAgentId = [];
$pendingAckCountByClientId = [];
$pendingAckCountByAgentId = [];
$dispatchCommunicationByInterventionId = [];
foreach ($allCommunications as $message) {
    if (!is_array($message)) {
        continue;
    }

    $communicationType = strtolower(trim((string) ($message['communication_type'] ?? $message['message_type'] ?? '')));
    $channel = trim((string) ($message['channel'] ?? $message['radio_channel'] ?? ''));
    $requiresAck = !empty($message['requires_ack']);
    $isTacticalMessage = in_array($communicationType, ['dispatch', 'radio', 'tactical'], true)
        || $channel !== ''
        || $requiresAck;
    if (!$isTacticalMessage) {
        continue;
    }

    $casefileId = trim((string) ($message['casefile_id'] ?? ''));
    $casefile = $casefileId !== '' ? ($casefilesById[$casefileId] ?? null) : null;
    $clientId = trim((string) ($message['client_id'] ?? ''));
    if ($clientId === '' && is_array($casefile)) {
        $clientId = (string) ($casefilePrimaryClientIds[(string) ($casefile['id'] ?? '')] ?? '');
    }
    $agentId = trim((string) ($message['agent_id'] ?? ''));
    $clientRow = $clientId !== '' ? ($clientsDirectoryById[$clientId] ?? null) : null;
    $agentRow = $agentId !== '' ? ($agentsDirectoryById[$agentId] ?? null) : null;
    $clientName = trim((string) ($message['client_name'] ?? (is_array($clientRow) ? ($clientRow['name'] ?? $clientRow['full_name'] ?? '') : '')));
    $agentName = trim((string) ($message['agent_name'] ?? (is_array($agentRow) ? ($agentRow['name'] ?? '') : '')));
    $channelLabel = $mapChannelLabel($channel);
    $scope = trim((string) ($message['radio_scope'] ?? ''));
    $scopeLabel = $mapScopeLabel($scope);
    $ackStatus = strtolower(trim((string) ($message['ack_status'] ?? ($requiresAck ? 'pending' : 'not_required'))));
    $ackLabel = $mapAckLabel($requiresAck, $ackStatus);
    $caseCode = is_array($casefile) ? trim((string) ($casefile['case_code'] ?? '')) : '';
    $clientCommune = is_array($clientRow) ? vg_map_normalize_commune_name((string) ($clientRow['commune'] ?? '')) : '';
    $subject = trim((string) ($message['subject'] ?? $message['dispatch_title'] ?? 'Ordre tactique'));
    $detailParts = array_values(array_filter([
        $channelLabel,
        $scopeLabel,
        $ackLabel,
        trim((string) ($message['dispatch_lane'] ?? '')),
        $caseCode,
    ], static fn(string $value): bool => $value !== ''));

    $radioPayload = [
        'id' => (string) ($message['id'] ?? ''),
        'title' => $subject !== '' ? $subject : 'Ordre tactique',
        'subject' => $subject !== '' ? $subject : 'Ordre tactique',
        'message' => $mapExcerpt((string) ($message['message'] ?? $message['content'] ?? ''), 220),
        'detail' => implode(' • ', $detailParts),
        'created_at' => (string) ($message['created_at'] ?? ''),
        'updated_at' => (string) ($message['updated_at'] ?? $message['created_at'] ?? ''),
        'client_id' => $clientId,
        'client_name' => $clientName,
        'client_commune' => $clientCommune,
        'agent_id' => $agentId,
        'agent_name' => $agentName,
        'sender_role' => (string) ($message['from_role'] ?? ''),
        'recipient_role' => (string) ($message['to_role'] ?? ''),
        'communication_type' => $communicationType !== '' ? $communicationType : 'radio',
        'message_type' => (string) ($message['message_type'] ?? $communicationType),
        'channel' => $channel,
        'channel_label' => $channelLabel,
        'radio_scope' => $scope,
        'scope_label' => $scopeLabel,
        'requires_ack' => $requiresAck,
        'ack_status' => $ackStatus,
        'ack_label' => $ackLabel,
        'dispatch_id' => (string) ($message['dispatch_id'] ?? $message['batch_id'] ?? ''),
        'dispatch_lane' => (string) ($message['dispatch_lane'] ?? ''),
        'dispatch_title' => (string) ($message['dispatch_title'] ?? ''),
        'casefile_id' => $casefileId,
        'case_code' => $caseCode,
        'case_priority' => is_array($casefile) ? (string) ($casefile['priority'] ?? '') : '',
        'intelligence_key' => (string) ($message['intelligence_key'] ?? (is_array($casefile) ? ($casefile['intelligence_key'] ?? '') : '')),
        'urgency' => (string) ($message['urgency'] ?? 'normal'),
        'context_type' => (string) ($message['context_type'] ?? ''),
        'context_id' => (string) ($message['context_id'] ?? ''),
    ];

    $radioCommunications[] = $radioPayload;

    if ($clientId !== '') {
        $existing = $latestCommunicationByClientId[$clientId] ?? null;
        if (!is_array($existing) || $mapTimestampValue($radioPayload) >= $mapTimestampValue($existing)) {
            $latestCommunicationByClientId[$clientId] = $radioPayload;
        }
    }

    if ($agentId !== '') {
        $existing = $latestCommunicationByAgentId[$agentId] ?? null;
        if (!is_array($existing) || $mapTimestampValue($radioPayload) >= $mapTimestampValue($existing)) {
            $latestCommunicationByAgentId[$agentId] = $radioPayload;
        }
    }

    if ($requiresAck && $ackStatus === 'pending') {
        if ($clientId !== '') {
            $pendingAckCountByClientId[$clientId] = (int) ($pendingAckCountByClientId[$clientId] ?? 0) + 1;
        }
        if ($agentId !== '') {
            $pendingAckCountByAgentId[$agentId] = (int) ($pendingAckCountByAgentId[$agentId] ?? 0) + 1;
        }
    }

    if (
        strtolower(trim((string) ($message['context_type'] ?? ''))) === 'intervention'
        && trim((string) ($message['context_id'] ?? '')) !== ''
    ) {
        $interventionId = trim((string) ($message['context_id'] ?? ''));
        $existing = $dispatchCommunicationByInterventionId[$interventionId] ?? null;
        if (!is_array($existing) || $mapTimestampValue($radioPayload) >= $mapTimestampValue($existing)) {
            $dispatchCommunicationByInterventionId[$interventionId] = $radioPayload;
        }
    }
}

usort($radioCommunications, static function (array $left, array $right) use ($mapTimestampValue): int {
    $leftPending = !empty($left['requires_ack']) && (string) ($left['ack_status'] ?? '') === 'pending';
    $rightPending = !empty($right['requires_ack']) && (string) ($right['ack_status'] ?? '') === 'pending';
    if ($leftPending !== $rightPending) {
        return $leftPending ? -1 : 1;
    }

    return $mapTimestampValue($right) <=> $mapTimestampValue($left);
});

$tacticalOrdersCount = count($radioCommunications);
$pendingAckCommunications = count(array_filter($radioCommunications, static function (array $message): bool {
    return !empty($message['requires_ack']) && (string) ($message['ack_status'] ?? '') === 'pending';
}));

$intelligenceSource = array_values(array_filter($incidentCasefiles, $mapCasefileIsActive));
if ($intelligenceSource === []) {
    $intelligenceSource = $incidentCasefiles;
}

usort($intelligenceSource, static function (array $left, array $right) use ($mapPriorityWeight, $mapTimestampValue): int {
    $priorityOrder = $mapPriorityWeight((string) ($right['priority'] ?? ''))
        <=> $mapPriorityWeight((string) ($left['priority'] ?? ''));
    if ($priorityOrder !== 0) {
        return $priorityOrder;
    }

    return $mapTimestampValue($right) <=> $mapTimestampValue($left);
});

$intelligenceCasefiles = [];
foreach (array_slice($intelligenceSource, 0, 16) as $casefile) {
    $casefileId = trim((string) ($casefile['id'] ?? ''));
    $primaryClientId = $casefileId !== '' ? (string) ($casefilePrimaryClientIds[$casefileId] ?? '') : '';
    $clientRow = $primaryClientId !== '' ? ($clientsDirectoryById[$primaryClientId] ?? null) : null;
    $ownerAgentId = trim((string) ($casefile['owner_agent_id'] ?? ''));
    $ownerAgent = $ownerAgentId !== '' ? ($agentsDirectoryById[$ownerAgentId] ?? null) : null;
    $linkedIntervention = $primaryClientId !== '' ? ($interventionByClientId[$primaryClientId] ?? null) : null;
    $latestClientCommunication = $primaryClientId !== '' ? ($latestCommunicationByClientId[$primaryClientId] ?? null) : null;
    $missionAgentNames = [];
    if (is_array($linkedIntervention)) {
        $missionAgentIds = $interventionAgentIds[(string) ($linkedIntervention['id'] ?? '')] ?? [];
        foreach ($missionAgentIds as $missionAgentId) {
            $missionAgent = $agentsDirectoryById[(string) $missionAgentId] ?? null;
            if (!is_array($missionAgent)) {
                continue;
            }

            $missionAgentName = trim((string) ($missionAgent['name'] ?? ''));
            if ($missionAgentName !== '') {
                $missionAgentNames[] = $missionAgentName;
            }
        }
    }

    $intelligenceCasefiles[] = [
        'id' => $casefileId,
        'case_code' => (string) ($casefile['case_code'] ?? ''),
        'title' => (string) ($casefile['title'] ?? 'Dossier OPS'),
        'summary' => $mapExcerpt((string) ($casefile['summary'] ?? ''), 180),
        'status' => (string) ($casefile['status'] ?? 'open'),
        'priority' => (string) ($casefile['priority'] ?? 'normale'),
        'client_id' => $primaryClientId,
        'client_name' => is_array($clientRow) ? (string) ($clientRow['name'] ?? $clientRow['full_name'] ?? 'Abonne VIGILANCE') : '',
        'client_commune' => is_array($clientRow) ? vg_map_normalize_commune_name((string) ($clientRow['commune'] ?? '')) : '',
        'owner_agent_id' => $ownerAgentId,
        'owner_agent_name' => is_array($ownerAgent) ? (string) ($ownerAgent['name'] ?? 'Agent terrain') : '',
        'intelligence_key' => (string) ($casefile['intelligence_key'] ?? ''),
        'intelligence_lane' => (string) ($casefile['intelligence_lane'] ?? ''),
        'signal_count' => (int) ($casefile['signal_count'] ?? 0),
        'event_count' => (int) ($casefile['event_count'] ?? 0),
        'post_dossier_quality_score' => (float) ($casefile['post_dossier_quality_score'] ?? 0),
        'post_dossier_quality_label' => (string) ($casefile['post_dossier_quality_label'] ?? ''),
        'post_dossier_orientation' => (string) ($casefile['post_dossier_orientation'] ?? ''),
        'post_dossier_fingerprint' => (string) ($casefile['post_dossier_fingerprint'] ?? ''),
        'last_event_at' => (string) ($casefile['last_event_at'] ?? $casefile['updated_at'] ?? ''),
        'updated_at' => (string) ($casefile['updated_at'] ?? ''),
        'location_label' => (string) ($casefile['location_label'] ?? ''),
        'mission_id' => is_array($linkedIntervention) ? (string) ($linkedIntervention['id'] ?? '') : '',
        'mission_status' => is_array($linkedIntervention) ? (string) ($linkedIntervention['status'] ?? '') : '',
        'mission_route' => is_array($linkedIntervention) ? (string) ($linkedIntervention['route'] ?? '') : '',
        'mission_agent_names' => $missionAgentNames,
        'dispatch_subject' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['subject'] ?? '') : '',
        'dispatch_channel_label' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['channel_label'] ?? '') : '',
        'dispatch_ack_label' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['ack_label'] ?? '') : '',
        'dispatch_lane' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['dispatch_lane'] ?? '') : '',
        'dispatch_message' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['message'] ?? '') : '',
    ];
}

$intelligenceLiveCount = count(array_filter($incidentCasefiles, $mapCasefileIsActive));

$alertByClient = [];
foreach ($alerts as $alert) {
    $clientId = (string) ($alert['client_id'] ?? '');
    if ($clientId === '') {
        continue;
    }

    if (!isset($alertByClient[$clientId])) {
        $alertByClient[$clientId] = $alert;
        continue;
    }

    $currentLevel = strtolower(vg_map_alert_level($alertByClient[$clientId]));
    $nextLevel = strtolower(vg_map_alert_level($alert));
    $currentWeight = VGX_ALERT_PRIORITY_WEIGHTS[$currentLevel] ?? 0;
    $nextWeight    = VGX_ALERT_PRIORITY_WEIGHTS[$nextLevel] ?? 0;
    if ($nextWeight > $currentWeight || strcmp((string) ($alert['created_at'] ?? ''), (string) ($alertByClient[$clientId]['created_at'] ?? '')) > 0) {
        $alertByClient[$clientId] = $alert;
    }
}

$clientsPayload = [];
foreach ($clients as $client) {
    $clientId = (string) ($client['id'] ?? '');
    $latitude = (string) ($client['latitude'] ?? '');
    $longitude = (string) ($client['longitude'] ?? '');
    if ($latitude === '' || $longitude === '') {
        continue;
    }

    $linkedAlert = $alertByClient[$clientId] ?? null;
    $clientCommune = vg_map_normalize_commune_name((string) ($client['commune'] ?? ''));
    $clientCasefile = $casefileByClientId[$clientId] ?? null;
    $latestClientCommunication = $latestCommunicationByClientId[$clientId] ?? null;
    $activeClientIntervention = $interventionByClientId[$clientId] ?? null;
    $clientsPayload[] = [
        'id' => $clientId,
        'name' => (string) ($client['name'] ?? $client['company_name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE'),
        'lat' => (float) $latitude,
        'lng' => (float) $longitude,
        'subscription' => (string) ($client['subscription'] ?? 'Essentiel'),
        'commune' => $clientCommune,
        'quartier' => (string) ($client['quartier'] ?? ''),
        'address' => trim(implode(' • ', array_filter([
            $clientCommune,
            (string) ($client['quartier'] ?? ''),
            (string) ($client['avenue'] ?? ''),
            (string) ($client['address'] ?? ''),
        ]))),
        'phone' => (string) ($client['phone'] ?? ''),
        'hasAlert' => $linkedAlert !== null,
        'alertId' => $linkedAlert ? (string) ($linkedAlert['id'] ?? '') : '',
        'alertLevel' => $linkedAlert ? vg_map_alert_level($linkedAlert) : '',
        'alertType' => $linkedAlert ? vg_map_alert_type($linkedAlert) : '',
        'alertMessage' => $linkedAlert ? (string) ($linkedAlert['message'] ?? '') : '',
        'alertStatus' => $linkedAlert ? (string) ($linkedAlert['status'] ?? '') : '',
        'alertCreatedAt' => $linkedAlert ? (string) ($linkedAlert['created_at'] ?? '') : '',
        'casefile_id' => is_array($clientCasefile) ? (string) ($clientCasefile['id'] ?? '') : '',
        'case_code' => is_array($clientCasefile) ? (string) ($clientCasefile['case_code'] ?? '') : '',
        'case_status' => is_array($clientCasefile) ? (string) ($clientCasefile['status'] ?? '') : '',
        'case_priority' => is_array($clientCasefile) ? (string) ($clientCasefile['priority'] ?? '') : '',
        'case_summary' => is_array($clientCasefile) ? (string) ($clientCasefile['summary'] ?? '') : '',
        'intelligence_lane' => is_array($clientCasefile) ? (string) ($clientCasefile['intelligence_lane'] ?? '') : '',
        'signal_count' => is_array($clientCasefile) ? (int) ($clientCasefile['signal_count'] ?? 0) : 0,
        'pending_ack_count' => (int) ($pendingAckCountByClientId[$clientId] ?? 0),
        'last_radio_subject' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['subject'] ?? '') : '',
        'last_radio_channel' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['channel'] ?? '') : '',
        'last_radio_channel_label' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['channel_label'] ?? '') : '',
        'last_radio_scope' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['radio_scope'] ?? '') : '',
        'last_radio_scope_label' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['scope_label'] ?? '') : '',
        'last_radio_ack_status' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['ack_status'] ?? '') : '',
        'last_radio_ack_label' => is_array($latestClientCommunication) ? (string) ($latestClientCommunication['ack_label'] ?? '') : '',
        'active_intervention_id' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['id'] ?? '') : '',
        'active_intervention_status' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['status'] ?? '') : '',
        'active_intervention_stage' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['mission_stage'] ?? (function_exists('vgx_mission_stage') ? vgx_mission_stage((string) ($activeClientIntervention['status'] ?? '')) : '')) : '',
        'active_dispatch_status' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['dispatch_status'] ?? '') : '',
        'active_ack_status' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['ack_status'] ?? '') : '',
        'active_last_status_at' => is_array($activeClientIntervention) ? (string) ($activeClientIntervention['last_status_at'] ?? $activeClientIntervention['updated_at'] ?? '') : '',
    ];
}

$availableAgents = [];
foreach ($agents as $agent) {
    $status = strtolower(trim((string) ($agent['status'] ?? '')));
    $visibleAgentStatuses = array_merge(VGX_AGENT_AVAILABLE_STATUSES, [
        'dispatch',
        'mission envoyee',
        'en mission',
        'en route',
        'sur place',
        'intervention en cours',
        'besoin de renfort',
    ]);
    if (!in_array($status, $visibleAgentStatuses, true)) {
        continue;
    }

    $agentLat = (float) ($agent['latitude'] ?? 0);
    $agentLng = (float) ($agent['longitude'] ?? 0);
    if ($agentLat === 0.0 && $agentLng === 0.0) {
        $hqLat    = (float) ($controlHq['latitude'] ?? -4.3250);
        $hqLng    = (float) ($controlHq['longitude'] ?? 15.3222);
        $seed     = abs(crc32((string) (($agent['zone'] ?? '') . ($agent['id'] ?? ''))));
        $agentLat = $hqLat + (($seed % 180) - 90) * 0.001;
        $agentLng = $hqLng + (((int) floor($seed / 180) % 180) - 90) * 0.001;
    }

    $agentId = (string) ($agent['id'] ?? '');
    $activeAgentIntervention = $interventionByAgentId[$agentId] ?? null;
    $latestAgentCommunication = $latestCommunicationByAgentId[$agentId] ?? null;
    $missionClientId = is_array($activeAgentIntervention) ? trim((string) ($activeAgentIntervention['client_id'] ?? '')) : '';
    $missionClient = $missionClientId !== '' ? ($clientsDirectoryById[$missionClientId] ?? null) : null;
    if ($missionClientId === '' && is_array($latestAgentCommunication)) {
        $missionClientId = trim((string) ($latestAgentCommunication['client_id'] ?? ''));
        $missionClient = $missionClientId !== '' ? ($clientsDirectoryById[$missionClientId] ?? null) : null;
    }
    $dispatchCasefile = is_array($latestAgentCommunication) && trim((string) ($latestAgentCommunication['casefile_id'] ?? '')) !== ''
        ? ($casefilesById[(string) ($latestAgentCommunication['casefile_id'] ?? '')] ?? null)
        : null;

    $availableAgents[] = [
        'id' => $agentId,
        'name' => (string) ($agent['name'] ?? 'Agent VIGILANCE'),
        'zone' => (string) ($agent['zone'] ?? 'Kinshasa'),
        'status' => (string) ($agent['status'] ?? 'Disponible'),
        'lat' => $agentLat,
        'lng' => $agentLng,
        'phone' => (string) ($agent['phone'] ?? ''),
        'role' => (string) ($agent['role_label'] ?? 'Agent terrain'),
        'pending_ack_count' => (int) ($pendingAckCountByAgentId[$agentId] ?? 0),
        'active_intervention_id' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['id'] ?? '') : '',
        'active_client_id' => $missionClientId,
        'active_client_name' => is_array($missionClient) ? (string) ($missionClient['name'] ?? $missionClient['full_name'] ?? 'Abonne VIGILANCE') : '',
        'mission_status' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['status'] ?? '') : '',
        'mission_stage' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['mission_stage'] ?? (function_exists('vgx_mission_stage') ? vgx_mission_stage((string) ($activeAgentIntervention['status'] ?? '')) : '')) : '',
        'mission_dispatch_status' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['dispatch_status'] ?? '') : '',
        'mission_ack_status' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['ack_status'] ?? '') : '',
        'mission_last_status_at' => is_array($activeAgentIntervention) ? (string) ($activeAgentIntervention['last_status_at'] ?? $activeAgentIntervention['updated_at'] ?? '') : '',
        'dispatch_subject' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['subject'] ?? '') : '',
        'dispatch_channel' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['channel'] ?? '') : '',
        'dispatch_channel_label' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['channel_label'] ?? '') : '',
        'dispatch_scope' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['radio_scope'] ?? '') : '',
        'dispatch_scope_label' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['scope_label'] ?? '') : '',
        'dispatch_ack_status' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['ack_status'] ?? '') : '',
        'dispatch_ack_label' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['ack_label'] ?? '') : '',
        'dispatch_requires_ack' => is_array($latestAgentCommunication) ? !empty($latestAgentCommunication['requires_ack']) : false,
        'dispatch_casefile_id' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['casefile_id'] ?? '') : '',
        'dispatch_case_code' => is_array($dispatchCasefile) ? (string) ($dispatchCasefile['case_code'] ?? '') : '',
        'dispatch_lane' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['dispatch_lane'] ?? '') : '',
        'dispatch_created_at' => is_array($latestAgentCommunication) ? (string) ($latestAgentCommunication['created_at'] ?? '') : '',
    ];
}

$clientsPayloadById = [];
foreach ($clientsPayload as $client) {
    $clientId = trim((string) ($client['id'] ?? ''));
    if ($clientId !== '') {
        $clientsPayloadById[$clientId] = $client;
    }
}

$availableAgentsById = [];
foreach ($availableAgents as $agent) {
    $agentId = trim((string) ($agent['id'] ?? ''));
    if ($agentId !== '') {
        $availableAgentsById[$agentId] = $agent;
    }
}

$droneFleet = [];
$droneResponse = function_exists('vgx_drone_api') ? vgx_drone_api('GET', '/drones') : ['ok' => false];
if (!empty($droneResponse['ok'])) {
    foreach ((array) ($droneResponse['data']['drones'] ?? []) as $drone) {
        if (!is_array($drone)) {
            continue;
        }

        $droneFleet[] = [
            'id' => (string) ($drone['id'] ?? $drone['drone_id'] ?? ''),
            'name' => (string) ($drone['name'] ?? 'Drone VIGILANCE'),
            'lat' => (float) ($drone['latitude'] ?? $controlHq['latitude']),
            'lng' => (float) ($drone['longitude'] ?? $controlHq['longitude']),
            'status' => (string) ($drone['status'] ?? 'idle'),
            'battery' => (int) ($drone['battery_pct'] ?? 0),
            'signal' => (int) ($drone['signal_pct'] ?? 0),
            'altitude' => (float) ($drone['altitude_m'] ?? 0),
            'speed' => (float) ($drone['speed_kmh'] ?? 0),
            'video_url' => (string) ($drone['video_url'] ?? ''),
        ];
    }
}

$cameraPayload = [];
$cameraSourceClientMap = [];
$cameraPlan = function_exists('vgx_camera_analysis_plan')
    ? vgx_camera_analysis_plan(max(1, count($cameras)), 1, 15, false)
    : ['selected' => [], 'primary_camera_id' => '', 'backup_camera_ids' => []];
$cameraPlanById = [];
foreach ((array) ($cameraPlan['selected'] ?? []) as $plannedCamera) {
    $plannedCameraId = trim((string) ($plannedCamera['camera_id'] ?? ''));
    if ($plannedCameraId === '') {
        continue;
    }

    $cameraPlanById[$plannedCameraId] = [
        'analysis_lane' => (string) ($plannedCamera['analysis_lane'] ?? 'standby'),
        'analysis_lane_label' => (string) ($plannedCamera['analysis_lane_label'] ?? 'Veille IA'),
        'backup_rank' => $plannedCamera['backup_rank'] ?? null,
        'priority_score' => (int) ($plannedCamera['priority_score'] ?? 0),
        'queue_index' => (int) ($plannedCamera['queue_index'] ?? 0),
    ];
}
foreach ($cameras as $camera) {
    if (!is_array($camera)) {
        continue;
    }

    $cameraId       = (string) ($camera['id'] ?? '');
    $cameraClientId = (string) ($camera['client_id'] ?? '');
    foreach (array_filter([
        $cameraId,
        (string) ($camera['name'] ?? $camera['label'] ?? $camera['camera_name'] ?? ''),
        (string) ($camera['stream_url'] ?? $camera['video_url'] ?? ''),
    ], static function (string $value): bool {
        return trim($value) !== '';
    }) as $cameraKey) {
        $cameraSourceClientMap[$cameraKey] = $cameraClientId;
    }

    $lastAnalysis   = function_exists('vgx_camera_last_analysis') ? vgx_camera_last_analysis($cameraId) : null;
    $smartStatus    = vgx_camera_smart_status($camera, $lastAnalysis);
    $summary        = function_exists('vgx_camera_intelligence_summary')
        ? vgx_camera_intelligence_summary($camera)
        : [];
    $planMeta       = $cameraPlanById[$cameraId] ?? [
        'analysis_lane' => 'standby',
        'analysis_lane_label' => 'Veille IA',
        'backup_rank' => null,
        'priority_score' => 0,
        'queue_index' => 0,
    ];

    $cameraPayload[] = [
        'id'                      => $cameraId,
        'client_id'               => $cameraClientId,
        'name'                    => (string) ($camera['name'] ?? $camera['label'] ?? $camera['camera_name'] ?? 'Camera VIGILANCE'),
        'status'                  => (string) ($camera['status'] ?? 'Active'),
        'stream_url'              => (string) ($camera['stream_url'] ?? $camera['video_url'] ?? ''),
        'zone'                    => (string) ($camera['zone'] ?? $camera['location'] ?? ''),
        'lat'                     => isset($camera['latitude'])  ? (float) $camera['latitude']  : null,
        'lng'                     => isset($camera['longitude']) ? (float) $camera['longitude'] : null,
        'smart_status'            => $smartStatus,
        'smart_label'             => (string) ($summary['smart_label'] ?? strtoupper($smartStatus)),
        'stream_online'           => !empty($summary['stream_online']),
        'intelligence_ready'      => !empty($summary['intelligence_ready']),
        'analysis_lane'           => (string) ($planMeta['analysis_lane'] ?? 'standby'),
        'analysis_lane_label'     => (string) ($planMeta['analysis_lane_label'] ?? 'Veille IA'),
        'backup_rank'             => $planMeta['backup_rank'],
        'priority_score'          => (int) ($planMeta['priority_score'] ?? 0),
        'queue_index'             => (int) ($planMeta['queue_index'] ?? 0),
        'profile_key'             => (string) ($summary['profile_key'] ?? ''),
        'profile_label'           => (string) ($summary['profile_label'] ?? ($summary['site_type_label'] ?? '')),
        'site_type_label'         => (string) ($summary['site_type_label'] ?? ''),
        'watch_zone_name'         => (string) ($summary['watch_zone_name'] ?? ''),
        'enabled_rule_summary'    => (string) ($summary['enabled_rule_summary'] ?? ''),
        'enabled_rule_details'    => array_values((array) ($summary['enabled_rule_details'] ?? [])),
        'profile_surveillance_summary' => (string) ($summary['profile_surveillance_summary'] ?? ''),
        'profile_alert_summary'   => (string) ($summary['profile_alert_summary'] ?? ''),
        'profile_ops_summary'     => (string) ($summary['profile_ops_summary'] ?? ''),
        'profile_expected_summary' => (string) ($summary['profile_expected_summary'] ?? ''),
        'last_analysis_at'        => $lastAnalysis['analyzed_at']        ?? null,
        'last_analysis_human'     => (string) ($summary['last_analysis_human'] ?? ''),
        'last_vehicle_count'      => (int) ($summary['vehicle_count'] ?? 0),
        'last_person_count'       => (int) ($summary['person_count'] ?? 0),
        'last_threat_count'       => (int) ($summary['threat_count'] ?? 0),
        'last_threat_detected'    => !empty($summary['threat_detected']),
        'last_threat_level'       => (string) ($summary['threat_level'] ?? 'quiet'),
        'last_faces_detected'     => (int) ($summary['faces_detected'] ?? 0),
        'last_face_match_count'   => (int) ($summary['face_match_count'] ?? 0),
        'last_face_match_detected' => !empty($summary['face_match_detected']),
        'last_face_top_risk_level' => (string) ($summary['face_top_risk_level'] ?? 'normal'),
        'last_face_match_labels'  => array_values((array) ($summary['face_match_labels'] ?? [])),
        'last_auto_alert_level'   => (string) ($summary['auto_alert_level'] ?? ''),
        'last_plate_count'        => (int) ($summary['plate_count'] ?? 0),
        'last_unauthorized_count' => (int) ($summary['unauthorized_count'] ?? 0),
        'last_congestion'         => (string) ($summary['congestion_level'] ?? ($lastAnalysis['congestion_level'] ?? 'unknown')),
        'recommendation'          => (string) ($summary['recommendation'] ?? ''),
        'last_motion_detected'    => !empty($summary['motion_detected']),
        'last_recommendation'     => (string) ($summary['recommendation'] ?? ($lastAnalysis['recommendation'] ?? '')),
    ];
}

$events = [];
$faceRecognitionEvents = function_exists('vgx_face_recognition_events')
    ? array_values(array_slice(vgx_face_recognition_events(), 0, 12))
    : [];
foreach ($alerts as $alert) {
    $clientId = (string) ($alert['client_id'] ?? '');
    $clientName = 'Abonne VIGILANCE';
    foreach ($clients as $client) {
        if ((string) ($client['id'] ?? '') === $clientId) {
            $clientName = (string) ($client['name'] ?? $client['company_name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE');
            break;
        }
    }

    $events[] = [
        'kind' => 'alert',
        'alert_id' => (string) ($alert['id'] ?? ''),
        'title' => vg_map_alert_type($alert) . ' • ' . vg_map_alert_level($alert),
        'detail' => $clientName . ' • ' . (string) ($alert['message'] ?? 'Aucune précision'),
        'created_at' => (string) ($alert['created_at'] ?? ''),
        'client_id' => $clientId,
    ];
}

foreach ($interventions as $intervention) {
    $agentName = 'Agent VIGILANCE';
    foreach ($agents as $agent) {
        if ((string) ($agent['id'] ?? '') === (string) ($intervention['agent_id'] ?? '')) {
            $agentName = (string) ($agent['name'] ?? 'Agent VIGILANCE');
            break;
        }
    }

    $events[] = [
        'kind' => 'mission',
        'title' => 'Mission • ' . (string) ($intervention['status'] ?? 'En suivi'),
        'detail' => $agentName . ' • ' . (string) ($intervention['comment'] ?? $intervention['report'] ?? 'Mission en suivi'),
        'created_at' => (string) ($intervention['updated_at'] ?? $intervention['created_at'] ?? ''),
        'client_id' => (string) ($intervention['client_id'] ?? ''),
    ];
}

foreach ($faceRecognitionEvents as $faceEvent) {
    if (!is_array($faceEvent) || (int) ($faceEvent['match_count'] ?? 0) <= 0) {
        continue;
    }

    $labels = array_values(array_filter(array_map(
        static fn($match): string => is_array($match) ? trim((string) ($match['label'] ?? '')) : '',
        (array) ($faceEvent['matches'] ?? [])
    )));
    $riskLevel = strtolower(trim((string) ($faceEvent['status'] ?? 'match')));
    $cameraName = (string) ($faceEvent['camera_name'] ?? 'Camera VIGILANCE');
    $clientName = trim((string) ($faceEvent['client_name'] ?? ''));
    $detailPrefix = $labels !== [] ? implode(', ', array_slice($labels, 0, 3)) : 'Correspondance faciale';

    $events[] = [
        'kind' => 'face',
        'title' => str_contains($riskLevel, 'critical') ? 'Personne recherchee • Critique' : 'Personne recherchee • Camera',
        'detail' => $detailPrefix . ' • ' . $cameraName . ($clientName !== '' ? ' • ' . $clientName : ''),
        'created_at' => (string) ($faceEvent['captured_at'] ?? ''),
        'client_id' => (string) ($faceEvent['client_id'] ?? ''),
    ];
}

foreach (array_slice($radioCommunications, 0, 14) as $communication) {
    $isAckPending = !empty($communication['requires_ack']) && (string) ($communication['ack_status'] ?? '') === 'pending';
    $isAcked = !empty($communication['requires_ack']) && (string) ($communication['ack_status'] ?? '') === 'acknowledged';
    $siteLabel = trim((string) ($communication['client_name'] ?? ''));
    $detailParts = array_values(array_filter([
        $siteLabel !== '' ? $siteLabel : trim((string) ($communication['agent_name'] ?? 'Terrain')),
        trim((string) ($communication['channel_label'] ?? '')),
        trim((string) ($communication['scope_label'] ?? '')),
        trim((string) ($communication['ack_label'] ?? '')),
    ], static fn(string $value): bool => $value !== ''));

    $events[] = [
        'kind' => $isAcked ? 'radio_ack' : 'radio',
        'title' => $isAcked
            ? 'ACK radio • ' . (string) ($communication['channel_label'] ?? 'Canal OPS')
            : (
                $isAckPending
                    ? 'Ordre radio • ACK attendu'
                    : 'Ordre radio • ' . (string) ($communication['channel_label'] ?? 'Canal OPS')
            ),
        'detail' => implode(' • ', array_filter([
            implode(' • ', $detailParts),
            trim((string) ($communication['subject'] ?? '')),
            trim((string) ($communication['message'] ?? '')),
        ])),
        'created_at' => (string) ($communication['created_at'] ?? ''),
        'client_id' => (string) ($communication['client_id'] ?? ''),
        'casefile_id' => (string) ($communication['casefile_id'] ?? ''),
        'dispatch_id' => (string) ($communication['dispatch_id'] ?? ''),
    ];
}

usort($events, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$hotAlerts = count(array_filter($alerts, static function (array $alert): bool {
    $level = strtolower(vg_map_alert_level($alert));
    return in_array($level, ['critique', 'critical', 'haute', 'high'], true);
}));

$missionsLive = count(array_filter($interventions, static function (array $mission): bool {
    $status = strtolower((string) ($mission['status'] ?? ''));
    return !in_array($status, ['mission terminee', 'mission terminée', 'mission cloturee', 'mission clôturée', 'situation maitrisee', 'situation maîtrisée', 'resolved', 'done'], true);
}));

$tacticalZones = [
    [
        'id' => 'hq-core',
        'kind' => 'hq',
        'label' => 'Rayon de commandement OPS',
        'center' => [
            'lat' => (float) ($controlHq['latitude'] ?? -4.3250),
            'lng' => (float) ($controlHq['longitude'] ?? 15.3222),
        ],
        'radius' => 1800,
        'color' => '#4eb4ff',
        'weight' => 2,
        'opacity' => 0.9,
        'fillOpacity' => 0.08,
        'detail' => 'Poste de commandement et rayon de coordination immédiate',
    ],
];

$tacticalCorridors = [];
foreach ($clientsPayload as $client) {
    if (empty($client['hasAlert'])) {
        continue;
    }

    $level = strtolower((string) ($client['alertLevel'] ?? ''));
    $isCritical = in_array($level, ['critique', 'critical', 'haute', 'high'], true);
    $tacticalZones[] = [
        'id' => 'client-zone-' . (string) ($client['id'] ?? ''),
        'client_id' => (string) ($client['id'] ?? ''),
        'kind' => $isCritical ? 'hotspot' : 'watch',
        'label' => ($isCritical ? 'Zone rouge' : 'Zone sous veille') . ' • ' . (string) ($client['name'] ?? 'Abonne VIGILANCE'),
        'center' => [
            'lat' => (float) ($client['lat'] ?? 0),
            'lng' => (float) ($client['lng'] ?? 0),
        ],
        'radius' => $isCritical ? 650 : 420,
        'color' => $isCritical ? '#ff6478' : '#ffd166',
        'weight' => $isCritical ? 3 : 2,
        'opacity' => 0.94,
        'fillOpacity' => $isCritical ? 0.16 : 0.09,
        'detail' => (string) ($client['alertType'] ?? 'Alerte') . ' • ' . (string) ($client['alertLevel'] ?? 'Veille') . ' • ' . (string) ($client['address'] ?? ''),
    ];

    $tacticalCorridors[] = [
        'id' => 'corridor-' . (string) ($client['id'] ?? ''),
        'client_id' => (string) ($client['id'] ?? ''),
        'kind' => $isCritical ? 'dispatch' : 'watch',
        'label' => 'Corridor OPS → ' . (string) ($client['name'] ?? 'site'),
        'points' => [
            [
                'lat' => (float) ($controlHq['latitude'] ?? -4.3250),
                'lng' => (float) ($controlHq['longitude'] ?? 15.3222),
            ],
            [
                'lat' => (float) ($client['lat'] ?? 0),
                'lng' => (float) ($client['lng'] ?? 0),
            ],
        ],
        'color' => $isCritical ? '#ff6478' : '#4eb4ff',
        'weight' => $isCritical ? 4 : 3,
        'opacity' => 0.88,
        'dashArray' => $isCritical ? '12 8' : '8 8',
        'detail' => $isCritical ? 'Projection d’intervention prioritaire' : 'Projection de surveillance',
    ];
}

$communeBuckets = [];
foreach ($clientsPayload as $client) {
    $communeKey = trim((string) ($client['commune'] ?? ''));
    if ($communeKey === '') {
        continue;
    }

    if (!isset($communeBuckets[$communeKey])) {
        $communeBuckets[$communeKey] = [
            'lat_sum' => 0.0,
            'lng_sum' => 0.0,
            'count' => 0,
            'hot' => 0,
        ];
    }

    $communeBuckets[$communeKey]['lat_sum'] += (float) ($client['lat'] ?? 0);
    $communeBuckets[$communeKey]['lng_sum'] += (float) ($client['lng'] ?? 0);
    $communeBuckets[$communeKey]['count'] += 1;
    if (!empty($client['hasAlert'])) {
        $communeBuckets[$communeKey]['hot'] += 1;
    }
}

$tacticalCommunes = [];
foreach ($communeBuckets as $commune => $bucket) {
    $count = max(1, (int) ($bucket['count'] ?? 1));
    $hot = (int) ($bucket['hot'] ?? 0);
    $tacticalCommunes[] = [
        'id' => 'commune-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $commune)),
        'label' => 'Commune tactique • ' . $commune,
        'center' => [
            'lat' => (float) ($bucket['lat_sum'] / $count),
            'lng' => (float) ($bucket['lng_sum'] / $count),
        ],
        'radius' => 950 + ($count * 70),
        'color' => $hot > 0 ? '#ff8a5b' : '#5bc0ff',
        'weight' => $hot > 0 ? 2 : 1,
        'opacity' => 0.8,
        'fillOpacity' => $hot > 0 ? 0.1 : 0.04,
        'detail' => $count . ' site(s) • ' . $hot . ' alerte(s) active(s)',
    ];
}

$knownCommunes = require __DIR__ . '/../config/communes.php';

$tacticalBoundaries = [
    'communes' => [],
    'quartiers' => [],
];

foreach ($knownCommunes as $slug => $commune) {
    $bucket = $communeBuckets[strtoupper($commune['label'])] ?? $communeBuckets[$commune['label']] ?? null;
    $siteCount = (int) ($bucket['count'] ?? 0);
    $hotCount = (int) ($bucket['hot'] ?? 0);
    $pressure = min(100, ($hotCount * 35) + ($siteCount * 8));
    $lat = (float) $commune['lat'];
    $lng = (float) $commune['lng'];
    $latOffset = (float) $commune['lat_offset'];
    $lngOffset = (float) $commune['lng_offset'];
    $polygon = isset($commune['polygon']) && is_array($commune['polygon']) && count($commune['polygon']) >= 4
        ? array_values($commune['polygon'])
        : vg_map_box_polygon($lat, $lng, $latOffset, $lngOffset);
    $shapeSource = trim((string) ($commune['source'] ?? ''));
    $shapeQuality = $shapeSource !== '' ? 'polygonal' : 'fallback-box';
    $tacticalBoundaries['communes'][] = [
        'id' => 'boundary-commune-' . $slug,
        'type' => 'commune',
        'commune' => (string) $commune['label'],
        'label' => 'Limite commune • ' . $commune['label'],
        'center' => ['lat' => $lat, 'lng' => $lng],
        'polygon' => $polygon,
        'color' => $hotCount > 0 ? '#ff8a5b' : '#7bbdff',
        'weight' => $hotCount > 0 ? 2.6 : 1.7,
        'opacity' => 0.84,
        'fillOpacity' => 0.03,
        'pressure' => $pressure,
        'sites' => $siteCount,
        'hot_alerts' => $hotCount,
        'detail' => $siteCount . ' site(s) • ' . $hotCount . ' alerte(s) • pression ' . $pressure . '/100 • contour ' . ($shapeQuality === 'polygonal' ? 'communal simplifié' : 'cadre de secours'),
        'source' => $shapeSource !== '' ? $shapeSource : 'Délimitation interne VIGILANCE basée sur référentiel OPS',
        'shape_quality' => $shapeQuality,
    ];
}

$quartierBuckets = [];
foreach ($clientsPayload as $client) {
    $quartier = trim((string) ($client['quartier'] ?? ''));
    if ($quartier === '') {
        continue;
    }

    $bucketKey = strtolower(trim((string) ($client['commune'] ?? '') . '|' . $quartier));
    if (!isset($quartierBuckets[$bucketKey])) {
        $quartierBuckets[$bucketKey] = [
            'commune' => (string) ($client['commune'] ?? ''),
            'quartier' => $quartier,
            'lat_sum' => 0.0,
            'lng_sum' => 0.0,
            'count' => 0,
            'hot' => 0,
        ];
    }

    $quartierBuckets[$bucketKey]['lat_sum'] += (float) ($client['lat'] ?? 0);
    $quartierBuckets[$bucketKey]['lng_sum'] += (float) ($client['lng'] ?? 0);
    $quartierBuckets[$bucketKey]['count'] += 1;
    if (!empty($client['hasAlert'])) {
        $quartierBuckets[$bucketKey]['hot'] += 1;
    }
}

foreach ($quartierBuckets as $bucketKey => $bucket) {
    $count = max(1, (int) ($bucket['count'] ?? 1));
    $centerLat = (float) ($bucket['lat_sum'] / $count);
    $centerLng = (float) ($bucket['lng_sum'] / $count);
    $latOffset = 0.006 + ($count * 0.0012);
    $lngOffset = 0.006 + ($count * 0.0012);
    $hotCount = (int) ($bucket['hot'] ?? 0);
    $pressure = min(100, ($hotCount * 40) + ($count * 10));
    $quartierSlug = preg_replace('/[^a-z0-9]+/i', '-', $bucketKey);
    $tacticalBoundaries['quartiers'][] = [
        'id' => 'boundary-quartier-' . strtolower($quartierSlug),
        'type' => 'quartier',
        'commune' => (string) ($bucket['commune'] ?? ''),
        'quartier' => (string) ($bucket['quartier'] ?? 'Quartier'),
        'label' => 'Limite quartier • ' . (string) ($bucket['quartier'] ?? 'Quartier') . ((string) ($bucket['commune'] ?? '') !== '' ? ' • ' . (string) $bucket['commune'] : ''),
        'center' => ['lat' => $centerLat, 'lng' => $centerLng],
        'polygon' => vg_map_box_polygon($centerLat, $centerLng, $latOffset, $lngOffset),
        'color' => $hotCount > 0 ? '#ffd166' : '#74f1d8',
        'weight' => 1.8,
        'opacity' => 0.82,
        'fillOpacity' => 0.025,
        'pressure' => $pressure,
        'sites' => $count,
        'hot_alerts' => $hotCount,
        'detail' => $count . ' site(s) • ' . $hotCount . ' alerte(s) • borne N ' . number_format($centerLat + $latOffset, 4, '.', '') . ' • S ' . number_format($centerLat - $latOffset, 4, '.', '') . ' • E ' . number_format($centerLng + $lngOffset, 4, '.', '') . ' • O ' . number_format($centerLng - $lngOffset, 4, '.', ''),
        'source' => 'Cadre quartier dérivé des positions clients et du renseignement OPS',
    ];
}

$quartierSignals = array_values(array_map(static function (array $boundary): array {
    return [
        'id' => (string) ($boundary['id'] ?? ''),
        'commune' => (string) ($boundary['commune'] ?? ''),
        'quartier' => (string) ($boundary['quartier'] ?? 'Quartier'),
        'label' => (string) ($boundary['label'] ?? 'Quartier'),
        'pressure' => (int) ($boundary['pressure'] ?? 0),
        'sites' => (int) ($boundary['sites'] ?? 0),
        'hot_alerts' => (int) ($boundary['hot_alerts'] ?? 0),
        'center' => (array) ($boundary['center'] ?? []),
        'polygon' => (array) ($boundary['polygon'] ?? []),
        'color' => (string) ($boundary['color'] ?? '#74f1d8'),
        'detail' => (string) ($boundary['detail'] ?? ''),
    ];
}, $tacticalBoundaries['quartiers']));

$cameraCountsByClient = [];
foreach ($cameras as $camera) {
    $cameraClientId = (string) ($camera['client_id'] ?? '');
    if ($cameraClientId === '') {
        continue;
    }

    if (!isset($cameraCountsByClient[$cameraClientId])) {
        $cameraCountsByClient[$cameraClientId] = 0;
    }
    $cameraCountsByClient[$cameraClientId] += 1;
}

$sectorsByCommune = [];
foreach ($clientsPayload as $client) {
    $communeName = trim((string) ($client['commune'] ?? ''));
    if ($communeName === '') {
        continue;
    }

    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $communeName));
    if (!isset($sectorsByCommune[$slug])) {
        $sectorsByCommune[$slug] = [
            'slug' => $slug,
            'commune' => $communeName,
            'sites' => 0,
            'hot_alerts' => 0,
            'camera_count' => 0,
            'client_ids' => [],
        ];
    }

    $sectorsByCommune[$slug]['sites'] += 1;
    if (!empty($client['hasAlert'])) {
        $sectorsByCommune[$slug]['hot_alerts'] += 1;
    }
    $clientId = (string) ($client['id'] ?? '');
    if ($clientId !== '') {
        $sectorsByCommune[$slug]['client_ids'][] = $clientId;
        $sectorsByCommune[$slug]['camera_count'] += (int) ($cameraCountsByClient[$clientId] ?? 0);
    }
}

$tacticalCoverage = [];
foreach ($availableAgents as $agent) {
    $isMission = in_array(strtolower(trim((string) ($agent['status'] ?? ''))), [
        'dispatch',
        'mission envoyee',
        'en mission',
        'en route',
        'sur place',
        'intervention en cours',
        'besoin de renfort',
    ], true);
    $hasPendingAck = (int) ($agent['pending_ack_count'] ?? 0) > 0
        || (!empty($agent['dispatch_requires_ack']) && (string) ($agent['dispatch_ack_status'] ?? '') === 'pending');
    $coverageDetailParts = array_values(array_filter([
        (string) ($agent['zone'] ?? 'Kinshasa'),
        (string) ($agent['status'] ?? 'Disponible'),
        trim((string) ($agent['dispatch_channel_label'] ?? '')),
        $hasPendingAck ? ((int) ($agent['pending_ack_count'] ?? 1)) . ' ACK terrain' : trim((string) ($agent['dispatch_subject'] ?? '')),
    ], static fn(string $value): bool => $value !== ''));
    $tacticalCoverage[] = [
        'id' => 'agent-cover-' . (string) ($agent['id'] ?? ''),
        'agent_id' => (string) ($agent['id'] ?? ''),
        'label' => 'Couverture agent • ' . (string) ($agent['name'] ?? 'Agent VIGILANCE'),
        'center' => [
            'lat' => (float) ($agent['lat'] ?? 0),
            'lng' => (float) ($agent['lng'] ?? 0),
        ],
        'radius' => $hasPendingAck ? 360 : ($isMission ? 420 : 620),
        'color' => $hasPendingAck ? '#ff6478' : ($isMission ? '#ffb347' : '#00cc66'),
        'weight' => 2,
        'opacity' => 0.88,
        'fillOpacity' => $hasPendingAck ? 0.12 : ($isMission ? 0.08 : 0.05),
        'detail' => implode(' • ', $coverageDetailParts),
    ];
}

$tacticalAssignments = [];
$assignmentIndexes = [];
$appendTacticalAssignment = static function (array $assignment) use (&$tacticalAssignments, &$assignmentIndexes): void {
    $clientId = trim((string) ($assignment['client_id'] ?? ''));
    $agentId = trim((string) ($assignment['agent_id'] ?? ''));
    $assignmentKey = $clientId . '|' . $agentId;
    $candidateRank = (int) ($assignment['source_rank'] ?? 0);
    $candidatePending = !empty($assignment['pending_ack']);

    if ($assignmentKey !== '' && isset($assignmentIndexes[$assignmentKey])) {
        $existingIndex = $assignmentIndexes[$assignmentKey];
        $existing = $tacticalAssignments[$existingIndex] ?? [];
        $existingRank = (int) ($existing['source_rank'] ?? 0);
        $existingPending = !empty($existing['pending_ack']);
        if ($candidateRank < $existingRank || ($candidateRank === $existingRank && !$candidatePending && $existingPending)) {
            return;
        }

        $tacticalAssignments[$existingIndex] = $assignment;
        return;
    }

    $assignmentIndexes[$assignmentKey] = count($tacticalAssignments);
    $tacticalAssignments[] = $assignment;
};

foreach ($activeInterventions as $intervention) {
    $interventionId = trim((string) ($intervention['id'] ?? ''));
    $clientId = trim((string) ($intervention['client_id'] ?? ''));
    $client = $clientId !== '' ? ($clientsPayloadById[$clientId] ?? null) : null;
    if (!is_array($client)) {
        continue;
    }

    $agentIds = $interventionId !== '' ? ((array) ($interventionAgentIds[$interventionId] ?? [])) : [];
    foreach ($agentIds as $index => $agentId) {
        $agent = $availableAgentsById[$agentId] ?? null;
        if (!is_array($agent)) {
            continue;
        }

        $dispatch = $interventionId !== '' ? ($dispatchCommunicationByInterventionId[$interventionId] ?? null) : null;
        $pendingAck = is_array($dispatch)
            ? (!empty($dispatch['requires_ack']) && (string) ($dispatch['ack_status'] ?? '') === 'pending')
            : ((int) ($agent['pending_ack_count'] ?? 0) > 0);
        $dispatchChannelLabel = is_array($dispatch)
            ? (string) ($dispatch['channel_label'] ?? '')
            : (string) ($agent['dispatch_channel_label'] ?? '');
        $dispatchAckLabel = is_array($dispatch)
            ? (string) ($dispatch['ack_label'] ?? '')
            : (string) ($agent['dispatch_ack_label'] ?? '');
        $dispatchLane = is_array($dispatch)
            ? (string) ($dispatch['dispatch_lane'] ?? '')
            : (string) ($agent['dispatch_lane'] ?? '');
        $detailParts = array_values(array_filter([
            'Mission ' . (string) ($intervention['status'] ?? 'En route'),
            $dispatchChannelLabel,
            $dispatchAckLabel,
            $dispatchLane,
        ], static fn(string $value): bool => trim($value) !== ''));

        $appendTacticalAssignment([
            'id' => 'assignment-live-' . ($interventionId !== '' ? $interventionId : $clientId) . '-' . $agentId,
            'label' => (string) ($agent['name'] ?? 'Agent VIGILANCE') . ' → ' . (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            'client_id' => $clientId,
            'alert_id' => (string) ($client['alertId'] ?? ''),
            'agent_id' => $agentId,
            'agent_name' => (string) ($agent['name'] ?? 'Agent VIGILANCE'),
            'client_name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            'dispatch_id' => is_array($dispatch) ? (string) ($dispatch['dispatch_id'] ?? '') : '',
            'casefile_id' => is_array($dispatch) ? (string) ($dispatch['casefile_id'] ?? '') : (string) ($client['casefile_id'] ?? ''),
            'channel' => is_array($dispatch) ? (string) ($dispatch['channel'] ?? '') : (string) ($agent['dispatch_channel'] ?? ''),
            'ack_status' => is_array($dispatch) ? (string) ($dispatch['ack_status'] ?? '') : (string) ($agent['dispatch_ack_status'] ?? ''),
            'pending_ack' => $pendingAck,
            'source' => 'dispatch_live',
            'source_rank' => 3,
            'points' => [
                [
                    'lat' => (float) ($agent['lat'] ?? 0),
                    'lng' => (float) ($agent['lng'] ?? 0),
                ],
                [
                    'lat' => (float) ($client['lat'] ?? 0),
                    'lng' => (float) ($client['lng'] ?? 0),
                ],
            ],
            'color' => $pendingAck ? '#ff6478' : ($index === 0 ? '#00cc66' : '#ffd166'),
            'weight' => $pendingAck ? 4 : ($index === 0 ? 3 : 2),
            'opacity' => 0.9,
            'dashArray' => $pendingAck ? '2 6' : ($index === 0 ? '5 7' : '3 9'),
            'detail' => implode(' • ', $detailParts),
        ]);
    }
}

foreach ($clientsPayload as $client) {
    if (empty($client['hasAlert'])) {
        continue;
    }

    $level = strtolower((string) ($client['alertLevel'] ?? ''));
    if (!in_array($level, ['critique', 'critical', 'haute', 'high'], true)) {
        continue;
    }

    $scoredAgents = [];
    foreach ($availableAgents as $agent) {
        $distance = sqrt(
            (((float) ($agent['lat'] ?? 0)) - ((float) ($client['lat'] ?? 0))) ** 2 +
            (((float) ($agent['lng'] ?? 0)) - ((float) ($client['lng'] ?? 0))) ** 2
        );
        $agent['distance_score'] = $distance;
        $scoredAgents[] = $agent;
    }

    usort($scoredAgents, static function (array $left, array $right): int {
        return ($left['distance_score'] ?? 0) <=> ($right['distance_score'] ?? 0);
    });

    $selectedAgents = array_slice($scoredAgents, 0, 2);
    foreach ($selectedAgents as $index => $agent) {
        $appendTacticalAssignment([
            'id' => 'assignment-' . (string) ($client['id'] ?? '') . '-' . (string) ($agent['id'] ?? $index),
            'label' => (string) ($agent['name'] ?? 'Agent VIGILANCE') . ' → ' . (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            'client_id' => (string) ($client['id'] ?? ''),
            'alert_id' => (string) ($client['alertId'] ?? ''),
            'agent_id' => (string) ($agent['id'] ?? ''),
            'agent_name' => (string) ($agent['name'] ?? 'Agent VIGILANCE'),
            'client_name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            'dispatch_id' => '',
            'casefile_id' => (string) ($client['casefile_id'] ?? ''),
            'channel' => (string) ($client['last_radio_channel'] ?? ''),
            'ack_status' => (string) ($client['last_radio_ack_status'] ?? ''),
            'pending_ack' => false,
            'source' => 'projection',
            'source_rank' => 1,
            'points' => [
                [
                    'lat' => (float) ($agent['lat'] ?? 0),
                    'lng' => (float) ($agent['lng'] ?? 0),
                ],
                [
                    'lat' => (float) ($client['lat'] ?? 0),
                    'lng' => (float) ($client['lng'] ?? 0),
                ],
            ],
            'color' => $index === 0 ? '#00cc66' : '#74f1d8',
            'weight' => $index === 0 ? 3 : 2,
            'opacity' => 0.86,
            'dashArray' => $index === 0 ? '5 7' : '3 9',
            'detail' => 'Couverture prioritaire • ' . (string) ($agent['zone'] ?? 'Kinshasa') . ' • score ' . number_format((float) ($agent['distance_score'] ?? 0), 3, '.', ''),
        ]);
    }
}

$tacticalSectors = [];
foreach ($tacticalBoundaries['communes'] as $boundary) {
    $sectorSlug = strtolower((string) preg_replace('/^boundary-commune-/', '', (string) ($boundary['id'] ?? '')));
    $sectorBase = $sectorsByCommune[$sectorSlug] ?? [
        'slug' => $sectorSlug,
        'commune' => (string) preg_replace('/^Limite commune •\s*/u', '', (string) ($boundary['label'] ?? 'Secteur')),
        'sites' => (int) ($boundary['sites'] ?? 0),
        'hot_alerts' => (int) ($boundary['hot_alerts'] ?? 0),
        'camera_count' => 0,
        'client_ids' => [],
    ];

    $clientIds = array_values(array_unique(array_filter(array_map('strval', (array) ($sectorBase['client_ids'] ?? [])))));
    $assignedAgents = [];
    foreach ($tacticalAssignments as $assignment) {
        if (in_array((string) ($assignment['client_id'] ?? ''), $clientIds, true)) {
            $assignedAgents[(string) ($assignment['agent_id'] ?? '')] = (string) ($assignment['agent_name'] ?? 'Agent VIGILANCE');
        }
    }

    $priorityScore = (int) (($sectorBase['hot_alerts'] * 50) + ($sectorBase['sites'] * 10) + ($sectorBase['camera_count'] * 4));
    $priority = 'stabilise';
    if ($priorityScore >= 70) {
        $priority = 'critique';
    } elseif ($priorityScore >= 35) {
        $priority = 'surveillance';
    }

    $sectorCodeBase = strtoupper(substr(str_replace('-', '', $sectorSlug !== '' ? $sectorSlug : 'kinshasa'), 0, 4));
    $sectorCode = 'OPS-' . str_pad($sectorCodeBase !== '' ? $sectorCodeBase : 'ZONE', 4, 'X') . '-' . str_pad((string) (count($tacticalSectors) + 1), 2, '0', STR_PAD_LEFT);
    $tacticalSectors[] = [
        'id' => 'sector-' . $sectorSlug,
        'code' => $sectorCode,
        'label' => 'Secteur OPS • ' . (string) ($sectorBase['commune'] ?? 'Kinshasa'),
        'commune' => (string) ($sectorBase['commune'] ?? 'Kinshasa'),
        'priority' => $priority,
        'priority_score' => min(100, $priorityScore),
        'sites' => (int) ($sectorBase['sites'] ?? 0),
        'hot_alerts' => (int) ($sectorBase['hot_alerts'] ?? 0),
        'camera_count' => (int) ($sectorBase['camera_count'] ?? 0),
        'client_ids' => $clientIds,
        'assigned_agents' => array_values($assignedAgents),
        'assigned_agent_ids' => array_values(array_keys($assignedAgents)),
        'assigned_agents_count' => count($assignedAgents),
        'center' => (array) ($boundary['center'] ?? ['lat' => $controlHq['latitude'], 'lng' => $controlHq['longitude']]),
        'polygon' => (array) ($boundary['polygon'] ?? []),
        'color' => $priority === 'critique' ? '#ff8a5b' : ($priority === 'surveillance' ? '#ffd166' : '#7bbdff'),
        'detail' => $sectorCode . ' • ' . (int) ($sectorBase['sites'] ?? 0) . ' site(s) • ' . (int) ($sectorBase['hot_alerts'] ?? 0) . ' alerte(s) • ' . (int) ($sectorBase['camera_count'] ?? 0) . ' caméra(s) • ' . count($assignedAgents) . ' agent(s)',
    ];
}

$tacticalAir = [];
foreach ($droneFleet as $index => $drone) {
    $offset = ($index + 1) * 0.004;
    $tacticalAir[] = [
        'id' => 'drone-track-' . (string) ($drone['id'] ?? $index),
        'label' => 'Piste aérienne • ' . (string) ($drone['name'] ?? 'Drone VIGILANCE'),
        'points' => [
            [
                'lat' => (float) ($drone['lat'] ?? $controlHq['latitude']) - $offset,
                'lng' => (float) ($drone['lng'] ?? $controlHq['longitude']) - ($offset / 2),
            ],
            [
                'lat' => (float) ($drone['lat'] ?? $controlHq['latitude']),
                'lng' => (float) ($drone['lng'] ?? $controlHq['longitude']),
            ],
            [
                'lat' => (float) ($drone['lat'] ?? $controlHq['latitude']) + ($offset / 2),
                'lng' => (float) ($drone['lng'] ?? $controlHq['longitude']) + $offset,
            ],
        ],
        'color' => in_array((string) ($drone['status'] ?? ''), ['flying', 'patrolling', 'returning'], true) ? '#ffd166' : '#9dbce1',
        'weight' => 2,
        'opacity' => 0.82,
        'dashArray' => '6 10',
        'detail' => 'Altitude ' . (string) ($drone['altitude'] ?? 0) . ' m • Vitesse ' . (string) ($drone['speed'] ?? 0) . ' km/h',
    ];
}

$trackingRecords = [];
foreach ($clientsPayload as $client) {
    if (empty($client['hasAlert'])) {
        continue;
    }

    $trackingRecords[] = [
        'id' => 'tracking-site-' . (string) ($client['id'] ?? ''),
        'type' => 'site',
        'client_id' => (string) ($client['id'] ?? ''),
        'label' => 'Trace site • ' . (string) ($client['name'] ?? 'Abonne VIGILANCE'),
        'source' => 'GPS client + alerte terrain',
        'last_seen' => (string) ($client['alertCreatedAt'] ?? ''),
        'status' => (string) ($client['alertLevel'] ?? 'Veille'),
        'points' => [
            [
                'lat' => (float) ($controlHq['latitude'] ?? -4.3250),
                'lng' => (float) ($controlHq['longitude'] ?? 15.3222),
            ],
            [
                'lat' => (float) ($client['lat'] ?? 0),
                'lng' => (float) ($client['lng'] ?? 0),
            ],
        ],
        'color' => '#4eb4ff',
    ];
}

foreach ($tacticalAssignments as $assignment) {
    $trackingRecords[] = [
        'id' => 'tracking-assignment-' . (string) ($assignment['id'] ?? ''),
        'type' => 'engagement',
        'client_id' => (string) ($assignment['client_id'] ?? ''),
        'agent_id' => (string) ($assignment['agent_id'] ?? ''),
        'label' => 'Trace engagement • ' . (string) ($assignment['label'] ?? 'Affectation tactique'),
        'source' => 'Projection agent → site',
        'last_seen' => date('c'),
        'status' => 'Actif',
        'points' => (array) ($assignment['points'] ?? []),
        'color' => (string) ($assignment['color'] ?? '#74f1d8'),
    ];
}

foreach ($tacticalAir as $track) {
    $trackingRecords[] = [
        'id' => 'tracking-air-' . (string) ($track['id'] ?? ''),
        'type' => 'air',
        'label' => 'Trace aerienne • ' . (string) ($track['label'] ?? 'Drone VIGILANCE'),
        'source' => 'Telemetrie drone',
        'last_seen' => date('c'),
        'status' => 'Live',
        'points' => (array) ($track['points'] ?? []),
        'color' => (string) ($track['color'] ?? '#ffd166'),
    ];
}

usort($trafficReports, static function (array $left, array $right): int {
    return strcmp((string) ($right['reported_at'] ?? $right['created_at'] ?? ''), (string) ($left['reported_at'] ?? $left['created_at'] ?? ''));
});

$trafficPayload = [];
foreach (array_slice($trafficReports, 0, 10) as $report) {
    if (!is_array($report)) {
        continue;
    }

    $trafficSourceType = strtolower((string) ($report['source_type'] ?? ''));
    $trafficSourceId = (string) ($report['source_id'] ?? '');
    $trafficClientId = '';
    if (in_array($trafficSourceType, ['client', 'site'], true)) {
        $trafficClientId = $trafficSourceId;
    } elseif (in_array($trafficSourceType, ['camera', 'cam'], true)) {
        $trafficClientId = (string) ($cameraSourceClientMap[$trafficSourceId] ?? '');
    }
    $trafficAlert = $trafficClientId !== '' ? ($alertByClient[$trafficClientId] ?? null) : null;

    $trafficPayload[] = [
        'id' => (string) ($report['id'] ?? ''),
        'axis' => (string) ($report['axis'] ?? 'Axe sous surveillance'),
        'detail' => (string) ($report['detail'] ?? 'Lecture trafic image'),
        'status' => (string) ($report['status'] ?? 'fluide'),
        'lat' => isset($report['latitude']) ? (float) $report['latitude'] : null,
        'lng' => isset($report['longitude']) ? (float) $report['longitude'] : null,
        'location_accuracy' => (string) ($report['location_accuracy'] ?? 'unlocated'),
        'color' => vg_map_traffic_color((string) ($report['status'] ?? 'fluide')),
        'radius_m' => vg_map_traffic_radius((string) ($report['status'] ?? 'fluide')),
        'node_type' => (string) ($report['node_type'] ?? 'arterial'),
        'vehicle_count' => (int) ($report['vehicle_count'] ?? 0),
        'queue_length_estimate' => (int) ($report['queue_length_estimate'] ?? 0),
        'culprit_label' => (string) ($report['culprit_label'] ?? ''),
        'culprit_position_hint' => (string) ($report['culprit_position_hint'] ?? ''),
        'recommendation' => (string) ($report['recommendation'] ?? ''),
        'reported_at' => (string) ($report['reported_at'] ?? $report['created_at'] ?? ''),
        'source_type' => (string) ($report['source_type'] ?? ''),
        'source_id' => (string) ($report['source_id'] ?? ''),
        'client_id' => $trafficClientId,
        'alert_id' => is_array($trafficAlert) ? (string) ($trafficAlert['id'] ?? '') : '',
    ];
}

$clientsByIdForTraffic = [];
foreach ($clientsPayload as $client) {
    $clientId = (string) ($client['id'] ?? '');
    if ($clientId !== '') {
        $clientsByIdForTraffic[$clientId] = $client;
    }
}

$resolveCommuneBoundary = static function (?float $lat, ?float $lng) use ($tacticalBoundaries): ?array {
    if ($lat === null || $lng === null) {
        return null;
    }

    foreach ((array) ($tacticalBoundaries['communes'] ?? []) as $boundary) {
        if (vg_map_point_in_polygon($lat, $lng, (array) ($boundary['polygon'] ?? []))) {
            return $boundary;
        }
    }

    return null;
};

$resolveQuartierSignal = static function (?float $lat, ?float $lng) use ($quartierSignals): ?array {
    if ($lat === null || $lng === null) {
        return null;
    }

    foreach ($quartierSignals as $signal) {
        if (vg_map_point_in_polygon($lat, $lng, (array) ($signal['polygon'] ?? []))) {
            return $signal;
        }
    }

    return null;
};

$trafficCommandNodes = [];
$communePressureBoard = [];
foreach ($trafficPayload as $report) {
    $lat = isset($report['lat']) ? (float) $report['lat'] : null;
    $lng = isset($report['lng']) ? (float) $report['lng'] : null;
    $client = $report['client_id'] !== '' ? ($clientsByIdForTraffic[(string) $report['client_id']] ?? null) : null;
    $communeBoundary = $resolveCommuneBoundary($lat, $lng);
    if ($communeBoundary === null && is_array($client)) {
        $clientCommune = vg_map_normalize_commune_name((string) ($client['commune'] ?? ''));
        foreach ((array) ($tacticalBoundaries['communes'] ?? []) as $boundary) {
            if (vg_map_normalize_commune_name((string) ($boundary['commune'] ?? '')) === $clientCommune) {
                $communeBoundary = $boundary;
                break;
            }
        }
    }

    if ($communeBoundary === null) {
        continue;
    }

    $quartierSignal = $resolveQuartierSignal($lat, $lng);
    $status = strtolower((string) ($report['status'] ?? 'fluide'));
    $basePressure = 18;
    if (in_array($status, VGX_LEVEL_CRITICAL, true)) {
        $basePressure = 70;
    } elseif (in_array($status, VGX_LEVEL_HIGH, true)) {
        $basePressure = 52;
    } elseif (in_array($status, VGX_LEVEL_MEDIUM, true)) {
        $basePressure = 34;
    }

    $commandPressure = min(
        100,
        $basePressure
        + (((int) ($report['queue_length_estimate'] ?? 0)) * 6)
        + (((int) ($report['vehicle_count'] ?? 0)) * 2)
        + (in_array((string) ($report['node_type'] ?? ''), ['roundabout', 'intersection'], true) ? 8 : 0)
    );
    $commandLevel = $commandPressure >= 75 ? 'critique' : ($commandPressure >= 45 ? 'surveillance' : 'controle');
    $communeSlug = vg_map_slug((string) ($communeBoundary['commune'] ?? 'kinshasa'));
    $quartierName = is_array($quartierSignal) ? (string) ($quartierSignal['quartier'] ?? '') : '';

    $trafficCommandNodes[] = [
        'id' => 'traffic-node-' . (string) ($report['id'] ?? uniqid('traffic-', true)),
        'title' => (string) ($report['axis'] ?? 'Axe sous surveillance'),
        'commune' => (string) ($communeBoundary['commune'] ?? 'Kinshasa'),
        'commune_slug' => $communeSlug,
        'quartier' => $quartierName,
        'lat' => $lat,
        'lng' => $lng,
        'node_type' => (string) ($report['node_type'] ?? 'arterial'),
        'status' => (string) ($report['status'] ?? 'fluide'),
        'pressure' => $commandPressure,
        'level' => $commandLevel,
        'vehicle_count' => (int) ($report['vehicle_count'] ?? 0),
        'queue_length_estimate' => (int) ($report['queue_length_estimate'] ?? 0),
        'camera_client_id' => (string) ($report['client_id'] ?? ''),
        'culprit_label' => (string) ($report['culprit_label'] ?? ''),
        'culprit_position_hint' => (string) ($report['culprit_position_hint'] ?? ''),
        'recommendation' => (string) ($report['recommendation'] ?? ''),
        'detail' => (string) ($report['detail'] ?? 'Lecture trafic image'),
        'color' => (string) ($report['color'] ?? '#ff9f1c'),
    ];

    if (!isset($communePressureBoard[$communeSlug])) {
        $communePressureBoard[$communeSlug] = [
            'commune' => (string) ($communeBoundary['commune'] ?? 'Kinshasa'),
            'commune_slug' => $communeSlug,
            'pressure' => 0,
            'reports' => 0,
            'hot_cases' => 0,
            'camera_count' => 0,
            'quartiers' => [],
        ];
    }

    $communePressureBoard[$communeSlug]['pressure'] = max((int) $communePressureBoard[$communeSlug]['pressure'], $commandPressure);
    $communePressureBoard[$communeSlug]['reports'] += 1;
    $communePressureBoard[$communeSlug]['hot_cases'] += $commandPressure >= 70 ? 1 : 0;
    if ($quartierName !== '') {
        $communePressureBoard[$communeSlug]['quartiers'][$quartierName] = true;
    }
}

$communePressureBoard = array_values(array_map(static function (array $board): array {
    return [
        'commune' => (string) ($board['commune'] ?? 'Kinshasa'),
        'commune_slug' => (string) ($board['commune_slug'] ?? ''),
        'pressure' => (int) ($board['pressure'] ?? 0),
        'reports' => (int) ($board['reports'] ?? 0),
        'hot_cases' => (int) ($board['hot_cases'] ?? 0),
        'quartiers' => array_values(array_keys((array) ($board['quartiers'] ?? []))),
    ];
}, $communePressureBoard));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'updated_at' => date('c'),
    'hq' => [
        'lat' => (float) ($controlHq['latitude'] ?? -4.3250),
        'lng' => (float) ($controlHq['longitude'] ?? 15.3222),
    ],
    'stats' => [
        'clients' => count($clientsPayload),
        'hot_alerts' => $hotAlerts,
        'agents_available' => count(array_filter($availableAgents, static function (array $agent): bool {
            return in_array(strtolower(trim((string) ($agent['status'] ?? ''))), VGX_AGENT_AVAILABLE_STATUSES, true);
        })),
        'missions_live' => $missionsLive,
        'cameras' => count($cameras),
        'drones_live' => count(array_filter($droneFleet, static function (array $drone): bool {
            return in_array((string) ($drone['status'] ?? ''), VGX_DRONE_ACTIVE_STATUSES, true);
        })),
        'drones_total' => count($droneFleet),
        'camera_direct_live' => trim((string) ($cameraPlan['primary_camera_id'] ?? '')) !== '' ? 1 : 0,
        'camera_backup_queue' => count((array) ($cameraPlan['backup_camera_ids'] ?? [])),
        'pending_ack_communications' => $pendingAckCommunications,
        'tactical_orders' => $tacticalOrdersCount,
        'intelligence_live' => $intelligenceLiveCount,
    ],
    'clients' => $clientsPayload,
    'agents' => $availableAgents,
    'cameras' => $cameraPayload,
    'analysis' => [
        'primary_camera_id' => (string) ($cameraPlan['primary_camera_id'] ?? ''),
        'backup_camera_ids' => array_values((array) ($cameraPlan['backup_camera_ids'] ?? [])),
    ],
    'drones' => $droneFleet,
    'tracking' => [
        'records' => $trackingRecords,
    ],
    'communications' => array_slice($radioCommunications, 0, 24),
    'intelligence' => [
        'casefiles' => $intelligenceCasefiles,
    ],
    'traffic' => [
        'reports'   => $trafficPayload,
        'command_nodes' => $trafficCommandNodes,
        'commune_pressure' => $communePressureBoard,
        'incidents' => array_values(array_map(static function (array $inc): array {
            return [
                'id'                      => (string) ($inc['id'] ?? ''),
                'type'                    => (string) ($inc['type'] ?? 'normal'),
                'severity'                => (string) ($inc['severity'] ?? 'fluide'),
                'status'                  => (string) ($inc['status'] ?? 'detecte'),
                'description'             => (string) ($inc['description'] ?? ''),
                'action'                  => (string) ($inc['action'] ?? ''),
                'police_recommended'      => !empty($inc['police_recommended']),
                'lat'                     => isset($inc['lat'])  ? (float) $inc['lat']  : null,
                'lng'                     => isset($inc['lng'])  ? (float) $inc['lng']  : null,
                'source_label'            => (string) ($inc['source_label'] ?? $inc['source_id'] ?? ''),
                'blocking_plate'          => (string) ($inc['blocking_plate'] ?? ''),
                'blocking_vehicle_label'  => (string) ($inc['blocking_vehicle_label'] ?? ''),
                'blocking_vehicle_position' => (string) ($inc['blocking_vehicle_position'] ?? ''),
                'vehicle_count'           => (int) ($inc['vehicle_count'] ?? 0),
                'node_type'               => (string) ($inc['node_type'] ?? 'arterial'),
                'created_at'              => (string) ($inc['created_at'] ?? ''),
            ];
        }, $trafficIncidents)),
    ],
    'tactical' => [
        'zones' => $tacticalZones,
        'corridors' => $tacticalCorridors,
        'communes' => $tacticalCommunes,
        'coverage' => $tacticalCoverage,
        'assignments' => $tacticalAssignments,
        'air' => $tacticalAir,
        'boundaries' => $tacticalBoundaries,
        'quartier_signals' => $quartierSignals,
        'sectors' => $tacticalSectors,
    ],
    'events' => array_slice($events, 0, 16),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
