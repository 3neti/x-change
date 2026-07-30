<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;

/**
 * @deprecated Use ClaimAuthenticationIntent.
 */
final class CampaignOfficerAuthorizationLoginIntent
{
    public const string SessionKey = ClaimAuthenticationIntent::SessionKey;

    public function __construct(
        private readonly ClaimAuthenticationIntent $intents,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     code: string,
     *     workflow_key: string,
     *     title: string,
     *     description: string,
     *     intended_url: string,
     *     handoff_url: string,
     *     created_at: string
     * }
     */
    public function remember(Request $request, string $code, ClaimWorkflowDescriptorData $workflow): array
    {
        return $this->intents->remember($request, $code, $workflow);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(Request $request): ?array
    {
        $payload = $this->intents->current($request);

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'campaign_authorization') {
            return null;
        }

        return $payload;
    }
}
