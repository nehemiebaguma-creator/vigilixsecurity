<?php

require __DIR__ . '/../includes/bootstrap.php';
vg_require_role('client');

$user = vg_current_user();
$client = vg_portal_require_client($user);
$clientId = (string) ($client['id'] ?? '');

if (vg_is_post()) {
    vg_create_alert(array(
        'client_id' => $clientId,
        'type' => $_POST['type'],
        'severity' => $_POST['severity'],
        'message' => $_POST['message'],
        'latitude' => $client['latitude'],
        'longitude' => $client['longitude'],
    ));
    vg_flash('success', 'Votre alerte a ete transmise au centre de controle.');
    vg_redirect('client/alerts.php');
}

$alerts = vg_client_alerts($clientId);
$criticalAlerts = array_values(array_filter($alerts, static function (array $alert): bool {
    $status = strtolower((string) ($alert['status'] ?? ''));
    $type = strtolower((string) ($alert['type'] ?? ''));
    return $type === 'sos' || $status === 'critique';
}));
$latestAlert = $alerts !== [] ? $alerts[0] : null;
$latestAlertStatus = (string) ($latestAlert['status'] ?? 'Aucune alerte');
$alertNextAction = $latestAlert
    ? 'Suivre le statut de l alerte puis garder le téléphone disponible pour le centre OPS.'
    : 'Déclencher uniquement en cas de situation réelle pour garder un flux clair au centre.';

vg_render_app_header('Mes alertes', $user, 'alerts.php');
?>
<style>
.ops-client-reading-strip{display:grid;gap:8px;margin:16px 0 0}
.ops-client-reading-step{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:start;padding:10px 12px;border-radius:16px;border:1px solid rgba(86,160,255,.14);background:rgba(8,16,29,.66);color:#d6ebff}
.ops-client-reading-step strong{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(255,92,113,.12);color:#ffd9de;font-size:.76rem}
.ops-client-priority-badge{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(86,160,255,.16);background:rgba(8,16,29,.74);color:#d8edff;font-size:.7rem;letter-spacing:.14em;text-transform:uppercase}
.ops-client-focus-header{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
.ops-client-quickfacts{display:grid;gap:10px;margin-top:14px}
.ops-client-quickfact{padding:12px 14px;border-radius:18px;border:1px solid rgba(86,160,255,.14);background:rgba(8,16,29,.66)}
.ops-client-quickfact span{display:block;color:#83b7ee;font-size:.76rem;text-transform:uppercase;letter-spacing:.18em}
.ops-client-quickfact strong{display:block;margin-top:6px;color:#fff}
.ops-client-quickfact small{display:block;margin-top:5px;color:#9ebfe7;line-height:1.55}
</style>
<section class="ops-client-alert-shell">
    <article class="ops-client-alert-hero">
        <p class="eyebrow">Gestion des alertes</p>
        <h2>Déclencher et suivre mes alertes</h2>
        <p>Le centre reçoit ici le type d’alerte, votre message, le site concerné, la position GPS et le contexte du dossier client pour accélérer la décision.</p>
        <div class="ops-client-reading-strip">
            <div class="ops-client-reading-step"><strong>1</strong><div>Déclencher l alerte avec un message court et clair sur la situation réelle.</div></div>
            <div class="ops-client-reading-step"><strong>2</strong><div><?php echo vg_escape($alertNextAction); ?></div></div>
        </div>
        <div class="ops-client-alert-stats" style="margin-top:16px;">
            <div class="ops-client-alert-stat"><span>Total alertes</span><strong><?php echo vg_escape((string) count($alerts)); ?></strong></div>
            <div class="ops-client-alert-stat"><span>Critiques / SOS</span><strong><?php echo vg_escape((string) count($criticalAlerts)); ?></strong></div>
            <div class="ops-client-alert-stat"><span>Site GPS</span><strong><?php echo vg_escape((string) (($client['latitude'] ?? '--') . ' / ' . ($client['longitude'] ?? '--'))); ?></strong></div>
        </div>
    </article>

    <section class="ops-client-alert-layout">
        <article class="ops-client-alert-card">
            <h3>Déclencher une alerte</h3>
            <p>Utilisez ce formulaire quand il faut remonter rapidement une situation au centre de contrôle.</p>
            <form method="post">
                <label>Type
                    <select name="type">
                        <option>SOS</option>
                        <option>Intrusion</option>
                        <option>Panique</option>
                        <option>Mouvement</option>
                    </select>
                </label>
                <label>Niveau de danger
                    <select name="severity">
                        <option>Critique</option>
                        <option>Elevee</option>
                        <option>Normale</option>
                    </select>
                </label>
                <label>Message
                    <textarea name="message" required placeholder="Décrivez rapidement la situation"></textarea>
                </label>
                <button class="btn btn-danger" type="submit">Déclencher l’alerte</button>
            </form>
        </article>

        <article class="ops-client-alert-card">
            <div class="ops-client-focus-header">
                <h3>Historique récent</h3>
                <div class="ops-client-priority-badge"><?php echo vg_escape($latestAlertStatus); ?></div>
            </div>
            <div class="ops-client-quickfacts">
                <div class="ops-client-quickfact">
                    <span>Dernière alerte</span>
                    <strong><?php echo vg_escape((string) ($latestAlert['type'] ?? 'Aucune alerte')); ?></strong>
                    <small><?php echo vg_escape((string) ($latestAlert['message'] ?? 'Aucun message pour le moment.')); ?></small>
                </div>
            </div>
            <div class="ops-client-alert-list">
                <?php foreach (array_slice(array_reverse($alerts), 0, 8) as $alert): ?>
                    <?php
                    $alertVideoPath = trim((string) (($alert['incident_video_relative_path'] ?? '') ?: ($alert['video_path'] ?? '')));
                    $alertVideoUrl = trim((string) ($alert['video_url'] ?? ''));
                    if ($alertVideoUrl === '' && $alertVideoPath !== '' && function_exists('vg_url')) {
                        $alertVideoUrl = vg_url($alertVideoPath);
                    }
                    ?>
                    <div class="ops-client-alert-item">
                        <strong><?php echo vg_escape(($alert['type'] ?? 'Alerte') . ' • ' . ($alert['status'] ?? 'Nouvelle')); ?></strong>
                        <p><?php echo vg_escape((string) ($alert['message'] ?? '')); ?></p>
                        <p style="margin-top:8px;"><?php echo vg_escape((string) ($alert['created_at'] ?? '')); ?></p>
                        <?php if ($alertVideoUrl !== ''): ?>
                            <p style="margin-top:10px;"><a class="btn" href="<?php echo vg_escape($alertVideoUrl); ?>" target="_blank" rel="noopener">Voir vidéo</a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($alerts)): ?>
                    <div class="ops-client-alert-item">
                        <strong>Aucune alerte pour le moment</strong>
                        <p>Les alertes déclenchées depuis votre portail apparaîtront ici.</p>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>
</section>
<?php vg_render_app_footer(); ?>
