<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake\Contributors;

use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Keepsake\KeepsakePayCodeTemplateReferences;

final readonly class EndpointCampaignKeepsakeContributor implements InstanceKeepsakeContributor
{
    public function __construct(
        private CanonicalKeepsakeJson $json,
        private KeepsakePayCodeTemplateReferences $templateReferences,
    ) {}

    public function key(): string
    {
        return 'campaigns';
    }

    public function snapshotSchemaVersion(): int
    {
        return 1;
    }

    public function blueprintSchemaVersion(): ?int
    {
        return 1;
    }

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
    {
        if (! $context->includes('campaigns')) {
            return new InstanceKeepsakeContribution($this->key(), 1, 1);
        }

        $owners = [];
        $accountReferences = [];

        foreach ($context->users as $user) {
            $ownerType = $user['model']->getMorphClass();
            $ownerId = $user['model']->getKey();
            $owners[$ownerType][] = $ownerId;
            $accountReferences[$ownerType.'|'.$ownerId] = $user['reference'];
        }

        $templateReferences = $this->templateReferences->forContext($context);
        $campaigns = [];
        $blueprints = [];

        if ($owners !== []) {
            $query = LeadCampaign::query()
                ->select([
                    'id', 'reference', 'owner_type', 'owner_id', 'pay_code_template_id',
                    'active_template_version_id', 'merchant_display_name', 'merchant_slug',
                    'endpoint_slug', 'title', 'description', 'status', 'usage_count',
                    'last_started_at', 'starts_limit', 'expires_at', 'created_at', 'updated_at',
                ])
                ->where(function ($query) use ($owners): void {
                    foreach ($owners as $ownerType => $ownerIds) {
                        $query->orWhere(function ($owner) use ($ownerType, $ownerIds): void {
                            $owner->where('owner_type', $ownerType)->whereIn('owner_id', $ownerIds);
                        });
                    }
                })
                ->orderBy('id');

            foreach ($query->lazyById((int) config('x-change.instance_keepsake.chunk_size', 100)) as $campaign) {
                if (count($campaigns) >= (int) config('x-change.instance_keepsake.max_campaigns', 10_000)) {
                    throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake campaign limit was exceeded.');
                }

                $accountReference = $accountReferences[$campaign->owner_type.'|'.$campaign->owner_id] ?? null;
                $templateReference = $templateReferences[(string) $campaign->pay_code_template_id] ?? null;
                $reference = 'campaign-'.str_pad((string) (count($campaigns) + 1), 6, '0', STR_PAD_LEFT);
                $campaigns[] = [
                    'reference' => $reference,
                    'source_reference' => (string) $campaign->reference,
                    'account_reference' => $accountReference,
                    'template_reference' => $templateReference,
                    'active_template_version_id' => $campaign->active_template_version_id,
                    'merchant_display_name' => (string) $campaign->merchant_display_name,
                    'merchant_slug' => (string) $campaign->merchant_slug,
                    'endpoint_slug' => (string) $campaign->endpoint_slug,
                    'title' => (string) $campaign->title,
                    'description' => $campaign->description,
                    'status' => (string) $campaign->status,
                    'usage_count' => (int) $campaign->usage_count,
                    'last_started_at' => $campaign->last_started_at?->toIso8601String(),
                    'starts_limit' => $campaign->starts_limit,
                    'expires_at' => $campaign->expires_at?->toIso8601String(),
                    'created_at' => $campaign->created_at?->toIso8601String(),
                    'updated_at' => $campaign->updated_at?->toIso8601String(),
                    'settings_included' => false,
                    'historical_only' => true,
                    'restorable' => false,
                ];
                $blueprints[] = [
                    'reference' => $reference,
                    'account_reference' => $accountReference,
                    'template_reference' => $templateReference,
                    'merchant_display_name' => (string) $campaign->merchant_display_name,
                    'merchant_slug' => (string) $campaign->merchant_slug,
                    'endpoint_slug' => (string) $campaign->endpoint_slug,
                    'title' => (string) $campaign->title,
                    'description' => $campaign->description,
                    'starts_limit' => $campaign->starts_limit,
                    'expires_at' => $campaign->expires_at?->toIso8601String(),
                    'desired_state' => 'disabled',
                    'settings_included' => false,
                    'merchant_certification_included' => false,
                    'activation_authority_included' => false,
                    'requires_review' => true,
                ];
            }
        }

        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: 1,
            snapshotFiles: [
                'snapshot/endpoint-campaigns.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.endpoint-campaigns.v1',
                    'campaigns' => $campaigns,
                ]),
            ],
            blueprintFiles: $context->includes('blueprint') ? [
                'blueprint/endpoint-campaigns.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.endpoint-campaign-blueprints.v1',
                    'inert' => true,
                    'importer_included' => false,
                    'campaigns' => $blueprints,
                ]),
            ] : [],
            summary: [
                'endpoint_campaigns' => count($campaigns),
                'endpoint_campaign_blueprints' => count($blueprints),
            ],
        );
    }
}
