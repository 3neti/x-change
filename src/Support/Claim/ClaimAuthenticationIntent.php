<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LogicException;

final class ClaimAuthenticationIntent
{
    public const string SessionKey = 'x-change.auth.intent';

    /**
     * @return array{
     *     type: string,
     *     authentication_mode: string,
     *     code: string,
     *     workflow_key: string,
     *     title: string,
     *     description: string,
     *     intended_url: string,
     *     handoff_url: string,
     *     created_at: string
     * }
     */
    public function remember(
        Request $request,
        string $code,
        ClaimWorkflowDescriptorData $workflow,
    ): array {
        $code = strtoupper(trim($code));
        $intendedUrl = route('x-change.claim.show', ['code' => $code]);
        $presentation = $this->presentation($workflow);

        $payload = [
            'type' => $presentation['type'],
            'authentication_mode' => $workflow->authentication_mode->value,
            'code' => $code,
            'workflow_key' => $workflow->key,
            'title' => $presentation['title'],
            'description' => $presentation['description'],
            'intended_url' => $intendedUrl,
            'handoff_url' => $presentation['handoff_url']($code),
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

        if (! is_array($payload)) {
            return null;
        }

        $type = $payload['type'] ?? null;
        $mode = $payload['authentication_mode']
            ?? match ($type) {
                'campaign_authorization' => ClaimAuthenticationMode::AuthenticatedOfficer->value,
                'onboarding_claimant_handoff' => ClaimAuthenticationMode::ClaimantHandoff->value,
                default => null,
            };

        if (
            ! is_string($type)
            || ! is_string($mode)
            || ! in_array($type, ['campaign_authorization', 'onboarding_claimant_handoff'], true)
            || ClaimAuthenticationMode::tryFrom($mode) === null
        ) {
            return null;
        }

        $payload['authentication_mode'] = $mode;

        return $payload;
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     description: string,
     *     handoff_url: callable(string): string
     * }
     */
    private function presentation(ClaimWorkflowDescriptorData $workflow): array
    {
        if ($workflow->key === 'account-funding.v1') {
            return [
                'type' => 'onboarding_claimant_handoff',
                'title' => 'Account sign-in required',
                'description' => 'Sign in as the verified recipient to add this Pay Code to your Account.',
                'handoff_url' => fn (string $code): string => route(
                    'x-change.claim.authorization-required',
                    ['code' => $code],
                ),
            ];
        }

        return match ($workflow->authentication_mode) {
            ClaimAuthenticationMode::AuthenticatedOfficer => [
                'type' => 'campaign_authorization',
                'title' => 'Officer authorization required',
                'description' => 'Sign in with the campaign officer account authorized to approve this worksheet.',
                'handoff_url' => fn (string $code): string => route(
                    'x-change.claim.authorization-required',
                    ['code' => $code],
                ),
            ],
            ClaimAuthenticationMode::ClaimantHandoff => [
                'type' => 'onboarding_claimant_handoff',
                'title' => 'Account setup in progress',
                'description' => 'Continue with the verified recipient identity to finish Account setup.',
                'handoff_url' => fn (string $code): string => route(
                    'x-change.claim.show',
                    ['code' => $code],
                ),
            ],
            ClaimAuthenticationMode::None => throw new LogicException(
                'A claim authentication intent requires an explicit authentication mode.',
            ),
        };
    }
}
