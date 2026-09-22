<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;

final class CockpitPayCodePaymentTransactionsController extends Controller
{
    public function __construct(private readonly VoucherAccessContract $vouchers) {}

    public function __invoke(Request $request, string $code, PaymentAttempt $attempt): JsonResponse
    {
        $voucher = $this->vouchers->findByCode($code);
        $owner = $request->user();

        abort_unless($voucher !== null && $owner instanceof Model
            && $voucher->owner_type === $owner->getMorphClass()
            && (string) $voucher->owner_id === (string) $owner->getKey()
            && $attempt->voucher_id === $voucher->getKey(), 404);

        $page = $attempt->observedPayments()
            ->with('statuses')
            ->reorder('id')
            ->cursorPaginate(25);

        return response()->json([
            'schema' => 'x-change.pay-code-payment-transactions.v1',
            'transactions' => $page->getCollection()->map(
                static fn (ObservedPaymentTransaction $transaction): array => [
                    'id' => $transaction->getKey(),
                    'provider_transaction_id' => $transaction->provider_transaction_id_ciphertext,
                    'amount_minor' => $transaction->amount_minor,
                    'currency' => $transaction->currency,
                    'provider_status' => $transaction->statuses->last()?->provider_status,
                    'status_history' => $transaction->statuses->map(static fn ($status): array => [
                        'provider_status' => $status->provider_status,
                        'observed_at' => $status->observed_at?->toIso8601String(),
                    ])->all(),
                    'settlement_rail' => $transaction->settlement_rail,
                    'occurred_at' => $transaction->occurred_at?->toIso8601String(),
                    'settled_at' => $transaction->settled_at?->toIso8601String(),
                    'payer' => [
                        'provenance' => 'provider_reported',
                        'name' => $transaction->payer_name_ciphertext,
                        'account_number' => $transaction->payer_account_ciphertext,
                        'institution_code' => $transaction->payer_institution_ciphertext,
                        'mobile_number' => $transaction->payer_mobile_ciphertext,
                        'mobile_verified' => false,
                    ],
                ],
            )->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
