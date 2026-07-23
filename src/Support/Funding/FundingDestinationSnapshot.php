<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Funding;

use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;

class FundingDestinationSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function fromData(FundingDestinationData $destination): array
    {
        return [
            'provider' => $destination->provider,
            'mode' => $destination->mode,
            'destinationType' => $destination->destinationType,
            'accountReference' => $destination->accountReference,
            'displayReference' => $destination->displayReference,
            'fingerprint' => $destination->fingerprint,
            'verificationStatus' => $destination->verificationStatus,
            'providerAccountId' => $destination->providerAccountId,
            'providerWalletId' => $destination->providerWalletId,
            'bankAccountNumber' => $destination->bankAccountNumber,
            'bankAccountName' => $destination->bankAccountName,
            'routingAlias' => $destination->routingAlias,
            'routingCredential' => $destination->routingCredential,
            'metadata' => $destination->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function toData(array $snapshot): FundingDestinationData
    {
        return new FundingDestinationData(
            provider: (string) ($snapshot['provider'] ?? ''),
            mode: (string) ($snapshot['mode'] ?? ''),
            destinationType: (string) ($snapshot['destinationType'] ?? ''),
            accountReference: (string) ($snapshot['accountReference'] ?? ''),
            displayReference: (string) ($snapshot['displayReference'] ?? ''),
            fingerprint: (string) ($snapshot['fingerprint'] ?? ''),
            verificationStatus: (string) ($snapshot['verificationStatus'] ?? ''),
            providerAccountId: self::optionalString($snapshot['providerAccountId'] ?? null),
            providerWalletId: self::optionalString($snapshot['providerWalletId'] ?? null),
            bankAccountNumber: self::optionalString($snapshot['bankAccountNumber'] ?? null),
            bankAccountName: self::optionalString($snapshot['bankAccountName'] ?? null),
            routingAlias: self::optionalString($snapshot['routingAlias'] ?? null),
            routingCredential: self::optionalString($snapshot['routingCredential'] ?? null),
            metadata: (array) ($snapshot['metadata'] ?? []),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
