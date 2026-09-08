<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

vg_fulcrum_api_require_admin();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string) ($_REQUEST['action'] ?? 'snapshot'));

if ($method !== 'GET') {
    vg_csrf_check();
}

switch ($action) {
    case 'refresh':
        $snapshot = vg_fulcrum_refresh_snapshot(true, 0);
        vg_fulcrum_log_audit('manual_refresh', 'module', 'fulcrum', ['channel' => 'api']);
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => $snapshot]);
        break;

    case 'delete_event':
        $rowId = trim((string) ($_POST['row_id'] ?? $_POST['id'] ?? ''));
        if ($rowId === '' || !vg_fulcrum_delete_event($rowId)) {
            vg_fulcrum_api_respond(['ok' => false, 'message' => 'Evenement introuvable.'], 404);
        }
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => vg_fulcrum_refresh_snapshot(true, 0)]);
        break;

    case 'purge_collection':
        $collection = trim((string) ($_POST['collection'] ?? ''));
        if ($collection === '' || !vg_fulcrum_purge_collection($collection)) {
            vg_fulcrum_api_respond(['ok' => false, 'message' => 'Collection non autorisee.'], 400);
        }
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => vg_fulcrum_store_snapshot()]);
        break;

    case 'clear_suppressions':
        vg_fulcrum_clear_suppressions();
        vg_fulcrum_log_audit('clear_suppressions', 'module', 'fulcrum', ['channel' => 'api']);
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => vg_fulcrum_refresh_snapshot(true, 0)]);
        break;

    case 'snapshot':
    default:
        $force = trim((string) ($_GET['force'] ?? '')) === '1';
        vg_fulcrum_api_respond(['ok' => true, 'snapshot' => vg_fulcrum_refresh_snapshot($force, 30)]);
        break;
}
