<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Payment\PaymentAttemptPresenter;

final class CockpitPayCodeCollectionAttemptController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly CreatePaymentAttempt $create,
        private readonly IssuePaymentInstructions $issue,
        private readonly PaymentAttemptPresenter $presenter,
    ) {}

    public function __invoke(Request $request, string $code): JsonResponse
    {
        abort_unless((bool) config('x-change.payment.attempts.enabled', true), 404);

        $voucher = $this->vouchers->findByCode($code);
        abort_if($voucher === null, 404);
        abort_unless(
            $request->user() !== null
            && $this->access->canView($request->user(), $voucher),
            404,
        );

        $browserSessionKey = 'x-change.cockpit.payment.browser-key';
        $browserKey = (string) $request->session()->get($browserSessionKey, '');

        if ($browserKey === '') {
            $browserKey = Str::random(64);
            $request->session()->put($browserSessionKey, $browserKey);
        }

        $idempotencySessionKey = 'x-change.cockpit.payment.attempt-idempotency.'.$voucher->getKey();
        $idempotencyKey = (string) $request->session()->get($idempotencySessionKey, '');

        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
            $request->session()->put($idempotencySessionKey, $idempotencyKey);
        }

        $attempt = $this->create->handle(
            voucher: $voucher,
            provider: (string) config('x-change.payment.attempts.provider', 'netbank'),
            browserKey: $browserKey,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'schema' => 'x-change.cockpit.payment-attempt.v1',
            'attempt' => $this->presenter->present($this->issue->handle($attempt)),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
