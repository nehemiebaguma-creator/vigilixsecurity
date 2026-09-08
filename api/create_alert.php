<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/cors.php';
require __DIR__ . '/../includes/validation.php';

vg_cors_headers();
vg_require_role('admin');

if (!vg_is_post()) {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'message' => 'Methode non autorisee.'));
    exit;
}

$clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
$type     = isset($_POST['type'])     ? trim($_POST['type'])     : 'SOS';
$severity = isset($_POST['severity']) ? trim($_POST['severity']) : 'Critique';
$message  = isset($_POST['message'])  ? trim($_POST['message'])  : 'Alerte declenchee.';
$lat      = isset($_POST['latitude'])  ? trim($_POST['latitude'])  : '';
$lng      = isset($_POST['longitude']) ? trim($_POST['longitude']) : '';

// Validation stricte
$errors = vg_validate(
    ['client_id' => $clientId, 'type' => $type, 'severity' => $severity, 'message' => $message],
    ['client_id' => 'required|integer', 'type' => 'required|max:50', 'severity' => 'required|max:50', 'message' => 'required|max:500']
);

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Données invalides', 'errors' => $errors));
    exit;
}

if ($clientId <= 0) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Identifiant client invalide.'));
    exit;
}

// Whitelist des types et severités autorisées
$allowedTypes = ['SOS', 'ALARME', 'INTRUSION', 'INCENDIE', 'MEDICAL', 'AUTRE'];
$allowedSeverities = ['Critique', 'Haute', 'Moyenne', 'Basse'];

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Type d\'alerte invalide.'));
    exit;
}

if (!in_array($severity, $allowedSeverities, true)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Severité d\'alerte invalide.'));
    exit;
}

if ($lat !== '' && (!is_numeric($lat) || $lat < -90 || $lat > 90)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Latitude invalide.'));
    exit;
}

if ($lng !== '' && (!is_numeric($lng) || $lng < -180 || $lng > 180)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => 'Longitude invalide.'));
    exit;
}

$alert = vg_create_alert(array(
    'client_id' => $clientId,
    'type'      => $type,
    'severity'  => $severity,
    'message'   => $message,
    'latitude'  => $lat,
    'longitude' => $lng,
));

if (!$alert) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'message' => 'Client introuvable.'));
    exit;
}

// Invalide les caches alerts et publie l'événement temps-réel
if (function_exists('vgx_cache_del')) {
    vgx_cache_del('vigilix:cache:alerts', 'vigilix:store:main', 'vigilance:cache:alerts', 'vigilance:store:main');
}
if (function_exists('vgx_publish_realtime_event')) {
    vgx_publish_realtime_event('vigilance:alerts:new', $alert);
} elseif (function_exists('vgx_cache_publish')) {
    vgx_cache_publish('vigilance:alerts:new', $alert);
    vgx_cache_publish('vigilix:alerts:new', $alert);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('ok' => true, 'alert' => $alert));
