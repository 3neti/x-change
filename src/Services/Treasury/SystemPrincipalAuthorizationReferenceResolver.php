<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use JsonException;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class SystemPrincipalAuthorizationReferenceResolver
{
    /**
     * @param  class-string  $model
     *
     * @throws JsonException
     */
    public function resolve(
        ?string $explicitReference,
        ?string $persistedReference,
        string $model,
        string $identifierColumn,
        string $identifier,
    ): string {
        $explicitReference = trim((string) $explicitReference);

        if ($explicitReference !== '') {
            return $explicitReference;
        }

        $persistedReference = trim((string) $persistedReference);

        if ($persistedReference !== '') {
            return $persistedReference;
        }

        $key = (string) config('app.key');

        if ($key === '') {
            throw new TreasuryConfigurationException(
                'A stable application key is required to provision the system principal.',
            );
        }

        $payload = json_encode([
            'schema' => 1,
            'purpose' => 'system-principal-provisioning',
            'model' => $model,
            'identifier_column' => $identifierColumn,
            'identifier' => mb_strtolower(trim($identifier)),
        ], JSON_THROW_ON_ERROR);

        return 'system-principal:auto:v1:'.hash_hmac('sha256', $payload, $key);
    }
}
