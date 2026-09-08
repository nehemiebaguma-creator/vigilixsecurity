<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/app-url.php';

if (function_exists('vg_require_role')) {
    vg_require_role('client');
}

$storageFile = vg_store_path();

$loadStore = static function () use ($storageFile): array {
    if (function_exists('vg_load_store')) {
        return vg_load_store();
    }

    if (function_exists('vgx_store')) {
        $store = vgx_store();
        if (is_array($store)) {
            return $store;
        }
    }

    if (!is_file($storageFile)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($storageFile), true);

    return is_array($decoded) ? $decoded : [];
};

$saveStore = static function (array $store) use ($storageFile): void {
    if (function_exists('vg_save_store')) {
        vg_save_store($store);
        return;
    }

    file_put_contents($storageFile, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};

$store = $loadStore();
$clients = function_exists('vgx_clients')
    ? array_values(vgx_clients())
    : (isset($store['clients']) && is_array($store['clients']) ? array_values($store['clients']) : []);
$cameras = function_exists('vgx_cameras')
    ? array_values(vgx_cameras())
    : (isset($store['cameras']) && is_array($store['cameras']) ? array_values($store['cameras']) : []);
$alerts = function_exists('vgx_alerts')
    ? array_values(vgx_alerts())
    : (isset($store['alerts']) && is_array($store['alerts']) ? array_values($store['alerts']) : []);
$payments = function_exists('vgx_payments')
    ? array_values(vgx_payments())
    : (isset($store['payments']) && is_array($store['payments']) ? array_values($store['payments']) : []);
$invoices = isset($store['invoices']) && is_array($store['invoices']) ? array_values($store['invoices']) : [];
$interventions = function_exists('vgx_interventions')
    ? array_values(vgx_interventions())
    : (isset($store['interventions']) && is_array($store['interventions']) ? array_values($store['interventions']) : []);
$agents = function_exists('vgx_agents')
    ? array_values(vgx_agents())
    : (isset($store['agents']) && is_array($store['agents']) ? array_values($store['agents']) : []);
$messages = function_exists('vgx_list_communications')
    ? array_values(vgx_list_communications())
    : (isset($store['communications']) && is_array($store['communications']) ? array_values($store['communications']) : []);
$calls = function_exists('vgx_list_call_requests')
    ? array_values(vgx_list_call_requests())
    : (isset($store['call_requests']) && is_array($store['call_requests']) ? array_values($store['call_requests']) : []);

$user = function_exists('vg_current_user') ? vg_current_user() : ($_SESSION['user'] ?? []);
$sessionClientId = (string) ($_SESSION['client_id'] ?? $user['client_id'] ?? '');
$sessionEmail = trim((string) ($_SESSION['portal_email'] ?? $user['email'] ?? ''));

$client = null;
foreach ($clients as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }

    $candidateId = (string) ($candidate['id'] ?? '');
    $candidatePortalEmail = trim((string) ($candidate['portal_email'] ?? ''));
    $candidateEmail = trim((string) ($candidate['email'] ?? ''));

    if ($sessionClientId !== '' && $candidateId === $sessionClientId) {
        $client = $candidate;
        break;
    }

    if ($sessionEmail !== '' && ($candidatePortalEmail !== '' || $candidateEmail !== '')) {
        if (strcasecmp($candidatePortalEmail, $sessionEmail) === 0 || strcasecmp($candidateEmail, $sessionEmail) === 0) {
            $client = $candidate;
            break;
        }
    }
}

if (
    !$client ||
    empty($client['portal_enabled']) ||
    empty($client['access_enabled']) ||
    !empty($client['portal_access_cut'])
) {
    if (function_exists('vg_logout')) {
        vg_logout();
    }

    header('Location: ' . vg_url('auth/login.php'));
    exit;
}

