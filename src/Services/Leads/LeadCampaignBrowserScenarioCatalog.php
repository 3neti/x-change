<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Leads;

final class LeadCampaignBrowserScenarioCatalog
{
    public const DefaultScenario = 'disbursable_feedback_endpoint';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return [
            $this->feedbackEndpoint(),
            $this->auiInsurance(),
            [
                ...$this->auiInsurance(),
                'key' => 'paired_campaign_qr',
                'title' => 'Paired seller/customer QR',
                'description' => 'Open a seller display without issuing a Pay Code. Scan its campaign QR from a separate customer browser, complete intake, and choose QR Ph to display payment on the seller screen.',
                'details_description' => 'Use two browser sessions. Test data only; generating a QR does not make a payment.',
                'fields' => ['Name', 'Mobile', 'Email', 'Address', 'Birth Date', 'Reference Code'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(?string $key): array
    {
        $selected = $key ?: self::DefaultScenario;

        foreach ($this->all() as $scenario) {
            if ($scenario['key'] === $selected) {
                return $scenario;
            }
        }

        return $this->feedbackEndpoint();
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackEndpoint(): array
    {
        return [
            'schema' => 'x-change.cockpit.lead-campaign-scenario-runner.v2',
            'key' => self::DefaultScenario,
            'title' => '₱25 Disbursable Feedback Endpoint',
            'description' => 'Create a one-use public campaign endpoint that mints a ₱25 disbursable Pay Code and reports lifecycle feedback by SMS.',
            'entry_point' => 'Public QR/link',
            'person_type' => 'Claimant',
            'pay_code_generation' => 'On scan',
            'claim_surface' => '/x/claim/{code}',
            'amount' => '₱25.00',
            'details_label' => 'Template instructions',
            'details_description' => 'The saved template remains the source of truth for every Pay Code minted by this test endpoint.',
            'fields' => [
                'Disbursable',
                'Feedback SMS · 09173011987',
                'Rider message · test feedback',
                'One start',
                '₱25 budget cap',
                'Expires after 24 hours',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auiInsurance(): array
    {
        return [
            'schema' => 'x-change.cockpit.lead-campaign-scenario-runner.v2',
            'key' => 'aui_on_demand_insurance_payment',
            'title' => 'AUI On-Demand Insurance Payment',
            'description' => 'Create a settlement Pay Code through a public campaign endpoint. Complete the application in the claim flow, then continue to the same code’s QR Ph payment page.',
            'entry_point' => 'Public QR/link',
            'person_type' => 'Prospect',
            'pay_code_generation' => 'On scan',
            'claim_surface' => '/x/claim/{code}',
            'amount' => '₱50.00 premium · ₱5,000.00 insured for 24 hours',
            'details_label' => 'Claim UX intake fields',
            'details_description' => 'A settled ₱50.00 payment starts ₱5,000.00 provisional personal-accident coverage for 24 hours. The completion claim collects the policy-holder details.',
            'fields' => [
                'Name',
                'Mobile',
                'Email',
                'Address',
                'Birthday',
                'Insurance product',
                'Vehicle registration number',
                'Driver license number',
                'Payment reference',
            ],
        ];
    }
}
