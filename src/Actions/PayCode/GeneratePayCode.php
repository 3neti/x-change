<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;
use LBHurtado\XChange\Services\BuildProvisioningFlowDescriptor;
use LBHurtado\XChange\Services\InstructionRevenueAllocatorService;
use LBHurtado\XChange\Services\ResumeProviderProvisioningFromOnboarding;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;
use RuntimeException;

class GeneratePayCode
{
    public function __construct(
        protected UserResolverContract $users,
        protected WalletAccessContract $wallets,
        protected EstimatePayCodeCost $estimatePayCodeCost,
        protected PayCodeIssuanceContract $issuance,
        protected InstructionRevenueAllocatorService $allocator,
        protected ?ProviderReadinessGuardContract $readinessGuard = null,
        protected ?ProviderRuntimeSettingsResolverContract $settings = null,
        protected ?BuildProvisioningFlowDescriptor $descriptors = null,
        protected ?XChangeOnboardingGatewayContract $onboarding = null,
        protected ?ResumeProviderProvisioningFromOnboarding $onboardingProvisioning = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): GeneratePayCodeResultData
    {
        $input = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);

        $issuer = $this->users->resolve($input);

        if (! $issuer) {
            $issuerId = data_get($input, 'metadata.issuer_id');

            if ($issuerId) {
                $issuerModel = config(
                    'x-change.onboarding.issuer_model',
                    config('auth.providers.users.model')
                );

                if (is_string($issuerModel) && class_exists($issuerModel)) {
                    $issuer = $issuerModel::query()->find($issuerId);
                }
            }
        }

        if (! $issuer) {
            throw new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer.');
        }

        if ($this->shouldGuardProviderReadiness()) {
            $provider = $this->settings()->provider(data_get($input, 'provider'));
            $readiness = $this->readinessGuard()->evaluateIssuer($issuer, $provider);
            $resumedProvisioning = $this->resumeProvisioningFromOnboarding($issuer, $input, $provider, $readiness);

            if ($resumedProvisioning !== null && (bool) data_get($resumedProvisioning, 'ready') === true) {
                $readiness = $this->readinessGuard()->evaluateIssuer($issuer, $provider);
            }

            if (! $readiness->ready) {
                throw new ProviderProvisioningRequired(
                    'Pay Code issuance requires provider provisioning before the voucher can be created.',
                    $this->buildProvisioningContext(
                        purpose: 'issue_pay_code',
                        provider: $provider,
                        mode: $readiness->mode,
                        readiness: $readiness->toArray(),
                        onboarding: data_get($resumedProvisioning, 'onboarding')
                            ?? $this->startIssuerOnboarding($issuer, $input, $provider, $readiness->mode),
                    ),
                );
            }
        }

        $wallet = $this->wallets->resolveForUser($issuer);
        $estimate = $this->estimatePayCodeCost->handle($input);

