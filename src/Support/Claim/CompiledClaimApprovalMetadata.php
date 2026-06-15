<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class CompiledClaimApprovalMetadata
{
    public static function fromResult(mixed $result): array
    {
        $metadata = data_get($result, 'approval_metadata')
            ?? data_get($result, 'approval_meta')
            ?? data_get($result, 'meta')
            ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        return self::normalize($metadata);
    }

    public static function normalize(array $metadata): array
    {
        return [
            'provider' => data_get($metadata, 'provider'),
            'authorization_type' => data_get($metadata, 'authorization_type'),
            'reference_id' => data_get($metadata, 'reference_id'),
            'otp_required' => (bool) data_get($metadata, 'otp_required', false),
            'expires_at' => data_get($metadata, 'expires_at'),
            'polling_required' => (bool) data_get($metadata, 'polling_required', false),
            'manual_review' => (bool) data_get($metadata, 'manual_review', false),
            'message' => data_get($metadata, 'message'),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
