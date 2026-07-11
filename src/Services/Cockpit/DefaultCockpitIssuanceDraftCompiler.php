<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceTemplateProfileData;

class DefaultCockpitIssuanceDraftCompiler implements CockpitIssuanceDraftCompilerContract
{
    public function __construct(
        private readonly ?CockpitIssuanceTemplateRegistryContract $templates = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compile(CockpitIssuanceDraftData $draft): array
    {
        $template = $this->template($draft);

        $payload = [
            'cash' => [
                'amount' => $draft->amount,
                'currency' => $draft->currency,
                'validation' => $this->validation($draft, $template),
            ],
            'inputs' => [
                'fields' => $this->inputFields($draft, $template),
            ],
            'count' => max(1, $draft->count),
            'feedback' => $this->feedback($draft, $template),
            'rider' => $this->rider($draft, $template),
            'metadata' => $this->metadata($draft, $template),
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
    private function feedback(CockpitIssuanceDraftData $draft, ?CockpitIssuanceTemplateProfileData $template): array
    {
        return array_replace_recursive($template?->default_feedback ?? [], [
            'email' => $draft->feedback['email'] ?? null,
            'mobile' => $draft->feedback['mobile'] ?? $draft->recipient_reference,
            'webhook' => $draft->feedback['webhook'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rider(CockpitIssuanceDraftData $draft, ?CockpitIssuanceTemplateProfileData $template): array
    {
        return array_replace_recursive($template?->default_rider ?? [], [
            'message' => $draft->rider['message'] ?? $draft->purpose ?? data_get($template?->default_rider, 'message'),
            'url' => $draft->rider['url'] ?? null,
            'splash' => $draft->rider['splash'] ?? null,
            'splash_timeout' => $draft->rider['splash_timeout'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(CockpitIssuanceDraftData $draft, ?CockpitIssuanceTemplateProfileData $template): array
    {
        return array_replace_recursive([
            'template' => $template?->metadata ?? [],
        ], $draft->metadata, [
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

    private function template(CockpitIssuanceDraftData $draft): ?CockpitIssuanceTemplateProfileData
    {
        if (! filled($draft->template_key)) {
            return null;
        }

        return $this->templates?->resolve((string) $draft->template_key);
    }

    /**
     * @return array<string, mixed>
     */
    private function validation(CockpitIssuanceDraftData $draft, ?CockpitIssuanceTemplateProfileData $template): array
    {
        return array_replace_recursive($template?->default_validation ?? [], $draft->validation);
    }

    /**
     * @return array<int, string>
     */
    private function inputFields(CockpitIssuanceDraftData $draft, ?CockpitIssuanceTemplateProfileData $template): array
    {
        return array_values(array_unique([
            ...($template?->default_input_fields ?? []),
            ...$draft->input_fields,
        ]));
    }
}
