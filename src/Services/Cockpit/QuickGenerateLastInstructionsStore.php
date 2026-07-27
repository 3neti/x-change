<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final class QuickGenerateLastInstructionsStore
{
    /**
     * @param  array<string, mixed>  $instructions
     */
    public function remember(
        Authenticatable $operator,
        array $instructions,
    ): void {
        Cache::put(
            $this->key($operator),
            [
                'schema' => 'x-change.cockpit.quick-generate-last-instructions.v1',
                'saved_at' => now()->toIso8601String(),
                'instructions' => $this->sanitize($instructions),
            ],
            now()->addSeconds(max(
                60,
                (int) config(
                    'x-change.cockpit.quick_generate.last_instructions_ttl_seconds',
                    604800,
                ),
            )),
        );
    }

    /**
     * @return array{schema: string, saved_at: string, instructions: array<string, mixed>}|null
     */
    public function for(?Authenticatable $operator): ?array
    {
        if ($operator === null) {
            return null;
        }

        $remembered = Cache::get($this->key($operator));

        if (
            ! is_array($remembered)
            || ($remembered['schema'] ?? null) !== 'x-change.cockpit.quick-generate-last-instructions.v1'
            || ! is_string($remembered['saved_at'] ?? null)
            || ! is_array($remembered['instructions'] ?? null)
        ) {
            return null;
        }

        return [
            'schema' => $remembered['schema'],
            'saved_at' => $remembered['saved_at'],
            'instructions' => $remembered['instructions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<string, mixed>
     */
    private function sanitize(array $instructions): array
    {
        data_set(
            $instructions,
            'metadata.custom.cockpit.template_preferences.mobile_validation',
            filled(data_get($instructions, 'cash.validation.mobile')),
        );

        Arr::forget($instructions, [
            'cash.validation.secret',
            'cash.validation.mobile',
            'validation.secret',
            'issuer_id',
            'metadata.issuer_id',
            'metadata.collection_wallet_id',
            'metadata.custom.cockpit.recipient_reference',
            'metadata.campaign',
            'metadata.custom.cockpit.campaign_context',
            'feedback.email',
            'feedback.mobile',
            'feedback.webhook',
            'starts_at',
            'expires_at',
        ]);

        return $instructions;
    }

    private function key(Authenticatable $operator): string
    {
        $identity = $operator::class.'|'.(string) $operator->getAuthIdentifier();

        return 'x-change:cockpit:quick-generate:last-instructions:'.hash('sha256', $identity);
    }
}
