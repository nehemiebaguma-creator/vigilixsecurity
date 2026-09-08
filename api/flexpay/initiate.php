<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

vg_require_role('client');

$client = vg_portal_require_client(vg_current_user());
$clientId = (string) ($client['id'] ?? '');
$returnPath = trim((string) ($_POST['return_to'] ?? 'client/payments.php'));
if ($returnPath === '' || str_contains($returnPath, '://') || str_starts_with($returnPath, '//')) {
    $returnPath = 'client/payments.php';
}
$returnUrl = vg_url($returnPath);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $returnUrl);
    exit;
}

if (!vg_flexpay_is_configured()) {
    vg_flash('error', 'Flexpay n est pas encore configure. Ajoutez le merchant code, le token et l URL publique dans la configuration.');
    header('Location: ' . $returnUrl);
    exit;
}

if (!vg_flexpay_is_enabled()) {
    vg_flash('error', 'Flexpay est configure mais desactive. Passez FLEXPAY_ENABLED a true pour ouvrir les paiements.');
    header('Location: ' . $returnUrl);
    exit;
}

$targetToken = trim((string) ($_POST['target_token'] ?? ''));
$targetType = strtolower(trim((string) ($_POST['target_type'] ?? 'payment')));
$targetId = trim((string) ($_POST['target_id'] ?? ''));
$network = strtolower(trim((string) ($_POST['network'] ?? 'auto'))) ?: 'auto';
$phone = vg_flexpay_normalize_phone((string) ($_POST['phone'] ?? (string) ($client['phone'] ?? '')));

if ($targetToken !== '' && str_contains($targetToken, '|')) {
    [$parsedTargetType, $parsedTargetId] = array_pad(explode('|', $targetToken, 2), 2, '');
    $targetType = strtolower(trim((string) $parsedTargetType));
    $targetId = trim((string) $parsedTargetId);
}

if ($phone === null) {
    vg_flash('error', 'Le numero doit etre valide au format 243XXXXXXXXX pour lancer le paiement mobile.');
    header('Location: ' . $returnUrl);
    exit;
}

if ($targetId === '' || !in_array($targetType, ['payment', 'invoice'], true)) {
    vg_flash('error', 'Selectionnez une facture ou un paiement en attente avant de lancer Flexpay.');
    header('Location: ' . $returnUrl);
    exit;
}

$invoiceId = '';
$paymentId = '';
$amount = 0.0;
$currency = 'USD';
$description = 'Paiement VIGILANCE Security';

if ($targetType === 'payment') {
    $payment = vgx_find_payment($targetId, $clientId);
    if (!is_array($payment)) {
        vg_flash('error', 'Le paiement demande est introuvable pour ce client.');
        header('Location: ' . $returnUrl);
        exit;
    }

    if (vgx_payment_status_is_paid((string) ($payment['status'] ?? ''))) {
        vg_flash('error', 'Ce paiement est deja marque comme paye.');
        header('Location: ' . $returnUrl);
        exit;
    }

    $paymentId = (string) ($payment['id'] ?? '');
    $invoiceId = (string) ($payment['invoice_id'] ?? '');
    $amount = (float) ($payment['amount'] ?? 0);
    $currency = (string) ($payment['currency'] ?? 'USD');
    $description = (string) ($payment['label_name'] ?? $payment['description'] ?? 'Paiement client VIGILANCE');
} else {
    $invoice = vgx_find_invoice($targetId, $clientId);
    if (!is_array($invoice)) {
        vg_flash('error', 'La facture demandee est introuvable pour ce client.');
        header('Location: ' . $returnUrl);
        exit;
    }

    if (vgx_payment_status_is_paid((string) ($invoice['status'] ?? ''))) {
        vg_flash('error', 'Cette facture est deja marquee comme payee.');
        header('Location: ' . $returnUrl);
        exit;
    }

    $invoiceId = (string) ($invoice['id'] ?? '');
    $amount = (float) ($invoice['amount'] ?? 0);
    $currency = (string) ($invoice['currency'] ?? 'USD');
    $description = (string) ($invoice['description'] ?? 'Facture client VIGILANCE');
}

if ($amount <= 0) {
    vg_flash('error', 'Le montant a payer est invalide.');
    header('Location: ' . $returnUrl);
    exit;
}

$transaction = vgx_create_payment_transaction([
    'provider' => 'flexpay',
    'client_id' => $clientId,
    'invoice_id' => $invoiceId,
    'payment_id' => $paymentId,
    'amount' => $amount,
    'currency' => $currency !== '' ? $currency : 'USD',
    'phone' => $phone,
    'network' => $network,
    'reference' => vg_flexpay_generate_reference($clientId),
    'description' => $description,
    'status' => 'draft',
    'callback_key' => bin2hex(random_bytes(16)),
]);

$urls = vg_flexpay_transaction_urls($transaction);
$transaction = vgx_update_payment_transaction((string) ($transaction['id'] ?? ''), [
    'callback_url' => $urls['callback'],
    'approve_url' => $urls['approve'],
    'cancel_url' => $urls['cancel'],
    'decline_url' => $urls['decline'],
]) ?? $transaction;

try {
    $gateway = vg_flexpay_initiate_mobile_payment($transaction);
    $newStatus = !empty($gateway['success']) ? 'processing' : 'echec';

    vgx_update_payment_transaction((string) ($transaction['id'] ?? ''), [
        'status' => $newStatus,
        'provider_code' => $gateway['code'] ?? null,
        'provider_message' => (string) ($gateway['message'] ?? ''),
        'provider_reference' => (string) ($gateway['provider_reference'] ?? ''),
        'order_number' => (string) ($gateway['order_number'] ?? ''),
        'request_payload' => $gateway['request'] ?? [],
        'response_payload' => $gateway['response'] ?? [],
    ]);

    if (!empty($gateway['success'])) {
        vg_flash('success', 'Demande Flexpay envoyee. Validez maintenant la transaction sur le telephone du client.');
    } else {
        vg_flash('error', (string) ($gateway['message'] ?? 'La demande Flexpay a ete refusee.'));
    }
} catch (Throwable $error) {
    vgx_update_payment_transaction((string) ($transaction['id'] ?? ''), [
        'status' => 'echec',
        'provider_message' => $error->getMessage(),
    ]);
    vg_flash('error', 'Echec Flexpay: ' . $error->getMessage());
}

header('Location: ' . $returnUrl);
exit;