        return DB::transaction(function () use ($issuer, $wallet, $input, $estimate): GeneratePayCodeResultData {
            $balanceBefore = $this->wallets->getBalance($wallet);

            $this->wallets->assertCanAfford($wallet, $estimate->total);

            $allocation = $this->allocator->allocate(
                issuer: $this->assertWalletableIssuer($issuer),
                estimate: $estimate,
                context: $this->buildAllocationContext($input, $estimate),
            );

            $issued = $this->issuance->issue($issuer, $input);

            $balanceAfter = $this->wallets->getBalance($wallet);

            return new GeneratePayCodeResultData(
                voucher_id: $issued['voucher_id'],
                code: (string) $issued['code'],
                amount: $issued['amount'],
                currency: (string) $issued['currency'],
                issuer: new IssuerData(
                    id: is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
                ),
                cost: $estimate,
                wallet: [
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
                debit: $this->normalizeDebit($allocation['debit'] ?? null),
                links: new PayCodeLinksData(
                    redeem: (string) data_get($issued, 'links.redeem'),
                    redeem_path: (string) data_get($issued, 'links.redeem_path'),
                ),
                allocations: $allocation['allocations'] ?? [],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function buildAllocationContext(array $input, PricingEstimateData $estimate): array
    {
        return [
            'requested_amount' => data_get($input, 'cash.amount'),
            'requested_currency' => data_get($input, 'cash.currency'),
            'idempotency_key' => data_get($input, '_meta.idempotency_key'),
            'correlation_id' => data_get($input, '_meta.correlation_id'),
            'cost' => [
                'currency' => $estimate->currency,
                'base_fee' => $estimate->base_fee,
                'components' => $estimate->components,
                'total' => $estimate->total,
                'charges' => $estimate->charges,
            ],
        ];
    }

    /**
     * @return object&Wallet
     */
    protected function assertWalletableIssuer(mixed $issuer): object
    {
        if (! is_object($issuer) || ! $issuer instanceof Wallet) {
            throw new RuntimeException('Resolved issuer is not wallet-enabled.');
        }

        return $issuer;
    }

    protected function normalizeDebit(mixed $debit): DebitData
    {
        if (is_object($debit)) {
            return new DebitData(
                id: $debit->id ?? null,
                amount: $debit->amount ?? null,
            );
        }

        if (is_array($debit)) {
            return new DebitData(
                id: $debit['id'] ?? null,
                amount: $debit['amount'] ?? null,
            );
        }

        return new DebitData;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    protected function resumeProvisioningFromOnboarding(
        mixed $issuer,
        array $input,
        string $provider,
        mixed $readiness,
    ): ?array {
        return $this->onboardingProvisioning()->handle(
            $this->onboardingReference($input),
            $issuer,
            [
                'provider' => $provider,
                'mode' => $readiness->mode,
                'purpose' => 'IssuePayCode',
                'status' => 'ready',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function startIssuerOnboarding(
        mixed $issuer,
        array $input,
        string $provider,
        ?string $mode,
    ): array {
        return (array) $this->onboarding()->startIssuer([
            'provider' => $provider,
            'mode' => $mode,
            'mobile' => is_object($issuer) ? ($issuer->mobile ?? null) : data_get($input, 'mobile'),
            'email' => is_object($issuer) ? ($issuer->email ?? null) : data_get($input, 'email'),
            'name' => is_object($issuer) ? ($issuer->name ?? null) : data_get($input, 'name'),
            'metadata' => [
                'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : data_get($input, 'metadata.issuer_id'),
                'onboarding_reference' => $this->onboardingReference($input),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $onboarding
     * @return array<string, mixed>
     */
    protected function buildProvisioningContext(
        string $purpose,
        string $provider,
        ?string $mode,
        array $readiness,
        array $onboarding,
    ): array {
        $descriptor = $this->descriptors()->handle(
            $provider,
            $mode ?? 'wallet_create',
            data_get($readiness, 'topology'),
        );

        return [
            'purpose' => $purpose,
            'provider' => $provider,
            'mode' => $mode,
            'reason' => data_get($readiness, 'reason'),
            'missing' => data_get($readiness, 'missing', []),
            'readiness' => $readiness,
            'onboarding' => $onboarding,
            'descriptor' => $descriptor->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function onboardingReference(array $input): ?string
    {
        $reference = data_get($input, 'onboarding.reference')
            ?? data_get($input, 'metadata.onboarding_reference')
            ?? data_get($input, '_meta.onboarding_reference');

        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        return trim($reference);
    }

    protected function readinessGuard(): ProviderReadinessGuardContract
    {
        return $this->readinessGuard ??= app(ProviderReadinessGuardContract::class);
    }

    protected function settings(): ProviderRuntimeSettingsResolverContract
    {
        return $this->settings ??= app(ProviderRuntimeSettingsResolverContract::class);
    }

    protected function descriptors(): BuildProvisioningFlowDescriptor
    {
        return $this->descriptors ??= app(BuildProvisioningFlowDescriptor::class);
    }

    protected function onboarding(): XChangeOnboardingGatewayContract
    {
        return $this->onboarding ??= app(XChangeOnboardingGatewayContract::class);
    }

    protected function onboardingProvisioning(): ResumeProviderProvisioningFromOnboarding
    {
        return $this->onboardingProvisioning ??= app(ResumeProviderProvisioningFromOnboarding::class);
    }

    protected function shouldGuardProviderReadiness(): bool
    {
        if ($this->readinessGuard !== null || $this->settings !== null) {
            return true;
        }

        return app()->bound(ProviderReadinessGuardContract::class)
            && app()->bound(ProviderRuntimeSettingsResolverContract::class);
    }
}
