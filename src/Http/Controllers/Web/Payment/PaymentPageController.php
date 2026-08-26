<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Payment\PaymentAttemptPresenter;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use Symfony\Component\HttpFoundation\Response;

class PaymentPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $progress,
        PaymentAttemptSessionGuard $sessions,
        PaymentAttemptPresenter $presenter,
    ): Response {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless($capabilities->resolve($voucher)->can_collect, 404);

        $collection = $progress->compute($voucher);
        $attempt = $this->attempt($request, $voucher, $sessions);
        $provider = strtolower((string) config('x-change.payment.attempts.provider', 'netbank'));
        $providerEnabled = (bool) config("x-change.funding.providers.{$provider}.enabled", false);

        $response = Inertia::render('x-change/claim/Payment', [
            'payment' => [
                'pay_code' => (string) $voucher->code,
                'currency' => $collection->currency,
                'target_amount_minor' => $collection->target_amount_minor,
                'collected_amount_minor' => $collection->collected_total_minor,
                'amount_due_minor' => $collection->remaining_to_collect_minor,
                'is_fully_paid' => $collection->is_fully_collected,
                'rider_message' => $this->riderMessage($voucher),
                'provider' => $provider,
                'provider_available' => $providerEnabled,
                'can_create_attempt' => (bool) config('x-change.payment.attempts.enabled', true)
                    && $providerEnabled
                    && ! $collection->is_fully_collected,
                'attempt' => $attempt === null ? null : $presenter->present($attempt),
                'receipt' => $collection->is_fully_collected
                    ? $this->receipt($voucher, $collection->currency)
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

    /**
     * @return array{
     *     pay_code: string,
     *     amount_paid_minor: int,
     *     currency: string,
     *     completed_at: ?string,
     *     payments: list<array{
     *         collection_number: int,
     *         amount_paid_minor: int,
     *         provider: string,
     *         receipt_reference: string,
     *         completed_at: ?string
     *     }>
     * }|null
     */
    private function receipt(Voucher $voucher, string $currency): ?array
    {
        $collections = VoucherCollection::query()
            ->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['collected', 'succeeded'])
            ->orderBy('collection_number')
            ->get([
                'collection_number',
                'collected_amount_minor',
                'provider',
                'completed_at',
            ]);

        if ($collections->isEmpty()) {
            return null;
        }

        $latest = $collections
            ->sortByDesc(fn (VoucherCollection $collection): int => $collection->completed_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'pay_code' => (string) $voucher->code,
            'amount_paid_minor' => (int) $collections->sum('collected_amount_minor'),
            'currency' => $currency,
            'completed_at' => $latest?->completed_at?->toIso8601String(),
            'payments' => $collections
                ->map(fn (VoucherCollection $collection): array => [
                    'collection_number' => (int) $collection->collection_number,
                    'amount_paid_minor' => (int) $collection->collected_amount_minor,
                    'provider' => (string) ($collection->provider ?: 'Recorded payment'),
                    'receipt_reference' => sprintf(
                        'PAY-%s-%02d',
                        (string) $voucher->code,
                        $collection->collection_number,
                    ),
                    'completed_at' => $collection->completed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
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
