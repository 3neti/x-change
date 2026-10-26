<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\PayCodeTerminalControlReadModel;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitVoucherDetailPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly PayCodeTerminalControlReadModel $terminalControls,
    ) {}

    public function __invoke(Request $request, string $code): Response
    {
        $voucher = $this->vouchers->findByCode($code);

        if ($voucher !== null) {
            abort_unless(
                $request->user() !== null
                && $this->access->canView($request->user(), $voucher),
                404,
            );
        }

        $props = $this->props->toVoucherDetailArray(
            code: $code,
            campaignPlanningKey: $this->optionalString($request->query('campaign_planning_key')),
            campaignExecutionId: $this->optionalString($request->query('campaign_execution_id')),
            campaignId: $this->optionalString($request->query('campaign_id')),
            campaignAudienceId: $this->optionalString($request->query('campaign_audience_id')),
            campaignRecipientId: $this->optionalString($request->query('campaign_recipient_id')),
            campaignSource: $this->optionalString($request->query('campaign_source')),
        );

        if ($voucher !== null) {
            $props['terminal_control'] = $this->terminalControls->forVoucher(
                $voucher,
                $request->user(),
            );

            $binding = StoredValueHolderBinding::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('status', 'active')
                ->first(['reference', 'holder_type', 'holder_id']);
            $holder = $request->user();
            $props['stored_value'] = [
                'activated' => $binding !== null,
                'holder_url' => $binding !== null
                    && $holder instanceof Model
                    && $binding->holder_type === $holder->getMorphClass()
                    && $binding->holder_id === (string) $holder->getKey()
                        ? route('x-change.balances.reusable.show', ['instrument' => $binding->reference], false)
                        : null,
            ];
        }

        return Inertia::render('x-change/cockpit/VoucherDetail', $props);
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
