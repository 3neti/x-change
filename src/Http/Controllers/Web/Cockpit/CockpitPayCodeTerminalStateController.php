<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Enums\PayCodeTerminalAction;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ManagePayCodeTerminalStateRequest;

final class CockpitPayCodeTerminalStateController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly VoucherLifecycleServiceContract $lifecycle,
    ) {}

    public function __invoke(
        ManagePayCodeTerminalStateRequest $request,
        string $code,
    ): RedirectResponse {
        $voucher = $this->vouchers->findByCode($code);
        abort_if($voucher === null, 404);

        $action = PayCodeTerminalAction::from((string) $request->validated('action'));
        $payload = [
            'reason' => (string) $request->validated('reason'),
        ];

        $result = match ($action) {
            PayCodeTerminalAction::Expire => $this->lifecycle->expire(
                (string) $voucher->getKey(),
                $payload,
            ),
            PayCodeTerminalAction::Cancel => $this->lifecycle->cancel(
                (string) $voucher->getKey(),
                $payload,
            ),
        };

        return to_route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code])
            ->with('success', (string) data_get(
                $result,
                'messages.0',
                'Pay Code lifecycle updated.',
            ));
    }
}
