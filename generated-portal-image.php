<?php

declare(strict_types=1);

$directory = 'C:/Users/REGIDESO/.codex/generated_images/019e3111-057b-7ff3-a169-8f9f363c7d26';

if (!is_dir($directory)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Generated image directory not found.';
    exit;
}

$patterns = [
    $directory . '/*.png',
    $directory . '/*.jpg',
    $directory . '/*.jpeg',
    $directory . '/*.webp',
];

$files = [];
foreach ($patterns as $pattern) {
    $matches = glob($pattern);
    if (is_array($matches)) {
        $files = array_merge($files, $matches);
    }
}

if (empty($files)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No generated image found.';
    exit;
}

usort($files, static function (string $a, string $b): int {
    return filemtime($b) <=> filemtime($a);
});

$file = $files[0];
$extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
$mime = 'image/png';

if ($extension === 'jpg' || $extension === 'jpeg') {
    $mime = 'image/jpeg';
} elseif ($extension === 'webp') {
    $mime = 'image/webp';
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($file);
