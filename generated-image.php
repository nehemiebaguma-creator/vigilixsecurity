<?php

require __DIR__ . '/includes/bootstrap.php';

$index = isset($_GET['i']) ? (int) $_GET['i'] : 0;
$files = vg_generated_image_files();

if (!isset($files[$index]) || !is_file($files[$index])) {
    http_response_code(404);
    exit('Image non disponible.');
}

$path = $files[$index];
header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
