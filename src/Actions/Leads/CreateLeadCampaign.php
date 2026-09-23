<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\XCampaign\Contracts\EndpointCampaignRepository;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Services\Leads\LeadCampaignPublicSlugService;
use LBHurtado\XChange\Services\Leads\LeadCampaignTemplateVersionId;

final readonly class CreateLeadCampaign
{
    public function __construct(
        private FundingQrMerchantProfileResolver $merchantProfiles,
        private LeadCampaignPublicSlugService $slugs,
        private EndpointCampaignRepository $endpoints,
        private LeadCampaignTemplateVersionId $templateVersions,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $owner, PayCodeTemplate $template, array $attributes): LeadCampaign
    {
        if (
            $template->owner_type !== $owner->getMorphClass()
            || (string) $template->owner_id !== (string) $owner->getKey()
        ) {
            throw new AuthorizationException('The Pay Code template does not belong to this account.');
        }

        if ($template->status !== 'active') {
            throw ValidationException::withMessages([
                'template' => 'Choose an active Pay Code template for this Lead Campaign.',
            ]);
        }

        $title = trim((string) ($attributes['title'] ?? $template->name));

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Give this Lead Campaign a title.',
            ]);
        }

        $merchant = $this->merchantProfiles->resolve($owner);
        $merchantSlug = $this->slugs->merchantSlug($merchant->displayName, $owner);
        $endpointSlug = $this->slugs->availableEndpointSlug(
            $merchantSlug,
            (string) ($attributes['endpoint_slug'] ?? $title),
        );

        $settings = [
            'kind' => 'lead',
            'entry_point' => 'public_qr_link',
            'entry_mode' => CampaignEntryMode::PayCodeOnOpen->value,
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
            ...(array) ($attributes['settings'] ?? []),
        ];

        return $this->endpoints->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'pay_code_template_id' => $template->getKey(),
            'active_template_version_id' => $this->templateVersions->forTemplate($template),
            'merchant_display_name' => $merchant->displayName,
            'merchant_slug' => $merchantSlug,
            'endpoint_slug' => $endpointSlug,
            'title' => $title,
            'description' => filled($attributes['description'] ?? null)
                ? trim((string) $attributes['description'])
                : null,
            'status' => (string) ($attributes['status'] ?? 'active'),
            'starts_limit' => filled($attributes['starts_limit'] ?? null)
                ? (int) $attributes['starts_limit']
                : null,
            'expires_at' => $attributes['expires_at'] ?? null,
            'merchant_certification_status' => 'none',
            'settings' => $settings,
        ]);
    }
}
