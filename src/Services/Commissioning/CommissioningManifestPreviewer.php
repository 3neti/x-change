<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commissioning;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;

final readonly class CommissioningManifestPreviewer
{
    public function __construct(
        private CommissioningManifestRepository $manifests,
        private TreasuryOpeningBalanceReconciliationService $reconciliation,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $manifestReference): array
    {
        $manifest = $this->manifests->load($manifestReference);
        $opening = (array) data_get($manifest, 'commissioning.opening', []);
        $policy = trim((string) ($opening['policy'] ?? ''));
        $connectionReference = trim((string) ($opening['connection'] ?? ''));
        $cutoverAt = trim((string) ($opening['cutover_at'] ?? ''));
        $cutoverTransactionId = trim((string) ($opening['cutover_transaction_id'] ?? ''));

        if ($policy !== 'system-capital') {
            throw new InvalidArgumentException('Hardened commissioning requires [commissioning.opening.policy] to be [system-capital].');
        }

        if ($connectionReference === '') {
            throw new InvalidArgumentException('Hardened commissioning requires [commissioning.opening.connection].');
        }

        if ($cutoverAt === '' || DateTimeImmutable::createFromFormat(DATE_ATOM, $cutoverAt) === false) {
            throw new InvalidArgumentException('Hardened commissioning requires an RFC 3339 [commissioning.opening.cutover_at].');
        }

        if ($cutoverTransactionId === '') {
            throw new InvalidArgumentException('Hardened commissioning requires [commissioning.opening.cutover_transaction_id].');
        }

        $onboardingConnection = trim((string) data_get($manifest, 'onboarding.connection_reference'));

        if ($onboardingConnection !== $connectionReference) {
            throw new InvalidArgumentException('Commissioning opening and onboarding must use the same Treasury connection.');
        }

        $observations = $this->reconciliation->observe([$connectionReference])->connections;

        if (count($observations) !== 1) {
            throw new InvalidArgumentException('Hardened commissioning requires exactly one provider balance observation.');
        }

        $observation = $observations[0];
        $allowedInitialState = $observation->status === TreasuryOpeningBalanceStatus::Reconciled
            || $observation->status === TreasuryOpeningBalanceStatus::ProviderSyncPending
            || ($observation->status === TreasuryOpeningBalanceStatus::ReviewRequired
                && $observation->reason === 'internal-inventory-not-registered');

        if (! $allowedInitialState) {
            throw new InvalidArgumentException(
                'Provider balance observation is not safe for opening commissioning: '.($observation->reason ?? $observation->status->value).'.',
            );
        }

        $invitationAmountMinor = (int) round(
            ((float) data_get($manifest, 'onboarding.invitation_amount', 0)) * 100,
        );
        $roles = (array) data_get($manifest, 'invitations.roles', []);
        $invitationReserveMinor = $invitationAmountMinor * count($roles);

        if ($invitationAmountMinor <= 0 || $roles === []) {
            throw new InvalidArgumentException('Hardened commissioning requires funded onboarding invitation roles.');
        }

        if ($observation->providerBalanceMinor < $invitationReserveMinor) {
            throw new InvalidArgumentException('Observed provider balance does not cover the commissioning invitation reserve.');
        }

        $facts = [
            'manifest_schema' => trim((string) data_get($manifest, 'schema')),
            'policy' => $policy,
            'connection' => $connectionReference,
            'currency' => $observation->currency,
            'cutover_at' => $cutoverAt,
            'cutover_transaction_id' => $cutoverTransactionId,
            'provider_balance_minor' => $observation->providerBalanceMinor,
            'provider_evidence_reference' => $observation->evidenceReference,
            'invitation_amount_minor' => $invitationAmountMinor,
            'invitation_count' => count($roles),
            'invitation_reserve_minor' => $invitationReserveMinor,
            'remaining_reserve_minor' => $observation->providerBalanceMinor - $invitationReserveMinor,
            'delivery_mode' => trim((string) data_get($manifest, 'commissioning.invitations.delivery_mode', 'manual')),
        ];

        if ($facts['delivery_mode'] !== 'manual') {
            throw new InvalidArgumentException('Hardened commissioning currently authorizes manual invitation delivery only.');
        }

        return [
            'schema' => 'x-change.commissioning-preview.v1',
            'ready' => true,
            'mutation' => false,
            'preview_token' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'facts' => $facts,
        ];
    }
}
