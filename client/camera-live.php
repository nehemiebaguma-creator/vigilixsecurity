<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

vg_require_role('client');

$user = vg_current_user();
$client = vg_portal_require_client($user);
$clientId = (string) ($client['id'] ?? '');
$cameraId = trim((string) ($_GET['camera_id'] ?? ''));
$camera = null;

foreach (vg_client_cameras($clientId) as $cameraRow) {
    if (is_array($cameraRow) && (string) ($cameraRow['id'] ?? '') === $cameraId) {
        $camera = $cameraRow;
        break;
    }
}

if (!is_array($camera)) {
    vg_flash('error', 'Camera introuvable ou non accessible dans votre portail.');
    vg_redirect('client/cameras.php');
}

$bridge = function_exists('vg_camera_mediamtx_stream')
    ? vg_camera_mediamtx_stream($camera)
    : ['enabled' => false, 'available' => false, 'playable' => false, 'hls_url' => '', 'message' => ''];
$preview = function_exists('vg_camera_browser_preview')
    ? vg_camera_browser_preview($camera)
    : ['supported' => false, 'mode' => 'none', 'url' => '', 'message' => ''];
$statusUrl = function_exists('vg_url')
    ? vg_url('api/camera_live_status.php?camera_id=' . rawurlencode((string) ($camera['id'] ?? '')))
    : '';
$streamLabel = function_exists('vg_camera_redact_url')
    ? vg_camera_redact_url((string) ($camera['stream_url'] ?? ''))
    : (string) ($camera['stream_url'] ?? '');
$prefersRealtime = !empty($bridge['webrtc_playable']) && trim((string) ($bridge['webrtc_url'] ?? '')) !== '';
$prefersHls = !$prefersRealtime && !empty($bridge['playable']) && trim((string) ($bridge['hls_url'] ?? '')) !== '';

