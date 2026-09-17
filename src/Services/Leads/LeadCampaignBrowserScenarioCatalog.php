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
            'amount' => '₱0.00 disbursement · ₱100.00 collection target',
            'details_label' => 'Claim UX intake fields',
            'details_description' => 'Complete the application, choose Continue to payment, then generate or download the ₱100.00 QR Ph on the payment page.',
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
