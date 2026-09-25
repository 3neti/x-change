<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException;
use LBHurtado\SettlementEnvelope\Exceptions\InvalidDriverException;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\CampaignWorkflowDraftEditor;
use LBHurtado\XChange\Services\Cockpit\QuickGenerateTemplateBlueprintSanitizer;
use LBHurtado\XChange\Services\Leads\LeadCampaignTemplateVersionId;

final class SaveCampaignWorkflowDraft
{
    public function __construct(
        private CampaignWorkflowDraftEditor $editor,
        private QuickGenerateTemplateBlueprintSanitizer $sanitizer,
        private LeadCampaignTemplateVersionId $versions,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Model $owner, array $attributes): PayCodeTemplate
    {
        if (trim((string) ($attributes['name'] ?? '')) === '') {
            throw ValidationException::withMessages(['name' => 'Enter a draft name.']);
        }

        return DB::transaction(function () use ($owner, $attributes): PayCodeTemplate {
            $source = PayCodeTemplate::query()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey())
                ->where('status', 'active')
                ->lockForUpdate()->findOrFail($attributes['pay_code_template_id']);
            try {
                $descriptor = $this->editor->resolve($owner, $attributes['workflow_id'], $attributes['workflow_version']);
            } catch (DriverNotFoundException|InvalidDriverException) {
                throw ValidationException::withMessages(['workflow_id' => 'This workflow version is unavailable for this account.']);
            }

            $entries = array_map(fn ($entry): string => $entry->value, $descriptor->workflow->entry_methods);
            if (! in_array($attributes['entry_method'], $entries, true)) {
                throw ValidationException::withMessages(['entry_method' => 'This workflow does not support the selected entry method.']);
            }
            $plans = $descriptor->workflow->plans->toCollection();
            $plan = $plans->first(fn ($plan): bool => $plan->code === ($attributes['plan_code'] ?? null) && $plan->version === ($attributes['plan_version'] ?? null));
            if (($plans->isNotEmpty() && $plan === null) || ($plans->isEmpty() && (filled($attributes['plan_code'] ?? null) || filled($attributes['plan_version'] ?? null)))) {
                throw ValidationException::withMessages(['plan_code' => 'Select an exact plan version offered by this workflow.']);
            }

            $instructions = $this->sanitizer->sanitize((array) $source->instructions_ciphertext, $source->include_amount, $source->include_purpose);
            if (! is_array($instructions['cash'] ?? null)) {
                throw ValidationException::withMessages(['pay_code_template_id' => 'The saved template must contain cash instructions.']);
            }
            if ($attributes['entry_method'] === 'payment_qr' && ! in_array($instructions['voucher_type'] ?? null, ['payable', 'settlement'], true)) {
                throw ValidationException::withMessages(['pay_code_template_id' => 'Payment QR requires a payable or settlement template.']);
            }
            if ($plan !== null && data_get($instructions, 'cash.currency', 'PHP') !== $plan->currency) {
                throw ValidationException::withMessages(['pay_code_template_id' => 'The template currency must match the selected plan.']);
            }

            data_set($instructions, CampaignWorkflowDraftEditor::SNAPSHOT_PATH, [
                'schema_version' => 1,
                'state' => 'draft_only',
                'source_template_reference' => $source->reference,
                'source_template_version' => $this->versions->forTemplate($source),
                'workflow' => $descriptor->toArray(),
                'plan' => $plan?->toArray(),
                'entry_method' => $attributes['entry_method'],
                'parameters' => [],
                'created_by' => $owner->getMorphClass().':'.$owner->getKey(),
                'created_at' => now()->toIso8601String(),
            ]);

            return PayCodeTemplate::query()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
                'name' => trim($attributes['name']),
                'description' => $source->description,
                'base_template_key' => $source->base_template_key,
                'instructions_ciphertext' => $instructions,
                'include_amount' => $source->include_amount,
                'include_purpose' => $source->include_purpose,
                'status' => 'draft',
            ]);
        });
    }
}
