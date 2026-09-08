<?php

require __DIR__ . '/../includes/bootstrap.php';
vg_require_role('client');

$user = vg_current_user();
$client = vg_portal_require_client($user);
$clientId = (string) ($client['id'] ?? '');
$payments = vg_client_payments($clientId);
$invoices = vg_client_invoices($clientId);
$gatewayTransactions = vg_client_payment_transactions($clientId);
$flashSuccess = vg_flash('success');
$flashError = vg_flash('error');
$totalAmount = 0.0;
foreach ($payments as $payment) {
    $totalAmount += (float) ($payment['amount'] ?? 0);
}
$latestPayment = $payments[0] ?? null;
$latestInvoice = $invoices[0] ?? null;
$latestGatewayTransaction = $gatewayTransactions[0] ?? null;
$financeNextAction = $latestInvoice
    ? 'Vérifier en priorité la facture ou l échéance la plus récente puis télécharger le reçu si disponible.'
    : 'Le portail affichera ici vos prochaines factures et paiements dès leur validation.';
$flexpayConfig = vg_flexpay_config();
$pendingPayments = array_values(array_filter($payments, static function ($payment): bool {
    return is_array($payment) && !vgx_payment_status_is_paid((string) ($payment['status'] ?? ''));
}));
$pendingPaymentInvoiceIds = array_values(array_filter(array_map(static function ($payment): string {
    return trim((string) ($payment['invoice_id'] ?? ''));
}, $pendingPayments)));
$pendingInvoices = array_values(array_filter($invoices, static function ($invoice): bool {
    return is_array($invoice) && !vgx_payment_status_is_paid((string) ($invoice['status'] ?? ''));
}));
$payableTargets = [];
foreach ($pendingPayments as $payment) {
    $payableTargets[] = [
        'target_type' => 'payment',
        'target_id' => (string) ($payment['id'] ?? ''),
        'label' => (string) ($payment['label_name'] ?? $payment['reference'] ?? 'Paiement client'),
        'meta' => 'Paiement en attente • ' . vg_money((float) ($payment['amount'] ?? 0), (string) ($payment['currency'] ?? 'USD')),
    ];
}
foreach ($pendingInvoices as $invoice) {
    $invoiceId = (string) ($invoice['id'] ?? '');
    if ($invoiceId !== '' && in_array($invoiceId, $pendingPaymentInvoiceIds, true)) {
        continue;
    }

    $payableTargets[] = [
        'target_type' => 'invoice',
        'target_id' => $invoiceId,
        'label' => (string) ($invoice['number'] ?? 'Facture client'),
        'meta' => 'Facture en attente • ' . vg_money((float) ($invoice['amount'] ?? 0), (string) ($invoice['currency'] ?? 'USD')),
    ];
}
$defaultPaymentPhone = vg_flexpay_normalize_phone((string) ($client['phone'] ?? '')) ?? preg_replace('/\D+/', '', (string) ($client['phone'] ?? ''));

