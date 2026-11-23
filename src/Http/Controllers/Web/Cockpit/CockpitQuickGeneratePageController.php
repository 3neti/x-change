<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\CampaignDisplaySession;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Banking\SelectedAccountClientFunds;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Cockpit\PayCodeTemplateReadModel;
use LBHurtado\XChange\Services\Cockpit\QuickGenerateLastInstructionsStore;
use LBHurtado\XChange\Services\Cockpit\RiderLibraryReadModel;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use LBHurtado\XChange\Services\Settlement\V2EventCatalog;
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
        private readonly OnDemandIssuanceFundingOrderPresenter $fundingOrderPresenter,
        private readonly OnDemandIssuanceFundingPolicy $onDemandFundingPolicy,
    ) {}

    public function __invoke(Request $request, CampaignDisplaySessions $displays): Response
    {
        $display = null;
        if ($request->filled('display_session')) {
            $display = CampaignDisplaySession::query()->where('reference', $request->query('display_session'))->firstOrFail();
            $displays->authorize($display, $request->user());
        }

        return Inertia::render('x-change/cockpit/QuickGenerate', [
            'stored_value_collect_url' => route('x-change.cockpit.stored-value-collections.index', [], false),
            'display_campaigns' => fn (): array => $displays->campaignsFor($request->user()),
            'display_session' => $display === null ? null : $displays->present($display),
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
            'rider_url_policy' => Arr::only((array) config('x-rider.redirects', []), ['allowed_schemes', 'allowed_hosts', 'allow_any_host']),
            'onboarding_policy' => [
                'otp_required' => (bool) config('x-change.onboarding.voucher.require_otp', false),
            ],
            'invitation_preset' => [
                'enabled' => $request->query('intent') === 'invite',
                'source' => 'cockpit',
            ],
            'startup_mode' => $this->startupMode(),
            'last_instructions' => $this->lastInstructions->for($request->user()),
            'saved_templates' => $this->templates->for($request->user()),
            'rider_library' => $this->riderLibrary->for($request->user()),
            'instruction_capabilities' => $this->instructionCapabilities->sanitized(),
            'v2_event_catalog' => app(V2EventCatalog::class)->definitions(),
            'settlement_rail_capabilities' => $this->settlementRails->sanitized(),
            'collection_destination' => $this->collectionDestination($request),
            'issuance_bank_account' => $this->issuanceBankAccount($request),
            'selected_account_funds' => app(SelectedAccountClientFunds::class)->forOwner($request->user()),
            'pos_voucher' => $this->posVoucher($request),
            'active_on_demand_funding_order' => $this->activeOnDemandFundingOrder($request),
            'reserve_issuance_index_url' => (bool) config('x-change.reserve_transfer.enabled', false)
                ? route('x-change.cockpit.quick-generate.reserve-issuances.index', [], false) : null,
            'on_demand_issuance_policy' => [
                'enabled' => $this->onDemandFundingPolicy->enabled(),
                'basis' => $this->onDemandFundingPolicy->basis()->value,
            ],
            'composer_view' => $request->query('view') === 'prompt' ? 'prompt' : 'standard',
            'prompt_interpret_endpoint' => route('x-change.cockpit.quick-generate.interpret'),
            'invoice_draft_url' => route('x-change.cockpit.quick-generate.invoice-drafts.store'),
        ]);
    }

    /** @return array{name: string, number: string, mode: string}|null */
    private function issuanceBankAccount(Request $request): ?array
    {
        $owner = $request->user();
        if (! $owner instanceof Model || app(ProviderRuntimeSettingsResolverContract::class)->provider() !== 'netbank') {
            return null;
        }
        $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)
            ->where('provider_code', 'netbank')->first();
        if ($preference !== null && $preference->mode !== 'shared') {
            if ($preference->mode !== 'dedicated' || $preference->provider_account_link_id === null) {
                return ['name' => 'Selected account unavailable', 'number' => '', 'mode' => 'unavailable'];
            }
            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
                ->where('provider_account_link_id', $preference->provider_account_link_id)->first();
            $details = $assignment?->details_ciphertext;
            if (! is_array($details) || ! filled($details['account_number'] ?? null)) {
                return ['name' => 'Selected account unavailable', 'number' => '', 'mode' => 'unavailable'];
            }

            return ['name' => (string) ($details['account_name'] ?? 'Bank account'),
                'number' => (string) ($details['provider_account_number'] ?? $details['account_number']), 'mode' => 'dedicated'];
        }
        $number = config('disbursement.source.account_number') ?: config('omnipay.gateways.netbank.options.sourceAccountNumber');
        if (! is_string($number) || trim($number) === '') {
            return null;
        }

        return ['name' => 'Platform shared NetBank account', 'number' => trim($number), 'mode' => 'shared'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeOnDemandFundingOrder(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $order = PayCodeIssuanceFundingOrder::query()
            ->where('issuer_type', $user::class)
            ->where('issuer_id', (string) $user->getKey())
            ->whereNotIn('status', [
                PayCodeIssuanceFundingOrderStatus::Issued->value,
                PayCodeIssuanceFundingOrderStatus::Cancelled->value,
                PayCodeIssuanceFundingOrderStatus::Expired->value,
            ])
            ->latest('id')
            ->first();

        return $order instanceof PayCodeIssuanceFundingOrder
            ? $this->fundingOrderPresenter->present($order)
            : null;
    }

    private function startupMode(): string
    {
        return config('x-change.cockpit.quick_generate.startup_mode') === 'repeat_last'
            ? 'repeat_last'
            : 'blank';
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

    /**
     * @return array{schema: string, label: string, description: string, authority: string, status: string, editable: false, managed_automatically: true}|null
     */
    private function collectionDestination(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $this->wallets->resolveForUser($user);

        return [
            'schema' => 'x-change.cockpit.collection-destination.v1',
            'label' => 'Your Client Funds',
            'description' => 'Payments are credited to the collection account authorized for the signed-in operator.',
            'authority' => 'authenticated_operator',
            'status' => 'ready',
            'editable' => false,
            'managed_automatically' => true,
        ];
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