vg_render_app_header('Direct camera', $user, 'cameras.php');
?>
<style>
.vg-live-shell{display:grid;gap:18px}
.vg-live-card,.vg-live-aside{background:linear-gradient(180deg,rgba(13,26,46,.96),rgba(8,18,33,.96));border:1px solid rgba(79,144,255,.18);border-radius:26px;box-shadow:0 18px 44px rgba(0,0,0,.22)}
.vg-live-card{padding:24px}
.vg-live-aside{padding:20px}
.vg-live-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.vg-live-head h2{margin:4px 0 6px}
.vg-live-eyebrow{font-size:.78rem;letter-spacing:.16em;text-transform:uppercase;color:#7fc4ff}
.vg-live-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:18px}
.vg-live-player{margin-top:18px;border-radius:22px;overflow:hidden;background:#030b13;border:1px solid rgba(95,170,255,.16);min-height:360px;display:grid;place-items:center;position:relative}
.vg-live-player video,.vg-live-player img,.vg-live-player iframe{display:block;width:100%;height:100%;min-height:360px;object-fit:cover;background:#030b13}
.vg-live-player iframe{border:0}
.vg-live-overlay{position:absolute;inset:auto 16px 16px 16px;display:flex;gap:10px;flex-wrap:wrap}
.vg-live-pill{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:999px;background:rgba(4,13,24,.82);border:1px solid rgba(95,170,255,.18);color:#deefff;font-size:.82rem}
.vg-live-pill--button{cursor:pointer}
.vg-live-status{margin-top:14px;padding:14px 16px;border-radius:18px;background:rgba(8,16,29,.7);border:1px solid rgba(95,170,255,.16);color:#d7ecff;line-height:1.6}
.vg-live-status strong{display:block;margin-bottom:6px}
.vg-live-status--warn{border-color:rgba(255,178,76,.24);background:rgba(255,178,76,.08);color:#ffdca6}
.vg-live-status--error{border-color:rgba(255,114,89,.24);background:rgba(255,114,89,.08);color:#ffc5bb}
.vg-live-facts{display:grid;gap:12px;margin-top:16px}
.vg-live-facts div{padding:14px 16px;border-radius:18px;background:rgba(8,16,29,.68);border:1px solid rgba(95,170,255,.14)}
.vg-live-facts span{display:block;color:#8fb8e4;font-size:.76rem;text-transform:uppercase;letter-spacing:.12em}
.vg-live-facts strong{display:block;margin-top:6px;color:#f4faff}
.vg-live-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}
.vg-live-note{color:#9cc5ef;font-size:.9rem;line-height:1.65}
@media (max-width: 980px){.vg-live-grid{grid-template-columns:1fr}.vg-live-player video,.vg-live-player img{min-height:280px}}
</style>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>

<section class="vg-live-shell">
    <div class="vg-live-grid">
        <article class="vg-live-card">
            <div class="vg-live-head">
                <div>
                    <div class="vg-live-eyebrow">Lecture protegee VIGILANCE</div>
                    <h2><?= vg_escape((string) ($camera['name'] ?? 'Camera VIGILANCE')); ?></h2>
                    <p class="vg-live-note"><?= vg_escape((string) ($camera['location'] ?? 'Emplacement non renseigne')); ?></p>
                </div>
                <span class="badge <?= 'badge-' . vg_status_badge((string) ($camera['status'] ?? '')) ?>"><?= vg_escape((string) ($camera['status'] ?? '')) ?></span>
            </div>

            <div class="vg-live-player" id="live-player-shell">
                <?php if (!empty($preview['supported']) && ($preview['mode'] ?? '') !== 'video' && !empty($preview['url'])): ?>
                    <img id="live-fallback-image" src="<?= vg_escape((string) $preview['url']) ?>" alt="Apercu camera <?= vg_escape((string) ($camera['name'] ?? '')) ?>">
                <?php else: ?>
                    <div id="live-placeholder" class="vg-live-note">Connexion au direct en cours...</div>
                <?php endif; ?>
                <iframe
                    id="live-webrtc-frame"
                    allow="autoplay; fullscreen; picture-in-picture"
                    referrerpolicy="no-referrer"
                    style="<?= $prefersRealtime ? '' : 'display:none;' ?>"
                ></iframe>
                <video
                    id="live-video"
                    controls
                    autoplay
                    muted
                    playsinline
                    style="<?= $prefersHls ? '' : 'display:none;' ?>"
                ></video>
                <div class="vg-live-overlay">
                    <span class="vg-live-pill" id="live-mode-pill"><?= $prefersRealtime ? 'Direct WebRTC temps reel' : ($prefersHls ? 'Direct HLS faible latence' : 'Apercu securise') ?></span>
                    <span class="vg-live-pill" id="live-refresh-pill">Auto reprise VIGILANCE</span>
                    <button class="vg-live-pill vg-live-pill--button" id="live-zoom-in" type="button">Zoom +</button>
                    <button class="vg-live-pill vg-live-pill--button" id="live-zoom-out" type="button">Zoom -</button>
                    <button class="vg-live-pill vg-live-pill--button" id="live-zoom-reset" type="button">Reset</button>
                </div>
            </div>

            <div class="vg-live-status <?= ($prefersRealtime || $prefersHls) ? '' : 'vg-live-status--warn' ?>" id="live-status-box">
                <strong id="live-status-title"><?= ($prefersRealtime || $prefersHls) ? 'Direct disponible' : 'Attente du direct' ?></strong>
                <span id="live-status-message"><?= vg_escape((string) ($bridge['message'] ?? 'Le direct sera relance automatiquement.')) ?></span>
            </div>

            <div class="vg-live-actions">
                <a class="btn btn-secondary" href="<?= vg_escape(vg_url('client/cameras.php')) ?>">Retour aux caméras</a>
                <?php if (!empty($preview['supported']) && !empty($preview['url'])): ?>
                    <a class="btn btn-secondary" href="<?= vg_escape((string) $preview['url']) ?>" target="_blank" rel="noopener">Ouvrir l apercu</a>
                <?php endif; ?>
            </div>
        </article>

        <aside class="vg-live-aside">
            <div class="vg-live-eyebrow">Etat technique</div>
            <div class="vg-live-facts">
                <div>
                    <span>Type</span>
                    <strong><?= vg_escape((string) ($camera['type'] ?? '')) ?></strong>
                </div>
                <div>
                    <span>Flux</span>
                    <strong id="live-stream-label"><?= vg_escape($streamLabel) ?></strong>
                </div>
                <div>
                    <span>Mode navigateur</span>
                    <strong id="live-bridge-flag"><?= $prefersRealtime ? 'WebRTC temps reel' : (!empty($bridge['enabled']) ? 'Pont RTSP vers HLS' : 'Apercu direct') ?></strong>
                </div>
                <div>
                    <span>Observation</span>
                    <strong>VIGILANCE vise d abord le temps reel WebRTC, puis bascule sur HLS faible latence, puis snapshot seulement en dernier recours.</strong>
                </div>
            </div>
        </aside>
    </div>
</section>

<script>
(function() {
    var statusUrl = <?= json_encode($statusUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var initialBridge = <?= json_encode($bridge, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var initialPreview = <?= json_encode($preview, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var video = document.getElementById('live-video');
    var webrtcFrame = document.getElementById('live-webrtc-frame');
    var fallbackImage = document.getElementById('live-fallback-image');
    var placeholder = document.getElementById('live-placeholder');
    var title = document.getElementById('live-status-title');
    var message = document.getElementById('live-status-message');
    var box = document.getElementById('live-status-box');
    var modePill = document.getElementById('live-mode-pill');
    var streamLabel = document.getElementById('live-stream-label');
    var bridgeFlag = document.getElementById('live-bridge-flag');
    var zoomInButton = document.getElementById('live-zoom-in');
    var zoomOutButton = document.getElementById('live-zoom-out');
    var zoomResetButton = document.getElementById('live-zoom-reset');
    var hlsInstance = null;
    var fallbackTimer = null;
    var currentHlsUrl = '';
    var currentWebRtcUrl = '';
    var currentFallbackUrl = '';
    var zoomLevel = 1;
    var fallbackRefreshMs = 2500;
    var statusPollInFlight = false;

    function applyZoom() {
        [video, fallbackImage, webrtcFrame].forEach(function (node) {
            if (!node) return;
            node.style.transformOrigin = 'center center';
            node.style.transition = 'transform 180ms ease';
            node.style.transform = 'scale(' + zoomLevel.toFixed(2) + ')';
        });
    }

    function setStatus(kind, headline, text) {
        box.classList.remove('vg-live-status--warn', 'vg-live-status--error');
        if (kind === 'warn') box.classList.add('vg-live-status--warn');
        if (kind === 'error') box.classList.add('vg-live-status--error');
        title.textContent = headline;
        message.textContent = text;
    }

    function buildLiveUrl(baseUrl) {
        return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
    }

    function stopFallbackRefresh() {
        if (fallbackTimer) {
            window.clearInterval(fallbackTimer);
            fallbackTimer = null;
        }
        currentFallbackUrl = '';
    }

    function stopWebRtc() {
        if (!webrtcFrame) {
            return;
        }
        webrtcFrame.style.display = 'none';
        if (currentWebRtcUrl !== '') {
            webrtcFrame.src = 'about:blank';
            currentWebRtcUrl = '';
        }
    }

    function startFallbackRefresh(preview) {
        var previewUrl = preview && preview.url ? String(preview.url) : '';
        if (!fallbackImage || !previewUrl) {
            stopFallbackRefresh();
            return;
        }
        if (currentFallbackUrl === previewUrl && fallbackTimer) {
            return;
        }
        stopFallbackRefresh();
        currentFallbackUrl = previewUrl;
        fallbackImage.src = buildLiveUrl(previewUrl);
        fallbackTimer = window.setInterval(function () {
            if (document.hidden) {
                return;
            }
            fallbackImage.src = buildLiveUrl(previewUrl);
        }, fallbackRefreshMs);
    }

    function showFallback(preview) {
        if (hlsInstance) {
            try { hlsInstance.destroy(); } catch (e) {}
            hlsInstance = null;
        }
        stopWebRtc();
        video.pause();
        video.removeAttribute('src');
        video.style.display = 'none';
        if (fallbackImage && preview && preview.url) {
            if (placeholder) placeholder.style.display = 'none';
            startFallbackRefresh(preview);
            fallbackImage.style.display = '';
        } else if (placeholder) {
            stopFallbackRefresh();
            if (fallbackImage) fallbackImage.style.display = 'none';
            placeholder.style.display = '';
        }
        modePill.textContent = 'Direct rapide snapshot';
    }

    function playWebRtc(url) {
        if (!url || !webrtcFrame) return;
        if (hlsInstance) {
            try { hlsInstance.destroy(); } catch (e) {}
            hlsInstance = null;
        }
        stopFallbackRefresh();
        if (fallbackImage) fallbackImage.style.display = 'none';
        if (placeholder) placeholder.style.display = 'none';
        video.pause();
        video.removeAttribute('src');
        video.style.display = 'none';
        webrtcFrame.style.display = '';
        if (currentWebRtcUrl !== url) {
            webrtcFrame.src = url;
            currentWebRtcUrl = url;
        }
        modePill.textContent = 'Direct WebRTC temps reel';
    }

    function playHls(url) {
        if (!url) return;
        if (currentHlsUrl === url && video.style.display !== 'none') return;
        currentHlsUrl = url;
        stopFallbackRefresh();
        stopWebRtc();
        if (fallbackImage) fallbackImage.style.display = 'none';
        if (placeholder) placeholder.style.display = 'none';
        video.style.display = '';
        modePill.textContent = 'Direct HLS faible latence';

        if (hlsInstance) {
            try { hlsInstance.destroy(); } catch (e) {}
            hlsInstance = null;
        }

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            hlsInstance = new Hls({
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
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(function() {});
            });
            hlsInstance.on(Hls.Events.ERROR, function(event, data) {
                if (data && data.fatal) {
                    showFallback(initialPreview);
                    setStatus('warn', 'Direct momentanement indisponible', 'La camera a coupe quelques secondes. VIGILANCE tente une reprise automatique.');
                }
            });
            hlsInstance.loadSource(url);
            hlsInstance.attachMedia(video);
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            video.play().catch(function() {});
        } else {
            showFallback(initialPreview);
            setStatus('warn', 'Navigateur limite', 'Ce navigateur ne lit pas le HLS nativement. L apercu securise reste disponible.');
        }
    }

    async function refreshStatus() {
        if (statusPollInFlight || !statusUrl || document.hidden) {
            return;
        }

        statusPollInFlight = true;
        try {
            var response = await fetch(statusUrl, { cache: 'no-store' });
            var payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.error || ('HTTP ' + response.status));
            }

            if (streamLabel) streamLabel.textContent = payload.camera.stream_label || '';
            if (bridgeFlag) {
                bridgeFlag.textContent = payload.bridge.webrtc_playable
                    ? 'WebRTC temps reel'
                    : (payload.bridge.enabled ? 'Pont RTSP vers HLS' : 'Apercu direct');
            }

            if (payload.bridge.webrtc_playable && payload.bridge.webrtc_url) {
                playWebRtc(payload.bridge.webrtc_url);
                setStatus(
                    'ok',
                    'Direct temps reel disponible',
                    payload.bridge.message || 'Le flux WebRTC temps reel est disponible.'
                );
                return;
            }

            if (payload.bridge.enabled && payload.bridge.playable && payload.bridge.hls_url) {
                playHls(payload.bridge.hls_url);
                setStatus(
                    'ok',
                    'Direct disponible',
                    payload.bridge.message || 'Le flux live est disponible.'
                );
                return;
            }

            showFallback(payload.preview);
            setStatus(
                payload.bridge.enabled ? 'warn' : 'error',
                payload.preview && payload.preview.url ? 'Direct rapide actif' : (payload.bridge.enabled ? 'Attente du direct' : 'Direct indisponible'),
                payload.bridge.message || payload.preview.message || 'Le direct revient automatiquement des que la camera republie.'
            );
        } catch (error) {
            showFallback(initialPreview);
            setStatus('warn', 'Verification en cours', 'Impossible de verifier le direct pour le moment. VIGILANCE va reessayer automatiquement.');
            console.error('VIGILANCE live status error:', error);
        } finally {
            statusPollInFlight = false;
        }
    }

    if (zoomInButton) {
        zoomInButton.addEventListener('click', function () {
            zoomLevel = Math.min(3, zoomLevel + 0.25);
            applyZoom();
        });
    }
    if (zoomOutButton) {
        zoomOutButton.addEventListener('click', function () {
            zoomLevel = Math.max(1, zoomLevel - 0.25);
            applyZoom();
        });
    }
    if (zoomResetButton) {
        zoomResetButton.addEventListener('click', function () {
            zoomLevel = 1;
            applyZoom();
        });
    }

    if (initialBridge && initialBridge.webrtc_playable && initialBridge.webrtc_url) {
        playWebRtc(initialBridge.webrtc_url);
        setStatus(
            'ok',
            'Direct temps reel disponible',
            initialBridge.message || 'Le flux WebRTC temps reel est disponible.'
        );
    } else if (initialBridge && initialBridge.enabled && initialBridge.playable && initialBridge.hls_url) {
        playHls(initialBridge.hls_url);
        setStatus(
            'ok',
            'Direct disponible',
            initialBridge.message || 'Le flux live est disponible.'
        );
    } else {
        showFallback(initialPreview);
        setStatus(
            'warn',
            initialPreview && initialPreview.url ? 'Direct rapide actif' : 'Attente du direct',
            (initialBridge && initialBridge.message) ? initialBridge.message : 'Reconnexion de la camera en cours.'
        );
    }

    applyZoom();
    refreshStatus();
    window.setInterval(function () {
        if (document.hidden) {
            return;
        }
        refreshStatus();
    }, 5000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshStatus();
        }
    });
})();
</script>
<?php vg_render_app_footer(); ?>
