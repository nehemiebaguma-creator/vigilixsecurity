<?php

require __DIR__ . '/../includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

vg_require_role('client');

$user = vg_current_user();
$client = vg_portal_require_client($user);
$clientId = (string) ($client['id'] ?? '');
$cameras = vg_client_cameras($clientId);

$activeCameras = array_values(array_filter($cameras, static function (array $camera): bool {
    return strtolower((string) ($camera['status'] ?? '')) === 'active';
}));
$focusCamera = $cameras[0] ?? null;
$focusCameraStatus = (string) ($focusCamera['status'] ?? 'Aucune caméra');
$cameraNextAction = $focusCamera
    ? 'Vérifier le flux principal puis utiliser les commandes distantes seulement si nécessaire.'
    : 'Faire rattacher une caméra au dossier client pour obtenir une vue distante.';

vg_render_app_header('Mes cameras', $user, 'cameras.php');
?>
<style>
.ops-client-reading-strip{display:grid;gap:8px;margin:16px 0 0}
.ops-client-reading-step{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:start;padding:10px 12px;border-radius:16px;border:1px solid rgba(86,160,255,.14);background:rgba(8,16,29,.66);color:#d6ebff}
.ops-client-reading-step strong{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(99,214,255,.12);color:#d8f6ff;font-size:.76rem}
.ops-client-priority-badge{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(86,160,255,.16);background:rgba(8,16,29,.74);color:#d8edff;font-size:.7rem;letter-spacing:.14em;text-transform:uppercase}
.ops-client-focus-header{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
.ops-client-camera-lead{margin-bottom:18px;padding:16px 18px;border-radius:20px;border:1px solid rgba(95,170,255,.16);background:rgba(8,16,29,.68)}
.ops-client-camera-lead span{display:block;color:#83b7ee;font-size:.76rem;text-transform:uppercase;letter-spacing:.18em}
.ops-client-camera-lead strong{display:block;margin-top:6px;color:#fff;font-size:1.1rem}
.ops-client-camera-lead small{display:block;margin-top:6px;color:#9ebfe7;line-height:1.55}
.ops-client-camera-controls{display:grid;gap:12px;margin-top:16px}
.ops-client-camera-preview{overflow:hidden;border-radius:18px;border:1px solid rgba(95,170,255,.16);background:#06111d}
.ops-client-camera-preview img,.ops-client-camera-preview video{display:block;width:100%;min-height:220px;max-height:320px;object-fit:cover;background:#06111d}
.ops-client-camera-command-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.ops-client-camera-command-grid form,.ops-client-camera-command-grid a{margin:0}
.ops-client-camera-command-grid .btn{width:100%;justify-content:center}
.ops-client-camera-note{color:#9fc2ea;line-height:1.65;font-size:.92rem}
.ops-client-camera-badges{display:flex;gap:10px;flex-wrap:wrap}
.ops-client-camera-badge{padding:8px 12px;border-radius:999px;border:1px solid rgba(95,170,255,.16);background:rgba(7,17,29,.72);color:#dceeff;font-size:.8rem}
.ops-client-camera-hidden-frame{width:0;height:0;border:0;position:absolute;left:-9999px;top:-9999px}
@media (max-width: 980px){.ops-client-camera-command-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 680px){.ops-client-camera-command-grid{grid-template-columns:1fr}}
.ops-client-hls-player{border-radius:16px;overflow:hidden;background:#040b14;margin-top:14px}
.ops-client-hls-player video{display:block;width:100%;max-height:320px;background:#040b14}
.ops-client-hls-note{margin-top:8px;color:#7fb3e8;font-size:.8rem}
.ops-client-hls-note--warning{color:#ffcb80}
.ops-client-stream-tip{margin-top:10px;padding:12px 14px;border-radius:16px;border:1px solid rgba(255,178,76,.18);background:rgba(255,178,76,.08);color:#ffd9a0;font-size:.84rem;line-height:1.55}
</style>
<!-- hls.js pour streaming RTSP→HLS via MediaMTX -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
<section class="ops-client-camera-shell">
    <article class="ops-client-camera-hero">
        <p class="eyebrow">Surveillance vidéo</p>
        <h2>Mes caméras connectées</h2>
        <p>Retrouvez ici les caméras reliées à votre site, leur emplacement, leur statut et le lien de flux lorsque celui-ci est disponible.</p>
        <div class="ops-client-reading-strip">
            <div class="ops-client-reading-step"><strong>1</strong><div>Lire d abord le statut de la caméra et vérifier si le flux est réellement exploitable.</div></div>
            <div class="ops-client-reading-step"><strong>2</strong><div><?php echo vg_escape($cameraNextAction); ?></div></div>
        </div>
        <div class="ops-client-site-grid" style="margin-top:16px;">
            <div class="ops-client-site-card">
                <span>Total caméras</span>
                <strong><?php echo vg_escape((string) count($cameras)); ?></strong>
            </div>
            <div class="ops-client-site-card">
                <span>Actives</span>
                <strong><?php echo vg_escape((string) count($activeCameras)); ?></strong>
            </div>
        </div>
    </article>

    <section class="ops-client-camera-grid">
        <article class="ops-client-camera-card--visual" style="grid-column:1 / -1;">
            <div class="ops-client-focus-header">
                <strong>Caméra focale</strong>
                <div class="ops-client-priority-badge"><?php echo vg_escape($focusCameraStatus); ?></div>
            </div>
            <div class="ops-client-camera-lead">
                <span>Point principal</span>
                <strong><?php echo vg_escape((string) ($focusCamera['name'] ?? 'Aucune caméra affectée')); ?></strong>
                <small><?php echo vg_escape((string) ($focusCamera['location'] ?? 'Aucun emplacement disponible pour le moment.')); ?></small>
            </div>
        </article>
        <?php foreach ($cameras as $camera): ?>
            <?php $cameraPreview = function_exists('vg_camera_browser_preview') ? vg_camera_browser_preview($camera) : ['supported' => false, 'mode' => 'none', 'url' => '', 'message' => '']; ?>
            <?php $cameraControls = function_exists('vg_camera_ip_webcam_controls') ? vg_camera_ip_webcam_controls($camera) : ['supported' => false]; ?>
            <?php $cameraBridge = function_exists('vg_camera_mediamtx_stream') ? vg_camera_mediamtx_stream($camera) : ['enabled' => false, 'playable' => false, 'hls_url' => '', 'message' => '']; ?>
            <?php $cameraOpenTarget = function_exists('vg_camera_client_open_target') ? vg_camera_client_open_target($camera) : ['url' => '', 'label' => '', 'message' => '']; ?>
            <?php $cameraStreamLabel = function_exists('vg_camera_redact_url') ? vg_camera_redact_url((string) ($camera['stream_url'] ?? '')) : (string) ($camera['stream_url'] ?? ''); ?>
            <article class="ops-client-camera-card--visual">
                <span>Caméra client</span>
                <strong><?php echo vg_escape($camera['name']); ?></strong>
                <p><?php echo vg_escape($camera['location']); ?></p>
                <div class="ops-client-camera-meta">
                    <div><strong>Type:</strong> <?php echo vg_escape($camera['type']); ?></div>
                    <div><strong>Statut:</strong> <?php echo vg_escape($camera['status']); ?></div>
                    <div><strong>Flux:</strong> <?php echo vg_escape($cameraStreamLabel !== '' ? $cameraStreamLabel : 'Non renseigné'); ?></div>
                </div>
                <div class="ops-client-quicknav">
                    <?php if (!empty($cameraOpenTarget['url'])): ?>
                        <a class="btn btn-primary" href="<?php echo vg_escape((string) $cameraOpenTarget['url']); ?>" target="_blank" rel="noopener"><?php echo vg_escape((string) ($cameraOpenTarget['label'] ?? 'Ouvrir')); ?></a>
                    <?php endif; ?>
                    <span class="badge <?php echo 'badge-' . vg_status_badge($camera['status']); ?>"><?php echo vg_escape($camera['status']); ?></span>
                </div>
                <?php if (!empty($cameraBridge['playable'])): ?>
                    <?php
                        $clientVideoId = 'hls-cv-' . preg_replace('/[^a-z0-9]/', '', strtolower((string)($camera['id'] ?? uniqid())));
                        $clientStatusId = $clientVideoId . '-status';
                    ?>
                    <div class="ops-client-hls-player">
                        <video id="<?php echo htmlspecialchars($clientVideoId, ENT_QUOTES); ?>" controls autoplay muted playsinline
                               data-hls-src="<?php echo htmlspecialchars((string) ($cameraBridge['hls_url'] ?? ''), ENT_QUOTES); ?>"></video>
                    </div>
                    <div class="ops-client-hls-note" id="<?php echo htmlspecialchars($clientStatusId, ENT_QUOTES); ?>">Initialisation du direct navigateur via MediaMTX...</div>
                    <script>
                    (function(){
                        var v = document.getElementById('<?php echo htmlspecialchars($clientVideoId, ENT_QUOTES); ?>');
                        var note = document.getElementById('<?php echo htmlspecialchars($clientStatusId, ENT_QUOTES); ?>');
                        var wrap = v ? v.parentNode : null;
                        var src = v ? v.getAttribute('data-hls-src') : null;
                        if (!v || !src) return;
                        var markUnavailable = function(message) {
                            if (wrap) wrap.style.display = 'none';
                            if (note) {
                                note.textContent = message;
                                note.classList.add('ops-client-hls-note--warning');
                            }
                        };
                        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                            var hls = new Hls({
                                lowLatencyMode: true,
                                liveSyncDurationCount: 1,
                                liveMaxLatencyDurationCount: 2,
                                backBufferLength: 0,
                                maxBufferLength: 2,
                                maxMaxBufferLength: 3,
                                startFragPrefetch: true,
                                maxLiveSyncPlaybackRate: 1.5,
                                manifestLoadingTimeOut: 5000,
                                levelLoadingTimeOut: 5000,
                                fragLoadingTimeOut: 4000
                            });
                            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                                if (note) note.textContent = 'Direct actif via MediaMTX.';
                                v.play().catch(function(){});
                            });
                            hls.on(Hls.Events.ERROR, function(event, data) {
                                if (data && data.fatal) {
                                    try { hls.destroy(); } catch (e) {}
                                    markUnavailable('Le direct navigateur ne repond pas. L aperçu image reste disponible juste en dessous.');
                                }
                            });
                            hls.loadSource(src);
                            hls.attachMedia(v);
                        } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
                            v.src = src;
                            v.addEventListener('loadedmetadata', function() {
                                if (note) note.textContent = 'Direct actif via MediaMTX.';
                            }, { once: true });
                            v.addEventListener('error', function() {
                                markUnavailable('Le direct navigateur ne repond pas. L aperçu image reste disponible juste en dessous.');
                            }, { once: true });
                        } else {
                            markUnavailable('Ce navigateur ne peut pas lire le direct HLS. L aperçu image reste disponible.');
                        }
                    })();
                    </script>
                <?php elseif (!empty($cameraBridge['enabled'])): ?>
                    <div class="ops-client-stream-tip"><?php echo vg_escape((string) ($cameraBridge['message'] ?? 'Le direct navigateur n est pas disponible pour le moment.')); ?></div>
                <?php endif; ?>
                <?php if (!empty($cameraPreview['supported']) || !empty($cameraControls['supported'])): ?>
                    <div class="ops-client-camera-controls">
                        <?php if (!empty($cameraPreview['supported'])): ?>
                            <div class="ops-client-camera-preview">
                                <?php if (($cameraPreview['mode'] ?? '') === 'video'): ?>
                                    <video controls autoplay muted playsinline preload="metadata">
                                        <source src="<?php echo vg_escape((string) ($cameraPreview['url'] ?? '')); ?>">
                                    </video>
                                <?php else: ?>
                                    <img
                                        class="ops-client-live-preview"
                                        data-src="<?php echo vg_escape((string) ($cameraPreview['url'] ?? '')); ?>"
                                        data-refresh-ms="900"
                                        src="<?php echo vg_escape((string) ($cameraPreview['url'] ?? '')); ?><?php echo str_contains((string) ($cameraPreview['url'] ?? ''), '?') ? '&' : '?'; ?>t=<?php echo rawurlencode((string) time()); ?>"
                                        alt="Aperçu <?php echo vg_escape((string) ($camera['name'] ?? 'Caméra')); ?>"
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cameraControls['supported'])): ?>
                            <div class="ops-client-camera-badges">
                                <span class="ops-client-camera-badge">Commande distante active</span>
                                <span class="ops-client-camera-badge"><?php echo vg_escape((string) ($cameraControls['base_url'] ?? '')); ?></span>
                            </div>
                            <div class="ops-client-camera-command-grid">
                                <a class="btn btn-secondary" href="<?php echo vg_escape((string) ($cameraControls['advanced_url'] ?? '')); ?>" target="_blank" rel="noopener">Zoom / réglages</a>
                                <a class="btn btn-secondary" href="<?php echo vg_escape((string) ($cameraControls['video_url'] ?? '')); ?>" target="_blank" rel="noopener">Live vidéo</a>
                                <a class="btn btn-secondary" href="<?php echo vg_escape((string) ($cameraControls['photo_focus_url'] ?? '')); ?>" target="_blank" rel="noopener">Photo focus</a>
                                <?php foreach ((array) ($cameraControls['commands'] ?? []) as $command): ?>
                                    <form method="post" action="<?php echo vg_escape((string) ($command['url'] ?? '')); ?>" target="vg-client-camera-control-frame">
                                        <button class="btn btn-secondary" type="submit"><?php echo vg_escape((string) ($command['label'] ?? 'Commander')); ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                            <div class="ops-client-camera-note">Torche, focus et bascule avant/arrière sont envoyés directement au téléphone. Pour le zoom fin, ouvre le panneau IP Webcam natif.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if (empty($cameras)): ?>
            <article class="ops-client-empty">
                <strong>Aucune caméra liée</strong>
                <p>Le centre OPS ou l’administration doivent encore affecter les caméras à votre dossier client.</p>
            </article>
        <?php endif; ?>
    </section>
</section>
<iframe name="vg-client-camera-control-frame" class="ops-client-camera-hidden-frame" aria-hidden="true"></iframe>
<script>
(function () {
    function buildLiveUrl(baseSrc) {
        return baseSrc + (baseSrc.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
    }
    document.querySelectorAll('.ops-client-live-preview[data-src][data-refresh-ms]').forEach(function (node) {
        var src = String(node.getAttribute('data-src') || '').trim();
        var refreshMs = parseInt(node.getAttribute('data-refresh-ms') || '0', 10);
        if (!src || !refreshMs || Number.isNaN(refreshMs)) {
            return;
        }
        refreshMs = Math.max(2500, refreshMs);
        window.setInterval(function () {
            if (document.hidden || !node.isConnected) {
                return;
            }
            node.src = buildLiveUrl(src);
        }, refreshMs);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && node.isConnected) {
                node.src = buildLiveUrl(src);
            }
        });
    });
})();
</script>
<?php vg_render_app_footer(); ?>
