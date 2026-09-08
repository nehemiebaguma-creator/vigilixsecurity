<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth/check.php';
require_once __DIR__ . '/includes/app-url.php';
require_once __DIR__ . '/includes/doctor.php';

$currentUser = function_exists('vg_current_user') ? vg_current_user() : [];
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$forwardedAddr = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
$isLocalRequest = $forwardedAddr === '' && in_array($remoteAddr, ['127.0.0.1', '::1'], true);
$isAdminSession = is_array($currentUser) && (($currentUser['role'] ?? '') === 'admin');
if (!$isLocalRequest && !$isAdminSession) {
    http_response_code(403);
    echo 'Acces reserve au poste local ou a une session admin.';
    exit;
}

$messages = [];
$pauseAfterLaunchMs = 0;
$selectedCameraId = trim((string) ($_REQUEST['camera_id'] ?? ''));

$pushMessage = static function (string $type, string $text) use (&$messages): void {
    $messages[] = [
        'type' => $type,
        'text' => $text,
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $selectedCameraId = trim((string) ($_POST['camera_id'] ?? $selectedCameraId));
    $postStore = vg_load_store();
    $postCameras = is_array($postStore['cameras'] ?? null) ? array_values($postStore['cameras']) : [];
    $selectedCamera = vg_doctor_find_camera_by_id($postCameras, $selectedCameraId);

    if ($action === 'repair-admin') {
        $store = vg_seed_admin_user(vg_load_store());
        vg_save_store($store);
        $pushMessage('success', 'Le compte admin de secours a ete reinitialise sur nehemiebaguma@gmail.com.');
    } elseif ($action === 'repair-shape') {
        $store = vg_ensure_store_shape(vg_load_store());
        $store = vg_seed_admin_user($store);
        vg_save_store($store);
        $pushMessage('success', 'Le stockage local a ete reinitialise proprement sans supprimer les donnees existantes.');
    } elseif ($action === 'start-mediamtx') {
        if (function_exists('vg_mediamtx_try_start_local_service') && vg_mediamtx_try_start_local_service()) {
            $pauseAfterLaunchMs = 1400;
            $pushMessage('success', 'Demarrage MediaMTX demande. VIGILANCE va reverifier le pont HLS.');
        } else {
            $pushMessage('error', 'MediaMTX n a pas pu etre relance maintenant. Verifiez mediamtx/start-mediamtx.bat ou attendez la fin d un demarrage deja en cours.');
        }
    } elseif ($action === 'start-vision') {
        if (function_exists('vg_vision_try_start_local_service') && vg_vision_try_start_local_service()) {
            $pauseAfterLaunchMs = 1800;
            if (function_exists('vgx_drone_clear_failure')) {
                vgx_drone_clear_failure();
            }
            $pushMessage('success', 'Demarrage du moteur IA Python demande. VIGILANCE reverifie le service vision.');
        } else {
            $pushMessage('error', 'Le moteur IA Python n a pas pu etre relance maintenant. Verifiez drone-api/start-drone-api.ps1 ou attendez la fin d un demarrage deja lance.');
        }
    } elseif ($action === 'start-camera-worker') {
        if (function_exists('vg_camera_ai_worker_try_start_local') && vg_camera_ai_worker_try_start_local()) {
            $pauseAfterLaunchMs = 1200;
            $pushMessage('success', 'Demarrage du worker camera demande. VIGILANCE reverifie les analyses automatiques.');
        } else {
            $pushMessage('error', 'Le worker camera n a pas pu etre relance maintenant. Verifiez start-camera-ai-worker.ps1 ou attendez la fin d un demarrage deja lance.');
        }
    } elseif ($action === 'refresh-camera-hls') {
        if ($selectedCamera === null) {
            $pushMessage('error', 'Camera introuvable pour republier le pont HLS.');
        } elseif (function_exists('vg_mediamtx_register_camera_path') && vg_mediamtx_register_camera_path($selectedCamera)) {
            $pauseAfterLaunchMs = 1000;
            $pushMessage('success', 'Le pont HLS de cette camera a ete republie dans MediaMTX.');
        } else {
            $pushMessage('error', 'Impossible de republier le pont HLS pour cette camera.');
        }
    } elseif ($action === 'diagnose-camera') {
        if ($selectedCamera === null) {
            $pushMessage('error', 'Camera introuvable pour le diagnostic detaille.');
        } else {
            $pushMessage('success', 'Diagnostic detaille recharge pour ' . (string) ($selectedCamera['name'] ?? 'la camera') . '.');
        }
    }
}

if ($pauseAfterLaunchMs > 0) {
    usleep($pauseAfterLaunchMs * 1000);
}

$store = vg_load_store();
$users = $store['users'] ?? [];
$clients = $store['clients'] ?? [];
$agents = $store['agents'] ?? [];
$cameras = is_array($store['cameras'] ?? null) ? array_values($store['cameras']) : [];
$selectedCamera = vg_doctor_find_camera_by_id($cameras, $selectedCameraId);
$services = vg_doctor_service_snapshot(true);
$selectedCameraPayload = $selectedCamera !== null ? vg_doctor_camera_public_payload($selectedCamera) : null;
$cameraDiagnosis = $selectedCamera !== null ? vg_doctor_camera_diagnosis($selectedCamera, $services) : null;
$cameraPlaybooks = ($selectedCamera !== null && $cameraDiagnosis !== null)
    ? vg_doctor_camera_playbooks($selectedCamera, $cameraDiagnosis)
    : [];
$initialPlaybook = $cameraPlaybooks[0] ?? null;

$routes = [
    'Accueil' => __DIR__ . '/index.php',
    'Connexion' => __DIR__ . '/auth/login.php',
    'Dashboard admin' => __DIR__ . '/admin/dashboard.php',
    'Centre OPS' => __DIR__ . '/admin/control-center.php',
    'Clients' => __DIR__ . '/admin/clients.php',
    'Cameras' => __DIR__ . '/admin/cameras.php',
    'Agents' => __DIR__ . '/admin/agents.php',
    'Portail client' => __DIR__ . '/client/dashboard.php',
    'Portail agent' => __DIR__ . '/agent/dashboard.php',
    'Changer mot de passe' => __DIR__ . '/password.php',
];

$routeCards = [];
foreach ($routes as $label => $path) {
    $relative = str_replace('\\', '/', ltrim(substr($path, strlen(__DIR__)), '/\\'));
    $routeCards[] = [
        'label' => $label,
        'relative' => $relative,
        'exists' => is_file($path),
        'url' => function_exists('vg_url') ? vg_url($relative) : '/' . $relative,
    ];
}

$selectedOpenTarget = is_array($selectedCameraPayload['open_target'] ?? null) ? $selectedCameraPayload['open_target'] : ['url' => '', 'label' => '', 'message' => ''];
$mediamtxStatus = (array) ($services['mediamtx'] ?? []);
$visionHealth = (array) ($services['vision'] ?? []);
$workerStatus = (array) ($services['worker'] ?? []);
$doctorSelectedMission = $selectedCameraPayload !== null
    ? (($selectedCameraPayload['category'] ?? 'privee') === 'routiere'
        ? 'Mode route: plaques, congestion, vehicule bloquant et scene exploitable sans casser le direct.'
        : 'Mode site: presence, intrusion, watchlist, levee de doute et scene exploitable sans decor inutile.')
    : 'Choisissez une camera pour afficher la doctrine la plus utile entre route, maison ou bureau.';
$doctorFocusChip = $selectedCameraPayload !== null
    ? (($selectedCameraPayload['category'] ?? 'privee') === 'routiere' ? 'Plaques / congestion' : 'Presence / watchlist')
    : 'Diagnostic terrain';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor | VIGILANCE Security</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/app.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/ops-next.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .doctor-grid{display:grid;gap:18px}
        .doctor-grid--services{grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
        .doctor-grid--two{grid-template-columns:repeat(auto-fit,minmax(340px,1fr))}
        .doctor-panel{padding:24px;border-radius:24px;border:1px solid rgba(84,152,221,.16);background:linear-gradient(180deg,rgba(12,22,36,.96),rgba(8,16,27,.99));box-shadow:0 24px 60px rgba(0,0,0,.24)}
        .doctor-title{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
        .doctor-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:999px;font-size:.78rem;font-weight:700;border:1px solid currentColor}
        .doctor-badge--ok{color:#00e676;background:rgba(0,230,118,.10)}
        .doctor-badge--warn{color:#f8c96d;background:rgba(248,201,109,.10)}
        .doctor-badge--bad{color:#ff6478;background:rgba(255,100,120,.12)}
        .doctor-badge--info{color:#4eb4ff;background:rgba(78,180,255,.10)}
        .doctor-livebar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:18px 0}
        .doctor-livebar__text{color:#a8cae8;font-size:.92rem}
        .doctor-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:16px}
        .doctor-metric{padding:14px 16px;border-radius:18px;background:rgba(7,16,29,.72);border:1px solid rgba(84,152,221,.12)}
        .doctor-metric span{display:block;font-size:.76rem;letter-spacing:.08em;text-transform:uppercase;color:#7fb1dc}
        .doctor-metric strong{display:block;margin-top:8px;font-size:1rem;color:#edf4ff;line-height:1.35}
        .doctor-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .doctor-note{margin-top:12px;color:#a8cae8;line-height:1.65}
        .doctor-list{display:grid;gap:10px;margin-top:14px}
        .doctor-list-item{padding:14px 16px;border-radius:18px;background:rgba(7,16,29,.72);border:1px solid rgba(84,152,221,.12)}
        .doctor-list-item strong{display:block;color:#edf4ff}
        .doctor-list-item p{margin:6px 0 0;color:#a8cae8}
        .doctor-inline-form{display:inline}
        .doctor-camera-card{padding:18px;border-radius:20px;background:rgba(7,16,29,.72);border:1px solid rgba(84,152,221,.12)}
        .doctor-camera-card + .doctor-camera-card{margin-top:12px}
        .doctor-route-row{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
        .doctor-route-row code,.doctor-list-item code{word-break:break-all}
        .doctor-subpanel{margin-top:18px;padding:18px;border-radius:20px;background:rgba(7,16,29,.76);border:1px solid rgba(84,152,221,.12)}
        .doctor-playbooks{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:14px}
        .doctor-playbook-button{display:flex;flex-direction:column;gap:6px;padding:14px 16px;border-radius:18px;border:1px solid rgba(84,152,221,.16);background:rgba(8,16,27,.95);color:#edf4ff;text-align:left;cursor:pointer;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
        .doctor-playbook-button strong{font-size:.96rem;line-height:1.3}
        .doctor-playbook-button small{font-size:.8rem;color:#a8cae8}
        .doctor-playbook-button.is-active{transform:translateY(-1px);box-shadow:0 12px 24px rgba(0,0,0,.22)}
        .doctor-playbook-detail{margin-top:14px;padding:16px;border-radius:18px;background:rgba(4,12,21,.72);border:1px solid rgba(84,152,221,.14)}
        .doctor-playbook-detail p{margin:8px 0 0;color:#a8cae8;line-height:1.6}
        .doctor-recommendations .doctor-list-item{background:rgba(10,18,31,.82)}
        .doctor-placeholder{padding:18px;border-radius:20px;background:rgba(7,16,29,.72);border:1px dashed rgba(84,152,221,.18);color:#a8cae8}
        .doctor-hidden{display:none !important}
        .doctor-muted{color:#6e9cc0}
        .doctor-hero{grid-template-columns:minmax(0,1.08fr) minmax(320px,.92fr);align-items:stretch}
        .doctor-hero__copy{display:grid;gap:14px;align-content:start}
        .doctor-hero__chips{display:flex;gap:10px;flex-wrap:wrap}
        .doctor-hero__chips span{display:inline-flex;align-items:center;min-height:34px;padding:0 13px;border-radius:999px;border:1px solid rgba(102,178,255,.18);background:rgba(7,18,33,.7);color:#dceeff;font-size:.76rem;letter-spacing:.16em;text-transform:uppercase}
        .doctor-hero__crest{position:relative;display:grid;gap:18px;padding:24px;border-radius:28px;border:1px solid rgba(96,166,255,.18);background:linear-gradient(180deg,rgba(8,19,35,.98),rgba(6,12,24,.94));box-shadow:0 26px 56px rgba(2,8,18,.28);overflow:hidden}
        .doctor-hero__crest::before{content:"";position:absolute;left:0;right:0;top:0;height:4px;background:linear-gradient(90deg,#00a3e0 0%,#00a3e0 46%,#f7d618 46%,#f7d618 58%,#ce1126 58%,#ce1126 100%);box-shadow:0 0 22px rgba(247,214,24,.18)}
        .doctor-hero__seal{width:112px;height:112px;padding:12px;border-radius:28px;border:1px solid rgba(247,214,24,.2);background:radial-gradient(circle at 50% 42%,rgba(247,214,24,.16),transparent 62%),rgba(7,15,28,.82);box-shadow:inset 0 1px 0 rgba(255,255,255,.04),0 18px 34px rgba(0,0,0,.24)}
        .doctor-hero__seal img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 10px 18px rgba(0,0,0,.25))}
        .doctor-hero__brief span{display:block;color:#8fbce2;font-size:.74rem;letter-spacing:.22em;text-transform:uppercase}
        .doctor-hero__brief strong{display:block;margin-top:10px;color:#fff;font-size:1.18rem;line-height:1.35}
        .doctor-hero__brief p{margin:10px 0 0;color:#a8cae8;line-height:1.65}
        .doctor-guide{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:0 0 22px}
        .doctor-guide-card{padding:20px;border-radius:24px;border:1px solid rgba(84,152,221,.14);background:linear-gradient(180deg,rgba(10,19,34,.96),rgba(7,14,26,.88));box-shadow:0 18px 40px rgba(0,0,0,.2)}
        .doctor-guide-card span{display:block;color:#86bbe9;font-size:.74rem;letter-spacing:.2em;text-transform:uppercase}
        .doctor-guide-card strong{display:block;margin-top:10px;color:#fff;font-size:1.04rem;line-height:1.38}
        .doctor-guide-card p{margin:10px 0 0;color:#a8cae8;line-height:1.62}
        @media (max-width:1100px){
            .doctor-hero,
            .doctor-guide{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <header class="brand-header">
            <a href="<?= htmlspecialchars(vg_url(), ENT_QUOTES, 'UTF-8'); ?>" class="brand-lockup">
                <img src="<?= htmlspecialchars(vg_url('brand-media.php?v=20260715-official-logo'), ENT_QUOTES, 'UTF-8'); ?>" alt="VIGILANCE Security" class="brand-mark">
                <div>
                    <strong>VIGILANCE Security</strong>
                    <span>Diagnostic et relance rapide</span>
                </div>
            </a>
            <div class="button-row">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('admin/cameras.php'), ENT_QUOTES, 'UTF-8'); ?>">Cameras</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('admin/control-center.php'), ENT_QUOTES, 'UTF-8'); ?>">Centre OPS</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>">Connexion</a>
            </div>
        </header>

        <section class="hero-simple doctor-hero">
            <div class="doctor-hero__copy">
                <p class="eyebrow">Doctor</p>
                <h1>Le vrai panneau de sante de VIGILANCE.</h1>
                <p>Cette page montre en direct si MediaMTX, le moteur IA Python ou le worker camera tournent, puis affiche pour chaque camera les vraies fonctions prêtes: direct, mouvement, plaques, congestion, presence ou degagement.</p>
                <div class="doctor-hero__chips">
                    <span>Direct + IA</span>
                    <span>Temps reel</span>
                    <span><?= htmlspecialchars($doctorFocusChip, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>Kinshasa</span>
                </div>
            </div>
            <aside class="doctor-hero__crest">
                <div class="doctor-hero__seal">
                    <img src="<?= htmlspecialchars(vg_url('assets/images/rdc-coat-of-arms.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Armoiries RDC">
                </div>
                <div class="doctor-hero__brief">
                    <span>Commandement technique RDC</span>
                    <strong>Doctor doit voir, expliquer, relancer et guider l operateur sans masquer le vrai probleme.</strong>
                    <p><?= htmlspecialchars($doctorSelectedMission, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </aside>
        </section>

        <section class="doctor-guide">
            <article class="doctor-guide-card">
                <span>Lecture plaques</span>
                <strong>Comptez 6 a 12 m en grand angle 4K pour une lecture utile.</strong>
                <p>Pour une lecture vraiment fiable, visez plutot 12 a 25 m avec zoom optique serre ou une camera dediee ANPR, face presque droite a la plaque.</p>
            </article>
            <article class="doctor-guide-card">
                <span>Temps reel</span>
                <strong>H.264, 12 a 15 FPS, I-frame 1 s, sous-flux pour l IA.</strong>
                <p>Gardez le flux principal pour l archive. Pour l analyse rapide, utilisez un sous-flux 640x360 a 1280x720 et laissez H.265+ desactive.</p>
            </article>
            <article class="doctor-guide-card">
                <span>Focus Doctor</span>
                <strong><?= htmlspecialchars($selectedCameraPayload['name'] ?? 'Aucune camera selectionnee', ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?= htmlspecialchars($doctorSelectedMission, ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
        </section>

        <?php foreach ($messages as $message): ?>
            <div class="flash flash-<?= htmlspecialchars((string) ($message['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($message['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>

        <section class="doctor-livebar">
            <span class="doctor-badge doctor-badge--info" data-doctor-live-state>Auto-refresh toutes les 6 s</span>
            <span class="doctor-livebar__text" data-doctor-last-sync>Derniere synchronisation: <?= htmlspecialchars((string) ($services['captured_at'] ?? date('c')), ENT_QUOTES, 'UTF-8'); ?></span>
        </section>

        <section class="grid four stats-grid">
            <article class="stat-card"><span>Users</span><strong><?= count($users); ?></strong></article>
            <article class="stat-card"><span>Clients</span><strong><?= count($clients); ?></strong></article>
            <article class="stat-card"><span>Agents</span><strong><?= count($agents); ?></strong></article>
            <article class="stat-card"><span>Cameras</span><strong><?= count($cameras); ?></strong></article>
        </section>

        <section class="doctor-grid doctor-grid--services">
            <article class="doctor-panel">
                <div class="doctor-title">
                    <div>
                        <h2>MediaMTX</h2>
                        <p class="doctor-note">Pont RTSP vers HLS pour le direct navigateur.</p>
                    </div>
                    <span class="doctor-badge <?= !empty($mediamtxStatus['available']) ? 'doctor-badge--ok' : 'doctor-badge--bad' ?>" data-doctor-mediamtx-badge>
                        <?= !empty($mediamtxStatus['available']) ? '✓ En ligne' : '✕ Hors ligne' ?>
                    </span>
                </div>
                <div class="doctor-metrics">
                    <div class="doctor-metric"><span>API</span><strong data-doctor-mediamtx-api><?= htmlspecialchars((string) ($mediamtxStatus['api_base'] ?? 'indisponible'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="doctor-metric"><span>HLS</span><strong data-doctor-mediamtx-hls><?= htmlspecialchars((string) ($mediamtxStatus['hls_base'] ?? 'indisponible'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="doctor-metric"><span>Paths</span><strong data-doctor-mediamtx-paths><?= (int) ($mediamtxStatus['paths_count'] ?? 0); ?></strong></div>
                </div>
                <p class="doctor-note" data-doctor-mediamtx-note><?= !empty($mediamtxStatus['available']) ? 'Pont HLS joignable depuis VIGILANCE.' : 'Le pont HLS ne repond pas encore.'; ?></p>
                <div class="doctor-actions">
                    <form method="post" class="doctor-inline-form">
                        <input type="hidden" name="action" value="start-mediamtx">
                        <?php if ($selectedCameraId !== ''): ?><input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Relancer MediaMTX</button>
                    </form>
                </div>
            </article>

            <article class="doctor-panel">
                <div class="doctor-title">
                    <div>
                        <h2>Moteur IA Python</h2>
                        <p class="doctor-note">Detection vehicules, plaques, personnes, congestion et ordres intelligents.</p>
                    </div>
                    <span class="doctor-badge <?= !empty($visionHealth['ready']) ? 'doctor-badge--ok' : 'doctor-badge--bad' ?>" data-doctor-vision-badge>
                        <?= !empty($visionHealth['ready']) ? '✓ Pret' : '✕ Arrete' ?>
                    </span>
                </div>
                <div class="doctor-metrics">
                    <div class="doctor-metric"><span>Vehicules</span><strong data-doctor-vision-model><?= !empty($visionHealth['vehicle_model_ready']) ? 'Modele charge' : 'Modele absent' ?></strong></div>
                    <div class="doctor-metric"><span>EasyOCR</span><strong data-doctor-vision-easyocr><?= !empty($visionHealth['easyocr_available']) ? 'Disponible' : 'Manquant' ?></strong></div>
                    <div class="doctor-metric"><span>Tesseract</span><strong data-doctor-vision-tesseract><?= !empty($visionHealth['tesseract_available']) ? 'Disponible' : 'Manquant' ?></strong></div>
                </div>
                <p class="doctor-note" data-doctor-vision-note><?= htmlspecialchars((string) ($visionHealth['error'] ?? 'Service vision pret.'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="doctor-actions">
                    <form method="post" class="doctor-inline-form">
                        <input type="hidden" name="action" value="start-vision">
                        <?php if ($selectedCameraId !== ''): ?><input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Relancer le moteur IA</button>
                    </form>
                </div>
            </article>

            <article class="doctor-panel">
                <div class="doctor-title">
                    <div>
                        <h2>Worker camera</h2>
                        <p class="doctor-note">Boucle qui lance les analyses automatiques sur les cameras.</p>
                    </div>
                    <span class="doctor-badge <?= !empty($workerStatus['running']) ? 'doctor-badge--ok' : 'doctor-badge--warn' ?>" data-doctor-worker-badge>
                        <?= !empty($workerStatus['running']) ? '✓ Actif' : '▶ A relancer' ?>
                    </span>
                </div>
                <div class="doctor-metrics">
                    <div class="doctor-metric"><span>Dernier tick</span><strong data-doctor-worker-tick><?= htmlspecialchars((string) ($workerStatus['last_tick_at'] ?? 'Jamais'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div class="doctor-metric"><span>Intervalle</span><strong data-doctor-worker-interval><?= (int) ($workerStatus['interval_seconds'] ?? 0); ?> s</strong></div>
                    <div class="doctor-metric"><span>Dernier cycle</span><strong data-doctor-worker-cycle><?= (int) ($workerStatus['last_ok_count'] ?? 0); ?> OK / <?= (int) ($workerStatus['last_error_count'] ?? 0); ?> erreur(s)</strong></div>
                </div>
                <p class="doctor-note" data-doctor-worker-note><?= !empty($workerStatus['running']) ? 'Le worker relance en boucle les analyses camera.' : 'Le worker doit etre relance pour garder les detections fraiches.'; ?></p>
                <div class="doctor-actions">
                    <form method="post" class="doctor-inline-form">
                        <input type="hidden" name="action" value="start-camera-worker">
                        <?php if ($selectedCameraId !== ''): ?><input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Relancer le worker</button>
                    </form>
                </div>
            </article>
        </section>

        <section class="doctor-grid doctor-grid--two" style="margin-top:18px">
            <article class="doctor-panel" id="camera-doctor">
                <div class="doctor-title">
                    <div>
                        <h2>Docteur camera</h2>
                        <p class="doctor-note">Selectionnez une camera pour verifier le flux, la capture IA et les vraies fonctions terrain disponibles.</p>
                    </div>
                    <?php if ($selectedCameraPayload !== null): ?>
                        <span class="doctor-badge doctor-badge--ok" data-doctor-camera-badge><?= htmlspecialchars((string) ($selectedCameraPayload['name'] ?? 'Camera'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php else: ?>
                        <span class="doctor-badge doctor-badge--warn" data-doctor-camera-badge>Choisissez une camera</span>
                    <?php endif; ?>
                </div>

                <?php if ($selectedCameraPayload !== null && $cameraDiagnosis !== null): ?>
                    <div class="doctor-metrics">
                        <div class="doctor-metric"><span>Flux</span><strong data-doctor-metric-flux><?= htmlspecialchars((string) ($cameraDiagnosis['validation']['label'] ?? 'Inconnu'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        <div class="doctor-metric"><span>Capture IA</span><strong data-doctor-metric-capture><?= !empty($cameraDiagnosis['capture_probe']['ok']) ? 'OK en ' . (int) ($cameraDiagnosis['capture_probe']['duration_ms'] ?? 0) . ' ms' : 'Echec'; ?></strong></div>
                        <div class="doctor-metric"><span>Direct</span><strong data-doctor-metric-hls><?= !empty($cameraDiagnosis['hls_probe']['ok']) ? 'HLS actif' : 'Mode secours'; ?></strong></div>
                        <div class="doctor-metric"><span>IA</span><strong data-doctor-metric-ia><?= htmlspecialchars((string) ($cameraDiagnosis['intelligence']['smart_label'] ?? 'En attente'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    </div>

                    <div class="doctor-actions">
                        <button type="button" class="btn btn-secondary" id="doctor-analyze-now" data-camera-id="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>">Analyser maintenant</button>
                        <form method="post" class="doctor-inline-form">
                            <input type="hidden" name="action" value="diagnose-camera">
                            <input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-secondary">Recharger le diagnostic</button>
                        </form>
                        <form method="post" class="doctor-inline-form">
                            <input type="hidden" name="action" value="refresh-camera-hls">
                            <input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-secondary">Republier le pont HLS</button>
                        </form>
                        <?php if (trim((string) ($selectedOpenTarget['url'] ?? '')) !== ''): ?>
                            <a class="btn btn-secondary" href="<?= htmlspecialchars((string) ($selectedOpenTarget['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" data-doctor-open-direct><?= htmlspecialchars((string) (($selectedOpenTarget['label'] ?? '') !== '' ? $selectedOpenTarget['label'] : 'Ouvrir le direct'), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php else: ?>
                            <a class="btn btn-secondary doctor-hidden" href="#" target="_blank" rel="noopener" data-doctor-open-direct>Ouvrir le direct</a>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('admin/cameras.php'), ENT_QUOTES, 'UTF-8'); ?>">Retour aux cameras</a>
                    </div>

                    <div class="doctor-list">
                        <div class="doctor-list-item">
                            <strong>Flux configure</strong>
                            <p data-doctor-stream-url><?= htmlspecialchars((string) ($cameraDiagnosis['stream_url_redacted'] ?? 'Aucun flux configure'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="doctor-list-item">
                            <strong>Sous-flux / analyse</strong>
                            <p data-doctor-analysis-url><?= htmlspecialchars((string) (($cameraDiagnosis['analysis_url_redacted'] ?? '') !== '' ? $cameraDiagnosis['analysis_url_redacted'] : 'Aucun sous-flux distinct detecte'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="doctor-list-item">
                            <strong>Capture IA</strong>
                            <p data-doctor-capture-label><?= htmlspecialchars((string) (($cameraDiagnosis['capture_source']['label'] ?? 'Capture inconnue') . ' • ' . ($cameraDiagnosis['capture_source']['message'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p data-doctor-capture-result><?= htmlspecialchars(!empty($cameraDiagnosis['capture_probe']['ok'])
                                ? ('Image valide ' . (string) ($cameraDiagnosis['capture_probe']['mime'] ?? 'image/jpeg') . ' • ' . number_format((int) ($cameraDiagnosis['capture_probe']['bytes_length'] ?? 0)) . ' octets')
                                : (string) ($cameraDiagnosis['capture_probe']['error'] ?? 'Test capture echoue.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="doctor-list-item">
                            <strong>Direct HLS</strong>
                            <p data-doctor-hls-message><?= htmlspecialchars((string) ($cameraDiagnosis['hls_probe']['message'] ?? 'Indisponible'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (trim((string) ($cameraDiagnosis['bridge']['hls_url'] ?? '')) !== ''): ?>
                                <p data-doctor-hls-url-wrap><code data-doctor-hls-url><?= htmlspecialchars((string) ($cameraDiagnosis['bridge']['hls_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></p>
                            <?php else: ?>
                                <p class="doctor-hidden" data-doctor-hls-url-wrap><code data-doctor-hls-url></code></p>
                            <?php endif; ?>
                        </div>
                        <div class="doctor-list-item">
                            <strong>Derniere analyse</strong>
                            <p data-doctor-analysis-human><?= htmlspecialchars((string) ($cameraDiagnosis['intelligence']['last_analysis_human'] ?? 'Jamais analysee'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p data-doctor-analysis-note><?= htmlspecialchars((string) ($cameraDiagnosis['intelligence']['recommendation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <div class="doctor-subpanel">
                        <div class="doctor-title">
                            <div>
                                <h3>Boutons terrain</h3>
                                <p class="doctor-note">Appuyez selon l anomalie a verifier. Le detail s adapte a la camera choisie.</p>
                            </div>
                            <span class="doctor-badge doctor-badge--info" data-doctor-playbook-live><?= htmlspecialchars((string) (($selectedCameraPayload['category'] ?? 'privee') === 'routiere' ? 'Mode route' : 'Mode prive'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="doctor-playbooks" data-doctor-playbook-buttons>
                            <?php foreach ($cameraPlaybooks as $index => $playbook): ?>
                                <?php $palette = vg_doctor_tone_palette((string) ($playbook['tone'] ?? 'info')); ?>
                                <button
                                    type="button"
                                    class="doctor-playbook-button<?= $index === 0 ? ' is-active' : '' ?>"
                                    data-doctor-playbook-button
                                    data-key="<?= htmlspecialchars((string) ($playbook['key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    style="border-color:<?= htmlspecialchars((string) ($palette['color'] ?? '#4eb4ff'), ENT_QUOTES, 'UTF-8'); ?>44;background:<?= htmlspecialchars((string) ($palette['background'] ?? 'rgba(78,180,255,.10)'), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <strong><?= htmlspecialchars((string) ($playbook['title'] ?? 'Playbook'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small><?= htmlspecialchars((string) ($playbook['status_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="doctor-playbook-detail" data-doctor-playbook-panel>
                            <?php if ($initialPlaybook !== null): ?>
                                <div class="doctor-title">
                                    <strong data-doctor-playbook-title><?= htmlspecialchars((string) ($initialPlaybook['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="doctor-badge doctor-badge--info" data-doctor-playbook-status><?= htmlspecialchars((string) ($initialPlaybook['status_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <p data-doctor-playbook-summary><?= htmlspecialchars((string) ($initialPlaybook['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p data-doctor-playbook-detail><?= htmlspecialchars((string) ($initialPlaybook['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php else: ?>
                                <div class="doctor-placeholder">Aucun playbook disponible tant que la camera n est pas choisie.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="doctor-subpanel doctor-recommendations">
                        <div class="doctor-title">
                            <div>
                                <h3>Ce que le docteur recommande</h3>
                                <p class="doctor-note">Actions concretes avant de conclure qu une camera ou l IA est en panne.</p>
                            </div>
                        </div>
                        <div class="doctor-list" data-doctor-recommendations>
                            <?php foreach ((array) ($cameraDiagnosis['recommendations'] ?? []) as $recommendation): ?>
                                <div class="doctor-list-item"><p><?= htmlspecialchars((string) $recommendation, ENT_QUOTES, 'UTF-8'); ?></p></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="doctor-placeholder">
                        Depuis la page cameras, cliquez sur le bouton <code>Docteur</code> d une camera. Vous pouvez aussi en choisir une plus bas.
                    </div>
                <?php endif; ?>
            </article>

            <article class="doctor-panel">
                <div class="doctor-title">
                    <div>
                        <h2>Reparer et verifier</h2>
                        <p class="doctor-note">Outils de secours pour la structure locale, l acces admin et les routes web importantes.</p>
                    </div>
                    <span class="doctor-badge doctor-badge--ok">Secours local</span>
                </div>
                <div class="doctor-actions">
                    <form method="post" class="doctor-inline-form">
                        <input type="hidden" name="action" value="repair-admin">
                        <?php if ($selectedCameraId !== ''): ?><input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-primary">Reinitialiser admin</button>
                    </form>
                    <form method="post" class="doctor-inline-form">
                        <input type="hidden" name="action" value="repair-shape">
                        <?php if ($selectedCameraId !== ''): ?><input type="hidden" name="camera_id" value="<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Reparer la structure locale</button>
                    </form>
                </div>
                <div class="doctor-list">
                    <div class="doctor-list-item">
                        <strong>Acces admin de secours</strong>
                        <p>Email: <code>nehemiebaguma@gmail.com</code></p>
                        <p>Mot de passe: <code>masque pour securite</code></p>
                    </div>
                    <?php foreach ($routeCards as $routeCard): ?>
                        <div class="doctor-list-item">
                            <div class="doctor-route-row">
                                <div>
                                    <strong><?= htmlspecialchars((string) ($routeCard['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p><?= !empty($routeCard['exists']) ? 'OK' : 'Fichier manquant'; ?> • <code><?= htmlspecialchars((string) ($routeCard['relative'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></p>
                                </div>
                                <a class="btn btn-secondary" href="<?= htmlspecialchars((string) ($routeCard['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ouvrir</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="doctor-panel" style="margin-top:18px">
            <div class="doctor-title">
                <div>
                    <h2>Choisir une camera</h2>
                    <p class="doctor-note">Chaque bouton ouvre le docteur directement sur la camera concernee.</p>
                </div>
                <span class="doctor-badge doctor-badge--ok"><?= count($cameras); ?> camera(s)</span>
            </div>
            <?php if (!$cameras): ?>
                <div class="doctor-list-item" style="margin-top:14px">
                    <strong>Aucune camera enregistree</strong>
                    <p>Ajoutez d abord une camera dans <code>admin/cameras.php</code>.</p>
                </div>
            <?php else: ?>
                <div class="doctor-list" style="margin-top:14px">
                    <?php foreach (array_reverse($cameras) as $camera): ?>
                        <?php
                        $cameraValidation = function_exists('vg_camera_stream_validation')
                            ? vg_camera_stream_validation($camera, 1)
                            : ['label' => 'Test indisponible', 'message' => 'Test indisponible'];
                        $cameraLabel = vg_doctor_camera_label($camera);
                        ?>
                        <div class="doctor-camera-card">
                            <div class="doctor-title">
                                <div>
                                    <strong><?= htmlspecialchars($cameraLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p class="doctor-note"><?= htmlspecialchars((string) ($cameraValidation['message'] ?? $cameraValidation['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url('doctor.php?camera_id=' . rawurlencode((string) ($camera['id'] ?? '')) . '#camera-doctor'), ENT_QUOTES, 'UTF-8'); ?>">Ouvrir le docteur</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var doctorStatusApiUrl = '<?= htmlspecialchars(vg_url('api/doctor_status.php'), ENT_QUOTES, 'UTF-8'); ?>';
        var cameraIntelligenceApiUrl = '<?= htmlspecialchars(vg_url('api/camera_intelligence.php'), ENT_QUOTES, 'UTF-8'); ?>';
        var selectedCameraId = '<?= htmlspecialchars($selectedCameraId, ENT_QUOTES, 'UTF-8'); ?>';
        var selectedPlaybookKey = '<?= htmlspecialchars((string) ($initialPlaybook['key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>';
        var currentPlaybooks = [];
        var refreshInFlight = false;

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function tonePalette(tone) {
            if (tone === 'ok') {
                return { color: '#00e676', background: 'rgba(0,230,118,.10)' };
            }
            if (tone === 'warn') {
                return { color: '#f8c96d', background: 'rgba(248,201,109,.10)' };
            }
            if (tone === 'bad') {
                return { color: '#ff6478', background: 'rgba(255,100,120,.12)' };
            }
            return { color: '#4eb4ff', background: 'rgba(78,180,255,.10)' };
        }

        function applyBadge(node, text, tone) {
            if (!node) {
                return;
            }
            var palette = tonePalette(tone);
            node.textContent = text;
            node.style.color = palette.color;
            node.style.background = palette.background;
            node.style.borderColor = palette.color;
        }

        function setText(selector, value) {
            document.querySelectorAll(selector).forEach(function (node) {
                node.textContent = String(value == null ? '' : value);
            });
        }

        function setHtml(selector, value) {
            document.querySelectorAll(selector).forEach(function (node) {
                node.innerHTML = String(value == null ? '' : value);
            });
        }

        function applyServices(services) {
            if (!services) {
                return;
            }

            var mediamtx = services.mediamtx || {};
            var vision = services.vision || {};
            var worker = services.worker || {};

            applyBadge(
                document.querySelector('[data-doctor-mediamtx-badge]'),
                mediamtx.available ? '✓ En ligne' : '✕ Hors ligne',
                mediamtx.available ? 'ok' : 'bad'
            );
            setText('[data-doctor-mediamtx-api]', mediamtx.api_base || 'indisponible');
            setText('[data-doctor-mediamtx-hls]', mediamtx.hls_base || 'indisponible');
            setText('[data-doctor-mediamtx-paths]', String(mediamtx.paths_count || 0));
            setText(
                '[data-doctor-mediamtx-note]',
                mediamtx.available
                    ? 'Pont HLS joignable. ' + String(mediamtx.ready_paths_count || 0) + ' path(s) prets.'
                    : 'Le pont HLS ne repond pas encore.'
            );

            applyBadge(
                document.querySelector('[data-doctor-vision-badge]'),
                (vision.ready || vision.ok) ? '✓ Pret' : '✕ Arrete',
                (vision.ready || vision.ok) ? 'ok' : 'bad'
            );
            setText('[data-doctor-vision-model]', vision.vehicle_model_ready ? 'Modele charge' : 'Modele absent');
            setText('[data-doctor-vision-easyocr]', vision.easyocr_available ? 'Disponible' : 'Manquant');
            setText('[data-doctor-vision-tesseract]', vision.tesseract_available ? 'Disponible' : 'Manquant');
            setText('[data-doctor-vision-note]', vision.error || 'Service vision pret.');

            applyBadge(
                document.querySelector('[data-doctor-worker-badge]'),
                worker.running ? '✓ Actif' : '▶ A relancer',
                worker.running ? 'ok' : 'warn'
            );
            setText('[data-doctor-worker-tick]', worker.last_tick_at || 'Jamais');
            setText('[data-doctor-worker-interval]', String(worker.interval_seconds || 0) + ' s');
            setText('[data-doctor-worker-cycle]', String(worker.last_ok_count || 0) + ' OK / ' + String(worker.last_error_count || 0) + ' erreur(s)');
            setText(
                '[data-doctor-worker-note]',
                worker.running
                    ? 'Le worker relance en boucle les analyses camera.'
                    : 'Le worker doit etre relance pour garder les detections fraiches.'
            );

            if (services.captured_at) {
                setText('[data-doctor-last-sync]', 'Derniere synchronisation: ' + services.captured_at);
            }
        }

        function renderRecommendations(items) {
            var container = document.querySelector('[data-doctor-recommendations]');
            if (!container) {
                return;
            }

            if (!Array.isArray(items) || !items.length) {
                container.innerHTML = '<div class="doctor-list-item"><p>Aucune recommandation supplementaire.</p></div>';
                return;
            }

            container.innerHTML = items.map(function (item) {
                return '<div class="doctor-list-item"><p>' + escapeHtml(item) + '</p></div>';
            }).join('');
        }

        function renderPlaybookDetail(playbook) {
            if (!playbook) {
                return;
            }

            setText('[data-doctor-playbook-title]', playbook.title || '');
            applyBadge(
                document.querySelector('[data-doctor-playbook-status]'),
                playbook.status_label || '',
                playbook.tone || 'info'
            );
            setText('[data-doctor-playbook-summary]', playbook.summary || '');
            setText('[data-doctor-playbook-detail]', playbook.detail || '');
        }

        function selectPlaybook(key) {
            if (!currentPlaybooks.length) {
                return;
            }

            var chosen = currentPlaybooks.find(function (entry) {
                return String(entry.key || '') === String(key || '');
            }) || currentPlaybooks[0];
            selectedPlaybookKey = String(chosen.key || '');

            document.querySelectorAll('[data-doctor-playbook-button]').forEach(function (button) {
                button.classList.toggle('is-active', String(button.getAttribute('data-key') || '') === selectedPlaybookKey);
            });

            renderPlaybookDetail(chosen);
        }

        function renderPlaybooks(playbooks) {
            var container = document.querySelector('[data-doctor-playbook-buttons]');
            if (!container) {
                return;
            }

            currentPlaybooks = Array.isArray(playbooks) ? playbooks.slice() : [];
            if (!currentPlaybooks.length) {
                container.innerHTML = '<div class="doctor-placeholder">Aucun bouton terrain disponible.</div>';
                return;
            }

            container.innerHTML = currentPlaybooks.map(function (playbook) {
                var palette = tonePalette(playbook.tone || 'info');
                return ''
                    + '<button type="button" class="doctor-playbook-button" data-doctor-playbook-button data-key="' + escapeHtml(playbook.key || '') + '"'
                    + ' style="border-color:' + palette.color + '44;background:' + palette.background + '">'
                    + '<strong>' + escapeHtml(playbook.title || 'Playbook') + '</strong>'
                    + '<small>' + escapeHtml(playbook.status_label || '') + '</small>'
                    + '</button>';
            }).join('');

            container.querySelectorAll('[data-doctor-playbook-button]').forEach(function (button) {
                button.addEventListener('click', function () {
                    selectPlaybook(String(button.getAttribute('data-key') || ''));
                });
            });

            if (!currentPlaybooks.some(function (playbook) { return String(playbook.key || '') === selectedPlaybookKey; })) {
                selectedPlaybookKey = String((currentPlaybooks[0] || {}).key || '');
            }
            selectPlaybook(selectedPlaybookKey);
        }

        function applyDiagnosis(camera, diagnosis, playbooks) {
            if (!camera || !diagnosis) {
                return;
            }

            applyBadge(
                document.querySelector('[data-doctor-camera-badge]'),
                camera.name || 'Camera',
                diagnosis.camera_category === 'routiere' ? 'warn' : 'ok'
            );
            applyBadge(
                document.querySelector('[data-doctor-playbook-live]'),
                diagnosis.camera_category === 'routiere' ? 'Mode route' : 'Mode prive',
                diagnosis.camera_category === 'routiere' ? 'warn' : 'info'
            );

            setText('[data-doctor-metric-flux]', (diagnosis.validation || {}).label || 'Inconnu');
            setText(
                '[data-doctor-metric-capture]',
                (diagnosis.capture_probe || {}).ok
                    ? 'OK en ' + String((diagnosis.capture_probe || {}).duration_ms || 0) + ' ms'
                    : 'Echec'
            );
            setText('[data-doctor-metric-hls]', (diagnosis.hls_probe || {}).ok ? 'HLS actif' : 'Mode secours');
            setText('[data-doctor-metric-ia]', ((diagnosis.intelligence || {}).smart_label || 'En attente'));
            setText('[data-doctor-stream-url]', diagnosis.stream_url_redacted || 'Aucun flux configure');
            setText('[data-doctor-analysis-url]', diagnosis.analysis_url_redacted || 'Aucun sous-flux distinct detecte');
            setText('[data-doctor-capture-label]', String(((diagnosis.capture_source || {}).label || 'Capture inconnue') + ' • ' + ((diagnosis.capture_source || {}).message || '')));
            setText(
                '[data-doctor-capture-result]',
                (diagnosis.capture_probe || {}).ok
                    ? 'Image valide ' + String((diagnosis.capture_probe || {}).mime || 'image/jpeg') + ' • ' + String((diagnosis.capture_probe || {}).bytes_length || 0) + ' octets'
                    : String((diagnosis.capture_probe || {}).error || 'Test capture echoue.')
            );
            setText('[data-doctor-hls-message]', (diagnosis.hls_probe || {}).message || 'Indisponible');
            setText('[data-doctor-analysis-human]', (diagnosis.intelligence || {}).last_analysis_human || 'Jamais analysee');
            setText('[data-doctor-analysis-note]', (diagnosis.intelligence || {}).recommendation || '');

            var hlsUrl = String(((diagnosis.bridge || {}).hls_url) || '');
            document.querySelectorAll('[data-doctor-hls-url]').forEach(function (node) {
                node.textContent = hlsUrl;
            });
            document.querySelectorAll('[data-doctor-hls-url-wrap]').forEach(function (node) {
                node.classList.toggle('doctor-hidden', hlsUrl === '');
            });

            var openDirect = document.querySelector('[data-doctor-open-direct]');
            if (openDirect) {
                var openTarget = camera.open_target || {};
                var openUrl = String(openTarget.url || '').trim();
                if (openUrl) {
                    openDirect.href = openUrl;
                    openDirect.textContent = openTarget.label || 'Ouvrir le direct';
                    openDirect.classList.remove('doctor-hidden');
                } else {
                    openDirect.classList.add('doctor-hidden');
                }
            }

            renderPlaybooks(playbooks || []);
            renderRecommendations((diagnosis.recommendations || []));
        }

        function fetchDoctorStatus() {
            if (refreshInFlight) {
                return Promise.resolve(null);
            }

            refreshInFlight = true;
            var url = doctorStatusApiUrl;
            if (selectedCameraId) {
                url += '?camera_id=' + encodeURIComponent(selectedCameraId);
            }

            return fetch(url, {
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, error: 'Reponse docteur invalide.' };
                });
            }).then(function (payload) {
                if (!payload || payload.ok === false) {
                    if (payload && payload.error) {
                        setText('[data-doctor-live-state]', payload.error);
                    }
                    return payload;
                }

                applyServices(payload.services || null);
                if (selectedCameraId && payload.camera && payload.diagnosis) {
                    applyDiagnosis(payload.camera, payload.diagnosis, payload.playbooks || []);
                }
                setText('[data-doctor-live-state]', 'Auto-refresh actif');
                return payload;
            }).catch(function (error) {
                setText('[data-doctor-live-state]', error instanceof Error ? error.message : String(error));
                return null;
            }).finally(function () {
                refreshInFlight = false;
            });
        }

        function postCameraIntelligence(params) {
            var body = new URLSearchParams();
            Object.keys(params).forEach(function (key) {
                body.set(key, String(params[key]));
            });

            return fetch(cameraIntelligenceApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                credentials: 'same-origin',
                body: body.toString()
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, error: 'Reponse IA invalide.' };
                });
            });
        }

        var analyzeButton = document.getElementById('doctor-analyze-now');
        if (analyzeButton && selectedCameraId) {
            analyzeButton.addEventListener('click', function () {
                var original = analyzeButton.textContent;
                analyzeButton.disabled = true;
                analyzeButton.textContent = 'Analyse...';
                setText('[data-doctor-live-state]', 'Analyse camera en cours...');
                postCameraIntelligence({
                    action: 'analyze_camera',
                    camera_id: selectedCameraId
                }).then(function (payload) {
                    if (payload && payload.error) {
                        setText('[data-doctor-live-state]', payload.error);
                    } else {
                        setText('[data-doctor-live-state]', 'Analyse relancee, synchronisation...');
                    }
                    return fetchDoctorStatus();
                }).finally(function () {
                    analyzeButton.disabled = false;
                    analyzeButton.textContent = original;
                });
            });
        }

        fetchDoctorStatus();
        window.setInterval(fetchDoctorStatus, 6000);
    });
    </script>
</body>
</html>
