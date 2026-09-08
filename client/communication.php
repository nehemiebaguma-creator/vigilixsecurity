<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/app-url.php';
require_once __DIR__ . '/../includes/constants.php';

if (function_exists('vg_require_role')) {
    vg_require_role('client');
}

$sessionUser = $_SESSION['user'] ?? [];
$client = function_exists('vg_portal_require_client')
    ? vg_portal_require_client($sessionUser)
    : (function_exists('vgx_find_client_by_email')
        ? vgx_find_client_by_email((string)($sessionUser['email'] ?? ''))
        : null);

$clientId = (string)($client['id'] ?? '');
$flash = null;

// ── POST: send message ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client !== null) {
    $message = trim((string)($_POST['message'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? 'Message client'));
    $requestedUrgency = in_array($_POST['urgency'] ?? '', ['normal', 'urgent', 'sos', 'critique'], true) ? (string) $_POST['urgency'] : 'normal';
    $urgency = $requestedUrgency === 'sos' ? 'critique' : $requestedUrgency;
    if ($message !== '' && function_exists('vgx_send_communication')) {
        vgx_send_communication([
            'from_role'   => 'client',
            'from_id'     => $clientId,
            'to_role'     => 'admin',
            'to_id'       => 'control-center',
            'subject'     => $subject !== '' ? $subject : 'Message client',
            'message'     => $message,
            'client_id'   => $clientId,
            'client_name' => (string)($client['name'] ?? 'Client'),
            'urgency'     => $urgency,
        ]);
        $flash = ['type' => 'ok', 'text' => 'Message transmis au centre de contrôle.'];
    }
}

// ── Load messages ──────────────────────────────────────────────────────────
$allMessages = function_exists('vgx_list_communications')
    ? vgx_list_communications('client', $clientId)
    : [];

// Client portal only shows messages explicitly exchanged with the client.
$store = function_exists('vgx_store') ? vgx_store() : [];
$allComms = array_values(is_array($store['communications'] ?? null) ? $store['communications'] : []);
$thread = array_values(array_filter($allComms, static function ($m) use ($clientId): bool {
    if (!is_array($m)) return false;
    $fromRole = strtolower(trim((string) ($m['from_role'] ?? '')));
    $toRole = strtolower(trim((string) ($m['to_role'] ?? '')));
    $messageClientId = trim((string) ($m['client_id'] ?? ''));
    $fromClientId = trim((string) ($m['from_id'] ?? $messageClientId));
    $toClientId = trim((string) ($m['to_id'] ?? $messageClientId));
    $isFromClient = $fromRole === 'client' && $fromClientId === $clientId;
    $isToClient = $toRole === 'client' && ($toClientId === $clientId || $messageClientId === $clientId);

    return $isFromClient || $isToClient;
}));
usort($thread, fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));