$clientRedirect = static function (array $params = []): void {
    $query = http_build_query($params);
    $target = 'client/dashboard.php' . ($query !== '' ? '?' . $query : '');
    header('Location: ' . vg_url($target));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'panic') {
        $message = trim((string) ($_POST['message'] ?? 'Urgence reelle signalee depuis le portail client.'));
        $panicOk = false;

        try {
            if (function_exists('vgx_raise_client_panic')) {
                $panicResult = vgx_raise_client_panic($client, $message);
                $panicOk = !empty($panicResult['alert']['id']) && !empty($panicResult['call']['id']);
            } else {
                $store['alerts'][] = [
                    'id' => uniqid('al_', true),
                    'client_id' => $client['id'] ?? null,
                    'type' => 'SOS',
                    'level' => 'Critique',
                    'status' => 'Nouvelle alerte',
                    'message' => $message,
                    'latitude' => $client['latitude'] ?? null,
                    'longitude' => $client['longitude'] ?? null,
                    'created_at' => date('c'),
                ];
                $saveStore($store);
                $panicOk = true;
            }
        } catch (\Throwable $e) {
            $panicOk = false;
        }

        $clientRedirect($panicOk
            ? ['panic' => '1']
            : ['error' => 'Impossible de transmettre l alarme au centre. Reessayez ou appelez directement le centre.']);
    }

    if ($action === 'call-center') {
        $callOk = false;

        try {
            if (function_exists('vgx_create_call_request')) {
                $call = vgx_create_call_request([
                    'client_id' => (string) ($client['id'] ?? ''),
                    'client_name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
                    'phone' => (string) ($client['phone'] ?? ''),
                    'requester_role' => 'client',
                    'requester_id' => (string) ($client['id'] ?? ''),
                    'target_role' => 'admin',
                    'target_id' => 'control-center',
                    'channel' => 'Portail client',
                    'mode' => 'urgent',
                    'status' => 'pending',
                    'notes' => 'Demande de rappel immediate depuis le portail client.',
                ]);

                $callOk = !empty($call['id']);

                if ($callOk && function_exists('vgx_send_communication')) {
                    vgx_send_communication([
                        'from_role' => 'client',
                        'from_id' => (string) ($client['id'] ?? ''),
                        'to_role' => 'admin',
                        'to_id' => 'control-center',
                        'subject' => 'Demande de rappel',
                        'message' => 'Le client demande un rappel rapide depuis son portail securise.',
                        'client_id' => (string) ($client['id'] ?? ''),
                        'client_name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
                        'urgency' => 'urgent',
                        'context_type' => 'call_request',
                        'context_id' => (string) ($call['id'] ?? ''),
                    ]);
                }
            } else {
                $store['call_requests'][] = [
                    'id' => uniqid('call_', true),
                    'client_id' => $client['id'] ?? null,
                    'client_name' => $client['name'] ?? 'Abonne VIGILANCE',
                    'phone' => $client['phone'] ?? '',
                    'status' => 'Nouveau',
                    'channel' => 'Portail client',
                    'created_at' => date('c'),
                ];
                $saveStore($store);
                $callOk = true;
            }
        } catch (\Throwable $e) {
            $callOk = false;
        }

        $clientRedirect($callOk
            ? ['call' => '1']
            : ['error' => 'Impossible de transmettre la demande d appel. Reessayez ou appelez directement le centre.']);
    }

    if ($action === 'send-message') {
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            $clientRedirect(['error' => 'Le message est vide. Ecrivez votre demande avant l envoi.']);
        }

        $messageOk = false;

        try {
            if (function_exists('vgx_send_communication')) {
                $messageRow = vgx_send_communication([
                    'from_role' => 'client',
                    'from_id' => (string) ($client['id'] ?? ''),
                    'to_role' => 'admin',
                    'to_id' => 'control-center',
                    'subject' => 'Message client',
                    'message' => $content,
                    'client_id' => (string) ($client['id'] ?? ''),
                    'client_name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
                    'context_type' => 'portal_message',
                ]);
                $messageOk = !empty($messageRow['id']);
            } else {
                $store['communications'][] = [
                    'id' => uniqid('msg_', true),
                    'from_role' => 'client',
                    'to_role' => 'admin',
                    'client_id' => $client['id'] ?? null,
                    'client_name' => $client['name'] ?? 'Abonne VIGILANCE',
                    'content' => $content,
                    'created_at' => date('c'),
                ];
                $saveStore($store);
                $messageOk = true;
            }
        } catch (\Throwable $e) {
            $messageOk = false;
        }

        $clientRedirect($messageOk
            ? ['msg' => '1']
            : ['error' => 'Impossible de transmettre le message au centre. Reessayez dans un instant.']);
    }

    if ($action === 'acknowledge-report') {
        $reportId = trim((string) ($_POST['report_id'] ?? ''));
        $reportMessage = null;
        foreach ($messages as $messageRow) {
            if (!is_array($messageRow) || (string) ($messageRow['id'] ?? '') !== $reportId) {
                continue;
            }
            $reportMessage = $messageRow;
            break;
        }

        if ($reportMessage !== null && function_exists('vgx_ack_communication')) {
            vgx_ack_communication($reportId, [
                'role' => 'client',
                'id' => (string) ($client['id'] ?? ''),
                'name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            ], 'Lecture confirmee depuis le portail client.');
        }

        header('Location: ' . vg_url('client/dashboard.php?ack=1'));
        exit;
    }
}

$clientId = (string) ($client['id'] ?? '');
$clientCameras = array_values(array_filter($cameras, static function ($camera) use ($clientId): bool {
    return is_array($camera) && (string) ($camera['client_id'] ?? '') === $clientId;
}));
$clientAlerts = array_values(array_filter($alerts, static function ($alert) use ($clientId): bool {
    return is_array($alert) && (string) ($alert['client_id'] ?? '') === $clientId;
}));
$clientPayments = array_values(array_filter($payments, static function ($payment) use ($clientId): bool {
    return is_array($payment) && (string) ($payment['client_id'] ?? '') === $clientId;
}));
$clientInterventions = array_values(array_filter($interventions, static function ($item) use ($clientId): bool {
    return is_array($item) && (string) ($item['client_id'] ?? '') === $clientId;
}));
$clientInvoices = array_values(array_filter($invoices, static function ($item) use ($clientId): bool {
    return is_array($item) && (string) ($item['client_id'] ?? '') === $clientId;
}));
$clientMessageVisible = static function ($item) use ($clientId): bool {
    if (!is_array($item)) {
        return false;
    }

    $fromRole = strtolower(trim((string) ($item['from_role'] ?? '')));
    $toRole = strtolower(trim((string) ($item['to_role'] ?? '')));
    $messageClientId = trim((string) ($item['client_id'] ?? ''));
    $fromClientId = trim((string) ($item['from_id'] ?? $messageClientId));
    $toClientId = trim((string) ($item['to_id'] ?? $messageClientId));

    $isFromClient = $fromRole === 'client' && $fromClientId === $clientId;
    $isToClient = $toRole === 'client' && ($toClientId === $clientId || $messageClientId === $clientId);

    return $isFromClient || $isToClient;
};
$clientMessages = array_values(array_filter($messages, static function ($item) use ($clientMessageVisible): bool {
    return $clientMessageVisible($item);
}));
$clientCalls = array_values(array_filter($calls, static function ($item) use ($clientId): bool {
    return is_array($item) && (string) ($item['client_id'] ?? '') === $clientId;
}));

$assignedAgentNames = [];
$assignedAgents = [];
foreach ($clientInterventions as $intervention) {
    $agentIds = function_exists('vgx_intervention_agent_ids')
        ? vgx_intervention_agent_ids($intervention)
        : (array) ($intervention['agent_ids'] ?? []);
    foreach ($agents as $agent) {
        if (!is_array($agent)) {
            continue;
        }
        if (in_array((string) ($agent['id'] ?? ''), array_map('strval', $agentIds), true)) {
            $assignedAgentNames[] = (string) ($agent['name'] ?? 'Agent VIGILANCE');
            $assignedAgents[(string) ($agent['id'] ?? uniqid('agt_', true))] = $agent;
        }
    }
}
$assignedAgentNames = array_values(array_unique(array_filter($assignedAgentNames)));
$assignedAgents = array_values($assignedAgents);
$clientAgentReports = array_values(array_filter($messages, static function ($item) use ($clientId, $clientMessageVisible): bool {
    return is_array($item)
        && $clientMessageVisible($item)
        && strtolower(trim((string) ($item['from_role'] ?? ''))) === 'agent';
}));
$latestAlert = !empty($clientAlerts) ? array_values(array_slice(array_reverse($clientAlerts), 0, 1))[0] : null;
$latestIntervention = !empty($clientInterventions) ? array_values(array_slice(array_reverse($clientInterventions), 0, 1))[0] : null;
$latestReceipt = null;
foreach (array_reverse($clientInvoices) as $invoice) {
    if ((string) ($invoice['document_type'] ?? '') === 'registration_receipt') {
        $latestReceipt = $invoice;
        break;
    }
}
$totalPayments = 0.0;
foreach ($clientPayments as $payment) {
    if (is_array($payment)) {
        $totalPayments += (float) ($payment['amount'] ?? 0);
    }
}
$panicSent   = isset($_GET['panic']) && $_GET['panic'] === '1';
$callSent    = isset($_GET['call'])  && $_GET['call']  === '1';
$messageSent = isset($_GET['msg'])   && $_GET['msg']   === '1';
$ackSent     = isset($_GET['ack'])   && $_GET['ack']   === '1';
$portalError = trim((string) ($_GET['error'] ?? ''));
$clientCameraSummaries = [];
foreach (array_slice($clientCameras, 0, 4) as $clientCamera) {
    $clientCameraSummaries[] = function_exists('vgx_camera_intelligence_summary')
        ? vgx_camera_intelligence_summary($clientCamera)
        : [];
}
$clientCameraLive = count(array_filter($clientCameras, static function ($camera): bool {
    return is_array($camera) && trim((string) ($camera['stream_url'] ?? '')) !== '';
}));
$clientCameraAlerts = count(array_filter($clientCameraSummaries, static function ($summary): bool {
    if (!is_array($summary)) {
        return false;
    }

    return in_array((string) ($summary['smart_status'] ?? ''), ['alert', 'congested'], true)
        || (int) ($summary['unauthorized_count'] ?? 0) > 0
        || !empty($summary['presence_detected'])
        || !empty($summary['motion_detected']);
}));
$clientAiRecommendation = $clientCameraSummaries[0]['recommendation']
    ?? 'Le centre maintient la veille sur votre site. Vous pouvez signaler toute anomalie reelle depuis ce portail.';
$leadAgent = $assignedAgents[0] ?? null;

// ── Protection status ──────────────────────────────────────────────────────
$openClientAlerts    = array_values(array_filter($clientAlerts, fn($a) => is_array($a) && in_array(strtolower((string)($a['status'] ?? '')), ['nouvelle alerte', 'nouvelle', 'new', 'ouverte', 'open'], true)));
$criticalClientAlerts = array_values(array_filter($openClientAlerts, fn($a) => in_array(strtolower((string)($a['level'] ?? $a['priority'] ?? '')), ['critique', 'critical'], true)));
$activeClientInts    = array_values(array_filter($clientInterventions, fn($i) => is_array($i) && !in_array(strtolower((string)($i['status'] ?? '')), ['mission terminee', 'mission terminée', 'resolue', 'resolved', 'done', 'terminee', 'cloturee'], true)));
usort($clientMessages, fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));
$sortedClientMessages = $clientMessages;
$clientPendingAckMessages = array_values(array_filter($sortedClientMessages, static function ($message) use ($clientId): bool {
    if (!is_array($message)) {
        return false;
    }

    $fromRole = strtolower(trim((string) ($message['from_role'] ?? '')));
    $toRole = strtolower(trim((string) ($message['to_role'] ?? '')));
    $toId = trim((string) ($message['to_id'] ?? ''));
    $messageClientId = trim((string) ($message['client_id'] ?? ''));
    $ackStatus = strtolower(trim((string) ($message['ack_status'] ?? 'pending')));

    return $fromRole !== 'client'
        && $toRole === 'client'
        && ($toId === $clientId || $messageClientId === $clientId)
        && !empty($message['requires_ack'])
        && $ackStatus !== 'acknowledged';
}));

