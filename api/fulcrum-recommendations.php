<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

vg_fulcrum_api_require_admin();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string) ($_REQUEST['action'] ?? 'list'));

if ($method !== 'GET') {
    vg_csrf_check();
}

switch ($action) {
    case 'validate':
        $rowId = trim((string) ($_POST['row_id'] ?? $_POST['id'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $row = vg_fulcrum_set_recommendation_status($rowId, 'validated', $note);
        if (!is_array($row)) {
            vg_fulcrum_api_respond(['ok' => false, 'message' => 'Recommendation introuvable.'], 404);
        }
        vg_fulcrum_api_respond(['ok' => true, 'recommendation' => $row]);
        break;

    case 'standby':
        $rowId = trim((string) ($_POST['row_id'] ?? $_POST['id'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $row = vg_fulcrum_set_recommendation_status($rowId, 'standby', $note);
        if (!is_array($row)) {
            vg_fulcrum_api_respond(['ok' => false, 'message' => 'Recommendation introuvable.'], 404);
        }
        vg_fulcrum_api_respond(['ok' => true, 'recommendation' => $row]);
        break;

    case 'delete':
        $rowId = trim((string) ($_POST['row_id'] ?? $_POST['id'] ?? ''));
        if ($rowId === '' || !vg_fulcrum_delete_recommendation($rowId)) {
            vg_fulcrum_api_respond(['ok' => false, 'message' => 'Recommendation introuvable.'], 404);
        }
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => vg_fulcrum_refresh_snapshot(true, 0)]);
        break;

    case 'list':
    default:
        $snapshot = vg_fulcrum_refresh_snapshot(false, 30);
        vg_fulcrum_api_respond([
            'ok' => true,
            'generated_at' => (string) ($snapshot['generated_at'] ?? vg_fulcrum_now()),
            'recommendations' => array_values((array) ($snapshot['recommendations'] ?? [])),
        ]);
        break;
}
