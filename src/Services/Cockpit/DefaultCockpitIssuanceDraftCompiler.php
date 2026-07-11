<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

class DefaultCockpitIssuanceDraftCompiler implements CockpitIssuanceDraftCompilerContract
{
    /**
     * @return array<string, mixed>
     */
    public function compile(CockpitIssuanceDraftData $draft): array
    {
        $payload = [
            'cash' => [
                'amount' => $draft->amount,
                'currency' => $draft->currency,
                'validation' => $draft->validation,
            ],
            'inputs' => [
                'fields' => array_values($draft->input_fields),
            ],
            'count' => max(1, $draft->count),
            'feedback' => $this->feedback($draft),
            'rider' => $this->rider($draft),
            'metadata' => $this->metadata($draft),
        ];

        if (filled($draft->idempotency_key) || filled($draft->correlation_id)) {
            $payload['_meta'] = array_filter([
                'idempotency_key' => $draft->idempotency_key,
                'correlation_id' => $draft->correlation_id,
                'source' => 'cockpit.issuance-draft',
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedback(CockpitIssuanceDraftData $draft): array
    {
        return [
            'email' => $draft->feedback['email'] ?? null,
            'mobile' => $draft->feedback['mobile'] ?? $draft->recipient_reference,
            'webhook' => $draft->feedback['webhook'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rider(CockpitIssuanceDraftData $draft): array
    {
        return [
            'message' => $draft->rider['message'] ?? $draft->purpose,
            'url' => $draft->rider['url'] ?? null,
            'splash' => $draft->rider['splash'] ?? null,
            'splash_timeout' => $draft->rider['splash_timeout'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(CockpitIssuanceDraftData $draft): array
    {
        return array_replace_recursive($draft->metadata, [
            'custom' => [
                'cockpit' => [
                    'template_key' => $draft->template_key,
                    'source' => 'cockpit.issuance-draft',
                    'schema' => $draft->schema,
                ],
            ],
            'campaign' => $draft->campaign?->toArray(),
        ]);
    }
}
