<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\QuickGenerateTemplateBlueprintSanitizer;

final readonly class SavePayCodeTemplate
{
    public function __construct(
        private CockpitIssuanceTemplateRegistryContract $templates,
        private QuickGenerateTemplateBlueprintSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Model $owner,
        array $attributes,
        ?PayCodeTemplate $template = null,
    ): PayCodeTemplate {
        $baseTemplateKey = (string) $attributes['base_template_key'];

        if ($this->templates->resolve($baseTemplateKey)?->enabled !== true) {
            throw ValidationException::withMessages([
                'base_template_key' => 'Choose an available Pay Code template.',
            ]);
        }

        $values = [
            'name' => trim((string) $attributes['name']),
            'description' => filled($attributes['description'] ?? null)
                ? trim((string) $attributes['description'])
                : null,
            'base_template_key' => $baseTemplateKey,
            'instructions_ciphertext' => $this->sanitizer->sanitize(
                $attributes['instructions'],
                (bool) $attributes['include_amount'],
                (bool) $attributes['include_purpose'],
            ),
            'include_amount' => (bool) $attributes['include_amount'],
            'include_purpose' => (bool) $attributes['include_purpose'],
            'status' => 'active',
        ];

        if ($template === null) {
            return PayCodeTemplate::query()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
                ...$values,
            ]);
        }

        return DB::transaction(function () use (
            $attributes,
            $owner,
            $template,
            $values,
        ): PayCodeTemplate {
            $locked = PayCodeTemplate::query()
                ->lockForUpdate()
                ->findOrFail($template->getKey());

            if (
                $locked->owner_type !== $owner->getMorphClass()
                || (string) $locked->owner_id !== (string) $owner->getKey()
                || $locked->status !== 'active'
            ) {
                throw new AuthorizationException;
            }

            $expectedUpdatedAt = (string) ($attributes['expected_updated_at'] ?? '');
            $actualUpdatedAt = $locked->updated_at?->toIso8601String() ?? '';

            if (
                $expectedUpdatedAt === ''
                || ! hash_equals($actualUpdatedAt, $expectedUpdatedAt)
            ) {
                throw ValidationException::withMessages([
                    'expected_updated_at' => 'This template changed in another session. Reopen it before saving your changes.',
                ]);
            }

            $locked->fill($values)->save();

            return $locked->refresh();
        });
    }
}
