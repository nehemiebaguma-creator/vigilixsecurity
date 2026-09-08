<?php

declare(strict_types=1);

ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/vision.php';

$options = getopt('', ['interval::', 'limit::', 'cooldown::', 'failure-cooldown::']);

$intervalSeconds = max(1, min(10, (int) ($options['interval'] ?? 1)));
$limit = max(1, min(8, (int) ($options['limit'] ?? 1)));
$cooldownSeconds = max(1, min(30, (int) ($options['cooldown'] ?? 1)));
$failureCooldownSeconds = max(5, min(120, (int) ($options['failure-cooldown'] ?? 15)));

$storageDir = rtrim(vg_storage_dir(), '/\\');
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}

$lockPath = $storageDir . '/camera-ai-worker.lock';
$statusPath = $storageDir . '/camera-ai-worker-status.json';
$stopPath = $storageDir . '/camera-ai-worker.stop';
$startedAt = date('c');
$pid = function_exists('getmypid') ? (int) getmypid() : 0;

$lockHandle = @fopen($lockPath, 'c+');
if (!is_resource($lockHandle)) {
    fwrite(STDERR, "Unable to open camera worker lock.\n");
    exit(1);
}

if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}

if (is_file($stopPath)) {
    @unlink($stopPath);
}

$writeStatus = static function (array $status) use ($statusPath): void {
    @file_put_contents(
        $statusPath,
        json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
};

$state = [
    'running' => true,
    'pid' => $pid,
    'started_at' => $startedAt,
    'last_tick_at' => $startedAt,
    'last_activity_at' => '',
    'last_results_count' => 0,
    'last_ok_count' => 0,
    'last_error_count' => 0,
    'eligible_camera_count' => 0,
    'due_camera_count' => 0,
    'primary_camera_id' => '',
    'idle_reason' => '',
    'last_camera_id' => '',
    'last_camera_error' => '',
    'last_camera_error_at' => '',
    'interval_seconds' => $intervalSeconds,
    'cooldown_seconds' => $cooldownSeconds,
    'failure_cooldown_seconds' => $failureCooldownSeconds,
];
$writeStatus($state);

register_shutdown_function(static function () use ($writeStatus, &$state, $stopPath, $lockHandle): void {
    $state['running'] = false;
    $state['last_tick_at'] = date('c');
    $writeStatus($state);

    if (is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }

    if (is_file($stopPath)) {
        @unlink($stopPath);
    }
});

while (true) {
    if (is_file($stopPath)) {
        break;
    }

    $loopStartedAt = microtime(true);
    $duePlan = function_exists('vgx_camera_analysis_plan')
        ? vgx_camera_analysis_plan($limit, $cooldownSeconds, $failureCooldownSeconds, true)
        : ['selected' => [], 'eligible_total' => 0, 'primary_camera_id' => ''];
    $allPlan = function_exists('vgx_camera_analysis_plan')
        ? vgx_camera_analysis_plan($limit, $cooldownSeconds, $failureCooldownSeconds, false)
        : ['selected' => [], 'eligible_total' => 0, 'primary_camera_id' => ''];
    $results = vgx_camera_auto_analyze_due($limit, $cooldownSeconds, $failureCooldownSeconds);
    $okCount = 0;
    $errorCount = 0;
    $now = date('c');

    foreach ($results as $result) {
        if (!is_array($result)) {
            continue;
        }

        if (!empty($result['ok'])) {
            $okCount++;
            $state['last_camera_error'] = '';
            $state['last_camera_error_at'] = '';
            continue;
        }

        $errorCount++;
        $summary = is_array($result['summary'] ?? null) ? (array) $result['summary'] : [];
        $state['last_camera_id'] = (string) ($summary['camera_id'] ?? $state['primary_camera_id'] ?? '');
        $state['last_camera_error'] = (string) ($result['error'] ?? 'Analyse camera echouee.');
        $state['last_camera_error_at'] = $now;
    }

    $state['running'] = true;
    $state['last_tick_at'] = $now;
    $state['last_results_count'] = count($results);
    $state['last_ok_count'] = $okCount;
    $state['last_error_count'] = $errorCount;
    $state['eligible_camera_count'] = (int) ($allPlan['eligible_total'] ?? 0);
    $state['due_camera_count'] = count((array) ($duePlan['selected'] ?? []));
    $state['primary_camera_id'] = (string) ($duePlan['primary_camera_id'] ?? ($allPlan['primary_camera_id'] ?? ''));
    $state['idle_reason'] = '';
    if ($state['last_camera_error'] === '' && $state['primary_camera_id'] !== '' && function_exists('vgx_camera_intelligence_state')) {
        $cameraState = vgx_camera_intelligence_state($state['primary_camera_id']);
        if (!empty($cameraState['last_error'])) {
            $state['last_camera_id'] = $state['primary_camera_id'];
            $state['last_camera_error'] = (string) $cameraState['last_error'];
            $state['last_camera_error_at'] = (string) ($cameraState['last_error_at'] ?? '');
        }
    }
    if (count($results) === 0) {
        if ($state['eligible_camera_count'] <= 0) {
            $state['idle_reason'] = 'Aucune camera eligible a l analyse IA. Verifier URL flux, statut actif et regles camera.';
        } elseif ($state['due_camera_count'] <= 0) {
            $state['idle_reason'] = $state['last_camera_error'] !== ''
                ? 'Worker actif. Camera eligible, derniere tentative en echec; nouvelle tentative apres cooldown.'
                : 'Worker actif. Cameras eligibles mais pas encore dues, cooldown ou derniere analyse trop recente.';
        } else {
            $state['idle_reason'] = 'Worker actif. Plan IA disponible mais aucune analyse retournee par le moteur.';
        }
    }
    if ($okCount > 0 || $errorCount > 0) {
        $state['last_activity_at'] = $now;
    }
    $writeStatus($state);

    $remainingMicroseconds = (int) round(($intervalSeconds * 1000000) - ((microtime(true) - $loopStartedAt) * 1000000));
    usleep(max(100000, $remainingMicroseconds));
}
