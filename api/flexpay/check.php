<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = vg_current_user();
if ($user === []) {
    header('Location: ' . vg_url('auth/login.php'));
    exit;
}

$returnPath = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? 'client/payments.php'));
if ($returnPath === '' || str_contains($returnPath, '://') || str_starts_with($returnPath, '//')) {
    $returnPath = 'client/payments.php';
}
$returnUrl = vg_url($returnPath);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $returnUrl);
    exit;
}

$transactionId = trim((string) ($_POST['transaction_id'] ?? ''));
if ($transactionId === '') {
    vg_flash('error', 'Transaction Flexpay introuvable.');
    header('Location: ' . $returnUrl);
    exit;
}

$role = strtolower(trim((string) ($user['role'] ?? '')));
$client = null;
if ($role === 'client') {
    $client = vg_portal_require_client($user);
}

$transaction = vgx_find_payment_transaction($transactionId, $role === 'client' ? (string) ($client['id'] ?? '') : '');
if (!is_array($transaction)) {
    vg_flash('error', 'La transaction demandee est introuvable ou non autorisee.');
    header('Location: ' . $returnUrl);
    exit;
}

$orderNumber = trim((string) ($transaction['order_number'] ?? ''));
if ($orderNumber === '') {
    vg_flash('error', 'Cette transaction ne possede pas encore de numero de commande Flexpay.');
    header('Location: ' . $returnUrl);
    exit;
}

if (!vg_flexpay_is_configured()) {
    vg_flash('error', 'Flexpay n est pas configure pour verifier cette transaction.');
    header('Location: ' . $returnUrl);
    exit;
}

try {
    $check = vg_flexpay_check_payment($orderNumber);
    $transactionData = is_array($check['transaction'] ?? null) ? $check['transaction'] : [];
    $changes = [
        'provider_code' => $check['code'] ?? null,
        'provider_message' => (string) ($check['message'] ?? ''),
        'check_payload' => $check['response'] ?? [],
        'order_number' => (string) ($transactionData['orderNumber'] ?? $orderNumber),
    ];

    if (!empty($check['paid'])) {
        vgx_finalize_payment_transaction($transactionId, array_merge($changes, [
            'status' => 'paye',
        ]));
        vg_flash('success', 'Paiement Flexpay confirme avec succes.');
    } elseif (!empty($check['success'])) {
        vgx_update_payment_transaction($transactionId, array_merge($changes, [
            'status' => 'echec',
        ]));
        vg_flash('error', 'Flexpay a repondu mais la transaction est marquee en echec.');
    } else {
        vgx_update_payment_transaction($transactionId, $changes);
        vg_flash('error', (string) ($check['message'] ?? 'Verification Flexpay impossible pour le moment.'));
    }
} catch (Throwable $error) {
    vgx_update_payment_transaction($transactionId, [
        'provider_message' => $error->getMessage(),
    ]);
    vg_flash('error', 'Echec de verification Flexpay: ' . $error->getMessage());
}

header('Location: ' . $returnUrl);
exit;