$protStatus = match(true) {
    count($criticalClientAlerts) > 0         => ['label' => 'ALERTE CRITIQUE',          'color' => '#ff2244', 'bg' => 'rgba(255,34,68,.12)',  'icon' => '🚨', 'desc' => 'Urgence en cours — le centre de contrôle a été notifié'],
    count($openClientAlerts) > 0             => ['label' => 'ALERTE EN COURS',           'color' => '#ff7c1e', 'bg' => 'rgba(255,124,30,.10)', 'icon' => '⚠️', 'desc' => 'Alerte active sur votre site — intervention en évaluation'],
    count($activeClientInts) > 0            => ['label' => 'INTERVENTION EN COURS',     'color' => '#4eb4ff', 'bg' => 'rgba(78,180,255,.10)', 'icon' => '🏃', 'desc' => 'Agents VIGILANCE déployés sur votre site'],
    default                                   => ['label' => 'SITE SOUS SURVEILLANCE',   'color' => '#00e676', 'bg' => 'rgba(0,230,118,.07)',  'icon' => '🛡️', 'desc' => 'Aucune alerte active — surveillance VIGILANCE nominale'],
};
$leadCamera = $clientCameras[0] ?? null;
$leadCameraSummary = is_array($clientCameraSummaries[0] ?? null) ? $clientCameraSummaries[0] : [];
$latestClientReport = !empty($clientAgentReports) ? array_values(array_slice(array_reverse($clientAgentReports), 0, 1))[0] : null;
$clientOpsTrigger = match (true) {
    count($criticalClientAlerts) > 0 => 'Escalade critique en cours',
    count($openClientAlerts) > 0 => 'Alerte ouverte a confirmer',
    $clientCameraAlerts > 0 => 'Signaux camera a verifier',
    count($activeClientInts) > 0 => 'Intervention terrain suivie',
    default => 'Veille nominale du site',
};
$clientOpsResponse = match (true) {
    count($criticalClientAlerts) > 0 => 'Le centre priorise l urgence, engage les agents et garde le canal client ouvert.',
    count($openClientAlerts) > 0 => 'Le centre confirme l alerte, verifie les flux et pousse un ordre de mission si besoin.',
    count($activeClientInts) > 0 => 'Le centre suit l agent affecte, la route et les retours terrain jusqu a cloture propre.',
    $clientCameraAlerts > 0 => 'Le centre demande une verification camera et consolide les preuves avant escalation.',
    default => 'Le centre maintient la surveillance, les contacts et la lecture camera en regime stable.',
};
$clientProofState = $latestClientReport
    ? 'Preuve terrain disponible: ' . (string) ($latestClientReport['subject'] ?? 'Rapport agent')
    : ($clientCameraAlerts > 0
        ? 'Preuve camera en construction, verification humaine recommandee.'
        : 'Aucune anomalie forte, journal propre et site sous surveillance.');
$clientTrustMode = match (true) {
    count($criticalClientAlerts) > 0 => 'Confiance degradee tant que l urgence n est pas confirmee et cloturee.',
    count($openClientAlerts) > 0 => 'Confiance sous controle avec lecture camera, canal centre et suivi des agents.',
    count($activeClientInts) > 0 => 'Confiance active: le centre suit terrain, client et preuves dans le meme dossier.',
    default => 'Confiance premium: aucun stale, surveillance lisible et contact centre immediat.',
};
$clientNextAction = match (true) {
    count($criticalClientAlerts) > 0 => 'Rester joignable et reserver l alarme aux urgences reelles supplementaires.',
    count($openClientAlerts) > 0 => 'Surveiller le canal centre et verifier que le message du site reste precis.',
    count($activeClientInts) > 0 => 'Lire les rapports agent et confirmer reception des comptes rendus importants.',
    $clientCameraAlerts > 0 => 'Verifier les cameras prioritaires et signaler tout contexte utile au centre.',
    default => 'Garder le dossier, les contacts et les points camera a jour pour une veille sans rupture.',
};
$clientOpsWatchFocus = $leadCamera
    ? trim(implode(' • ', array_filter([
        (string) ($leadCamera['name'] ?? 'Camera principale'),
        (string) ($leadCamera['location'] ?? ''),
        (string) ($leadCameraSummary['smart_label'] ?? ''),
    ])))
    : 'Aucune camera principale definie pour le moment.';
$clientMissionContact = $leadAgent
    ? trim(implode(' • ', array_filter([
        (string) ($leadAgent['name'] ?? 'Agent VIGILANCE'),
        (string) ($leadAgent['role_label'] ?? 'Agent terrain'),
        (string) ($leadAgent['zone'] ?? 'Kinshasa'),
    ])))
    : 'Centre OPS VIGILANCE 24/7';
$clientAiBrief = function_exists('vg_ai_role_brief')
    ? vg_ai_role_brief('client', [
        'site_name' => (string) ($client['name'] ?? $client['full_name'] ?? 'Site VIGILANCE'),
        'client_name' => (string) ($client['name'] ?? $client['full_name'] ?? 'Site VIGILANCE'),
        'client_open_alerts' => count($openClientAlerts),
        'client_active_interventions' => count($activeClientInts),
        'client_camera_live' => $clientCameraLive,
        'assigned_agents' => count($assignedAgents),
    ])
    : null;
$opsSupportPhone = preg_replace('/\D+/', '', (string) (vg_app('whatsapp_number') ?? '243819174732'));
$opsWhatsAppUrl = function_exists('vg_whatsapp_url')
    ? vg_whatsapp_url((string) (vg_app('whatsapp_number') ?? '243819174732'), 'Bonjour, je vous contacte depuis mon portail client VIGILANCE.')
    : '#';
