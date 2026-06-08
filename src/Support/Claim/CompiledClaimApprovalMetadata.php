<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class CompiledClaimApprovalMetadata
{
    public static function fromResult(mixed $result): array
    {
        $metadata = data_get($result, 'approval_metadata', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        return self::normalize($metadata);
    }

    public static function normalize(array $metadata): array
    {
        return [
            'provider' => self::nullableString($metadata['provider'] ?? null),
            'authorization_type' => self::nullableString($metadata['authorization_type'] ?? null),
            'reference_id' => self::nullableString($metadata['reference_id'] ?? null),
            'expires_at' => self::nullableString($metadata['expires_at'] ?? null),
            'otp_required' => (bool) ($metadata['otp_required'] ?? false),
            'polling_required' => (bool) ($metadata['polling_required'] ?? false),
            'manual_review' => (bool) ($metadata['manual_review'] ?? false),
            'message' => self::nullableString($metadata['message'] ?? null),
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
