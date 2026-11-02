<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentQrDeliveryMode;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use LBHurtado\XChange\Services\Payment\PaymentAttemptPresenter;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use LBHurtado\XChange\Services\Payment\PaymentQrDeliveryPolicy;
use LBHurtado\XChange\Services\Payment\PaymentReceiptReadModel;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;
use LBHurtado\XChange\Services\Settlement\ParticipantDocumentSession;
use LBHurtado\XChange\Services\SettlementRepaymentPolicy;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use Symfony\Component\HttpFoundation\Response;

class PaymentPageController extends Controller
{
    public function __construct(
        private readonly PaymentReceiptReadModel $receipts,
    ) {}

    public function __invoke(
        Request $request,
        string $code,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $progress,
        PaymentAttemptSessionGuard $sessions,
        PaymentAttemptPresenter $presenter,
        PaymentQrDeliveryPolicy $delivery,
        CampaignDisplaySessions $displays,
    ): Response {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless($capabilities->resolve($voucher)->can_collect, 404);
        app(ParticipantDocumentSession::class)->grant($request, $voucher, 'payer');

        $display = $displays->forPayer($voucher, $request);

        $collection = $progress->compute($voucher);
        $feeMinor = app(PayCodeCommercialSaleService::class)
            ->outstandingCollectionFee($voucher);
        $repayment = SettlementRepaymentPolicy::forVoucher($voucher);
        $repaymentReady = ! $repayment || SettlementRepaymentPolicy::disbursed($voucher);
        $principalDue = $repayment
            ? min($collection->remaining_to_collect_minor, SettlementRepaymentPolicy::paymentMinor($voucher))
            : $collection->remaining_to_collect_minor;
        $attempt = $this->attempt($request, $voucher, $sessions);
        $attempt ??= $display?->attempt;
        $attemptData = $attempt === null ? null : $presenter->present($attempt);
        $deliveryModes = $attempt === null
            ? $delivery->forVoucher($voucher, $display !== null)
            : $delivery->forAttempt($attempt, $voucher, $display !== null);
        $payerPageQr = in_array(PaymentQrDeliveryMode::PayerPage, $deliveryModes, true);
        $sellerDisplayQr = in_array(PaymentQrDeliveryMode::SellerDisplay, $deliveryModes, true);

        if (! $payerPageQr && $attemptData !== null) {
            $attemptData['qr_code'] = null;
        }
        if ($attemptData !== null) {
            $attemptData['qr_delivery_modes'] = $delivery->values($deliveryModes);
        }
        $provider = strtolower((string) config('x-change.payment.attempts.provider', 'netbank'));
        $providerEnabled = (bool) config("x-change.funding.providers.{$provider}.enabled", false);
        $documentsReady = true;
        try {
            app(DocumentPaymentGate::class)->ensureReady($voucher);
        } catch (VoucherRequiresSettlementEnvelope) {
            $documentsReady = false;
        }
        $requirements = collect((array) data_get($voucher->metadata, 'instructions.execution.document_requirements', []))
            ->filter(fn ($item) => is_array($item) && ($item['contributor'] ?? null) === 'payer'
                && is_string($item['type'] ?? null) && is_string($item['timing'] ?? null) && is_bool($item['requires_acceptance'] ?? null))
            ->map(fn ($item) => ['type' => $item['type'], 'timing' => $item['timing'], 'requires_acceptance' => $item['requires_acceptance']])
            ->values()->all();
        $paymentDetails = data_get($voucher->metadata, 'instructions.payment_details');
        $documentIds = is_array($paymentDetails) ? array_column($paymentDetails['documents'] ?? [], 'id') : null;
        $sharedInvoices = $voucher->envelope()->first()?->attachments()->get()->filter(
            fn ($item): bool => data_get($item->metadata, 'shared_with_payer') === true
                && data_get($item->metadata, 'contributor_role') === 'issuer'
                && ($documentIds === null || in_array($item->document_id, $documentIds, true))
        )->map(fn ($item): array => [
            'name' => $item->original_filename,
            'url' => route('x-change.pay.invoice.show', ['code' => $voucher->code, 'attachment' => $item->getKey()]),
        ])->values()->all() ?? [];
        if ($requirements !== [] && is_string(config('app.key')) && trim(config('app.key')) !== '') {
            $sessionHash = app(ParticipantDocumentSession::class)->fingerprint($request, $voucher, 'payer');
            $attachments = $voucher->envelope()->first()?->attachments()->orderByDesc('id')->get() ?? collect();
            foreach ($requirements as &$requirement) {
                $own = $attachments->first(fn ($item) => $item->doc_type === $requirement['type']
                    && data_get($item->metadata, 'contributor_role') === 'payer'
                    && data_get($item->metadata, 'participant_session_hash') === $sessionHash);
                $requirement['status'] = $own?->review_status;
            }
            unset($requirement);
        }

        $response = Inertia::render('x-change/claim/Payment', [
            'payment' => [
                'pay_code' => (string) $voucher->code,
                'currency' => $collection->currency,
                'target_amount_minor' => $collection->target_amount_minor,
                'collected_amount_minor' => $collection->collected_total_minor,
                'amount_due_minor' => $principalDue + $feeMinor,
                'principal_due_minor' => $principalDue,
                'repayment_mode' => $repayment,
                'repayment_ready' => $repaymentReady,
                'remaining_to_collect_minor' => $collection->remaining_to_collect_minor,
                'fee_due_minor' => $feeMinor,
                'is_fully_paid' => $collection->is_fully_collected,
                'rider_message' => $this->riderMessage($voucher),
                'payment_details' => is_array($paymentDetails) ? [
                    'description' => $paymentDetails['description'] ?? null,
                    'reference' => $paymentDetails['reference'] ?? null,
                    'fields' => $paymentDetails['fields'] ?? [],
                ] : null,
                'shared_invoices' => $sharedInvoices,
                'document_requirements' => $requirements,
                'document_upload_url' => $requirements === [] ? null : route('x-change.pay.documents.store', ['code' => $voucher->code]),
                'documents_ready' => $documentsReady,
                'provider' => $provider,
                'provider_available' => $providerEnabled,
                'can_create_attempt' => (bool) config('x-change.payment.attempts.enabled', true)
                    && $providerEnabled
                    && $repaymentReady
                    && $documentsReady
                    && $attempt?->status !== PaymentAttemptStatus::Verified
                    && ($display === null || ($display->ended_at === null && ! $display->expires_at->isPast() && $displays->intakeReady($display, $voucher)))
                    && ! $collection->is_fully_collected,
                'poll_interval_ms' => max(1000, (int) config('x-change.payment.attempts.ui_refresh_interval_milliseconds', 5000)),
                'attempt' => $attemptData,
                'paired_display' => $display === null || ! $sellerDisplayQr ? null : [
                    'reference' => $display->reference,
                    'status' => $displays->present($display)['status'],
                ],
                'receipt' => $collection->collected_total_minor > 0
                    ? $this->receipts->forVoucher($voucher, $collection->currency)
                    : null,
            ],
            'notice' => $request->session()->get('payment_notice'),
        ])->toResponse($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function riderMessage(Voucher $voucher): ?string
    {
        $message = trim((string) $voucher->instructions->rider->message);

        return $message === '' ? null : $message;
    }

    private function attempt(
        Request $request,
        Voucher $voucher,
        PaymentAttemptSessionGuard $sessions,
    ): ?PaymentAttempt {
        $reference = trim((string) $request->query('attempt', ''));

        if ($reference === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()
            ->where('reference', $reference)
            ->where('voucher_id', $voucher->getKey())
            ->firstOrFail();

        $browserKey = (string) $request->session()->get('x-change.payment.browser-key', '');
        $sessions->assertOwner($attempt, $browserKey);

        return $attempt;
    }
}
