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
use LBHurtado\XChange\Services\Cockpit\FundingCockpitReadModelProvider;
use LBHurtado\XChange\Services\Funding\Base64PngQrPhFundingSimulationQrRenderer;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitFundingPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly FundingCockpitReadModelProvider $funding,
        private readonly Base64PngQrPhFundingSimulationQrRenderer $simulationQr,
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

        return Inertia::render('x-change/cockpit/Funding', [
            ...$this->props->toArray(),
            'funding_read_model' => $this->funding->forOperator($operator)->toArray(),
            'funding_instruction' => $request->session()->pull('funding_instruction'),
            'funding_notice' => $request->session()->pull('funding_notice'),
            'funding_poll_interval' => max(
                1000,
                (int) config('x-change.funding.ui_refresh_interval_milliseconds', 5000),
            ),
            'standing_funding_address' => $this->standingFundingAddressAvailability($operator),
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
        $required = [
            'payment-gateway.netbank.funding.api_url',
            'payment-gateway.netbank.funding.token_url',
            'payment-gateway.netbank.funding.client_id',
            'payment-gateway.netbank.funding.client_secret',
            'payment-gateway.netbank.funding.corporate_account_number',
            'payment-gateway.netbank.funding.corporate_account_name',
            'payment-gateway.netbank.funding.vca_alias',
            'payment-gateway.netbank.funding.vca_alias_token',
            'payment-gateway.netbank.funding.reference_key',
            'payment-gateway.netbank.funding.qr_endpoint',
            'payment-gateway.netbank.funding.qr_merchant_name',
            'payment-gateway.netbank.funding.qr_merchant_city',
        ];
        $configured = collect($required)->every(
            fn (string $key): bool => is_string(config($key))
                && trim((string) config($key)) !== '',
        );
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

        return [
            'enabled' => $enabled,
            'available' => $enabled && $configured,
            'status' => match (true) {
                ! $enabled => 'disabled',
                ! $configured => 'not_configured',
                default => 'available',
            },
            'provider' => 'netbank',
            'exists' => $address !== null,
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
