<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowCatalog;
use LBHurtado\SettlementEnvelope\Data\WorkflowContext;
use LBHurtado\SettlementEnvelope\Data\WorkflowDescriptor;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Settlement\CampaignWorkflowPublicationSnapshot;

final class CampaignWorkflowDraftEditor
{
    public const SNAPSHOT_PATH = 'metadata.custom.campaign_workflow_draft';

    public function __construct(private WorkflowCatalog $catalog, private CampaignWorkflowPublicationSnapshot $snapshots) {}

    public function context(Model $owner): WorkflowContext
    {
        $identity = $owner->getMorphClass().':'.$owner->getKey();

        return new WorkflowContext(actorId: $identity, accountId: $identity);
    }

    public function resolve(Model $owner, string $id, string $version): WorkflowDescriptor
    {
        return $this->catalog->resolve($id, $version, $this->context($owner));
    }

    /** @return array<string, mixed> */
    public function for(Model $owner): array
    {
        return [
            'action_url' => route('x-change.cockpit.campaigns.workflow-drafts.store'),
            'workflows' => array_map($this->presentation(...), $this->catalog->available($this->context($owner))),
            'drafts' => PayCodeTemplate::query()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey())
                ->where('status', 'draft')
                ->latest('id')->limit(50)->get()
                ->filter(fn (PayCodeTemplate $template): bool => is_array(data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH)))
                ->map(fn (PayCodeTemplate $template): array => [
                    'reference' => $template->reference,
                    'name' => $template->name,
                    'updated_at' => $template->updated_at?->toIso8601String(),
                    'workflow_title' => data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH.'.workflow.title'),
                    'plan_title' => data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH.'.plan.title'),
                    'entry_method' => data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH.'.entry_method'),
                    'expected_snapshot_hash' => $this->snapshots->hash((array) $template->instructions_ciphertext),
                    'publish_url' => data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH.'.workflow.id') === 'aui.personal-accident.provisional-cover'
                        && data_get($template->instructions_ciphertext, self::SNAPSHOT_PATH.'.entry_method') === 'payment_qr'
                        ? route('x-change.cockpit.campaigns.workflow-drafts.publish', ['draft' => $template->reference]) : null,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentation(WorkflowDescriptor $descriptor): array
    {
        return [
            'id' => $descriptor->id,
            'version' => $descriptor->version,
            'title' => $descriptor->title,
            'service' => $descriptor->workflow->service,
            'entry_methods' => array_map(fn ($entry): string => $entry->value, $descriptor->workflow->entry_methods),
            'plans' => $descriptor->workflow->plans->toArray(),
            'notifications' => $descriptor->workflow->notifications->toArray(),
            'documents' => $descriptor->documents->toArray(),
            'checklist' => $descriptor->checklist->toArray(),
            'gates' => $descriptor->gates,
            'requires_review' => $descriptor->workflow->requires_review,
            'configured' => $descriptor->readiness->configured,
        ];
    }
}
