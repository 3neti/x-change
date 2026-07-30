<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;

final class CampaignOfficerAuthorizationLoginIntent
{
    public const string SessionKey = 'x-change.auth.intent';

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
        $code = strtoupper(trim($code));
        $intendedUrl = route('x-change.claim.show', ['code' => $code]);

        $payload = [
            'type' => 'campaign_authorization',
            'code' => $code,
            'workflow_key' => $workflow->key,
            'title' => 'Officer authorization required',
            'description' => 'Sign in with the campaign officer account authorized to approve this worksheet.',
            'intended_url' => $intendedUrl,
            'handoff_url' => route('x-change.claim.authorization-required', ['code' => $code]),
            'created_at' => now()->toIso8601String(),
        ];

        $request->session()->put('url.intended', $intendedUrl);
        $request->session()->put(self::SessionKey, $payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(Request $request): ?array
    {
        $payload = $request->session()->get(self::SessionKey);

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'campaign_authorization') {
            return null;
        }

        return $payload;
    }
}