vg_render_app_header('Paiements et factures', $user, 'payments.php');
?>
<style>
.ops-client-reading-strip{display:grid;gap:8px;margin:16px 0 0}
.ops-client-reading-step{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:start;padding:10px 12px;border-radius:16px;border:1px solid rgba(86,160,255,.14);background:rgba(8,16,29,.66);color:#d6ebff}
.ops-client-reading-step strong{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(99,214,255,.12);color:#d8f6ff;font-size:.76rem}
.ops-client-priority-badge{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(86,160,255,.16);background:rgba(8,16,29,.74);color:#d8edff;font-size:.7rem;letter-spacing:.14em;text-transform:uppercase}
.ops-client-focus-header{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
.ops-client-pay-lead{margin-bottom:18px;padding:16px 18px;border-radius:20px;border:1px solid rgba(95,170,255,.16);background:rgba(8,16,29,.68)}
.ops-client-pay-lead span{display:block;color:#83b7ee;font-size:.76rem;text-transform:uppercase;letter-spacing:.18em}
.ops-client-pay-lead strong{display:block;margin-top:6px;color:#fff;font-size:1.1rem}
.ops-client-pay-lead small{display:block;margin-top:6px;color:#9ebfe7;line-height:1.55}
.ops-client-pay-flash{margin:18px 0;padding:14px 16px;border-radius:18px;border:1px solid rgba(95,170,255,.18);background:rgba(8,16,29,.72)}
.ops-client-pay-flash--success{border-color:rgba(46,211,159,.28);color:#9ef0cf}
.ops-client-pay-flash--error{border-color:rgba(255,86,111,.26);color:#ffbec9}
.ops-client-inline-form{display:grid;gap:12px}
.ops-client-inline-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
.ops-client-inline-note{padding:12px 14px;border-radius:16px;background:rgba(8,16,29,.66);border:1px solid rgba(86,160,255,.12);color:#9ebfe7;line-height:1.55}
.ops-client-txn-list{display:grid;gap:12px}
.ops-client-txn-item{padding:14px 16px;border-radius:18px;background:rgba(8,16,29,.64);border:1px solid rgba(86,160,255,.12)}
.ops-client-txn-item strong{display:block;color:#f4fbff}
.ops-client-txn-item small{display:block;color:#9ebfe7;line-height:1.5}
</style>
<section class="ops-client-pay-shell">
    <article class="ops-client-pay-hero">
        <p class="eyebrow">Finance client</p>
        <h2>Paiements et factures</h2>
        <p>Suivez ici vos montants, vos échéances, vos statuts de règlement et les factures liées à votre abonnement ou à vos services.</p>
        <div class="ops-client-reading-strip">
            <div class="ops-client-reading-step"><strong>1</strong><div>Lire la facture ou le paiement le plus récent avant de parcourir l historique complet.</div></div>
            <div class="ops-client-reading-step"><strong>2</strong><div><?php echo vg_escape($financeNextAction); ?></div></div>
        </div>
        <div class="ops-client-pay-stats" style="margin-top:16px;">
            <div class="ops-client-pay-stat"><span>Paiements</span><strong><?php echo vg_escape((string) count($payments)); ?></strong></div>
            <div class="ops-client-pay-stat"><span>Factures</span><strong><?php echo vg_escape((string) count($invoices)); ?></strong></div>
            <div class="ops-client-pay-stat"><span>Total historique</span><strong><?php echo vg_escape(number_format($totalAmount, 0, ',', ' ')); ?> USD</strong></div>
        </div>
    </article>

    <?php if ($flashSuccess): ?>
        <div class="ops-client-pay-flash ops-client-pay-flash--success"><?php echo vg_escape((string) $flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="ops-client-pay-flash ops-client-pay-flash--error"><?php echo vg_escape((string) $flashError); ?></div>
    <?php endif; ?>

    <section class="ops-client-pay-layout" style="margin-bottom:18px;">
        <article class="ops-client-pay-card">
            <div class="ops-client-focus-header">
                <h3>Payer maintenant</h3>
                <div class="ops-client-priority-badge"><?php echo vg_escape(!empty($flexpayConfig['enabled']) ? 'Flexpay actif' : 'Flexpay indisponible'); ?></div>
            </div>
            <?php if (!$payableTargets): ?>
                <div class="ops-client-inline-note">Aucune echeance en attente n est disponible pour le moment sur ce dossier.</div>
            <?php elseif (empty($flexpayConfig['configured'])): ?>
                <div class="ops-client-inline-note">Le module Flexpay est prepare, mais les identifiants marchand et l URL publique ne sont pas encore renseignes dans la configuration.</div>
            <?php elseif (empty($flexpayConfig['enabled'])): ?>
                <div class="ops-client-inline-note">Le module Flexpay est configure, mais il reste desactive pour le moment. Activez FLEXPAY_ENABLED pour ouvrir le paiement client.</div>
            <?php else: ?>
                <form class="ops-client-inline-form" method="post" action="<?php echo vg_escape(vg_url('api/flexpay/initiate.php')); ?>">
                    <input type="hidden" name="return_to" value="client/payments.php">
                    <div class="ops-client-inline-grid">
                        <label>
                            <span>Facture ou paiement</span>
                            <select name="target_token" required>
                                <?php foreach ($payableTargets as $target): ?>
                                    <option value="<?php echo vg_escape((string) $target['target_type'] . '|' . (string) $target['target_id']); ?>">
                                        <?php echo vg_escape((string) $target['label'] . ' - ' . (string) $target['meta']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>Telephone Mobile Money</span>
                            <input type="text" name="phone" value="<?php echo vg_escape((string) $defaultPaymentPhone); ?>" placeholder="243XXXXXXXXX" required>
                        </label>

                        <label>
                            <span>Reseau</span>
                            <select name="network">
                                <option value="auto">Detection automatique</option>
                                <option value="airtel">Airtel Money</option>
                                <option value="orange">Orange Money</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="afrimoney">Afrimoney</option>
                            </select>
                        </label>
                    </div>

                    <div class="ops-client-inline-note">
                        Flexpay envoie la demande de paiement sur le numero indique. Le reseau est detecte automatiquement par le numero, ce qui permet d utiliser le meme flux sur plusieurs reseaux mobile money.
                    </div>

                    <div class="button-row">
                        <button class="btn btn-primary" type="submit">Lancer le paiement Flexpay</button>
                    </div>
                </form>
            <?php endif; ?>
        </article>

        <article class="ops-client-pay-card">
            <div class="ops-client-focus-header">
                <h3>Suivi Flexpay</h3>
                <div class="ops-client-priority-badge"><?php echo vg_escape((string) vg_flexpay_status_label((string) ($latestGatewayTransaction['status'] ?? 'Aucune transaction'))); ?></div>
            </div>
            <div class="ops-client-pay-lead">
                <span>Transaction focale</span>
                <strong><?php echo vg_escape((string) ($latestGatewayTransaction['reference'] ?? 'Aucune transaction Flexpay')); ?></strong>
                <small><?php echo vg_escape((string) ($latestGatewayTransaction ? (vg_money((float) ($latestGatewayTransaction['amount'] ?? 0), (string) ($latestGatewayTransaction['currency'] ?? 'USD')) . ' • ' . vg_flexpay_status_label((string) ($latestGatewayTransaction['status'] ?? ''))) : 'Les demandes Mobile Money lancees ici apparaitront dans cet historique.')); ?></small>
            </div>
            <div class="ops-client-txn-list">
                <?php foreach ($gatewayTransactions as $transaction): ?>
                    <?php
                    $transactionStatus = (string) ($transaction['status'] ?? '');
                    $transactionOrder = (string) ($transaction['order_number'] ?? '');
                    $canCheckTransaction = $transactionOrder !== '' && !vgx_payment_status_is_paid($transactionStatus) && !in_array(strtolower($transactionStatus), ['echec', 'failed', 'cancelled', 'annule'], true);
                    ?>
                    <div class="ops-client-txn-item">
                        <strong><?php echo vg_escape((string) ($transaction['reference'] ?? 'Transaction Flexpay')); ?></strong>
                        <small>
                            <?php echo vg_escape(vg_money((float) ($transaction['amount'] ?? 0), (string) ($transaction['currency'] ?? 'USD'))); ?>
                            • <?php echo vg_escape(vg_flexpay_status_label($transactionStatus)); ?>
                            • <?php echo vg_escape(vg_flexpay_network_label((string) ($transaction['network'] ?? 'auto'))); ?>
                            • <?php echo vg_escape((string) ($transaction['phone'] ?? '')); ?>
                        </small>
                        <?php if (!empty($transaction['provider_message'])): ?>
                            <small><?php echo vg_escape((string) $transaction['provider_message']); ?></small>
                        <?php endif; ?>
                        <?php if ($transactionOrder !== ''): ?>
                            <small>Commande Flexpay: <?php echo vg_escape($transactionOrder); ?></small>
                        <?php endif; ?>
                        <?php if ($canCheckTransaction): ?>
                            <form method="post" action="<?php echo vg_escape(vg_url('api/flexpay/check.php')); ?>" style="margin-top:12px;">
                                <input type="hidden" name="transaction_id" value="<?php echo vg_escape((string) ($transaction['id'] ?? '')); ?>">
                                <input type="hidden" name="return_to" value="client/payments.php">
                                <button class="btn btn-secondary" type="submit">Verifier maintenant</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($gatewayTransactions)): ?>
                    <div class="ops-client-txn-item">
                        <strong>Aucune transaction Flexpay</strong>
                        <p>Les paiements lances depuis ce portail remonteront ici avec leur statut et leur numero de commande.</p>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="ops-client-pay-layout">
        <article class="ops-client-pay-card">
            <div class="ops-client-focus-header">
                <h3>Mes paiements</h3>
                <div class="ops-client-priority-badge"><?php echo vg_escape((string) ($latestPayment['status'] ?? 'Aucun paiement')); ?></div>
            </div>
            <div class="ops-client-pay-lead">
                <span>Paiement focal</span>
                <strong><?php echo vg_escape((string) ($latestPayment['reference'] ?? 'Aucun paiement enregistré')); ?></strong>
                <small><?php echo vg_escape((string) ($latestPayment ? vg_money($latestPayment['amount'] ?? 0, $latestPayment['currency'] ?? 'USD') : 'Les règlements apparaîtront ici une fois validés.')); ?></small>
            </div>
            <div class="ops-client-pay-list">
                <?php foreach ($payments as $payment): ?>
                    <div class="ops-client-pay-item">
                        <strong><?php echo vg_escape((string) ($payment['reference'] ?? 'Paiement')); ?></strong>
                        <div class="ops-client-pay-meta">
                            <div><strong>Montant:</strong> <?php echo vg_escape(vg_money($payment['amount'], $payment['currency'])); ?></div>
                            <div><strong>Echéance:</strong> <?php echo vg_escape((string) ($payment['due_date'] ?? '')); ?></div>
                        </div>
                        <p style="margin-top:12px;"><span class="badge <?php echo 'badge-' . vg_status_badge($payment['status']); ?>"><?php echo vg_escape($payment['status']); ?></span></p>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                    <div class="ops-client-pay-item">
                        <strong>Aucun paiement enregistré</strong>
                        <p>Vos règlements apparaîtront ici dès qu’ils seront saisis dans le système.</p>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="ops-client-pay-card">
            <div class="ops-client-focus-header">
                <h3>Mes factures</h3>
                <div class="ops-client-priority-badge"><?php echo vg_escape((string) ($latestInvoice['status'] ?? 'Aucune facture')); ?></div>
            </div>
            <div class="ops-client-pay-lead">
                <span>Facture focale</span>
                <strong><?php echo vg_escape((string) ($latestInvoice['number'] ?? 'Aucune facture disponible')); ?></strong>
                <small><?php echo vg_escape((string) ($latestInvoice ? vg_money($latestInvoice['amount'] ?? 0, $latestInvoice['currency'] ?? 'USD') : 'Les factures validées seront visibles ici.')); ?></small>
            </div>
            <div class="ops-client-pay-list">
                <?php foreach ($invoices as $invoice): ?>
                    <div class="ops-client-pay-item">
                        <strong><?php echo vg_escape((string) ($invoice['number'] ?? 'Facture')); ?></strong>
                        <div class="ops-client-pay-meta">
                            <div><strong>Montant:</strong> <?php echo vg_escape(vg_money($invoice['amount'], $invoice['currency'])); ?></div>
                            <div><strong>Statut:</strong> <span class="badge <?php echo 'badge-' . vg_status_badge($invoice['status']); ?>"><?php echo vg_escape($invoice['status']); ?></span></div>
                        </div>
                        <?php if ((string) ($invoice['document_type'] ?? '') === 'registration_receipt'): ?>
                            <p style="margin-top:12px;">Reçu d’enregistrement disponible au téléchargement.</p>
                            <div class="button-row" style="margin-top:12px;">
                                <a class="btn btn-secondary" href="<?php echo vg_escape(vg_url('registration-receipt.php?invoice_id=' . urlencode((string) ($invoice['id'] ?? '')))); ?>" target="_blank" rel="noopener">Voir reçu</a>
                                <a class="btn btn-primary" href="<?php echo vg_escape(vg_url('registration-receipt.php?invoice_id=' . urlencode((string) ($invoice['id'] ?? '')) . '&download=1')); ?>">Télécharger reçu</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                    <div class="ops-client-pay-item">
                        <strong>Aucune facture disponible</strong>
                        <p>Les factures validées par l’administration apparaîtront ici.</p>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>
</section>
<?php vg_render_app_footer(); ?>
