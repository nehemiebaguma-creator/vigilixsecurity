<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$respond = static function (int $statusCode, array $payload): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$input = file_get_contents('php://input');
$payload = [];

if ($_POST !== []) {
    $payload = $_POST;
} elseif (is_string($input) && trim($input) !== '') {
    $decoded = json_decode($input, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    } else {
        parse_str($input, $parsed);
        if (is_array($parsed)) {
            $payload = $parsed;
        }
    }
}

$transactionId = trim((string) ($_GET['transaction_id'] ?? $payload['transaction_id'] ?? ''));
$callbackKey = trim((string) ($_GET['key'] ?? ''));

if ($transactionId === '' || $callbackKey === '') {
    $respond(400, ['ok' => false, 'message' => 'Callback Flexpay incomplet.']);
}

$transaction = vgx_find_payment_transaction($transactionId);
if (!is_array($transaction)) {
    $respond(404, ['ok' => false, 'message' => 'Transaction Flexpay inconnue.']);
}

if (!hash_equals((string) ($transaction['callback_key'] ?? ''), $callbackKey)) {
    $respond(403, ['ok' => false, 'message' => 'Signature de callback invalide.']);
}

$orderNumber = trim((string) ($payload['orderNumber'] ?? $transaction['order_number'] ?? ''));
$changes = [
    'callback_payload' => $payload,
    'provider_code' => $payload['code'] ?? null,
    'provider_message' => (string) ($payload['message'] ?? ''),
    'provider_reference' => (string) ($payload['provider_reference'] ?? $transaction['provider_reference'] ?? ''),
    'order_number' => $orderNumber,
];

$callbackSuccess = vg_flexpay_is_success_code($payload['code'] ?? $payload['status'] ?? null);

try {
    if ($orderNumber !== '' && vg_flexpay_is_configured()) {
        $check = vg_flexpay_check_payment($orderNumber);
        $changes['check_payload'] = $check['response'] ?? [];
        $changes['provider_message'] = (string) ($check['message'] ?? $changes['provider_message']);

        if (!empty($check['paid'])) {
            vgx_finalize_payment_transaction($transactionId, array_merge($changes, ['status' => 'paye']));
            $respond(200, ['ok' => true, 'message' => 'Paiement confirme.']);
        }

        if (!empty($check['success'])) {
            vgx_update_payment_transaction($transactionId, array_merge($changes, ['status' => 'echec']));
            $respond(200, ['ok' => true, 'message' => 'Transaction marquee en echec.']);
        }
    }
} catch (Throwable $error) {
    $changes['provider_message'] = $error->getMessage();
}

if ($callbackSuccess) {
    vgx_finalize_payment_transaction($transactionId, array_merge($changes, ['status' => 'paye']));
    $respond(200, ['ok' => true, 'message' => 'Paiement callback accepte.']);
}

vgx_update_payment_transaction($transactionId, array_merge($changes, ['status' => 'echec']));
$respond(200, ['ok' => true, 'message' => 'Callback enregistre.']);
