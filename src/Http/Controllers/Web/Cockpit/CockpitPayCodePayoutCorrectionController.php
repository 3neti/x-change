<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Disbursement\RefurbishRejectedPayCodePayout;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RefurbishPayCodePayoutRequest;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;

final class CockpitPayCodePayoutCorrectionController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly RefurbishRejectedPayCodePayout $refurbish,
    ) {}

    public function __invoke(
        RefurbishPayCodePayoutRequest $request,
        string $code,
    ): RedirectResponse {
        $voucher = $this->vouchers->findByCode($code);
        abort_if($voucher === null, 404);
        abort_unless(
            $request->user() !== null
            && $this->access->canView($request->user(), $voucher),
            404,
        );

        $result = $this->refurbish->handle(
            voucher: $voucher,
            requestedBy: $request->user(),
            bankCode: (string) $request->validated('bank_code'),
            accountNumber: (string) $request->validated('account_number'),
            mobile: $this->optionalString($request->validated('mobile')),
        );

        $redirect = to_route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]);

        if (($result['provider_submission_accepted'] ?? true) === false) {
            return $redirect->withErrors([
                'account_number' => 'The provider did not accept this payout submission. Your funds remain protected; review the destination and retry.',
            ]);
        }

        return $redirect->with('success', $result['status'] === 'succeeded'
                ? 'The corrected payout was completed.'
                : 'The corrected payout was submitted for provider verification.');
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