$clientPortalRealtimeConfig = [
    'role' => 'client',
    'id' => $clientId,
    'clientId' => $clientId,
    'clientName' => (string) ($client['name'] ?? 'Client VIGILANCE'),
    'wsPort' => function_exists('vg_ptt_port') ? (int) vg_ptt_port() : 8765,
    'secureFeedUrl' => vg_url('api/secure-feed.php'),
    'ackUrl' => vg_url('api/ack_communication.php'),
    'startCallUrl' => vg_url('api/start_portal_call.php'),
    'updateCallUrl' => vg_url('api/update_call_status.php'),
    'initialMessages' => array_values($sortedClientMessages),
    'initialPendingAckCount' => count($clientPendingAckMessages),
];
$clientPortalRealtimeAsset = (int) (@filemtime(__DIR__ . '/../assets/js/client-portal-live.js') ?: 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail client | VIGILANCE Security</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/app.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/ops-next.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        :root{--cl-bg:#040c18;--cl-panel:#071526;--cl-border:rgba(78,180,255,.12);--cl-text:#d4eeff;--cl-muted:#6a8aaa}
        body{background-color:var(--cl-bg)!important;}
        .cl-shell{max-width:1240px;margin:0 auto;padding:22px 18px;}
        .cl-flag-stripe{height:5px;background:linear-gradient(90deg,#007fff 0%,#007fff 33.3%,#f7d618 33.3%,#f7d618 66.6%,#ce1126 66.6%,#ce1126 100%);box-shadow:0 0 14px rgba(247,214,24,.12);}
        /* Header */
        .cl-header{display:flex;align-items:center;justify-content:space-between;padding:16px 0 20px;gap:12px;flex-wrap:wrap;}
        .cl-brand{display:flex;align-items:center;gap:12px;}
        .cl-brand strong{font-size:1rem;color:#f4fbff;}
        .cl-brand span{font-size:.75rem;color:var(--cl-muted);}
        /* Status banner */
        .cl-prot-banner{display:flex;align-items:center;gap:16px;padding:14px 20px;border-radius:16px;border:1px solid;margin-bottom:20px;}
        .cl-prot-icon{font-size:2rem;flex-shrink:0;}
        .cl-prot-pulse{width:14px;height:14px;border-radius:50%;flex-shrink:0;animation:cl-pulse 2s infinite;}
        @keyframes cl-pulse{0%,100%{opacity:1}50%{opacity:.25}}
        /* KPI strip */
        .cl-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:20px;}
        .cl-kpi{background:var(--cl-panel);border:1px solid var(--cl-border);border-radius:14px;padding:12px 14px;}
        .cl-kpi span{font-size:.7rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.06em;}
        .cl-kpi strong{display:block;font-size:1.3rem;font-weight:700;color:#f4fbff;margin-top:4px;font-family:monospace;}
        .cl-brain-banner{position:relative;overflow:hidden;border-radius:22px;border:1px solid;padding:20px 22px;margin-bottom:18px;background:linear-gradient(180deg,rgba(7,21,38,.96),rgba(5,15,28,.95));box-shadow:0 20px 52px rgba(0,0,0,.22);}
        .cl-brain-banner::after{content:"";position:absolute;right:-60px;bottom:-90px;width:220px;height:220px;background:radial-gradient(circle,rgba(78,180,255,.14),transparent 70%);pointer-events:none;}
        .cl-brain-head h2{margin:10px 0 8px;font-size:clamp(1.2rem,2vw,1.75rem);line-height:1.1;color:#f5fbff;letter-spacing:-.03em;max-width:18ch;}
        .cl-brain-head p{margin:0;color:#abc8e1;line-height:1.66;max-width:78ch;}
        .cl-brain-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px;}
        .cl-brain-card{padding:15px;border-radius:16px;border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.035);}
        .cl-brain-card span{display:block;font-size:.68rem;color:#78a2c8;text-transform:uppercase;letter-spacing:.09em;}
        .cl-brain-card strong{display:block;margin-top:8px;font-size:1rem;color:#f4fbff;line-height:1.2;}
        .cl-brain-card p{margin:8px 0 0;color:#9dbad4;font-size:.77rem;line-height:1.58;}
        .cl-brain-actions{margin-top:16px;padding:16px;border-radius:16px;border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.03);}
        .cl-brain-actions ul{margin:10px 0 0;padding-left:18px;}
        .cl-brain-actions li{margin:0 0 8px;color:#c9e3fb;line-height:1.58;}
        /* Grid */
        .cl-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
        .cl-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;}
        .cl-panel{background:var(--cl-panel);border:1px solid var(--cl-border);border-radius:18px;padding:18px 20px;}
        .cl-panel h2{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#4eb4ff;margin:0 0 12px;}
        /* SOS */
        .cl-sos{border-radius:18px;border:2px solid rgba(255,34,68,.35);background:rgba(255,34,68,.07);padding:18px 20px;}
        .cl-sos h2{color:#ff6680;}
        .cl-sos textarea{background:rgba(4,11,22,.92);border:1px solid rgba(255,77,109,.25);border-radius:12px;color:#f4fbff;font:inherit;font-size:.83rem;padding:10px 14px;resize:vertical;width:100%;box-sizing:border-box;min-height:80px;}
        .cl-sos-btn{display:block;width:100%;padding:14px;border-radius:14px;font-size:1.05rem;font-weight:700;font-family:monospace;letter-spacing:.08em;border:none;cursor:pointer;background:linear-gradient(135deg,#cc0022,#ff2244);color:#fff;margin-top:10px;text-align:center;box-shadow:0 0 24px rgba(255,34,68,.35);}
        .cl-sos-btn:hover{background:linear-gradient(135deg,#ee0033,#ff4466);}
        /* Chat */
        .cl-chat{display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;padding-right:4px;margin-bottom:10px;}
        .cl-bubble{padding:10px 14px;border-radius:14px;max-width:90%;font-size:.82rem;line-height:1.55;}
        .cl-bubble.from-center,.cl-bubble.from-agent,.cl-bubble.from-admin{background:rgba(78,180,255,.09);border:1px solid rgba(78,180,255,.18);align-self:flex-start;color:#d4eeff;}
        .cl-bubble.from-client{background:rgba(0,230,118,.08);border:1px solid rgba(0,230,118,.18);align-self:flex-end;color:#c4f4dc;}
        .cl-bubble .bsender{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;opacity:.65;}
        .cl-bubble .btime{font-size:.62rem;opacity:.45;margin-top:4px;text-align:right;}
        .cl-bubble .baudio{width:100%;margin-top:10px;border-radius:12px;display:block}
        .cl-audio-tag{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:rgba(89,216,160,.12);border:1px solid rgba(89,216,160,.22);color:#bff5dc;font-size:.58rem;letter-spacing:.08em;margin-left:8px;vertical-align:middle}
        /* Compose */
        .cl-compose{display:flex;flex-direction:column;gap:8px;}
        .cl-compose textarea{background:rgba(4,11,22,.92);border:1px solid var(--cl-border);border-radius:12px;color:#f4fbff;font:inherit;font-size:.82rem;padding:10px 14px;resize:vertical;min-height:68px;box-sizing:border-box;width:100%;}
        .cl-compose textarea:focus{outline:none;border-color:rgba(78,180,255,.35);}
        /* Alert cards */
        .cl-alert-list{display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto;}
        .cl-alert-row{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;border:1px solid;}
        .cl-alert-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
        /* Camera cards */
        .cl-cam-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
        .cl-cam-card{background:rgba(4,11,22,.8);border:1px solid var(--cl-border);border-radius:12px;padding:12px 14px;}
        .cl-cam-card strong{display:block;font-size:.85rem;color:#f4fbff;margin-bottom:4px;}
        .cl-cam-card p{font-size:.75rem;color:var(--cl-muted);margin:0;}
        /* Agent card */
        .cl-agent-card{background:rgba(4,11,22,.8);border:1px solid rgba(78,180,255,.15);border-radius:12px;padding:12px 14px;display:flex;gap:12px;align-items:center;}
        .cl-agent-avatar{width:38px;height:38px;border-radius:50%;background:rgba(78,180,255,.12);border:2px solid rgba(78,180,255,.25);display:flex;align-items:center;justify-content:center;font-weight:700;color:#4eb4ff;flex-shrink:0;}
        /* Payment */
        .cl-pay-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.82rem;}
        .cl-pay-row:last-child{border-bottom:none;}
        /* Confirm */
        .cl-confirm{padding:10px 14px;border-radius:10px;font-size:.82rem;font-weight:600;margin-top:10px;background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.25);color:#2ed39f;}
        .cl-error{padding:12px 14px;border-radius:12px;font-size:.82rem;font-weight:600;margin:14px 0;background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.28);color:#ff8ea2;}
        /* Intervention */
        .cl-int-row{padding:10px 12px;border-radius:12px;border:1px solid;margin-bottom:8px;}
        .cl-intel-card{background:rgba(4,11,22,.82);border:1px solid var(--cl-border);border-radius:14px;padding:14px;}
        .cl-intel-card span{display:block;font-size:.68rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.08em;}
        .cl-intel-card strong{display:block;margin-top:8px;color:#f4fbff;font-size:1rem;}
        .cl-smart-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:rgba(78,180,255,.1);border:1px solid rgba(78,180,255,.18);font-size:.72rem;font-weight:700;letter-spacing:.06em;color:#dff4ff;}
        .cl-doctrine-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px;}
        .cl-doctrine-card{background:linear-gradient(180deg,rgba(7,21,38,.96),rgba(5,14,27,.88));border:1px solid var(--cl-border);border-radius:18px;padding:16px 16px 14px;box-shadow:0 18px 40px rgba(0,0,0,.18);}
        .cl-doctrine-card span{display:block;font-size:.66rem;color:#83b7ee;text-transform:uppercase;letter-spacing:.18em;}
        .cl-doctrine-card strong{display:block;margin-top:10px;color:#f4fbff;font-size:1rem;line-height:1.28;}
        .cl-doctrine-card small{display:block;margin-top:10px;color:#9fc3e3;line-height:1.58;font-size:.76rem;}
        .cl-ops-rail{display:grid;grid-template-columns:1.06fr .94fr;gap:16px;margin-bottom:16px;}
        .cl-ops-flow{display:grid;gap:10px;}
        .cl-ops-step{display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:start;padding:12px 14px;border-radius:15px;border:1px solid rgba(78,180,255,.14);background:rgba(4,11,22,.74);}
        .cl-ops-step b{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgba(78,180,255,.14);color:#e2f3ff;font-size:.76rem;}
        .cl-ops-step div{color:#c7dff4;line-height:1.58;font-size:.82rem;}
        .cl-ops-sidecard{padding:14px 16px;border-radius:16px;border:1px solid rgba(78,180,255,.14);background:rgba(4,11,22,.74);}
        .cl-ops-sidecard span{display:block;font-size:.66rem;color:#83b7ee;text-transform:uppercase;letter-spacing:.16em;}
        .cl-ops-sidecard strong{display:block;margin-top:8px;color:#f4fbff;font-size:1rem;line-height:1.3;}
        .cl-ops-sidecard p{margin:10px 0 0;color:#9fc3e3;line-height:1.58;font-size:.8rem;}
        html[data-theme="light"]{
            --cl-bg:#eef4fb;
            --cl-panel:#ffffff;
            --cl-border:rgba(35,57,88,.16);
            --cl-text:#102133;
            --cl-muted:#51657d;
        }
        html[data-theme="light"] body{background:
            radial-gradient(circle at 10% 7%,rgba(0,111,201,.12),transparent 24%),
            radial-gradient(circle at 91% 8%,rgba(247,214,24,.1),transparent 18%),
            linear-gradient(180deg,#eef4fb 0%,#f9fbff 44%,#edf3fa 100%)!important;color:var(--cl-text)!important}
        html[data-theme="light"] .cl-header{padding:16px 18px;border-radius:28px;border:1px solid rgba(35,57,88,.16);background:
            linear-gradient(135deg,rgba(0,111,201,.08),transparent 46%),
            linear-gradient(180deg,rgba(255,255,255,.96),rgba(239,246,253,.92));box-shadow:0 24px 50px rgba(24,42,66,.08)}
        html[data-theme="light"] .cl-brand strong{color:#102133}
        html[data-theme="light"] .cl-brand span{color:#51657d}
        html[data-theme="light"] .cl-kpi,
        html[data-theme="light"] .cl-brain-banner,
        html[data-theme="light"] .cl-brain-card,
        html[data-theme="light"] .cl-brain-actions,
        html[data-theme="light"] .cl-panel,
        html[data-theme="light"] .cl-cam-card,
        html[data-theme="light"] .cl-agent-card,
        html[data-theme="light"] .cl-intel-card,
        html[data-theme="light"] .cl-doctrine-card,
        html[data-theme="light"] .cl-ops-step,
        html[data-theme="light"] .cl-ops-sidecard{
            background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(242,247,253,.96))!important;
            border-color:rgba(35,57,88,.16)!important;
            color:#102133!important;
            box-shadow:0 18px 38px rgba(24,42,66,.08)!important
        }
        html[data-theme="light"] .cl-brain-banner,
        html[data-theme="light"] .cl-doctrine-card{position:relative;overflow:hidden}
        html[data-theme="light"] .cl-brain-banner::after{background:radial-gradient(circle,rgba(45,168,255,.16),transparent 70%)}
        html[data-theme="light"] .cl-prot-banner{box-shadow:0 20px 42px rgba(24,42,66,.08)!important;backdrop-filter:blur(14px)}
        html[data-theme="light"] .cl-kpi span,
        html[data-theme="light"] .cl-brain-card span,
        html[data-theme="light"] .cl-intel-card span,
        html[data-theme="light"] .cl-doctrine-card span,
        html[data-theme="light"] .cl-ops-sidecard span{
            color:#006fc9!important
        }
        html[data-theme="light"] .cl-brand strong,
        html[data-theme="light"] .cl-kpi strong,
        html[data-theme="light"] .cl-brain-head h2,
        html[data-theme="light"] .cl-brain-card strong,
        html[data-theme="light"] .cl-panel h2,
        html[data-theme="light"] .cl-cam-card strong,
        html[data-theme="light"] .cl-intel-card strong,
        html[data-theme="light"] .cl-doctrine-card strong,
        html[data-theme="light"] .cl-ops-sidecard strong{
            color:#102133!important
        }
        html[data-theme="light"] .cl-brain-head p,
        html[data-theme="light"] .cl-brain-card p,
        html[data-theme="light"] .cl-brain-actions li,
        html[data-theme="light"] .cl-cam-card p,
        html[data-theme="light"] .cl-ops-sidecard p,
        html[data-theme="light"] .cl-int-row,
        html[data-theme="light"] .cl-agent-card,
        html[data-theme="light"] .cl-brand span{
            color:#51657d!important
        }
        html[data-theme="light"] .cl-sos{
            background:linear-gradient(180deg,#fff4f6,#fffdfd)!important;
            border-color:rgba(207,23,49,.24)!important;
            box-shadow:0 18px 38px rgba(132,22,44,.08)!important
        }
        html[data-theme="light"] .cl-sos h2{color:#b5122b!important}
        html[data-theme="light"] .cl-sos textarea,
        html[data-theme="light"] .cl-compose textarea{
            background:#ffffff!important;
            border-color:rgba(35,57,88,.18)!important;
            color:#102133!important
        }
        html[data-theme="light"] .cl-alert-row,
        html[data-theme="light"] .cl-int-row{box-shadow:0 12px 24px rgba(24,42,66,.06)!important;color:#102133!important}
        html[data-theme="light"] .cl-smart-badge{
            background:#eef6ff!important;
            border-color:rgba(0,111,201,.2)!important;
            color:#005fa8!important
        }
        html[data-theme="light"] .cl-ops-step b,
        html[data-theme="light"] .cl-agent-avatar{
            background:#eef6ff!important;
            border-color:rgba(0,111,201,.2)!important;
            color:#005fa8!important
        }
        html[data-theme="light"] .cl-ops-step div{color:#334155!important}
        html[data-theme="light"] .cl-bubble.from-center,
        html[data-theme="light"] .cl-bubble.from-agent,
        html[data-theme="light"] .cl-bubble.from-admin{
            background:#eef6ff!important;
            border-color:rgba(0,111,201,.18)!important;
            color:#17324f!important
        }
        html[data-theme="light"] .cl-bubble.from-client{
            background:#ecfff7!important;
            border-color:rgba(11,143,102,.18)!important;
            color:#07583d!important
        }
        html[data-theme="light"] .cl-bubble .bsender,
        html[data-theme="light"] .cl-bubble .btime{color:#51657d!important;opacity:1}
        html[data-theme="light"] .cl-confirm{
            background:linear-gradient(180deg,#ecfff7,#f8fffb)!important;
            border-color:rgba(11,143,102,.22)!important;
            color:#07583d!important
        }
        html[data-theme="light"] .cl-error{
            background:linear-gradient(180deg,#fff4f6,#fffdfd)!important;
            border-color:rgba(207,23,49,.24)!important;
            color:#8b0e21!important
        }
        @media(max-width:1100px){.cl-doctrine-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cl-ops-rail{grid-template-columns:1fr}}
        @media(max-width:900px){.cl-grid,.cl-grid-3,.cl-brain-grid,.cl-doctrine-grid{grid-template-columns:1fr}}
    </style>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(vg_url('brand-media.php?asset=favicon&v=20260715-official-logo'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="cl-flag-stripe"></div>
<main class="cl-shell">

    <header class="cl-header">
        <a href="<?= htmlspecialchars(vg_url(), ENT_QUOTES, 'UTF-8') ?>" class="cl-brand" style="text-decoration:none;">
            <img src="<?= htmlspecialchars(vg_url('brand-media.php?v=20260715-official-logo'), ENT_QUOTES, 'UTF-8') ?>" alt="VIGILANCE" style="height:36px;width:auto;">
            <div>
                <strong>VIGILANCE Security</strong>
                <span>Portail abonne securise</span>
            </div>
        </a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/communication.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.78rem;">Messages</a>
            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/payments.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.78rem;">Paiements</a>
            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/cameras.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.78rem;">Caméras</a>
            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.78rem;">Déconnexion</a>
        </div>
    </header>

    <?php if (!$client): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--cl-muted);">Portail indisponible — contacter le centre pour régénérer votre lien d'accès.</div>
    <?php else: ?>

    <!-- ── PROTECTION STATUS BANNER ──────────────────────────────────────── -->
    <div class="cl-prot-banner" style="background:<?= $protStatus['bg'] ?>;border-color:<?= $protStatus['color'] ?>40;">
        <div class="cl-prot-icon"><?= $protStatus['icon'] ?></div>
        <div style="flex:1;">
            <div style="font-size:.65rem;color:var(--cl-muted);font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:2px;">STATUT DE PROTECTION — <?= htmlspecialchars((string)($client['name'] ?? '')) ?></div>
            <div style="font-size:1.15rem;font-weight:800;font-family:monospace;color:<?= $protStatus['color'] ?>;letter-spacing:.05em;"><?= $protStatus['label'] ?></div>
            <div style="font-size:.8rem;color:#a0bcd0;margin-top:2px;"><?= htmlspecialchars($protStatus['desc']) ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:.65rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px;">Abonnement</div>
            <div style="font-size:.95rem;font-weight:700;color:#f4fbff;"><?= htmlspecialchars((string)($client['subscription'] ?? 'Essentiel')) ?></div>
            <div style="font-size:.7rem;color:var(--cl-muted);margin-top:2px;"><?= htmlspecialchars((string)($client['commune'] ?? '')) ?><?= !empty($client['commune']) ? ' — ' : '' ?>Kinshasa</div>
        </div>
    </div>

    <!-- ── KPI STRIP ──────────────────────────────────────────────────────── -->
    <?php if ($portalError !== ''): ?>
        <div class="cl-error"><?= htmlspecialchars($portalError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="cl-kpis">
        <div class="cl-kpi">
            <span>Alertes actives</span>
            <strong style="<?= count($openClientAlerts) > 0 ? 'color:#ff4d6d;' : 'color:#2ed39f;' ?>"><?= count($openClientAlerts) ?></strong>
        </div>
        <div class="cl-kpi">
            <span>Caméras</span>
            <strong><?= count($clientCameras) ?></strong>
        </div>
        <div class="cl-kpi">
            <span>Interventions</span>
            <strong><?= count($activeClientInts) ?></strong>
        </div>
        <div class="cl-kpi">
            <span>Agents</span>
            <strong><?= count($assignedAgents) ?></strong>
        </div>
        <div class="cl-kpi">
            <span>Messages</span>
            <strong><?= count($clientMessages) ?></strong>
        </div>
        <div class="cl-kpi">
            <span>Total réglé</span>
            <strong style="font-size:.9rem;"><?= number_format($totalPayments, 0, ',', ' ') ?></strong>
        </div>
    </div>

    <?php if (is_array($clientAiBrief)): ?>
    <section class="cl-brain-banner" style="border-color:<?= htmlspecialchars((string) ($clientAiBrief['tone']['border'] ?? 'rgba(78,180,255,.2)'), ENT_QUOTES, 'UTF-8') ?>;">
        <div class="cl-brain-head">
            <span class="cl-smart-badge" style="background:<?= htmlspecialchars((string) ($clientAiBrief['tone']['bg'] ?? 'rgba(78,180,255,.08)'), ENT_QUOTES, 'UTF-8') ?>;border-color:<?= htmlspecialchars((string) ($clientAiBrief['tone']['border'] ?? 'rgba(78,180,255,.2)'), ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars((string) ($clientAiBrief['tone']['color'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars((string) ($clientAiBrief['eyebrow'] ?? 'Noyau intelligence VIGILANCE'), ENT_QUOTES, 'UTF-8') ?></span>
            <h2><?= htmlspecialchars((string) ($clientAiBrief['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars((string) ($clientAiBrief['copy'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;">
                <?php foreach ((array) ($clientAiBrief['chips'] ?? []) as $chip): ?>
                    <span class="cl-smart-badge"><?= htmlspecialchars((string) $chip, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="cl-brain-grid">
            <?php foreach ((array) ($clientAiBrief['cards'] ?? []) as $card): ?>
                <article class="cl-brain-card" style="box-shadow:inset 0 0 0 1px <?= htmlspecialchars((string) ($card['accent'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8') ?>22;">
                    <span><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong style="color:<?= htmlspecialchars((string) ($card['accent'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars((string) ($card['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <p><?= htmlspecialchars((string) ($card['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="cl-brain-actions">
            <span class="cl-smart-badge" style="margin-bottom:6px;">Actions recommandees</span>
            <ul>
                <?php foreach ((array) ($clientAiBrief['actions'] ?? []) as $actionItem): ?>
                    <li><?= htmlspecialchars((string) $actionItem, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <section class="cl-doctrine-grid">
        <article class="cl-doctrine-card">
            <span>Déclencheur surveillé</span>
            <strong><?= htmlspecialchars($clientOpsTrigger, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($clientOpsWatchFocus, ENT_QUOTES, 'UTF-8') ?></small>
        </article>
        <article class="cl-doctrine-card">
            <span>Réponse centre OPS</span>
            <strong><?= htmlspecialchars((string) ($leadAgent['name'] ?? 'Centre OPS VIGILANCE'), ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($clientOpsResponse, ENT_QUOTES, 'UTF-8') ?></small>
        </article>
        <article class="cl-doctrine-card">
            <span>Preuve et confirmation</span>
            <strong><?= htmlspecialchars($clientProofState, ENT_QUOTES, 'UTF-8') ?></strong>
            <small>Les preuves viennent des caméras, des rapports terrain et de vos confirmations client dans le même dossier.</small>
        </article>
        <article class="cl-doctrine-card">
            <span>Confiance client</span>
            <strong><?= htmlspecialchars($clientNextAction, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($clientTrustMode, ENT_QUOTES, 'UTF-8') ?></small>
        </article>
    </section>

    <section class="cl-ops-rail">
        <article class="cl-panel">
            <h2>🎯 Doctrine centre OPS</h2>
            <div class="cl-ops-flow">
                <div class="cl-ops-step">
                    <b>1</b>
                    <div>Le centre relie votre site, vos alertes, vos cameras et les agents affectés a un seul fil opérationnel lisible.</div>
                </div>
                <div class="cl-ops-step">
                    <b>2</b>
                    <div>La camera dominante et le dernier signal utile servent a confirmer si l alerte doit rester locale, être escaladée ou clôturée.</div>
                </div>
                <div class="cl-ops-step">
                    <b>3</b>
                    <div>Le canal client sert a valider le contexte réel, recevoir les comptes rendus agent et garder une confiance propre jusqu a la clôture.</div>
                </div>
            </div>
        </article>

        <article class="cl-panel">
            <h2>📍 Point maître du dossier</h2>
            <div class="cl-ops-sidecard" style="margin-bottom:12px;">
                <span>Lecture camera prioritaire</span>
                <strong><?= htmlspecialchars((string) ($leadCamera['name'] ?? 'Aucune camera prioritaire'), ENT_QUOTES, 'UTF-8') ?></strong>
                <p><?= htmlspecialchars((string) ($leadCameraSummary['recommendation'] ?? $clientAiRecommendation), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="cl-ops-sidecard" style="margin-bottom:12px;">
                <span>Contact opérationnel</span>
                <strong><?= htmlspecialchars($clientMissionContact, ENT_QUOTES, 'UTF-8') ?></strong>
                <p>Le centre reste le chef d orchestre. L agent affecté reprend ensuite le terrain, la preuve et le retour de mission.</p>
            </div>
            <div class="cl-ops-sidecard">
                <span>Action recommandée maintenant</span>
                <strong><?= htmlspecialchars($clientNextAction, ENT_QUOTES, 'UTF-8') ?></strong>
                <p>Utilisez le bon canal: alarme pour l urgence réelle, message pour le contexte, appel pour accélérer un échange humain.</p>
            </div>
        </article>
    </section>

    <!-- ── SOS + CHAT ROW ─────────────────────────────────────────────────── -->
    <div class="cl-grid" style="margin-bottom:16px;">

        <!-- SOS ALARM -->
        <div class="cl-sos">
            <h2>🚨 Alarme urgence</h2>
            <p style="font-size:.8rem;color:#c0809a;margin:0 0 10px;">Utiliser uniquement en cas de réelle urgence. Le centre reçoit votre alarme immédiatement avec votre GPS.</p>
            <form method="post">
                <input type="hidden" name="action" value="panic">
                <textarea name="message" placeholder="Décrivez brièvement l'urgence réelle..." rows="3"></textarea>
                <button type="submit" class="cl-sos-btn">🚨 DÉCLENCHER L'ALARME</button>
            </form>
            <?php if ($panicSent): ?><div class="cl-confirm">✅ Alarme transmise — le centre de contrôle a été notifié.</div><?php endif; ?>

            <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,77,109,.12);">
                <div style="font-size:.72rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">Contact direct</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="post" style="display:contents;">
                        <input type="hidden" name="action" value="call-center">
                        <button type="submit" class="btn btn-secondary" style="font-size:.78rem;">📞 Demander un rappel</button>
                    </form>
                    <button
                        type="button"
                        class="btn btn-primary"
                        style="font-size:.78rem;"
                        data-live-call-target="control-center"
                        data-live-call-role="admin"
                        data-live-call-name="Centre OPS VIGILANCE"
                    >Appel live OPS</button>
                    <a class="btn btn-secondary" href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', (string)(vg_app('whatsapp_number') ?? '243819174732'))) ?>" style="font-size:.78rem;">Appeler le centre</a>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_whatsapp_url((string)(vg_app('whatsapp_number') ?? '243819174732'), 'Bonjour, je vous contacte depuis mon portail client VIGILANCE.'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="font-size:.78rem;">WhatsApp</a>
                </div>
                <?php if ($callSent): ?><div class="cl-confirm">✅ Demande de rappel transmise au centre.</div><?php endif; ?>
            </div>
        </div>

        <!-- CHAT WITH CENTER -->
        <div class="cl-panel">
            <h2>💬 Canal centre de contrôle</h2>
            <div class="cl-chat" id="cl-chat">
                <?php if (!$sortedClientMessages): ?>
                    <div style="text-align:center;padding:20px 0;color:var(--cl-muted);font-size:.8rem;">
                        <div style="font-size:1.6rem;margin-bottom:6px;">📭</div>
                        Aucun échange pour le moment. Écrivez au centre.
                    </div>
                <?php else: ?>
                    <?php foreach ($sortedClientMessages as $msg):
                        $isClient = in_array($msg['from_role'] ?? '', ['client'], true);
                        $audioUrl = trim((string) ($msg['audio_url'] ?? ''));
                        $sender   = $isClient ? 'Vous' : ucfirst((string)($msg['from_role'] ?? 'Centre'));
                        $cls      = $isClient ? 'from-client' : 'from-' . ($msg['from_role'] ?? 'center');
                        $ackPending = !$isClient
                            && !empty($msg['requires_ack'])
                            && strtolower((string) ($msg['ack_status'] ?? 'pending')) !== 'acknowledged';
                    ?>
                    <div class="cl-bubble <?= $cls ?>" data-message-id="<?= htmlspecialchars((string) ($msg['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-ack-status="<?= htmlspecialchars((string) ($msg['ack_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="bsender"><?= htmlspecialchars($sender) ?><?php if (!empty($msg['subject']) && $msg['subject'] !== 'Message VIGILANCE'): ?> — <?= htmlspecialchars($msg['subject']) ?><?php endif; ?></div>
                        <?= nl2br(htmlspecialchars((string)($msg['message'] ?? $msg['content'] ?? ''))) ?>
                        <?php if ($audioUrl !== ''): ?>
                            <audio class="baudio" controls preload="none" src="<?= htmlspecialchars($audioUrl, ENT_QUOTES, 'UTF-8') ?>"></audio>
                        <?php endif; ?>
                        <?php if (!$isClient && !empty($msg['requires_ack'])): ?>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;">
                                <span data-ack-badge style="display:inline-flex;align-items:center;min-height:24px;padding:0 10px;border-radius:999px;border:1px solid <?= $ackPending ? 'rgba(255,191,102,.26)' : 'rgba(89,216,160,.22)' ?>;background:<?= $ackPending ? 'rgba(45,28,8,.84)' : 'rgba(10,36,29,.78)' ?>;color:<?= $ackPending ? '#ffe0b0' : '#bff5dc' ?>;font-size:.64rem;letter-spacing:.08em;"><?= $ackPending ? 'ACK attendu' : 'ACK confirme' ?></span>
                                <?php if ($ackPending): ?>
                                    <button type="button" class="btn btn-secondary" style="font-size:.72rem;padding:5px 12px;" data-client-ack="<?= htmlspecialchars((string) ($msg['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Marquer lu</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="btime"><?= htmlspecialchars(substr((string)($msg['created_at'] ?? ''), 0, 16)) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" class="cl-compose">
                <input type="hidden" name="action" value="send-message">
                <textarea name="content" placeholder="Écrire au centre... (question, information de site, demande de suivi, rapport)"></textarea>
                <button type="submit" class="btn btn-primary" style="align-self:flex-end;font-size:.8rem;">Envoyer au centre</button>
            </form>
            <?php if ($messageSent): ?><div class="cl-confirm" style="margin-top:8px;">✅ Message transmis au centre de contrôle.</div><?php endif; ?>
            <?php if ($ackSent): ?><div class="cl-confirm" style="margin-top:8px;">✅ Accusé de réception transmis.</div><?php endif; ?>
            <div style="margin-top:10px;text-align:right;">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/communication.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;">Voir tous les échanges →</a>
            </div>
        </div>
    </div>

    <!-- ── ALERTS + INTERVENTIONS ─────────────────────────────────────────── -->
    <div class="cl-grid" style="margin-bottom:16px;">
        <article class="cl-panel">
            <h2>🚨 Alertes récentes (<?= count($clientAlerts) ?>)</h2>
            <?php if (!$clientAlerts): ?>
                <div style="text-align:center;padding:20px 0;color:var(--cl-muted);font-size:.8rem;">Aucune alerte — site en surveillance nominale</div>
            <?php else: ?>
            <div class="cl-alert-list">
                <?php foreach (array_slice(array_reverse($clientAlerts), 0, 6) as $a):
                    $isOpen = in_array(strtolower((string)($a['status'] ?? '')), ['nouvelle alerte', 'nouvelle', 'new', 'ouverte', 'open'], true);
                    $isCrit = in_array(strtolower((string)($a['level'] ?? $a['priority'] ?? '')), ['critique', 'critical'], true);
                    $ac     = $isCrit ? '#ff4d6d' : ($isOpen ? '#ff9f1c' : '#2ed39f');
                ?>
                <div class="cl-alert-row" style="background:<?= $ac ?>08;border-color:<?= $ac ?>30;">
                    <div class="cl-alert-dot" style="background:<?= $ac ?>;<?= $isOpen ? 'animation:cl-pulse 1.5s infinite;' : '' ?>"></div>
                    <div style="flex:1;">
                        <div style="font-size:.83rem;font-weight:600;color:<?= $ac ?>"><?= htmlspecialchars((string)($a['type'] ?? 'Alerte')) ?> <span style="font-size:.7rem;opacity:.6;"><?= htmlspecialchars(strtoupper((string)($a['status'] ?? ''))) ?></span></div>
                        <div style="font-size:.75rem;color:var(--cl-muted);margin-top:2px;"><?= htmlspecialchars(mb_strimwidth((string)($a['message'] ?? ''), 0, 70, '…')) ?></div>
                    </div>
                    <div style="font-size:.65rem;color:var(--cl-muted);white-space:nowrap;"><?= substr((string)($a['created_at'] ?? ''), 0, 16) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>

        <article class="cl-panel">
            <h2>🏃 Interventions (<?= count($clientInterventions) ?>)</h2>
            <?php if (!$clientInterventions): ?>
                <div style="text-align:center;padding:20px 0;color:var(--cl-muted);font-size:.8rem;">Aucune intervention enregistrée</div>
            <?php else: ?>
            <?php foreach (array_slice(array_reverse($clientInterventions), 0, 5) as $iv):
                $ivStat = (string)($iv['status'] ?? 'En attente');
                $ivDone = in_array(strtolower($ivStat), ['mission terminee', 'mission terminée', 'resolue', 'resolved', 'done', 'terminee', 'cloturee'], true);
                $ivRoute = in_array(strtolower($ivStat), ['en route', 'en_route', 'depart', 'dispatch'], true);
                $ivC     = $ivDone ? '#2ed39f' : ($ivRoute ? '#4eb4ff' : '#ff9f1c');
            ?>
            <div class="cl-int-row" style="background:<?= $ivC ?>08;border-color:<?= $ivC ?>30;">
                <div style="font-size:.72rem;font-weight:700;color:<?= $ivC ?>;text-transform:uppercase;letter-spacing:.06em;"><?= htmlspecialchars($ivStat) ?></div>
                <div style="font-size:.8rem;color:#c0d8f0;margin-top:3px;"><?= htmlspecialchars(mb_strimwidth((string)($iv['comment'] ?? $iv['report'] ?? 'Mission VIGILANCE'), 0, 80, '…')) ?></div>
                <div style="font-size:.68rem;color:var(--cl-muted);margin-top:3px;"><?= substr((string)($iv['created_at'] ?? ''), 0, 16) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </article>
    </div>

    <!-- ── CAMERAS + AGENTS ──────────────────────────────────────────────── -->
    <div class="cl-grid" style="margin-bottom:16px;">
        <article class="cl-panel">
            <h2>📡 Mes caméras (<?= count($clientCameras) ?>)</h2>
            <?php if (!$clientCameras): ?>
                <div style="color:var(--cl-muted);font-size:.82rem;">Aucune caméra liée — contacter le centre pour l'installation.</div>
            <?php else: ?>
            <div class="cl-cam-grid">
                <?php foreach ($clientCameras as $cameraIndex => $cam): ?>
                <?php
                $camOpenTarget = function_exists('vg_camera_client_open_target') ? vg_camera_client_open_target($cam) : ['url' => '', 'label' => 'Ouvrir'];
                $camSummary = is_array($clientCameraSummaries[$cameraIndex] ?? null) ? $clientCameraSummaries[$cameraIndex] : [];
                ?>
                <div class="cl-cam-card">
                    <strong><?= htmlspecialchars((string)($cam['name'] ?? 'Caméra')) ?></strong>
                    <p><?= htmlspecialchars((string)($cam['location'] ?? 'Emplacement non renseigné')) ?></p>
                    <p style="margin-top:4px;"><?= htmlspecialchars(strtoupper((string)($cam['status'] ?? 'PRÊTE'))) ?> · <?= htmlspecialchars((string)($cam['type'] ?? 'IP')) ?></p>
                    <?php if ($camSummary !== []): ?>
                        <span class="cl-smart-badge" style="margin-top:8px;color:<?= htmlspecialchars((string) ($camSummary['smart_color'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8') ?>;border-color:<?= htmlspecialchars((string) ($camSummary['smart_color'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8') ?>44;background:<?= htmlspecialchars((string) ($camSummary['smart_bg'] ?? 'rgba(78,180,255,.10)'), ENT_QUOTES, 'UTF-8') ?>;">
                            <?= htmlspecialchars(trim(((string) ($camSummary['smart_icon'] ?? 'IA')) . ' ' . ((string) ($camSummary['smart_label'] ?? 'Lecture IA en attente'))), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <p style="margin-top:8px;color:#c7dff4;line-height:1.58;"><?= htmlspecialchars((string) ($camSummary['recommendation'] ?? 'Lecture IA en attente.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($camOpenTarget['url'])): ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars((string) $camOpenTarget['url']) ?>" target="_blank" rel="noopener" style="font-size:.72rem;margin-top:8px;"><?= htmlspecialchars((string) ($camOpenTarget['label'] ?? 'Ouvrir')) ?></a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div style="margin-top:12px;">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/cameras.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;">Voir toutes les caméras →</a>
            </div>
        </article>

        <article class="cl-panel">
            <h2>👮 Agents assignés (<?= count($assignedAgents) ?>)</h2>
            <?php if (!$assignedAgents): ?>
                <div style="color:var(--cl-muted);font-size:.82rem;padding:12px 0;">Aucun agent directement assigné à ce dossier pour le moment.</div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($assignedAgents as $ag): ?>
                <div class="cl-agent-card">
                    <div class="cl-agent-avatar"><?= mb_strtoupper(mb_substr((string)($ag['name'] ?? 'A'), 0, 1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.88rem;font-weight:600;color:#f4fbff;"><?= htmlspecialchars((string)($ag['name'] ?? 'Agent VIGILANCE')) ?></div>
                        <div style="font-size:.75rem;color:var(--cl-muted);"><?= htmlspecialchars((string)($ag['role_label'] ?? 'Agent terrain')) ?> · <?= htmlspecialchars((string)($ag['zone'] ?? 'Kinshasa')) ?></div>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <?php if (!empty($ag['phone'])): ?>
                            <a class="btn btn-secondary" href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', (string)$ag['phone'])) ?>" style="font-size:.72rem;">Appeler</a>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/communication.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.72rem;">Suivi</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- REPORTS FROM AGENTS -->
            <?php if ($clientAgentReports): ?>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06);">
                <div style="font-size:.7rem;color:var(--cl-muted);font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">Rapports terrain reçus</div>
                <?php foreach (array_slice(array_reverse($clientAgentReports), 0, 3) as $rep): ?>
                <div style="padding:10px 12px;background:rgba(4,11,22,.8);border:1px solid rgba(78,180,255,.1);border-radius:10px;margin-bottom:6px;">
                    <div style="font-size:.8rem;font-weight:600;color:#c0d8f0;"><?= htmlspecialchars((string)($rep['subject'] ?? 'Rapport agent')) ?></div>
                    <div style="font-size:.75rem;color:var(--cl-muted);margin-top:3px;"><?= htmlspecialchars(mb_strimwidth((string)($rep['message'] ?? $rep['content'] ?? ''), 0, 80, '…')) ?></div>
                    <form method="post" style="margin-top:8px;">
                        <input type="hidden" name="action" value="acknowledge-report">
                        <input type="hidden" name="report_id" value="<?= htmlspecialchars((string)($rep['id'] ?? '')) ?>">
                        <button type="submit" class="btn btn-secondary" style="font-size:.72rem;padding:4px 12px;">Confirmer réception</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>
    </div>

    <!-- ── SITE INFO + FINANCE ────────────────────────────────────────────── -->
    <div class="cl-grid" style="margin-bottom:16px;">
        <article class="cl-panel">
            <h2>🏢 Informations du site protégé</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <?php
                $siteFields = [
                    'Commune'      => $client['commune'] ?? '—',
                    'Quartier'     => $client['quartier'] ?? '—',
                    'Avenue'       => $client['avenue'] ?? '—',
                    'Adresse'      => $client['address'] ?? '—',
                    'Latitude'     => !empty($client['latitude']) ? (string)round((float)$client['latitude'], 6) : '—',
                    'Longitude'    => !empty($client['longitude']) ? (string)round((float)$client['longitude'], 6) : '—',
                    'Statut dossier' => $client['status'] ?? '—',
                    'Email portail'  => $client['portal_email'] ?? $client['email'] ?? '—',
                ];
                foreach ($siteFields as $label => $val): ?>
                <div style="background:rgba(4,11,22,.8);border:1px solid var(--cl-border);border-radius:10px;padding:8px 12px;">
                    <div style="font-size:.65rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.06em;"><?= htmlspecialchars($label) ?></div>
                    <div style="font-size:.82rem;font-weight:600;color:#f4fbff;margin-top:2px;word-break:break-all;"><?= htmlspecialchars($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="cl-panel">
            <h2>💰 Historique financier</h2>
            <div style="margin-bottom:12px;">
                <div style="font-size:.68rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.07em;">Total réglé</div>
                <div style="font-size:1.5rem;font-weight:800;font-family:monospace;color:#f4fbff;margin-top:2px;"><?= number_format($totalPayments, 0, ',', ' ') ?> <span style="font-size:.8rem;font-weight:400;color:var(--cl-muted);">USD</span></div>
            </div>
            <?php foreach (array_slice(array_reverse($clientPayments), 0, 5) as $pay): ?>
            <div class="cl-pay-row">
                <span style="color:#d4eeff;"><?= htmlspecialchars((string)($pay['amount'] ?? '0')) ?> USD</span>
                <span style="color:<?= strtolower((string)($pay['status'] ?? '')) === 'paye' ? '#2ed39f' : '#ff9f1c' ?>;font-size:.75rem;"><?= htmlspecialchars(ucfirst((string)($pay['status'] ?? 'En attente'))) ?></span>
                <span style="color:var(--cl-muted);font-size:.72rem;"><?= substr((string)($pay['due_date'] ?? $pay['created_at'] ?? ''), 0, 10) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$clientPayments): ?>
                <div style="color:var(--cl-muted);font-size:.82rem;">Aucun paiement enregistré pour ce dossier.</div>
            <?php endif; ?>
            <div style="margin-top:12px;">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/payments.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;">Voir tous les paiements →</a>
            </div>

            <?php if ($latestReceipt): ?>
            <div style="margin-top:14px;padding:12px 14px;background:rgba(78,180,255,.06);border:1px solid rgba(78,180,255,.15);border-radius:12px;">
                <div style="font-size:.72rem;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.07em;">Reçu d'enregistrement</div>
                <div style="font-size:.85rem;font-weight:600;color:#f4fbff;margin-top:4px;"><?= htmlspecialchars((string)($latestReceipt['number'] ?? 'Reçu VIGILANCE')) ?></div>
                <div style="display:flex;gap:6px;margin-top:8px;">
                    <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('registration-receipt.php?invoice_id=' . rawurlencode((string)($latestReceipt['id'] ?? '')))) ?>" target="_blank" style="font-size:.72rem;">Voir</a>
                    <a class="btn btn-primary"   href="<?= htmlspecialchars(vg_url('registration-receipt.php?invoice_id=' . rawurlencode((string)($latestReceipt['id'] ?? '')) . '&download=1')) ?>" style="font-size:.72rem;">Télécharger</a>
                </div>
            </div>
            <?php endif; ?>
        </article>
    </div>

    <?php endif; // end if $client ?>
</main>

<script>
// Auto-scroll chat
var chat = document.getElementById('cl-chat');
if (chat) chat.scrollTop = chat.scrollHeight;
window.VigilanceClientPortalConfig = <?= json_encode($clientPortalRealtimeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?= function_exists('vg_app_script_markup') ? vg_app_script_markup() : '' ?>
<?= function_exists('vg_portal_realtime_script_markup') ? vg_portal_realtime_script_markup() : '' ?>
<script src="<?= htmlspecialchars(vg_url('assets/js/client-portal-live.js?v=' . $clientPortalRealtimeAsset), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