// Assigned agent
$interventions = array_values(is_array($store['interventions'] ?? null) ? $store['interventions'] : []);
$assignedAgent = null;
foreach ($interventions as $iv) {
    if (!is_array($iv) || (string)($iv['client_id'] ?? '') !== $clientId) continue;
    $agentIds = function_exists('vgx_intervention_agent_ids')
        ? vgx_intervention_agent_ids($iv)
        : (array) ($iv['agent_ids'] ?? []);
    $agentId = (string) ($agentIds[0] ?? ($iv['agent_id'] ?? ''));
    if ($agentId !== '' && function_exists('vgx_find_agent_by_id')) {
        $ag = vgx_find_agent_by_id($agentId);
        if ($ag !== null) { $assignedAgent = $ag; break; }
    }
}
$clientPendingAckMessages = array_values(array_filter($thread, static function ($message) use ($clientId): bool {
    if (!is_array($message)) {
        return false;
    }

    $fromRole = strtolower(trim((string) ($message['from_role'] ?? '')));
    $toRole = strtolower(trim((string) ($message['to_role'] ?? '')));
    $messageClientId = trim((string) ($message['client_id'] ?? ''));
    $toId = trim((string) ($message['to_id'] ?? $messageClientId));

    return $fromRole !== 'client'
        && $toRole === 'client'
        && ($toId === $clientId || $messageClientId === $clientId)
        && !empty($message['requires_ack'])
        && strtolower((string) ($message['ack_status'] ?? 'pending')) !== 'acknowledged';
}));
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
    'initialMessages' => array_values($thread),
    'initialPendingAckCount' => count($clientPendingAckMessages),
];
$clientPortalRealtimeAsset = (int) (@filemtime(__DIR__ . '/../assets/js/client-portal-live.js') ?: 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie — <?= htmlspecialchars((string)($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?> | VIGILANCE</title>
    <link rel="stylesheet" href="<?= function_exists('vg_url') ? htmlspecialchars(vg_url('assets/css/app.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8') : '../assets/css/app.css?v=20260711-auditbrand2' ?>">
    <style>
        *{box-sizing:border-box;}
        :root{--bg:#040c18;--panel:#071526;--border:rgba(78,180,255,.13);--text:#d4eeff;--muted:#6a8aaa}
        html,body{margin:0;padding:0;color:var(--text);font:14px/1.6 'Segoe UI',system-ui,sans-serif;height:100%;}
        html{background-color:var(--bg);}
        body{background-color:var(--bg)!important;}
        .comm-flag{height:5px;background:linear-gradient(90deg,#007fff 0%,#007fff 33.3%,#f7d618 33.3%,#f7d618 66.6%,#ce1126 66.6%,#ce1126 100%);}
        .comm-shell{display:flex;flex-direction:column;height:calc(100vh - 5px);}
        /* Header */
        .comm-header{display:flex;align-items:center;justify-content:space-between;padding:12px 22px;background:rgba(7,21,38,.97);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap;gap:10px;}
        .comm-title{display:flex;align-items:center;gap:10px;}
        .comm-title-text strong{font-size:.95rem;color:#f4fbff;}
        .comm-title-text span{font-size:.72rem;color:var(--muted);}
        .comm-online-dot{width:8px;height:8px;border-radius:50%;background:#00e676;box-shadow:0 0 8px #00e67680;animation:blink 2s infinite;flex-shrink:0;}
        @keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
        /* Content area */
        .comm-body{display:flex;flex:1;overflow:hidden;gap:0;}
        /* Sidebar */
        .comm-sidebar{width:260px;flex-shrink:0;background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow-y:auto;}
        .comm-sidebar-section{padding:14px 16px;}
        .comm-sidebar-title{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px;}
        .comm-info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.78rem;}
        .comm-info-row:last-child{border-bottom:none;}
        .comm-info-label{color:var(--muted);}
        .comm-info-val{color:#d4eeff;font-weight:600;}
        .comm-agent-card{background:rgba(4,11,22,.7);border:1px solid rgba(78,180,255,.12);border-radius:10px;padding:10px 12px;margin-top:4px;}
        .comm-agent-name{font-size:.85rem;font-weight:600;color:#f4fbff;}
        .comm-agent-meta{font-size:.72rem;color:var(--muted);margin-top:2px;}
        /* Chat main */
        .comm-chat-area{flex:1;display:flex;flex-direction:column;overflow:hidden;}
        .comm-thread{flex:1;overflow-y:auto;padding:18px 22px;display:flex;flex-direction:column;gap:10px;}
        .comm-day-sep{text-align:center;font-size:.65rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin:8px 0;display:flex;align-items:center;gap:10px;}
        .comm-day-sep::before,.comm-day-sep::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.06);}
        /* Bubbles */
        .comm-bubble{display:flex;flex-direction:column;max-width:74%;}
        .comm-bubble.right{align-self:flex-end;align-items:flex-end;}
        .comm-bubble.left{align-self:flex-start;align-items:flex-start;}
        .comm-bubble-sender{font-size:.63rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
        .comm-bubble-body{padding:10px 14px;border-radius:14px;font-size:.83rem;line-height:1.55;word-break:break-word;}
        .comm-bubble.right .comm-bubble-body{background:rgba(0,180,100,.1);border:1px solid rgba(0,230,118,.2);color:#c0f4dc;border-bottom-right-radius:4px;}
        .comm-bubble.left  .comm-bubble-body{background:rgba(78,180,255,.09);border:1px solid rgba(78,180,255,.2);color:#d0e8ff;border-bottom-left-radius:4px;}
        .comm-bubble.left.from-agent .comm-bubble-body{background:rgba(255,214,0,.07);border-color:rgba(255,214,0,.2);color:#f4e8a0;}
        .comm-bubble-meta{font-size:.62rem;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:6px;}
        .comm-bubble-audio{width:100%;margin-top:10px;border-radius:12px;display:block}
        .comm-urgency-sos{background:rgba(255,34,68,.1);border-color:rgba(255,34,68,.3)!important;color:#ffb0c0!important;}
        .comm-urgency-urgent{border-color:rgba(255,124,30,.35)!important;}
        .comm-urgency-badge{font-size:.6rem;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;}
        .urg-sos{background:rgba(255,34,68,.2);color:#ff6680;}
        .urg-urgent{background:rgba(255,124,30,.2);color:#ff9f3c;}
        /* Compose */
        .comm-compose-area{background:var(--panel);border-top:1px solid var(--border);padding:14px 20px;flex-shrink:0;}
        .comm-compose-row{display:flex;gap:10px;align-items:flex-end;}
        .comm-compose-fields{flex:1;display:flex;flex-direction:column;gap:6px;}
        .comm-compose-subrow{display:flex;gap:8px;align-items:center;}
        .comm-subject-input{background:rgba(4,11,22,.92);border:1px solid var(--border);border-radius:8px;color:#f4fbff;font:inherit;font-size:.78rem;padding:5px 10px;flex:1;}
        .comm-subject-input:focus{outline:none;border-color:rgba(78,180,255,.35);}
        .comm-select{background:rgba(4,11,22,.92);border:1px solid var(--border);border-radius:8px;color:#a0b8d0;font:inherit;font-size:.75rem;padding:5px 10px;}
        .comm-textarea{background:rgba(4,11,22,.92);border:1px solid var(--border);border-radius:12px;color:#f4fbff;font:inherit;font-size:.83rem;padding:10px 14px;resize:none;height:72px;flex:1;}
        .comm-textarea:focus{outline:none;border-color:rgba(78,180,255,.35);}
        .comm-send-btn{padding:10px 20px;border-radius:12px;font-size:.82rem;font-weight:600;border:none;background:linear-gradient(135deg,#0080d0,#39c7ff);color:#fff;cursor:pointer;white-space:nowrap;flex-shrink:0;}
        .comm-send-btn:hover{background:linear-gradient(135deg,#0090e8,#5ad2ff);}
        /* Flash */
        .comm-flash{padding:8px 16px;border-radius:10px;font-size:.8rem;font-weight:600;margin:10px 20px 0;}
        .comm-flash.ok{background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.25);color:#2ed39f;}
        /* Empty */
        .comm-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);font-size:.85rem;gap:8px;}
        @media(max-width:720px){.comm-sidebar{display:none}.comm-body{flex-direction:column}}
    </style>
</head>
<body>
<div class="comm-flag"></div>
<div class="comm-shell">

    <!-- HEADER -->
    <header class="comm-header">
        <div class="comm-title">
            <div class="comm-online-dot"></div>
            <div>
                <img src="<?= function_exists('vg_url') ? htmlspecialchars(vg_url('brand-media.php?v=20260715-official-logo'), ENT_QUOTES, 'UTF-8') : '../brand-media.php?v=20260715-official-logo' ?>" alt="VIGILANCE Security" style="height:28px;vertical-align:middle;margin-right:8px;">
            </div>
            <div class="comm-title-text">
                <strong>Canal securise — Centre de controle VIGILANCE</strong>
                <span><?= htmlspecialchars((string)($client['name'] ?? 'Client')) ?> · <?= htmlspecialchars((string)($client['commune'] ?? 'Kinshasa')) ?></span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('client/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;">← Retour portail</a>
            <a class="btn btn-secondary" href="<?= function_exists('vg_url') ? htmlspecialchars(vg_url('auth/logout.php'), ENT_QUOTES, 'UTF-8') : '../auth/logout.php' ?>" style="font-size:.75rem;">Déconnexion</a>
        </div>
    </header>

    <div class="comm-body">

        <!-- SIDEBAR -->
        <aside class="comm-sidebar">
            <div class="comm-sidebar-section">
                <div class="comm-sidebar-title">Mon compte</div>
                <div class="comm-info-row"><span class="comm-info-label">Nom</span><span class="comm-info-val"><?= htmlspecialchars((string)($client['name'] ?? '—')) ?></span></div>
                <div class="comm-info-row"><span class="comm-info-label">Abonnement</span><span class="comm-info-val"><?= htmlspecialchars((string)($client['subscription'] ?? '—')) ?></span></div>
                <div class="comm-info-row"><span class="comm-info-label">Commune</span><span class="comm-info-val"><?= htmlspecialchars((string)($client['commune'] ?? '—')) ?></span></div>
                <div class="comm-info-row"><span class="comm-info-label">Messages</span><span class="comm-info-val"><?= count($thread) ?></span></div>
            </div>

            <?php if ($assignedAgent): ?>
            <div class="comm-sidebar-section" style="border-top:1px solid rgba(255,255,255,.05);">
                <div class="comm-sidebar-title">Agent assigné</div>
                <div class="comm-agent-card">
                    <div class="comm-agent-name"><?= htmlspecialchars((string)($assignedAgent['name'] ?? 'Agent VIGILANCE')) ?></div>
                    <div class="comm-agent-meta"><?= htmlspecialchars((string)($assignedAgent['role_label'] ?? 'Agent terrain')) ?><br><?= htmlspecialchars((string)($assignedAgent['zone'] ?? 'Kinshasa')) ?></div>
                    <?php if (!empty($assignedAgent['phone'])): ?>
                        <a class="btn btn-secondary" href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', (string)$assignedAgent['phone'])) ?>" style="font-size:.7rem;margin-top:8px;display:inline-block;">📞 Appeler l'agent</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="comm-sidebar-section" style="border-top:1px solid rgba(255,255,255,.05);">
                <div class="comm-sidebar-title">Contact d'urgence</div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-live-call-target="control-center"
                        data-live-call-role="admin"
                        data-live-call-name="Centre OPS VIGILANCE"
                        style="font-size:.72rem;"
                    >Appel live OPS</button>
                    <?php if ($assignedAgent && !empty($assignedAgent['id'])): ?>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-live-call-target="<?= htmlspecialchars((string) ($assignedAgent['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-live-call-role="agent"
                        data-live-call-name="<?= htmlspecialchars((string) ($assignedAgent['name'] ?? 'Agent VIGILANCE'), ENT_QUOTES, 'UTF-8') ?>"
                        style="font-size:.72rem;"
                    >Appel live agent</button>
                    <?php endif; ?>
                    <a class="btn btn-secondary" href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', (string)(function_exists('vg_app') ? vg_app('whatsapp_number') ?? '243819174732' : '243819174732'))) ?>" style="font-size:.72rem;">📞 Appel direct</a>
                    <?php if (function_exists('vg_whatsapp_url')): ?>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_whatsapp_url((string)(vg_app('whatsapp_number') ?? '243819174732'), 'Bonjour VIGILANCE, je vous contacte depuis mon espace abonne.'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="font-size:.72rem;">💬 WhatsApp centre</a>
                    <?php endif; ?>
                </div>
                <div style="margin-top:12px;font-size:.7rem;color:var(--muted);line-height:1.5;">
                    Pour les urgences immédiates, utilisez le bouton 🚨 Alarme dans votre <a href="<?= htmlspecialchars(vg_url('client/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" style="color:#4eb4ff;">portail</a>.
                </div>
            </div>

            <div class="comm-sidebar-section" style="border-top:1px solid rgba(255,255,255,.05);">
                <div class="comm-sidebar-title">Légende</div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:.72rem;">
                    <div style="display:flex;gap:8px;align-items:center;"><div style="width:12px;height:12px;border-radius:3px;background:rgba(0,230,118,.2);border:1px solid rgba(0,230,118,.3);flex-shrink:0;"></div> Mes messages</div>
                    <div style="display:flex;gap:8px;align-items:center;"><div style="width:12px;height:12px;border-radius:3px;background:rgba(78,180,255,.2);border:1px solid rgba(78,180,255,.3);flex-shrink:0;"></div> Centre OPS</div>
                    <div style="display:flex;gap:8px;align-items:center;"><div style="width:12px;height:12px;border-radius:3px;background:rgba(255,214,0,.15);border:1px solid rgba(255,214,0,.25);flex-shrink:0;"></div> Agent terrain</div>
                </div>
            </div>
        </aside>

        <!-- CHAT AREA -->
        <div class="comm-chat-area">
            <?php if ($flash): ?>
                <div class="comm-flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['text']) ?></div>
            <?php endif; ?>

            <div class="comm-thread" id="comm-thread">
                <?php if (!$thread): ?>
                    <div class="comm-empty">
                        <div style="font-size:2.5rem;">💬</div>
                        <div>Aucun échange pour le moment</div>
                        <div style="font-size:.78rem;color:var(--muted);">Utilisez le formulaire ci-dessous pour écrire au centre de contrôle.</div>
                    </div>
                <?php else: ?>
                    <?php
                    $prevDay = '';
                    foreach ($thread as $msg):
                        $isClient    = ($msg['from_role'] ?? '') === 'client';
                        $isAgent     = ($msg['from_role'] ?? '') === 'agent';
                        $side        = $isClient ? 'right' : 'left';
                        $fromClass   = !$isClient && $isAgent ? 'from-agent' : '';
                        $urgency     = (string)($msg['urgency'] ?? 'normal');
                        $urgencyBody = $urgency === 'sos' ? 'comm-urgency-sos' : ($urgency === 'urgent' ? 'comm-urgency-urgent' : '');
                        $senderLabel = $isClient ? 'Vous' : ($isAgent ? ('Agent · ' . ($msg['agent_name'] ?? 'Agent terrain')) : 'Centre OPS VIGILANCE');
                        $msgDay      = substr((string)($msg['created_at'] ?? ''), 0, 10);
                        $msgTime     = substr((string)($msg['created_at'] ?? ''), 0, 16);
                        $audioUrl    = trim((string) ($msg['audio_url'] ?? ''));
                        $requiresAck = !$isClient && !empty($msg['requires_ack']);
                        $ackStatus   = strtolower((string) ($msg['ack_status'] ?? ($requiresAck ? 'pending' : 'not_required')));
                        $ackPending  = $requiresAck && $ackStatus !== 'acknowledged';
                    ?>
                    <?php if ($msgDay !== $prevDay): $prevDay = $msgDay; ?>
                        <div class="comm-day-sep"><?= htmlspecialchars($msgDay) ?></div>
                    <?php endif; ?>
                    <div class="comm-bubble <?= $side ?> <?= $fromClass ?>" data-message-id="<?= htmlspecialchars((string) ($msg['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-ack-status="<?= htmlspecialchars((string) ($msg['ack_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="comm-bubble-sender"><?= htmlspecialchars($senderLabel) ?></div>
                        <div class="comm-bubble-body <?= $urgencyBody ?>">
                            <?php if (!empty($msg['subject']) && !in_array((string) $msg['subject'], ['Message VIGILANCE', 'Message VIGILANCE', 'Message client', 'Message abonne'], true)): ?>
                                <div style="font-weight:700;font-size:.78rem;margin-bottom:5px;opacity:.8;"><?= htmlspecialchars($msg['subject']) ?></div>
                            <?php endif; ?>
                            <?= nl2br(htmlspecialchars((string)($msg['message'] ?? $msg['content'] ?? ''))) ?>
                            <?php if ($audioUrl !== ''): ?>
                                <audio class="comm-bubble-audio" controls preload="none" src="<?= htmlspecialchars($audioUrl, ENT_QUOTES, 'UTF-8') ?>"></audio>
                            <?php endif; ?>
                        </div>
                        <div class="comm-bubble-meta">
                            <span><?= htmlspecialchars($msgTime) ?></span>
                            <?php if ($urgency === 'sos'): ?>
                                <span class="comm-urgency-badge urg-sos">SOS</span>
                            <?php elseif ($urgency === 'urgent'): ?>
                                <span class="comm-urgency-badge urg-urgent">Urgent</span>
                            <?php endif; ?>
                            <?php if ($requiresAck): ?>
                                <span data-ack-badge class="comm-urgency-badge" style="background:<?= $ackPending ? 'rgba(255,191,102,.18)' : 'rgba(89,216,160,.18)' ?>;color:<?= $ackPending ? '#ffe0b0' : '#bff5dc' ?>;"><?= $ackPending ? 'ACK attendu' : 'ACK confirme' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ackPending): ?>
                            <div style="margin-top:8px;">
                                <button type="button" class="btn btn-secondary" style="font-size:.72rem;padding:5px 12px;" data-client-ack="<?= htmlspecialchars((string) ($msg['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Marquer lu</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- COMPOSE -->
            <form method="post" class="comm-compose-area">
                <div class="comm-compose-row">
                    <div class="comm-compose-fields">
                        <div class="comm-compose-subrow">
                            <input type="text" name="subject" placeholder="Sujet (ex: caméra hors ligne, suivi mission...)" class="comm-subject-input" maxlength="120">
                            <select name="urgency" class="comm-select">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="sos">🔴 SOS</option>
                            </select>
                        </div>
                        <textarea name="message" class="comm-textarea" placeholder="Écrire au centre de contrôle... Expliquez clairement votre besoin : caméra hors ligne, vérification de site, suivi de mission, demande d'information." required></textarea>
                    </div>
                    <button type="submit" class="comm-send-btn">Envoyer →</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Scroll thread to bottom on load
var thread = document.getElementById('comm-thread');
if (thread) thread.scrollTop = thread.scrollHeight;

// Auto-resize textarea
var ta = document.querySelector('.comm-textarea');
if (ta) {
    ta.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });
}
window.VigilanceClientPortalConfig = <?= json_encode($clientPortalRealtimeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?= function_exists('vg_app_script_markup') ? vg_app_script_markup() : '' ?>
<?= function_exists('vg_portal_realtime_script_markup') ? vg_portal_realtime_script_markup() : '' ?>
<script src="<?= htmlspecialchars(vg_url('assets/js/client-portal-live.js?v=' . $clientPortalRealtimeAsset), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
