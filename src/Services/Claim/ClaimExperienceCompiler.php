<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimExperienceData;
use LBHurtado\XChange\Data\Claim\ClaimExperienceDiagnosticsData;
use LBHurtado\XChange\Data\Claim\ClaimPhaseData;
use LBHurtado\XChange\Services\NamedVoucherSliceService;
use Spatie\LaravelData\DataCollection;

class ClaimExperienceCompiler
{
    public function __construct(
        protected NamedVoucherSliceService $namedSlices,
    ) {}

    public function compile(Voucher $voucher): ClaimExperienceData
    {
        $instructions = $this->instructions($voucher);
        $rider = data_get($instructions, 'rider', []);

        $hasRiderSplash = filled(data_get($rider, 'splash'));
        $hasRiderMessage = filled(data_get($rider, 'message'));
        $redirectUrl = data_get($rider, 'redirect_url') ?? data_get($rider, 'url');

        $phases = [];

        if ($hasRiderSplash) {
            $phases[] = new ClaimPhaseData(
                key: 'rider_intro',
                owner: 'x-rider',
                source: 'voucher.instructions.rider.splash',
                stages: [[
                    'type' => 'splash',
                    'key' => 'legacy-splash',
                    'enabled' => true,
                    'phase' => 'pre_claim',
                    'presentation' => 'fullscreen',
                    'content' => data_get($rider, 'splash'),
                    'content_type' => 'html',
                    'payload' => [
                        'content' => data_get($rider, 'splash'),
                        'content_type' => 'html',
                        'timeout' => data_get($rider, 'splash_timeout'),
                        'presentation' => 'fullscreen',
                        'meta' => data_get($rider, 'splash_meta', []),
                    ],
                    'meta' => data_get($rider, 'splash_meta', []),
                ]],
            );
        }

        $phases[] = new ClaimPhaseData(
            key: 'pre_claim',
            owner: 'claim-widget',
            source: 'claim.route',
            action_url: '/x/claim',
        );

        $phases[] = new ClaimPhaseData(
            key: 'form_flow',
            owner: 'claim-widget',
            source: 'voucher-redemption.yaml',
            fields: $this->formFlowFields($voucher),
            skip_stages: $hasRiderSplash ? ['splash'] : [],
        );

        $phases[] = new ClaimPhaseData(
            key: 'confirmation',
            owner: 'form-flow',
            source: 'form-flow',
        );

        if ($hasRiderMessage) {
            $phases[] = new ClaimPhaseData(
                key: 'success_rider',
                owner: 'x-rider',
                source: 'voucher.instructions.rider.message',
                stages: [[
                    'type' => 'message',
                    'key' => 'success-message',
                    'enabled' => true,
                    'phase' => 'post_claim',
                    'content' => data_get($rider, 'message'),
                    'content_type' => 'text',
                    'payload' => [
                        'content' => data_get($rider, 'message'),
                        'content_type' => 'text',
                    ],
                ]],
            );
        }

        if (filled($redirectUrl)) {
            $phases[] = new ClaimPhaseData(
                key: 'redirect',
                owner: 'claim-widget',
                source: 'voucher.instructions.rider.redirect_url',
                url: $redirectUrl,
                delay_seconds: (int) (data_get($rider, 'redirect_delay') ?? 5),
                show_countdown: true,
            );
        }

        return new ClaimExperienceData(
            version: 1,
            entry: [
                'mode' => $hasRiderSplash ? 'rider_first' : 'form_first',
                'initial_phase' => $hasRiderSplash ? 'rider_intro' : 'pre_claim',
            ],
            options: [
                'skip_consumed_splash' => $hasRiderSplash,
                'show_redirect_countdown' => filled($redirectUrl),
            ],
            phases: new DataCollection(ClaimPhaseData::class, $phases),
            consumed: [
                'splash' => $hasRiderSplash,
            ],
            diagnostics: new ClaimExperienceDiagnosticsData(
                duplicate_splash_prevented: $hasRiderSplash,
                redirect_owner: filled($redirectUrl) ? 'claim-widget' : null,
                splash_owner: $hasRiderSplash ? 'x-rider' : 'form-flow',
                form_flow_splash_policy: $hasRiderSplash ? 'skip_consumed' : 'allow',
                consumed: [
                    'splash' => $hasRiderSplash,
                ],
                form_flow_owner: 'claim-widget',
            ),
        );
    }

    private function instructions(Voucher $voucher): array
    {
        $metadata = $voucher->metadata ?? [];

        return (array) data_get($metadata, 'instructions', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formFlowFields(Voucher $voucher): array
    {
        if (! $this->namedSlices->hasNamedSlices($voucher)) {
            return [];
        }

        return [
            [
                'key' => 'slice_ids',
                'type' => 'slice_selector',
                'label' => 'Slices to Redeem',
                'required' => true,
                'options' => $this->namedSlices->claimOptions($voucher),
                'selection' => 'one_or_many',
            ],
        ];
    }
}
