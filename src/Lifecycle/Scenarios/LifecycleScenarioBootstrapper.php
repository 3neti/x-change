<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use RuntimeException;

final class LifecycleScenarioBootstrapper
{
    public function __construct(
        private readonly EstimatePayCodeCost $estimatePayCodeCost,
        private readonly GeneratePayCode $generatePayCode,
        private readonly VoucherAccessContract $vouchers,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     */
    public function bootstrap(
        array $scenario,
        ?string $issuerOption = null,
        ?string $walletOption = null,
        ?float $amountOption = null,
        ?int $timeoutOption = null,
        ?int $pollOption = null,
        ?int $maxPollsOption = null,
    ): LifecycleScenarioBootstrapResult {
        $this->assertLifecycleUserModelSupportsMobile();

        $issuerId = (int) ($issuerOption ?: $scenario['issuer_id'] ?? 1);
        $walletId = (int) ($walletOption ?: $scenario['wallet_id'] ?? 1);
        $amount = (float) ($amountOption ?: $scenario['amount'] ?? 25);
        $timeout = (int) ($timeoutOption ?: $scenario['timeout'] ?? 180);
        $poll = max(1, (int) ($pollOption ?: $scenario['poll'] ?? 10));
        $maxPolls = $this->resolveMaxPolls($timeout, $poll, $maxPollsOption);

        $issuer = $this->resolveIssuerModel($issuerId);
        $baseClaimMobile = $this->resolveScenarioMobile($scenario, $issuer);
        $idempotencyKey = 'lifecycle-'.(string) str()->uuid();

        $lifecycleInput = $this->buildLifecycleInput(
            scenario: $scenario,
            issuerId: $issuerId,
            walletId: $walletId,
            amount: $amount,
            idempotencyKey: $idempotencyKey,
        );

        $estimate = $this->estimatePayCodeCost
            ->handle($lifecycleInput)
            ->toArray();

        $generated = $this->generatePayCode->handle($lifecycleInput);
        $voucher = $this->vouchers->findByCodeOrFail($generated->code);

        return new LifecycleScenarioBootstrapResult(
            issuerId: $issuerId,
            walletId: $walletId,
            amount: $amount,
            timeout: $timeout,
            poll: $poll,
            maxPolls: $maxPolls,
            issuer: $issuer,
            baseClaimMobile: $baseClaimMobile,
            idempotencyKey: $idempotencyKey,
            lifecycleInput: $lifecycleInput,
            estimate: $estimate,
            generated: $generated,
            voucher: $voucher,
        );
    }

    public function resolveMaxPolls(int $timeout, int $poll, ?string $maxPollsOption = null): ?int
    {
        if ($maxPollsOption !== null && $maxPollsOption !== '') {
            return max(1, (int) $maxPollsOption);
        }

        return (int) ceil($timeout / max(1, $poll));
    }

    public function assertLifecycleUserModelSupportsMobile(): void
    {
        $class = $this->userModelClass();

        if (! is_subclass_of($class, HasMobileChannel::class)) {
            throw new RuntimeException(sprintf(
                'Configured lifecycle user model [%s] must implement [%s].',
                $class,
                HasMobileChannel::class,
            ));
        }
    }

    public function userModelClass(): string
    {
        $class = (string) config('x-change.lifecycle.defaults.user_model', User::class);

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Configured lifecycle user model is invalid.');
        }

        return $class;
    }

    public function resolveIssuerModel(int $issuerId): Model
    {
        $class = $this->userModelClass();

        /** @var Model|null $issuer */
        $issuer = $class::query()->find($issuerId);

        if (! $issuer) {
            throw new RuntimeException(sprintf(
                'Unable to resolve lifecycle issuer [%s] using model [%s].',
                $issuerId,
                $class,
            ));
        }

        if (! $issuer instanceof HasMobileChannel) {
            throw new RuntimeException(sprintf(
                'Lifecycle issuer model [%s] must implement [%s].',
                $class,
                HasMobileChannel::class,
            ));
        }

        return $issuer;
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    public function resolveScenarioMobile(array $scenario, Model $issuer): string
    {
        $mobile = (string) (
        data_get($scenario, 'mobile')
            ?: ($issuer instanceof HasMobileChannel ? $issuer->getMobileChannel() : null)
            ?: ''
        );

        if ($mobile === '') {
            throw new RuntimeException(
                'Lifecycle scenario requires a mobile number either in scenario config or on the issuer mobile channel.'
            );
        }

        return $mobile;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function buildLifecycleInput(
        array $scenario,
        int $issuerId,
        int $walletId,
        float $amount,
        string $idempotencyKey,
    ): array {
        return [
            'issuer_id' => $issuerId,
            'wallet_id' => $walletId,

            'cash' => [
                'amount' => $amount,
                'currency' => $scenario['currency'] ?? 'PHP',
                'validation' => [
                    'secret' => data_get($scenario, 'cash.validation.secret'),
                    'mobile' => data_get($scenario, 'cash.validation.mobile'),
                    'payable' => data_get($scenario, 'cash.validation.payable'),
                    'country' => data_get($scenario, 'cash.validation.country', 'PH'),
                    'location' => data_get($scenario, 'cash.validation.location'),
                    'radius' => data_get($scenario, 'cash.validation.radius'),
                    'mobile_verification' => data_get($scenario, 'cash.validation.mobile_verification'),
                ],
                'settlement_rail' => data_get($scenario, 'cash.settlement_rail', 'INSTAPAY'),
                'fee_strategy' => data_get($scenario, 'cash.fee_strategy', 'absorb'),
                'slice_mode' => data_get($scenario, 'cash.slice_mode'),
                'slices' => data_get($scenario, 'cash.slices'),
                'max_slices' => data_get($scenario, 'cash.max_slices'),
                'min_withdrawal' => data_get($scenario, 'cash.min_withdrawal'),
            ],

            'inputs' => [
                'fields' => (array) data_get($scenario, 'inputs.fields', []),
            ],

            'feedback' => [
                'mobile' => data_get($scenario, 'feedback.mobile'),
                'email' => data_get($scenario, 'feedback.email'),
                'webhook' => data_get($scenario, 'feedback.webhook'),
            ],

            'rider' => [
                'message' => data_get($scenario, 'rider.message'),
                'url' => data_get($scenario, 'rider.url'),
                'redirect_timeout' => data_get($scenario, 'rider.redirect_timeout'),
                'splash' => data_get($scenario, 'rider.splash'),
                'splash_timeout' => data_get($scenario, 'rider.splash_timeout'),
                'og_source' => data_get($scenario, 'rider.og_source'),
            ],

            'count' => (int) data_get($scenario, 'count', 1),
            'prefix' => data_get($scenario, 'prefix', 'TEST'),
            'mask' => data_get($scenario, 'mask', '****'),
            'ttl' => data_get($scenario, 'ttl'),

            'starts_at' => data_get($scenario, 'starts_at'),
            'expires_at' => data_get($scenario, 'expires_at'),

            'validation' => data_get($scenario, 'validation'),
            'metadata' => data_get($scenario, 'metadata'),
            'voucher_type' => data_get($scenario, 'voucher_type'),
            'target_amount' => data_get($scenario, 'target_amount'),
            'rules' => data_get($scenario, 'rules'),

            '_meta' => [
                'idempotency_key' => $idempotencyKey,
            ],
        ];
    }
}
