<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/cors.php';

vg_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée. Utiliser POST.']);
    exit;
}

$body  = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
$email = trim((string) ($body['email'] ?? $_POST['email'] ?? ''));
$pass  = trim((string) ($body['password'] ?? $_POST['password'] ?? ''));

if ($email === '' || $pass === '') {
    http_response_code(400);
    echo json_encode(['error' => 'email et password requis.']);
    exit;
}

$user = vg_authenticate($email, $pass);

if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Identifiants incorrects ou accès désactivé.']);
    exit;
}

$token = vg_jwt_issue($user);

echo json_encode([
    'token' => $token,
    'user'  => [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ],
    'expires_in' => 86400 * 7,
]);
