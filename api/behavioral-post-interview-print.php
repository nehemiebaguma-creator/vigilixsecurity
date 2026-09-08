<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/behavioral.php';

$user = function_exists('vg_api_auth') ? vg_api_auth() : null;
if (!is_array($user) || $user === []) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Non authentifie.';
    exit;
}

$caps = vg_behavioral_capabilities($user);
if (empty($caps['post_dossier']) || empty($caps['export'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Fiche post-entretien reservee a l administrateur.';
    exit;
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'session_id manquant.';
    exit;
}

$payload = vg_behavioral_export_payload($sessionId, $user);
if (!is_array($payload)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Dossier introuvable ou non exportable.';
    exit;
}

$session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
$dossier = is_array($payload['post_interview_dossier'] ?? null) ? $payload['post_interview_dossier'] : null;
if (!is_array($dossier)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Aucune fiche post-entretien disponible pour cette session.';
    exit;
}

$authorizationRow = [
    'id' => uniqid('bha_', true),
    'session_id' => $sessionId,
    'authorization_type' => 'post_interview_dossier_print',
    'status' => 'granted',
    'subject_label' => (string) ($session['subject_label'] ?? ''),
    'granted_by_role' => strtolower(trim((string) ($user['role'] ?? 'admin'))),
    'granted_by_id' => vg_behavioral_actor_id($user),
    'granted_to_role' => strtolower(trim((string) ($user['role'] ?? 'admin'))),
    'granted_to_id' => vg_behavioral_actor_id($user),
    'details' => ['printed_at' => vg_behavioral_now(), 'export_kind' => 'post_interview_print'],
    'created_at' => vg_behavioral_now(),
];
vg_behavioral_store_append_rows('behavioral_authorizations', [$authorizationRow]);
vg_behavioral_db_sync_row('behavioral_authorizations', $authorizationRow);

$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$subject = is_array($dossier['subject'] ?? null) ? $dossier['subject'] : [];
$interview = is_array($dossier['interview'] ?? null) ? $dossier['interview'] : [];
$evidence = is_array($dossier['evidence'] ?? null) ? $dossier['evidence'] : [];
$quality = is_array($evidence['quality'] ?? null) ? $evidence['quality'] : [];
$analysis = is_array($dossier['analysis'] ?? null) ? $dossier['analysis'] : [];
$orientation = is_array($dossier['internal_orientation'] ?? null) ? $dossier['internal_orientation'] : [];
$integrity = is_array($dossier['integrity'] ?? null) ? $dossier['integrity'] : [];
$timeline = is_array($dossier['evidence_timeline'] ?? null) ? $dossier['evidence_timeline'] : [];
$steps = is_array($dossier['admin_next_steps'] ?? null) ? $dossier['admin_next_steps'] : [];
$questions = is_array($dossier['relance_questions'] ?? null) ? $dossier['relance_questions'] : [];
$chain = is_array($dossier['chain_of_custody'] ?? null) ? $dossier['chain_of_custody'] : [];
$limitations = is_array($dossier['limitations'] ?? null) ? $dossier['limitations'] : [];
$warnings = is_array($quality['warnings'] ?? null) ? $quality['warnings'] : [];
$logoUrl = function_exists('vg_url') ? vg_url('assets/images/vigilance-wordmark.svg') : '../assets/images/vigilance-wordmark.svg';

$listHtml = static function (array $items, string $empty, callable $h): string {
    if ($items === []) {
        return '<li>' . $h($empty) . '</li>';
    }

    $html = '';
    foreach ($items as $item) {
        if (is_array($item)) {
            $label = trim((string) ($item['label'] ?? $item['step'] ?? 'Trace'));
            $detail = trim((string) ($item['detail'] ?? $item['note'] ?? $item['summary'] ?? ''));
            $meta = trim((string) (($item['time'] ?? '') ?: ($item['at'] ?? '')));
            $html .= '<li><strong>' . $h($label) . '</strong>';
            if ($detail !== '') {
                $html .= '<span>' . $h($detail) . '</span>';
            }
            if ($meta !== '') {
                $html .= '<small>' . $h($meta) . '</small>';
            }
            $html .= '</li>';
            continue;
        }

        $html .= '<li>' . $h($item) . '</li>';
    }

    return $html;
};

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche post-entretien | VIGILANCE Security</title>
    <style>
        :root{--ink:#101827;--muted:#506174;--line:#d9e3ee;--blue:#0074c8;--gold:#b7791f;--danger:#c81e3a;--paper:#f7fbff}
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(135deg,#eaf5ff,#fff 38%,#fff7e6);color:var(--ink);font-family:"Segoe UI",Tahoma,sans-serif}
        .toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:flex-end;gap:10px;padding:14px 22px;background:rgba(255,255,255,.88);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
        .toolbar button{border:0;border-radius:999px;padding:11px 18px;background:var(--blue);color:#fff;font-weight:800;letter-spacing:.06em;cursor:pointer}
        .page{width:min(1120px,calc(100% - 32px));margin:24px auto 40px;background:#fff;border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 70px rgba(16,31,54,.16);overflow:hidden}
        header{display:grid;grid-template-columns:240px 1fr;gap:24px;padding:34px 38px;background:linear-gradient(135deg,#071526,#102947 55%,#071526);color:#fff}
        header img{width:210px;max-height:90px;object-fit:contain;border-radius:18px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);padding:10px}
        header h1{margin:0;font-size:2.15rem;line-height:1.05;letter-spacing:-.03em}
        header p{margin:12px 0 0;color:#c9dcf1;line-height:1.55}
        .warning{margin:0;padding:16px 38px;background:#fff7df;border-bottom:1px solid #f0d9a6;color:#573b08;font-weight:700}
        .content{display:grid;gap:22px;padding:30px 38px 38px}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .card{padding:17px 18px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(180deg,#fff,#f7fbff)}
        .card span,.section-title span{display:block;color:var(--blue);font-size:.72rem;text-transform:uppercase;letter-spacing:.16em;font-weight:900}
        .card strong{display:block;margin-top:8px;font-size:1.18rem;line-height:1.28}
        .card small{display:block;margin-top:8px;color:var(--muted);line-height:1.5}
        .orientation{border-color:#f0d9a6;background:linear-gradient(180deg,#fff8e8,#fff)}
        .orientation span{color:var(--gold)}
        .risk{border-color:#bde3ff;background:linear-gradient(180deg,#edf8ff,#fff)}
        .section{display:grid;gap:12px;padding:20px;border:1px solid var(--line);border-radius:22px;background:#fff}
        .section-title{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
        .section-title h2{margin:0;font-size:1.2rem}
        ul{margin:0;padding:0;list-style:none;display:grid;gap:10px}
        li{padding:12px 14px;border-radius:15px;background:var(--paper);border:1px solid #e3edf7;line-height:1.5}
        li strong{display:block;margin-bottom:4px}
        li span,li small{display:block;color:var(--muted)}
        .columns{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .hash{padding:14px 16px;border-radius:16px;background:#071526;color:#d9ecff;word-break:break-word;font-family:Consolas,monospace}
        footer{padding:18px 38px;border-top:1px solid var(--line);color:var(--muted);font-size:.86rem;line-height:1.55}
        @media (max-width:850px){header,.grid,.columns{grid-template-columns:1fr}.page{width:calc(100% - 18px);border-radius:18px}header,.content,.warning,footer{padding-left:20px;padding-right:20px}}
        @media print{body{background:#fff}.toolbar{display:none}.page{width:100%;margin:0;border:0;border-radius:0;box-shadow:none}.section,.card{break-inside:avoid}header{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimer / enregistrer PDF</button>
    </div>
    <main class="page">
        <header>
            <img src="<?= $h($logoUrl) ?>" alt="VIGILANCE Security">
            <div>
                <h1><?= $h($dossier['title'] ?? 'Dossier post-entretien comportemental') ?></h1>
                <p>Fiche interne admin generee apres analyse comportementale. Elle consolide les preuves, la timeline, les limites et la chaine de garde sans remplacer une autorite officielle.</p>
            </div>
        </header>
        <p class="warning"><?= $h($dossier['legal_warning'] ?? 'Document interne VIGILANCE, non officiel.') ?></p>
        <section class="content">
            <div class="grid">
                <article class="card">
                    <span>Personne</span>
                    <strong><?= $h($subject['label'] ?? 'Personne analysee') ?></strong>
                    <small><?= $h(($subject['location_label'] ?? '') ?: 'Lieu non renseigne') ?></small>
                </article>
                <article class="card orientation">
                    <span>Orientation</span>
                    <strong><?= $h($orientation['label'] ?? 'Lecture interne') ?></strong>
                    <small><?= $h($orientation['action_level'] ?? 'Validation humaine obligatoire.') ?></small>
                </article>
                <article class="card risk">
                    <span>Indice relecture</span>
                    <strong><?= number_format((float) ($analysis['risk_score'] ?? 0), 1, ',', ' ') ?> / 100</strong>
                    <small>Stress <?= number_format((float) ($analysis['stress_score'] ?? 0), 1, ',', ' ') ?> | coherence <?= number_format((float) ($analysis['coherence_score'] ?? 0), 1, ',', ' ') ?></small>
                </article>
                <article class="card">
                    <span>Qualite preuve</span>
                    <strong><?= number_format((float) ($quality['score'] ?? 0), 1, ',', ' ') ?> / 100</strong>
                    <small><?= $h($quality['label'] ?? 'preuve non evaluee') ?></small>
                </article>
            </div>
            <div class="section">
                <div class="section-title">
                    <div><span>Synthese</span><h2>Lecture admin</h2></div>
                </div>
                <p><?= $h($analysis['summary'] ?? 'Synthese non disponible.') ?></p>
                <p><strong>Verrou humain:</strong> <?= $h($analysis['decision_gate'] ?? 'Validation humaine obligatoire.') ?></p>
            </div>
            <div class="columns">
                <section class="section">
                    <div class="section-title"><div><span>Timeline</span><h2>Moments exploitables</h2></div></div>
                    <ul><?= $listHtml(array_slice($timeline, 0, 14), 'Aucune timeline exploitable.', $h) ?></ul>
                </section>
                <section class="section">
                    <div class="section-title"><div><span>Relance</span><h2>Questions IA utiles</h2></div></div>
                    <ul><?= $listHtml($questions, 'Aucune question automatique disponible.', $h) ?></ul>
                </section>
            </div>
            <div class="columns">
                <section class="section">
                    <div class="section-title"><div><span>Action</span><h2>Suites admin</h2></div></div>
                    <ul><?= $listHtml($steps, 'Conserver le dossier et demander validation humaine.', $h) ?></ul>
                </section>
                <section class="section">
                    <div class="section-title"><div><span>Fragilites</span><h2>Points a surveiller</h2></div></div>
                    <ul><?= $listHtml($warnings, 'Aucune fragilite majeure signalee.', $h) ?></ul>
                </section>
            </div>
            <section class="section">
                <div class="section-title"><div><span>Trace</span><h2>Chaine de garde</h2></div></div>
                <ul><?= $listHtml($chain, 'Aucune trace consolidee.', $h) ?></ul>
            </section>
            <section class="section">
                <div class="section-title"><div><span>Limites</span><h2>Cadre obligatoire</h2></div></div>
                <ul><?= $listHtml($limitations, 'Document interne uniquement.', $h) ?></ul>
            </section>
            <div class="hash">Empreinte <?= $h($integrity['hash_algorithm'] ?? 'sha256') ?>: <?= $h($integrity['fingerprint'] ?? 'non calculee') ?></div>
        </section>
        <footer>
            Genere le <?= $h($dossier['generated_at'] ?? vg_behavioral_now()) ?> pour la session <?= $h($session['session_code'] ?? $sessionId) ?>. Usage interne VIGILANCE Security seulement.
        </footer>
    </main>
</body>
</html>
