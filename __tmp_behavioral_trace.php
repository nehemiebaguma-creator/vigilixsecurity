<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/behavioral.php';
$store = vgx_store();
$admin = [];
foreach (($store['users'] ?? []) as $u) { if (is_array($u) && (($u['role'] ?? '') === 'admin')) { $admin = $u; break; } }
$_SESSION['user'] = function_exists('vg_prepare_user_session') ? vg_prepare_user_session($admin) : $admin;
$tests = [
  'db_available' => function() { return vg_behavioral_db_available(); },
  'settings' => function() { return vg_behavioral_settings(); },
  'camera_sources' => function() { return vg_behavioral_camera_sources(); },
  'visible_sessions' => function() use ($admin) { return vg_behavioral_visible_sessions($admin); },
  'visible_reports' => function() use ($admin) { return vg_behavioral_visible_reports($admin); },
  'visible_comments' => function() use ($admin) { return vg_behavioral_visible_comments($admin); },
  'visible_authorizations' => function() use ($admin) { return vg_behavioral_visible_authorizations($admin); },
];
foreach ($tests as $name => $fn) {
  $start = microtime(true);
  fwrite(STDOUT, "start:$name\n");
  fflush(STDOUT);
  $result = $fn();
  $elapsed = microtime(true) - $start;
  fwrite(STDOUT, "done:$name:$elapsed:" . (is_array($result) ? count($result) : (($result === true || $result === false) ? (int) $result : 0)) . "\n");
  fflush(STDOUT);
}
?>
