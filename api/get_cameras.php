<?php

require __DIR__ . '/../includes/bootstrap.php';

$clientId = trim((string) ($_GET['client_id'] ?? ''));
$cameras = $clientId !== '' ? vgx_client_cameras($clientId) : vgx_cameras();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'ok' => true,
    'cameras' => $cameras,
));
