<?php

require __DIR__ . '/../includes/bootstrap.php';
vg_require_role('admin');

if (!vg_is_post()) {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'message' => 'Methode non autorisee.'));
    exit;
}

$alertId  = trim((string) ($_POST['alert_id'] ?? ''));
$agentIds = isset($_POST['agent_ids']) ? (array) $_POST['agent_ids'] : array();
$redirect = trim((string) ($_POST['redirect'] ?? 'admin/interventions.php'));
$wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($redirect === '' || preg_match('/^[a-z]+:\/\//i', $redirect)) {
    $redirect = 'admin/interventions.php';
}
$redirect = ltrim($redirect, '/');

if ($alertId === '') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'message' => 'Identifiant alerte invalide.'));
    exit;
}

if (empty($agentIds)) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'message' => 'Aucun agent selectionne.'));
    exit;
}

$intervention = vg_assign_agents($alertId, $agentIds);

if (!$intervention) {
    if ($wantsJson) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'message' => 'Impossible d affecter les agents a cette alerte.'));
        exit;
    }
    vg_flash('error', 'Impossible d affecter les agents a cette alerte.');
    vg_redirect($redirect !== '' ? $redirect : 'admin/control-center.php');
}

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => true, 'intervention' => $intervention));
    exit;
}

vg_flash('success', 'Agents assignes a l alerte.');
vg_redirect($redirect !== '' ? $redirect : 'admin/interventions.php');
