<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class ClaimWalkthroughQaMatrix
{
    /**
     * @return array<string, mixed>
     */
    public function report(ClaimWalkthroughScenarioRepository $scenarios): array
    {
        return [
            'schema' => 'x-change.claim-walkthrough.qa-matrix.v1',
            'boundary' => [
                'surface' => 'public_claim_and_form_flow',
                'cockpit' => false,
                'money_movement' => false,
                'submit_claim' => false,
            ],
            'recommended_options' => [
                '--dry-run',
                '--preview-cache',
                '--profile=qa',
                '--json',
            ],
            'entries' => $this->entries($scenarios),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(ClaimWalkthroughScenarioRepository $scenarios): array
    {
        return [
            $this->available(
                priority: 'P0',
                scenarioKey: 'claim_basic_no_rider',
                purpose: 'Baseline entry, x-ray, form, confirmation handoff.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P0',
                scenarioKey: 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
                purpose: 'Minimal cash claim with the smallest useful story.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P1',
                scenarioKey: 'claim_basic_15_preview_with_rider',
                purpose: 'Rider message, splash, URL, redirect handoff, and OG preview.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P1',
                scenarioKey: 'claim_named_three_slices_preview',
                purpose: 'Named slices and amount explanation.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P2',
                scenarioKey: 'claim_fake_otp_handler_preview',
                purpose: 'Redeemer mobile OTP verification, explicitly separate from Paynamics issuer payout OTP.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P2',
                scenarioKey: 'claim_fake_kyc_handler_preview',
                purpose: 'Redeemer identity verification copy, loading, and retry states.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P2',
                scenarioKey: 'claim_mocked_location_handler_preview',
                purpose: 'Redeemer location permission explanation, retry controls, and map sizing.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P2',
                scenarioKey: 'claim_mocked_selfie_handler_preview',
                purpose: 'Redeemer camera permission, selfie preview, retake, and continue states.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P2',
                scenarioKey: 'claim_signature_handler_preview',
                purpose: 'Redeemer signature pad sizing, clear, cancel, and continue states.',
                scenarios: $scenarios,
            ),
            $this->available(
                priority: 'P3',
                scenarioKey: 'claim_paynamics_approval_walkthrough',
                purpose: 'Paynamics redeemer waiting state and issuer OTP surface.',
                scenarios: $scenarios,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function available(
        string $priority,
        string $scenarioKey,
        string $purpose,
        ClaimWalkthroughScenarioRepository $scenarios
    ): array {
        $scenario = $scenarios->get($scenarioKey);

        return [
            'priority' => $priority,
            'status' => 'available',
            'scenario' => $scenarioKey,
            'label' => data_get($scenario, 'label'),
            'purpose' => $purpose,
            'money_movement' => (bool) data_get($scenario, 'fixture.money_movement', false),
            'submit_claim' => false,
            'command' => $this->command($scenarioKey),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planned(string $priority, string $lane, string $purpose): array
    {
        return [
            'priority' => $priority,
            'status' => 'planned',
            'scenario' => null,
            'lane' => $lane,
            'purpose' => $purpose,
            'money_movement' => false,
            'submit_claim' => false,
            'command' => null,
        ];
    }

    private function command(string $scenarioKey): string
    {
        return sprintf(
            'php artisan xchange:claim-walkthrough %s --dry-run --preview-cache --profile=qa --json',
            $scenarioKey,
        );
    }
}
