<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Cockpit\FundingActivityCockpitReadModel;
use LBHurtado\XChange\Services\Cockpit\FundingCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\FundingMethodSelectorCockpitReadModel;
use LBHurtado\XChange\Services\Cockpit\FundingQrMerchantProfileReadModel;
use LBHurtado\XChange\Services\Cockpit\FundingRequestCockpitReadModel;
use LBHurtado\XChange\Services\Funding\Base64PngQrPhFundingSimulationQrRenderer;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;
use LBHurtado\XChange\Services\Funding\StandingFundingProfileResolver;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;
use Throwable;

class CockpitFundingPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly FundingCockpitReadModelProvider $funding,
        private readonly FundingRequestCockpitReadModel $fundingRequests,
        private readonly FundingMethodSelectorCockpitReadModel $fundingMethods,
        private readonly FundingActivityCockpitReadModel $fundingActivity,
        private readonly FundingQrMerchantProfileReadModel $merchantProfiles,
        private readonly Base64PngQrPhFundingSimulationQrRenderer $simulationQr,
        private readonly StandingFundingProfileResolver $standingAddressProfile,
        private readonly FundingProjectionChannel $fundingChannels,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): Response
    {
        $operator = $request->user();

        if ($operator === null) {
            throw new AuthenticationException;
        }

        $fundingRequests = $this->fundingRequests->forOperator($operator);
        $standingFundingAddress = $this->standingFundingAddressAvailability(
            $operator,
        );

        return Inertia::render('x-change/cockpit/Funding', [
            ...$this->props->toArray(includeReadModel: false),
            'funding_read_model' => $this->funding->forOperator($operator)->toArray(),
            'funding_requests' => $fundingRequests,
            'funding_method_selector' => $this->fundingMethods
                ->forAccountFunding(
                    $fundingRequests,
                    $standingFundingAddress,
                ),
            'funding_activity' => Inertia::defer(
                fn (): array => $this->fundingActivity->forOperator(
                    $operator,
                    $fundingRequests,
                ),
                'funding-activity',
            ),
            'funding_instruction' => $request->session()->pull('funding_instruction'),
            'funding_notice' => $request->session()->pull('funding_notice')
                ?? $request->session()->pull('funding_account_notice'),
            'funding_request_submitted_reference' => $request->session()->pull(
                'funding_request_submitted_reference',
            ),
            'funding_workspace_mode' => match ($request->string('mode')->toString()) {
                'bank_transfer' => 'bank_transfer',
                'pay_code' => 'pay_code',
                'reviewed_value' => 'reviewed_value',
                default => 'self_top_up',
            },
            'pay_code_funding_preview' => $request->session()->pull(
                'pay_code_funding_preview',
            ),
            'funding_poll_interval' => max(
                1000,
                (int) config('x-change.funding.ui_refresh_interval_milliseconds', 5000),
            ),
            'funding_realtime' => [
                'enabled' => (bool) config('x-change.funding.broadcast_enabled', false),
                'channel' => $this->fundingChannels->nameForOwner($operator),
                'event' => '.FundingProjectionChanged',
                'workflow_event' => '.FundingRequestChanged',
            ],
            'standing_funding_address' => $standingFundingAddress,
            'funding_qr_merchant_profile' => $this->merchantProfiles->forOwner($operator),
            'funding_simulation' => [
                'enabled' => (bool) config('x-change.cockpit.qrph_funding_simulation.enabled', false),
                'mode' => 'rollback-only',
                'provider_calls' => false,
                'balance_changes' => false,
                'amount' => '₱25.00',
                'mobile_ready' => data_get($operator, 'mobile_verified_at') !== null,
                'qr_code' => $this->simulationQr->render(2_500, 'PHP'),
            ],
        ]);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function standingFundingAddressAvailability(Model $operator): array
    {
        $enabled = (bool) config('x-change.funding.standing_addresses.enabled', false);
        $address = StandingFundingAddress::query()
            ->where('owner_type', $operator::class)
            ->where('owner_id', $operator->getKey())
            ->where('provider_code', 'netbank')
            ->where('purpose', FundingAddressPurpose::AccountFunding)
            ->first();
        $mode = $address?->recognition_mode->value
            ?? (string) config(
                'x-change.funding.standing_addresses.default_recognition_mode',
                'observe_only',
            );
        $profile = null;
        if ($enabled) {
            try {
                $profile = $this->standingAddressProfile->forOwner('netbank', $operator, $address);
            } catch (Throwable) {
                $profile = null;
            }
        }
        $configured = $profile?->configured ?? false;
        $profileReady = $profile?->ready ?? false;
        $status = $profile?->status ?? 'not_configured';
        $scheme = $profile?->scheme ?? $address?->derivation_scheme;

        $available = $enabled && $configured && $profileReady;

        return [
            'enabled' => $enabled,
            'available' => $available,
            'status' => match (true) {
                ! $enabled => 'disabled',
                ! $configured => 'not_configured',
                default => $status,
            },
            'provider' => 'netbank',
            'exists' => $address !== null,
            'address_scheme' => $scheme,
            'scheme_label' => $profile?->schemeLabel ?? 'Persisted legacy address',
            'scheme_warning' => $profile?->schemeWarning,
            'production_safe' => $profile?->productionSafe ?? false,
            'purpose' => FundingAddressPurpose::AccountFunding->value,
            'recognition_mode' => $mode,
            'address_status' => $address?->status->value,
            'temporary' => false,
            'provider_calls' => true,
            'funding_intent_created' => false,
            'automatic_credit_enabled' => $mode === 'automatic',
            'minimum_amount_minor' => $address?->minimum_amount_minor
                ?? (int) config('x-change.funding.standing_addresses.limits.minimum_amount_minor', 100),
            'maximum_amount_minor' => $address?->maximum_amount_minor
                ?? (int) config('x-change.funding.standing_addresses.limits.maximum_amount_minor', 5_000_000),
            'daily_limit_minor' => $address?->daily_limit_minor
                ?? (int) config('x-change.funding.standing_addresses.limits.daily_limit_minor', 10_000_000),
        ];
    }
}
