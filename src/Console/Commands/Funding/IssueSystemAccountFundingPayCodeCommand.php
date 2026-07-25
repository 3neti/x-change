<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Contracts\SystemAccountFundingPayCodeAuthorizationContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;
use Throwable;

final class IssueSystemAccountFundingPayCodeCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:issue-pay-code
        {--amount= : Exact amount in the provider currency, such as 1000.00}
        {--recipient-id= : Account owner allowed to claim the Pay Code}
        {--bearer : Explicitly issue an unbound Pay Code when enabled}
        {--connection= : Treasury connection reference}
        {--reference= : Stable idempotency reference}
        {--expires-at= : Explicit ISO-8601 expiry; defaults to the configured TTL}
        {--evidence-reference= : Reference to verified backing evidence}
        {--authorization-reference= : Reference to the approving control record}
        {--commit : Reserve system funds and issue the Pay Code}
        {--confirm-production : Explicitly acknowledge production issuance}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Preview or issue a system-backed Account Funding Pay Code';

    public function handle(
        TreasuryProviderConnectionCatalog $connections,
        SystemUserResolverContract $systemUsers,
        TreasuryPrincipalReferenceResolverContract $principalReferences,
        TreasuryPositionReadModelContract $positions,
        SystemAccountFundingPayCodeAuthorizationContract $authorization,
        IssueSystemAccountFundingPayCode $issue,
    ): int {
        try {
            $connection = $this->resolveConnection($connections);
            $reference = $this->requiredOption('reference');
            $amountMinor = $this->parseAmount(
                $this->requiredOption('amount'),
                $connection->decimalPlaces,
            );
            $recipient = $this->resolveRecipient();
            $bearer = (bool) $this->option('bearer');
            $commit = (bool) $this->option('commit');
            $evidenceReference = $this->optionalOption(
                'evidence-reference',
            );
            $authorizationReference = $this->optionalOption(
                'authorization-reference',
            );
            $expiresAt = $this->resolveExpiry($reference);
            $system = $systemUsers->resolve();

            if (
                ! $system instanceof Model
                || ! $system instanceof Authenticatable
            ) {
                throw new RuntimeException(
                    'The configured system principal is not an authenticatable model.',
                );
            }

            $authorization->authorize(
                new SystemAccountFundingPayCodeAuthorizationData(
                    amountMinor: $amountMinor,
                    connectionReference: $connection->reference,
                    bearer: $bearer,
                    commit: $commit,
                    productionConfirmed: (bool) $this->option(
                        'confirm-production',
                    ),
                    idempotencyReference: $reference,
                    evidenceReference: $evidenceReference,
                    authorizationReference: $authorizationReference,
                ),
            );

            $before = $this->positionBalances(
                $positions,
                $principalReferences->resolve($system),
                $connection,
            );

            if (! $commit) {
                $this->renderPayload(
                    $this->payload(
                        mode: 'preview',
                        status: $before['client_funds_minor'] >= $amountMinor
                            ? 'preview_ready'
                            : 'insufficient_system_funds',
                        connection: $connection,
                        amountMinor: $amountMinor,
                        reference: $reference,
                        expiresAt: $expiresAt,
                        recipient: $recipient,
                        bearer: $bearer,
                        before: $before,
                        after: [
                            'client_funds_minor' => $before['client_funds_minor'] - $amountMinor,
                            'pay_code_reserve_minor' => $before['pay_code_reserve_minor'] + $amountMinor,
                        ],
                    ),
                    'System Account Funding Pay Code preview',
                );

                return $before['client_funds_minor'] >= $amountMinor
                    ? self::SUCCESS
                    : self::FAILURE;
            }

            $existing = $this->findExisting($reference);
            $issuance = $issue->handle(
                new IssueSystemAccountFundingPayCodeData(
                    amountMinor: $amountMinor,
                    connectionReference: $connection->reference,
                    idempotencyReference: $reference,
                    expiresAt: $expiresAt,
                    recipient: $recipient,
                    evidenceReference: $evidenceReference,
                    source: 'system_utility',
                    metadata: [
                        'custom' => [
                            'system_account_funding' => [
                                'authorization_reference' => $authorizationReference,
                            ],
                        ],
                    ],
                ),
            );
            $after = $this->positionBalances(
                $positions,
                $principalReferences->resolve($system),
                $connection,
            );

            $this->renderPayload(
                $this->payload(
                    mode: 'commit',
                    status: $existing === null ? 'issued' : 'replayed',
                    connection: $connection,
                    amountMinor: $amountMinor,
                    reference: $reference,
                    expiresAt: $issuance->expires_at,
                    recipient: $recipient,
                    bearer: $bearer,
                    before: $before,
                    after: $after,
                    issuance: $issuance,
                ),
                $existing === null
                    ? 'Account Funding Pay Code issued'
                    : 'Existing Account Funding Pay Code returned',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.system-account-funding-pay-code-command.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'provider_calls' => false,
                'inventory_changed' => false,
            ]);

            return self::FAILURE;
        }
    }

    private function resolveConnection(
        TreasuryProviderConnectionCatalog $connections,
    ): TreasuryProviderConnectionData {
        $reference = $this->optionalOption('connection');

        if ($reference !== null) {
            return collect($connections->active([$reference]))->sole();
        }

        $active = $connections->active();

        if (count($active) !== 1) {
            throw new RuntimeException(
                'Select one active Treasury connection with --connection.',
            );
        }

        return $active[0];
    }

    private function resolveRecipient(): ?Model
    {
        $bearer = (bool) $this->option('bearer');
        $recipientId = $this->optionalOption('recipient-id');

        if ($bearer && $recipientId !== null) {
            throw new RuntimeException(
                'Use either --recipient-id or --bearer, not both.',
            );
        }

        if ($bearer) {
            return null;
        }

        if ($recipientId === null) {
            throw new RuntimeException(
                'Recipient-bound issuance requires --recipient-id.',
            );
        }

        $modelClass = (string) config(
            'auth.providers.users.model',
            '',
        );

        if (
            $modelClass === ''
            || ! is_subclass_of($modelClass, Model::class)
        ) {
            throw new RuntimeException(
                'The configured Account owner model is invalid.',
            );
        }

        $recipient = $modelClass::query()->find($recipientId);

        if (
            ! $recipient instanceof Model
            || ! $recipient instanceof Authenticatable
        ) {
            throw new RuntimeException(
                'The requested Account Funding recipient was not found.',
            );
        }

        return $recipient;
    }

    private function resolveExpiry(string $reference): Carbon
    {
        $existing = $this->findExisting($reference);
        $requestedExpiry = $this->optionalOption('expires-at');

        if ($requestedExpiry !== null) {
            return Carbon::parse($requestedExpiry);
        }

        if ($existing?->expires_at !== null) {
            return Carbon::parse(
                $existing->expires_at->toIso8601String(),
            );
        }

        $ttlSeconds = max(
            60,
            (int) config(
                'x-change.funding.system_pay_codes.default_ttl_seconds',
                604800,
            ),
        );

        return Carbon::parse(
            now()->addSeconds($ttlSeconds)->toIso8601String(),
        );
    }

    private function findExisting(
        string $reference,
    ): ?SystemAccountFundingPayCodeIssuance {
        return SystemAccountFundingPayCodeIssuance::query()
            ->with('voucher')
            ->where(
                'idempotency_reference_hash',
                hash('sha256', trim($reference)),
            )
            ->first();
    }

    /**
     * @return array{client_funds_minor: int, pay_code_reserve_minor: int}
     */
    private function positionBalances(
        TreasuryPositionReadModelContract $positions,
        string $principalReference,
        TreasuryProviderConnectionData $connection,
    ): array {
        $matching = array_values(array_filter(
            $positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->currency === $connection->currency
                && $position->connectionReference === $connection->reference,
        ));

        return [
            'client_funds_minor' => $this->balanceForPurpose(
                $matching,
                TreasuryPositionPurpose::ClientFunds,
            ),
            'pay_code_reserve_minor' => $this->balanceForPurpose(
                $matching,
                TreasuryPositionPurpose::PayCodeReserve,
            ),
        ];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function balanceForPurpose(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): int {
        return collect($positions)->first(
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        )?->balanceMinor ?? 0;
    }

    /**
     * @param  array{client_funds_minor: int, pay_code_reserve_minor: int}  $before
     * @param  array{client_funds_minor: int, pay_code_reserve_minor: int}  $after
     * @return array<string, mixed>
     */
    private function payload(
        string $mode,
        string $status,
        TreasuryProviderConnectionData $connection,
        int $amountMinor,
        string $reference,
        CarbonInterface $expiresAt,
        ?Model $recipient,
        bool $bearer,
        array $before,
        array $after,
        ?SystemAccountFundingPayCodeIssuance $issuance = null,
    ): array {
        $voucher = $issuance?->voucher;

        return [
            'schema' => 'x-change.system-account-funding-pay-code-command.v1',
            'success' => ! in_array(
                $status,
                ['insufficient_system_funds', 'rejected'],
                true,
            ),
            'mode' => $mode,
            'status' => $status,
            'reference' => $reference,
            'connection' => [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
            ],
            'amount' => [
                'minor' => $amountMinor,
                'formatted' => $this->formatAmount(
                    $amountMinor,
                    $connection->decimalPlaces,
                ),
            ],
            'recipient' => [
                'mode' => $bearer ? 'bearer' : 'bound',
                'id' => $recipient?->getKey(),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'positions' => [
                'before' => $before,
                'after' => $after,
            ],
            'pay_code' => $voucher instanceof Voucher
                ? [
                    'issuance_reference' => $issuance?->reference,
                    'voucher_id' => $voucher->getKey(),
                    'code' => $voucher->code,
                    'claim_url' => Route::has('x-change.claim.show')
                        ? route(
                            'x-change.claim.show',
                            ['code' => $voucher->code],
                        )
                        : null,
                ]
                : null,
            'provider_calls' => false,
            'inventory_changed' => false,
            'accounting' => $mode === 'preview'
                ? 'No mutation; proposed Client Funds to Pay Code Reserve transfer.'
                : 'System Client Funds reserved once in Pay Code Reserve.',
        ];
    }

    private function parseAmount(
        string $amount,
        int $decimalPlaces,
    ): int {
        $amount = trim($amount);
        $pattern = $decimalPlaces === 0
            ? '/^\d+$/'
            : '/^\d+(?:\.\d{1,'.$decimalPlaces.'})?$/';

        if (preg_match($pattern, $amount) !== 1) {
            throw new RuntimeException(
                "Amount must be a positive value with at most {$decimalPlaces} decimal places.",
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '',
        );
        $factor = 10 ** $decimalPlaces;

        if (strlen($whole) > 12) {
            throw new RuntimeException('Amount is too large.');
        }

        $minor = ((int) $whole * $factor)
            + (int) str_pad($fraction, $decimalPlaces, '0');

        if ($minor <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return $minor;
    }

    private function formatAmount(
        int $amountMinor,
        int $decimalPlaces,
    ): string {
        return number_format(
            $amountMinor / (10 ** $decimalPlaces),
            $decimalPlaces,
            '.',
            '',
        );
    }

    private function requiredOption(string $name): string
    {
        $value = $this->optionalOption($name);

        if ($value === null) {
            throw new RuntimeException(
                "The --{$name} option is required.",
            );
        }

        return $value;
    }

    private function optionalOption(string $name): ?string
    {
        $value = trim((string) $this->option($name));

        return $value === '' ? null : $value;
    }
}
