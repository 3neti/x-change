<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException;
use LBHurtado\SettlementEnvelope\Exceptions\InvalidDriverException;
use LBHurtado\XCampaign\Contracts\EndpointCampaignRepository;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Models\CampaignWorkflowPublication;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\CampaignWorkflowDraftEditor;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Services\Leads\LeadCampaignPublicSlugService;
use LBHurtado\XChange\Services\Settlement\CampaignWorkflowPublicationSnapshot;

final class PublishCampaignWorkflowDraft
{
    public function __construct(
        private CampaignWorkflowDraftEditor $editor,
        private CampaignWorkflowPublicationSnapshot $snapshots,
        private EndpointCampaignRepository $campaigns,
        private FundingQrMerchantProfileResolver $merchants,
        private LeadCampaignPublicSlugService $slugs,
    ) {}

    public function handle(Model $owner, string $reference, string $expectedHash): CampaignWorkflowPublication
    {
        return DB::transaction(function () use ($owner, $reference, $expectedHash): CampaignWorkflowPublication {
            $template = PayCodeTemplate::query()->where('reference', $reference)
                ->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey())
                ->whereIn('status', ['draft', 'published'])->lockForUpdate()->firstOrFail();
            $instructions = (array) $template->instructions_ciphertext;
            if (! hash_equals($this->snapshots->hash($instructions), $expectedHash)) {
                throw ValidationException::withMessages(['draft' => 'The draft changed. Reload before publishing.']);
            }
            $draft = (array) data_get($instructions, CampaignWorkflowDraftEditor::SNAPSHOT_PATH, []);
            try {
                $descriptor = $this->editor->resolve($owner, (string) data_get($draft, 'workflow.id'), (string) data_get($draft, 'workflow.version'));
                if (! $descriptor->readiness->configured) {
                    throw new DomainException('The private workflow connection is not ready.');
                }
                if ($this->snapshots->hash(Arr::except((array) ($draft['workflow'] ?? []), ['readiness']))
                    !== $this->snapshots->hash(Arr::except($descriptor->toArray(), ['readiness']))) {
                    throw new DomainException('The workflow definition changed. Create a new reviewed draft.');
                }
                Arr::forget($instructions, CampaignWorkflowDraftEditor::SNAPSHOT_PATH);
                $snapshot = $this->snapshots->fromDraft($draft, $instructions);
            } catch (DomainException|DriverNotFoundException|InvalidDriverException $exception) {
                throw ValidationException::withMessages(['draft' => $exception instanceof DomainException
                    ? $exception->getMessage() : 'This exact workflow is unavailable for this account.']);
            }
            $existing = CampaignWorkflowPublication::query()->where('draft_template_id', $template->getKey())->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($template->status !== 'draft') {
                throw ValidationException::withMessages(['draft' => 'The publication record is unavailable.']);
            }
            $hash = $this->snapshots->hash($snapshot);
            $revision = 'cwp_'.substr($hash, 0, 40);
            $merchant = $this->merchants->resolve($owner);
            $merchantSlug = $this->slugs->merchantSlug($merchant->displayName, $owner);
            $campaign = $this->campaigns->create([
                'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(),
                'pay_code_template_id' => $template->getKey(), 'active_template_version_id' => $revision,
                'merchant_display_name' => $merchant->displayName, 'merchant_slug' => $merchantSlug,
                'endpoint_slug' => $this->slugs->availableEndpointSlug($merchantSlug, $template->name),
                'title' => $template->name, 'description' => $template->description, 'status' => 'active',
                'merchant_certification_status' => 'none',
                'settings' => [
                    'kind' => 'collection', 'usage_key' => 'collection', 'entry_point' => 'payment_qr',
                    'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
                    'person_type' => 'payer', 'pay_code_generation' => 'on_payment',
                    'workflow_publication' => ['revision' => $revision],
                ],
            ]);
            $publication = CampaignWorkflowPublication::query()->create([
                'endpoint_campaign_id' => $campaign->getKey(), 'campaign_revision_id' => $revision,
                'draft_template_id' => $template->getKey(), 'snapshot' => $snapshot, 'snapshot_hash' => $hash,
                'published_by_type' => $owner->getMorphClass(), 'published_by_id' => (string) $owner->getKey(),
                'published_at' => now(),
            ]);
            $template->forceFill(['status' => 'published'])->save();

            return $publication;
        });
    }
}
