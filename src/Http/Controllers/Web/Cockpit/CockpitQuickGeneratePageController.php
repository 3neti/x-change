<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\PayCodeTemplateReadModel;
use LBHurtado\XChange\Services\Cockpit\QuickGenerateLastInstructionsStore;
use LBHurtado\XChange\Services\Cockpit\RiderLibraryReadModel;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitQuickGeneratePageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly QuickGenerateLastInstructionsStore $lastInstructions,
        private readonly PayCodeTemplateReadModel $templates,
        private readonly RiderLibraryReadModel $riderLibrary,
        private readonly InstructionCapabilityReadinessRegistry $instructionCapabilities,
        private readonly SettlementRailCapabilityRegistryContract $settlementRails,
        private readonly WalletAccessContract $wallets,
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $payCodeAccess,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('x-change/cockpit/QuickGenerate', [
            ...$this->props->toQuickGenerateArray(
                campaignPlanningKey: $this->optionalString($request->query('campaign_planning_key')),
                campaignExecutionId: $this->optionalString($request->query('campaign_execution_id')),
                campaignId: $this->optionalString($request->query('campaign_id')),
                campaignAudienceId: $this->optionalString($request->query('campaign_audience_id')),
                campaignRecipientId: $this->optionalString($request->query('campaign_recipient_id')),
                campaignSource: $this->optionalString($request->query('campaign_source')),
                campaignTemplateKey: $this->optionalString($request->query('campaign_template_key')),
                campaignAmount: $this->optionalScalar($request->query('campaign_amount')),
                campaignCurrency: $this->optionalString($request->query('campaign_currency')),
                campaignRecipientReference: $this->optionalString($request->query('campaign_recipient_reference')),
                campaignPurpose: $this->optionalString($request->query('campaign_purpose')),
            ),
            'feedback_defaults' => $this->feedbackDefaults($request),
            'onboarding_policy' => [
                'otp_required' => (bool) config('x-change.onboarding.voucher.require_otp', false),
            ],
            'invitation_preset' => [
                'enabled' => $request->query('intent') === 'invite',
                'source' => 'cockpit',
            ],
            'last_instructions' => $this->lastInstructions->for($request->user()),
            'saved_templates' => $this->templates->for($request->user()),
            'rider_library' => $this->riderLibrary->for($request->user()),
            'instruction_capabilities' => $this->instructionCapabilities->sanitized(),
            'settlement_rail_capabilities' => $this->settlementRails->sanitized(),
            'current_user_wallet_id' => $this->currentUserWalletId($request),
            'pos_voucher' => $this->posVoucher($request),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function posVoucher(Request $request): ?array
    {
        $code = $this->optionalString($request->query('pos_code'));

        if ($code === null) {
            return null;
        }

        $voucher = $this->vouchers->findByCode($code);

        if ($voucher === null) {
            return null;
        }

        abort_unless(
            $request->user() !== null
            && $this->payCodeAccess->canView($request->user(), $voucher),
            404,
        );

        $props = $this->props->toVoucherDetailArray((string) $voucher->code);
        $projection = data_get($props, 'read_model.voucher');

        return is_array($projection) ? $projection : null;
    }

    private function currentUserWalletId(Request $request): string|int|null
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return $this->wallets->resolveForUser($user)->getKey();
    }

    /**
     * @return array{schema: string, email: ?string, mobile: ?string, webhook: ?string, source: string, read_only: bool}
     */
    private function feedbackDefaults(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [
                'schema' => 'x-change.cockpit.quick-generate-feedback-defaults.v1',
                'email' => null,
                'mobile' => null,
                'webhook' => null,
                'source' => 'unavailable',
                'read_only' => true,
            ];
        }

        $email = $this->optionalString(data_get($user, 'email'));
        $mobile = $this->optionalString(data_get($user, 'mobile'));
        $identifier = (string) ($user->getAuthIdentifier() ?? $email ?? $mobile ?? 'operator');
        $publicKey = substr(hash('sha256', $identifier.'|'.($email ?? '').'|x-change-cockpit-feedback'), 0, 24);

        return [
            'schema' => 'x-change.cockpit.quick-generate-feedback-defaults.v1',
            'email' => $email,
            'mobile' => $mobile,
            'webhook' => url('/x/webhooks/operator/'.$publicKey),
            'source' => 'authenticated-user',
            'read_only' => true,
        ];
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function optionalScalar(mixed $value): int|float|string|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
