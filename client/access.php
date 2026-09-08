<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/app-url.php';

if (function_exists('vg_bootstrap_session')) {
    vg_bootstrap_session();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

    if (!is_dir(dirname($storageFile))) {
        @mkdir(dirname($storageFile), 0777, true);
    }

    file_put_contents($storageFile, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};

$store = $loadStore();
$clients = isset($store['clients']) && is_array($store['clients']) ? array_values($store['clients']) : [];
$users = isset($store['users']) && is_array($store['users']) ? array_values($store['users']) : [];
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$client = null;
$clientIndex = null;

foreach ($clients as $index => $candidate) {
    if (!is_array($candidate)) {
        continue;
    }

    $tokens = array_filter([
        (string) ($candidate['portal_token'] ?? ''),
        (string) ($candidate['access_token'] ?? ''),
        (string) ($candidate['token'] ?? ''),
    ]);

    if ($token !== '' && in_array($token, $tokens, true)) {
        $client = $candidate;
        $clientIndex = $index;
        break;
    }
}

$error = '';
$success = '';

if (!$client) {
    http_response_code(404);
}

if ($client && (!empty($client['portal_access_cut']) || (empty($client['access_enabled']) && !empty($client['portal_enabled'])))) {
    $error = 'L acces a ce portail a ete coupe par le centre de controle.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client && $error === '') {
    $password = trim((string) ($_POST['password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['password_confirm'] ?? ''));

    if ($password === '' || strlen($password) < 6) {
        $error = 'Definis un mot de passe d au moins 6 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $error = 'La confirmation du mot de passe ne correspond pas.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $portalEmail = trim((string) ($client['portal_email'] ?? $client['email'] ?? ''));

        $client['portal_enabled'] = true;
        $client['access_enabled'] = true;
        $client['status'] = $client['status'] ?? 'Actif';
        $client['portal_email'] = $portalEmail;
        $client['password_hash'] = $hash;
        $client['portal_password_hash'] = $hash;
        $client['password_defined_at'] = date('c');
        $client['updated_at'] = date('c');

        $clients[$clientIndex] = $client;
        $store['clients'] = $clients;

        $linkedUserIndex = null;
        foreach ($users as $index => $user) {
            if (!is_array($user)) {
                continue;
            }

            $userClientId = (string) ($user['client_id'] ?? '');
            $clientId = (string) ($client['id'] ?? '');
            $userEmail = trim((string) ($user['email'] ?? ''));

            if (($clientId !== '' && $userClientId === $clientId) || ($portalEmail !== '' && strcasecmp($userEmail, $portalEmail) === 0)) {
                $linkedUserIndex = $index;
                break;
            }
        }

        $userPayload = [
            'id' => $linkedUserIndex !== null ? ($users[$linkedUserIndex]['id'] ?? uniqid('usr_', true)) : uniqid('usr_', true),
            'name' => (string) ($client['name'] ?? 'Abonne VIGILANCE'),
            'email' => $portalEmail,
            'role' => 'client',
            'client_id' => $client['id'] ?? null,
            'password_hash' => $hash,
            'access_enabled' => true,
            'status' => 'active',
            'updated_at' => date('c'),
        ];

        if ($linkedUserIndex !== null) {
            $users[$linkedUserIndex] = array_merge($users[$linkedUserIndex], $userPayload);
        } else {
            $users[] = $userPayload;
        }

        $store['users'] = array_values($users);
        $saveStore($store);

        $_SESSION['user'] = [
            'id' => $userPayload['id'],
            'name' => $userPayload['name'],
            'email' => $userPayload['email'],
            'role' => 'client',
            'client_id' => $client['id'] ?? null,
        ];
        $_SESSION['client_id'] = $client['id'] ?? null;
        $_SESSION['portal_email'] = $portalEmail;

        if (function_exists('vg_login_user')) {
            vg_login_user($_SESSION['user']);
        }

        header('Location: ' . vg_url('client/dashboard.php'));
        exit;
    }
}

$clientName = $client ? (string) ($client['name'] ?? 'Abonne VIGILANCE') : 'Lien indisponible';
$clientEmail = $client ? (string) ($client['portal_email'] ?? $client['email'] ?? '') : '';
$clientAddress = $client ? trim(implode(' • ', array_filter([
    (string) ($client['address'] ?? ''),
    (string) ($client['commune'] ?? ''),
    (string) ($client['quartier'] ?? ''),
    (string) ($client['avenue'] ?? ''),
]))) : '';
$portalPosterUrl = vg_url('assets/images/client-portal-visual.svg?v=20260627-vigilance');
$portalImageUrl = $portalPosterUrl;
$hasPortalVideo = false;
$hasPortalImage = true;
$portalMediaUrl = $portalPosterUrl;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace abonne direct | VIGILANCE Security</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/app.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(vg_url('assets/css/ops-next.css?v=20260711-auditbrand2'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .auth-card.auth-card--portal-media {
            width: min(1240px, calc(100% - 24px));
            margin-inline: auto;
        }

        .auth-grid.auth-grid--portal-media {
            grid-template-columns: minmax(360px, 1fr) minmax(420px, 540px);
            gap: 24px;
            align-items: start;
        }

        .auth-grid.auth-grid--portal-media > .auth-panel {
            min-width: 0;
        }

        .auth-grid.auth-grid--portal-media > .auth-highlight {
            min-width: 0;
        }

        .auth-highlight.auth-highlight--portal-media {
            position: relative;
            overflow: hidden;
        }

        .portal-video-shell {
            width: 100%;
            max-width: 540px;
            margin: 6px auto 22px;
        }

        .portal-video-frame {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid rgba(83, 152, 222, 0.18);
            background: rgba(7, 15, 28, 0.96);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.34);
        }

        .portal-video-frame video,
        .portal-video-frame img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: #050b14;
        }

        .portal-video-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 2;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(5, 14, 26, 0.76);
            border: 1px solid rgba(131, 193, 243, 0.2);
            color: #dff2ff;
            font-size: 0.72rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .portal-video-note {
            margin-top: 10px;
            color: #83c1f3;
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .portal-video-action {
            position: absolute;
            right: 14px;
            bottom: 14px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(5, 14, 26, 0.82);
            border: 1px solid rgba(131, 193, 243, 0.24);
            color: #f3f8ff;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        @media (max-width: 980px) {
            .auth-card.auth-card--portal-media {
                width: min(860px, calc(100% - 24px));
            }

            .auth-grid.auth-grid--portal-media {
                grid-template-columns: 1fr;
            }

            .portal-video-shell {
                width: 100%;
                margin: 0 0 18px;
            }

            .auth-grid.auth-grid--portal-media {
                gap: 18px;
            }
        }
    </style>
</head>
<body class="auth-shell">
    <main class="auth-card auth-card--portal-media">
        <div class="auth-layout">
            <div class="auth-brand">
                <img src="<?= htmlspecialchars(vg_url('brand-media.php?v=20260715-official-logo'), ENT_QUOTES, 'UTF-8'); ?>" alt="VIGILANCE Security" class="auth-logo">
                <div>
                    <p class="eyebrow">Espace abonne direct</p>
                    <h1><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p>Ce lien est prevu pour ouvrir votre espace rapidement, definir votre mot de passe une seule fois puis acceder a vos cameras, alertes et paiements.</p>
                </div>
            </div>

            <?php if ($client): ?>
                <section class="auth-kpis">
                    <article class="auth-kpi"><span>Abonnement</span><strong><?= htmlspecialchars((string) ($client['subscription'] ?? 'Abonne'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                    <article class="auth-kpi"><span>Acces</span><strong><?= !empty($client['access_enabled']) ? 'Portail actif' : 'Sous controle OPS'; ?></strong></article>
                    <article class="auth-kpi"><span>Adresse</span><strong><?= htmlspecialchars((string) ($client['commune'] ?? 'Site client'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                </section>
            <?php endif; ?>

        <?php if (!$client): ?>
            <section class="panel danger-panel">
                <h2>Lien introuvable</h2>
                <p>Ce lien client n est plus valide ou n existe pas encore dans le systeme.</p>
                <a class="btn btn-primary" href="<?= htmlspecialchars(vg_url(), ENT_QUOTES, 'UTF-8'); ?>">Retour a l accueil</a>
            </section>
        <?php else: ?>
            <div class="auth-grid auth-grid--portal-media">
                <section class="auth-panel">
                    <div class="portal-intro">
                        <div>
                            <h2>Definir mon mot de passe</h2>
                            <p>Ce lien ouvre directement votre espace abonne. Definissez votre mot de passe une seule fois, puis entrez dans votre espace avec vos informations, vos cameras, vos alertes et vos paiements.</p>
                        </div>
                        <div class="portal-meta">
                            <span class="status-pill"><?= htmlspecialchars((string) ($client['subscription'] ?? 'Abonne'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="status-pill"><?= !empty($client['access_enabled']) ? 'Acces actif' : 'Acces coupe'; ?></span>
                        </div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif ($success !== ''): ?>
                        <div class="flash flash-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" class="stack">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <label>
                            <span>Nouveau mot de passe</span>
                            <input type="password" name="password" autocomplete="new-password" required>
                        </label>
                        <label>
                            <span>Confirmer le mot de passe</span>
                            <input type="password" name="password_confirm" autocomplete="new-password" required>
                        </label>
                        <div class="button-row">
                            <button type="submit" class="btn btn-primary">Entrer dans mon portail</button>
                            <a class="btn btn-secondary" href="<?= htmlspecialchars(vg_url(), ENT_QUOTES, 'UTF-8'); ?>">Retour accueil</a>
                        </div>
                    </form>
                </section>

                <section class="auth-highlight auth-highlight--portal-media">
                    <div class="portal-video-shell">
                        <div class="portal-video-frame">
                            <div class="portal-video-badge">Espace abonne</div>
                            <?php if ($hasPortalVideo): ?>
                                <video controls autoplay muted loop playsinline poster="<?= htmlspecialchars($hasPortalImage ? $portalImageUrl : $portalPosterUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <source src="<?= htmlspecialchars($portalVideoUrl, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                    Votre navigateur ne peut pas lire cette vidéo.
                                </video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($hasPortalImage ? $portalImageUrl : $portalPosterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Visuel portail client VIGILANCE">
                            <?php endif; ?>
                            <a class="portal-video-action" href="<?= htmlspecialchars($portalMediaUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Voir le visuel premium</a>
                        </div>
                        <div class="portal-video-note">Le visuel d acces client reste maintenant aligne sur la marque VIGILANCE, sans ancien logo ni ancien nom.</div>
                    </div>

                    <h2>Informations du site</h2>
                    <div class="auth-role-list">
                        <div class="auth-role-card">
                            <strong>Email portail</strong>
                            <p><?= htmlspecialchars($clientEmail !== '' ? $clientEmail : 'Non renseigne', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="auth-role-card">
                            <strong>Adresse du site</strong>
                            <p><?= htmlspecialchars($clientAddress !== '' ? $clientAddress : 'Adresse non complete', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="auth-role-card">
                            <strong>Apres activation</strong>
                            <p>Vous pourrez suivre vos cameras, vos alertes, vos demandes d'appel centre et l'historique de vos interventions.</p>
                        </div>
                    </div>
                </section>
            </div>
        <?php endif; ?>
        </div>
    </main>
</body>
</html>
