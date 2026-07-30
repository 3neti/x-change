<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;
use RuntimeException;

final readonly class IssueSystemAccountFundingPayCode
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private IssueTreasuryBackedPayCode $payCodes,
        private TreasuryPayCodeAccountingService $accounting,
        private VoucherClaimantReference $claimantReferences,
        private AccountFundingPayCodeJournal $journal,
        private VoucherIssuancePayloadNormalizer $instructions,
    ) {}

    public function handle(
        IssueSystemAccountFundingPayCodeData $data,
    ): SystemAccountFundingPayCodeIssuance {
        $system = $this->systemUsers->resolve();

        if (
            ! $system instanceof Model
            || ! $system instanceof Authenticatable
        ) {
            throw new RuntimeException(
                'The configured system principal is not an authenticatable model.',
            );
        }

        $recipient = $data->recipient;

        if (
            $recipient !== null
            && ! $recipient instanceof Authenticatable
        ) {
            throw new RuntimeException(
                'The Account Funding recipient is not authenticatable.',
            );
        }

        $connection = collect($this->connections->active([
            trim($data->connectionReference),
        ]))->sole();
        $idempotencyReference = trim($data->idempotencyReference);
        $evidenceReference = $this->requiredReference(
            (string) $data->evidenceReference,
            'evidence reference',
        );
        $authorizationReference = $this->requiredReference(
            (string) $data->authorizationReference,
            'authorization reference',
        );
        $source = $this->requiredReference($data->source, 'issuance source', 64);

        if ($data->amountMinor <= 0) {
            throw new RuntimeException(
                'System Account Funding Pay Code amount must be positive.',
            );
        }

        if (! $data->expiresAt->isFuture()) {
            throw new RuntimeException(
                'System Account Funding Pay Code expiry must be in the future.',
            );
        }

        $idempotencyReference = $this->requiredReference(
            $idempotencyReference,
            'idempotency reference',
        );
        $idempotencyReferenceHash = hash('sha256', $idempotencyReference);
        $claimantReference = $this->claimantReferences->for($recipient);
        $fingerprint = $this->fingerprint(
            $data,
            $connection->provider,
            $connection->currency,
            $system,
            $recipient,
        );

        $issuance = DB::transaction(function () use (
            $claimantReference,
            $connection,
            $data,
            $authorizationReference,
            $evidenceReference,
            $fingerprint,
            $idempotencyReferenceHash,
            $recipient,
            $source,
            $system,
        ): SystemAccountFundingPayCodeIssuance {
            $existing = SystemAccountFundingPayCodeIssuance::query()
                ->with('voucher')
                ->where(
                    'idempotency_reference_hash',
                    $idempotencyReferenceHash,
                )
                ->lockForUpdate()
                ->first();

            if ($existing instanceof SystemAccountFundingPayCodeIssuance) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new RuntimeException(
                        'The System Account Funding Pay Code reference was already used with different inputs.',
                    );
                }

                if (! $existing->voucher instanceof Voucher) {
                    throw new RuntimeException(
                        'The existing System Account Funding Pay Code issuance is incomplete.',
                    );
                }

                return $existing;
            }

            $issuance = SystemAccountFundingPayCodeIssuance::query()->create([
                'idempotency_reference_hash' => $idempotencyReferenceHash,
                'request_fingerprint' => $fingerprint,
                'source' => $source,
                'issuer_type' => $system::class,
                'issuer_id' => (string) $system->getKey(),
                'recipient_type' => $recipient?->getMorphClass(),
                'recipient_id' => $recipient === null
                    ? null
                    : (string) $recipient->getKey(),
                'bearer' => $recipient === null,
                'connection_reference' => $connection->reference,
                'provider_code' => $connection->provider,
                'amount_minor' => $data->amountMinor,
                'currency' => $connection->currency,
                'evidence_reference' => $evidenceReference,
                'authorization_reference' => $authorizationReference,
                'status' => 'preparing',
                'expires_at' => $data->expiresAt,
                'metadata' => $data->metadata,
            ]);

            $this->portfolios->provision(
                $system,
                [$connection->reference],
            );

            if ($recipient instanceof Model) {
                $this->portfolios->provision(
                    $recipient,
                    [$connection->reference],
                );
            }

            $voucherInstructions = [
                'cash' => [
                    'amount' => $data->amountMinor / (10 ** $connection->decimalPlaces),
                    'currency' => $connection->currency,
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'prefix' => 'FUND',
                'mask' => '****',
                'expires_at' => $data->expiresAt,
                'voucher_type' => 'redeemable',
                'claim' => [
                    'outcomes' => [[
                        'key' => 'account_funding',
                        'pricing_profile' => 'account-funding-v1',
                    ]],
                    'selection' => 'server',
                    'consumption' => 'one_of',
                    'default_outcome' => 'account_funding',
                    'onboarding' => [
                        'mode' => $data->onboarding ? 'required' : 'if_required',
                    ],
                    'claimant' => $recipient === null
                        ? ['mode' => 'unbound']
                        : [
                            'mode' => 'recipient',
                            'reference' => $claimantReference,
                        ],
                ],
                'metadata' => array_replace_recursive([
                    'issuer_id' => (string) $system->getAuthIdentifier(),
                    'source' => $source,
                    'custom' => [
                        'settlement' => [
                            'destinations' => ['account_funding'],
                            'account_funding' => [
                                'pricing_profile' => 'account-funding-v1',
                            ],
                        ],
                        'system_account_funding' => [
                            'issuance_reference' => $issuance->reference,
                            'source' => $source,
                        ],
                        'onboarding_grant' => [
                            'enabled' => $data->onboarding,
                            'provider_calls' => false,
                        ],
                    ],
                ], $data->metadata),
                'onboarding' => $data->onboarding,
            ];
            $voucher = $this->payCodes->handle(
                $system,
                $this->instructions->normalize($voucherInstructions),
                $data->expiresAt,
            );
            $reservation = $this->accounting->reserveAccountFunding(
                systemOwner: $system,
                voucher: $voucher,
                connectionReference: $connection->reference,
                providerPrincipalMinor: $data->amountMinor,
                currency: $connection->currency,
            );
            $voucherMetadata = is_array($voucher->metadata)
                ? $voucher->metadata
                : [];
            data_set($voucherMetadata, 'treasury.account_funding', [
                'status' => 'ready',
                'destinations' => ['account_funding'],
                'pricing_profile' => 'account-funding-v1',
                'provider_cost_minor' => 0,
                'provider_calls' => false,
                'system_issuance_reference' => $issuance->reference,
                'funding_request_reference' => data_get(
                    $data->metadata,
                    'custom.reviewed_funding.request_reference',
                ),
            ]);
            $voucher->forceFill(['metadata' => $voucherMetadata])->saveQuietly();
            $issuance->forceFill([
                'voucher_id' => $voucher->getKey(),
                'reservation_operation_reference' => $reservation->operationReference,
                'status' => 'issued',
                'issued_at' => now(),
            ])->save();

            return $issuance->refresh()->load('voucher');
        }, attempts: 5);

        DB::afterCommit(fn () => $this->journal->recordIssued($issuance));

        return $issuance;
    }

    private function requiredReference(
        string $value,
        string $label,
        int $maximumLength = 191,
    ): string {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $maximumLength) {
            throw new RuntimeException(
                "The System Account Funding Pay Code {$label} is invalid.",
            );
        }

        return $value;
    }

    private function fingerprint(
        IssueSystemAccountFundingPayCodeData $data,
        string $provider,
        string $currency,
        Model $system,
        ?Model $recipient,
    ): string {
        return hash('sha256', (string) json_encode([
            'amount_minor' => $data->amountMinor,
            'connection_reference' => trim($data->connectionReference),
            'provider' => $provider,
            'currency' => $currency,
            'system' => $system->getMorphClass().':'.$system->getKey(),
            'recipient' => $recipient === null
                ? 'bearer'
                : $recipient->getMorphClass().':'.$recipient->getKey(),
            'evidence_reference' => trim((string) $data->evidenceReference),
            'authorization_reference' => trim(
                (string) $data->authorizationReference,
            ),
            'source' => trim($data->source),
            'expires_at' => $data->expiresAt->toIso8601String(),
            'metadata' => $data->metadata,
            'onboarding' => $data->onboarding,
        ], JSON_THROW_ON_ERROR));
    }
}
