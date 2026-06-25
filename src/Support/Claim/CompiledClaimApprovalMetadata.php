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
            'provider' => self::nullableString(data_get($metadata, 'provider')),
            'authorization_type' => self::nullableString(data_get($metadata, 'authorization_type')),
            'reference_id' => self::nullableString(data_get($metadata, 'reference_id')),
            'expires_at' => self::nullableString(data_get($metadata, 'expires_at')),
            'otp_required' => (bool) data_get($metadata, 'otp_required', false),
            'polling_required' => (bool) data_get($metadata, 'polling_required', false),
            'manual_review' => (bool) data_get($metadata, 'manual_review', false),
            'message' => self::nullableString(data_get($metadata, 'message')),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
