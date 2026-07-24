<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\PaymentGateway\Enums\NetbankStandingAddressScheme;
use LBHurtado\PaymentGateway\Funding\NetbankStandingAddressProfile;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Cockpit\FundingCockpitReadModelProvider;
use LBHurtado\XChange\Services\Funding\Base64PngQrPhFundingSimulationQrRenderer;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;
use Throwable;

class CockpitFundingPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly FundingCockpitReadModelProvider $funding,
        private readonly Base64PngQrPhFundingSimulationQrRenderer $simulationQr,
        private readonly NetbankStandingAddressProfile $standingAddressProfile,
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

        return Inertia::render('x-change/cockpit/Funding', [
            ...$this->props->toArray(),
            'funding_read_model' => $this->funding->forOperator($operator)->toArray(),
            'funding_instruction' => $request->session()->pull('funding_instruction'),
            'funding_notice' => $request->session()->pull('funding_notice'),
            'funding_poll_interval' => max(
                1000,
                (int) config('x-change.funding.ui_refresh_interval_milliseconds', 5000),
            ),
            'funding_realtime' => [
                'enabled' => (bool) config('x-change.funding.broadcast_enabled', true),
                'channel' => $this->fundingChannels->nameForOwner($operator),
                'event' => '.FundingProjectionChanged',
            ],
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
        $scheme = $address?->derivation_scheme;
        $profileReady = false;
        $status = 'available';

        if ($enabled && $configured) {
            try {
                $this->standingAddressProfile->referenceLength();

                if ($address instanceof StandingFundingAddress) {
                    $profileReady = strlen($address->funding_address_ciphertext) === 16;
                    $status = $profileReady ? 'available' : 'legacy_address_requires_retirement';
                    $scheme ??= 'legacy-unclassified';
                } else {
                    $selectedScheme = $this->standingAddressProfile->scheme();
                    $scheme = $selectedScheme->value;

                    if ($selectedScheme === NetbankStandingAddressScheme::MobileV1) {
                        $mobile = MobileNumber::normalize(
                            $operator->getAttribute('mobile'),
                        );
                        $profileReady = $operator->getAttribute('mobile_verified_at') !== null
                            && is_string($mobile)
                            && preg_match('/\A639\d{9}\z/', $mobile) === 1;
                        $status = $profileReady ? 'available' : 'mobile_not_verified';
                    } else {
                        $this->standingAddressProfile->derive(
                            new StandingFundingAddressRequestData(
                                ownerReference: 'cockpit-readiness',
                                accountReference: 'cockpit-readiness',
                                purpose: FundingAddressPurpose::AccountFunding,
                                currency: 'PHP',
                            ),
                        );
                        $profileReady = true;
                    }
                }
            } catch (Throwable) {
                $profileReady = false;
                $status = 'not_configured';
            }
        }

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
            'scheme_label' => match ($scheme) {
                NetbankStandingAddressScheme::MobileV1->value => 'Verified mobile suffix',
                NetbankStandingAddressScheme::AccountHmacV2->value => 'Opaque Account reference',
                default => 'Persisted legacy address',
            },
            'scheme_warning' => $scheme === NetbankStandingAddressScheme::MobileV1->value
                ? 'Development-friendly but easier to correlate; production rejects this scheme.'
                : null,
            'production_safe' => $scheme === NetbankStandingAddressScheme::AccountHmacV2->value,
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
